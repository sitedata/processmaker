<?php

namespace Tests\Feature\ImportExport\Exporters;

use Illuminate\Support\Arr;
use ProcessMaker\ImportExport\Exporter;
use ProcessMaker\ImportExport\Exporters\ProcessExporter;
use ProcessMaker\ImportExport\Importer;
use ProcessMaker\ImportExport\Options;
use ProcessMaker\ImportExport\Tree;
use ProcessMaker\ImportExport\Utils;
use ProcessMaker\Managers\SignalManager;
use ProcessMaker\Models\Group;
use ProcessMaker\Models\GroupMember;
use ProcessMaker\Models\Process;
use ProcessMaker\Models\ProcessCategory;
use ProcessMaker\Models\ProcessNotificationSetting;
use ProcessMaker\Models\Screen;
use ProcessMaker\Models\SignalData;
use ProcessMaker\Models\SignalEventDefinition;
use ProcessMaker\Models\User;
use Tests\Feature\ImportExport\HelperTrait;
use Tests\TestCase;

class ProcessExporterTest extends TestCase
{
    use HelperTrait;

    private function fixtures()
    {
        // Create simple screens. Extensive screen tests are in ScreenExporterTest.php
        $cancelScreen = $this->createScreen('basic-form-screen', ['title' => 'Cancel Screen']);
        $requestDetailScreen = $this->createScreen('basic-display-screen', ['title' => 'Request Detail Screen']);

        $manager = User::factory()->create(['username' => 'manager']);
        $group = Group::factory()->create(['name' => 'Group', 'description' => 'My Example Group', 'manager_id' => $manager->id]);
        $user = User::factory()->create(['username' => 'testuser']);
        $user->groups()->sync([$group->id]);

        $process = $this->createProcess('basic-process', [
            'name' => 'Process',
            'user_id' => $user->id,
            'cancel_screen_id' => $cancelScreen->id,
            'request_detail_screen_id' => $requestDetailScreen->id,
        ]);

        // Notification Settings.
        $processNotificationSetting1 = ProcessNotificationSetting::factory()->create([
            'process_id' => $process->id,
            'notifiable_type' => 'requester',
            'notification_type' => 'assigned',
        ]);
        $processNotificationSetting2 = ProcessNotificationSetting::factory()->create([
            'process_id' => $process->id,
            'notifiable_type' => 'requester',
            'notification_type' => 'assigned',
            'element_id' => 'node_3',
        ]);

        return [$process, $cancelScreen, $requestDetailScreen, $user, $processNotificationSetting1, $processNotificationSetting2];
    }

    public function testExport()
    {
        $this->addGlobalSignalProcess();

        list($process, $cancelScreen, $requestDetailScreen, $user, $processNotificationSetting1, $processNotificationSetting2) = $this->fixtures();

        $exporter = new Exporter();
        $exporter->exportProcess($process);
        $tree = $exporter->tree();

        $this->assertEquals($process->uuid, Arr::get($tree, '0.uuid'));
        $this->assertEquals($process->category->uuid, Arr::get($tree, '0.dependents.1.uuid'));
        $this->assertEquals($cancelScreen->uuid, Arr::get($tree, '0.dependents.2.uuid'));
        $this->assertEquals($requestDetailScreen->uuid, Arr::get($tree, '0.dependents.3.uuid'));
        $this->assertEquals($user->groups->first()->uuid, Arr::get($tree, '0.dependents.0.dependents.0.uuid'));
    }

    public function testImport()
    {
        list($process, $cancelScreen, $requestDetailScreen, $user, $processNotificationSetting1, $processNotificationSetting2) = $this->fixtures();

        $this->runExportAndImport($process, ProcessExporter::class, function () use ($process, $cancelScreen, $requestDetailScreen, $user) {
            \DB::delete('delete from process_notification_settings');
            $process->forceDelete();
            $cancelScreen->delete();
            $requestDetailScreen->delete();
            $user->groups->first()->manager->delete();
            $user->groups()->delete();
            $user->delete();

            $this->assertEquals(0, Process::where('name', 'Process')->count());
            $this->assertEquals(0, Screen::where('title', 'Request Detail Screen')->count());
            $this->assertEquals(0, Screen::where('title', 'Cancel Screen')->count());
            $this->assertEquals(0, User::where('username', 'testuser')->count());
            $this->assertEquals(0, Group::where('name', 'Group')->count());
        });

        $process = Process::where('name', 'Process')->firstOrFail();
        $this->assertEquals(1, Screen::where('title', 'Request Detail Screen')->count());
        $this->assertEquals(1, Screen::where('title', 'Cancel Screen')->count());
        $this->assertEquals('testuser', $process->user->username);

        $group = $process->user->groups->first();
        $this->assertEquals('Group', $group->name);
        $this->assertEquals('My Example Group', $group->description);
        $this->assertEquals($user->groups->first()->manager->id, $group->manager_id);

        $notificationSettings = $process->notification_settings;
        $this->assertCount(2, $notificationSettings);
        $this->assertEquals('assigned', $notificationSettings[0]['notification_type']);
        $this->assertEquals('node_3', $notificationSettings[1]['element_id']);
    }

    public function testSignals()
    {
        $process = $this->createProcess('process-with-signals', [
            'name' => 'my process',
        ]);

        $this->runExportAndImport($process, ProcessExporter::class, function () use ($process) {
            SignalManager::removeSignal($this->globalSignal);
            $this->assertNull(SignalManager::findSignal('test_global_signal'));
            $process->forceDelete();
        });

        $this->assertNotNull(SignalManager::findSignal('test_global_signal'));

        $globalSignals = SignalManager::getAllSignals(true, [SignalManager::getGlobalSignalProcess()])->toArray();
        $this->assertEquals('test_global_signal', $globalSignals[0]['id']);
    }

    public function testSubprocesses()
    {
        $parentProcess = $this->createProcess('process-with-different-kinds-of-call-activities', ['name' => 'parent']);
        $subProcess = $this->createProcess('basic-process', ['name' => 'sub']);
        $packageProcess = $this->createProcess('basic-process', ['name' => 'package', 'package_key' => 'foo']);

        Utils::setAttributeAtXPath($parentProcess, '/bpmn:definitions/bpmn:process/bpmn:callActivity[1]', 'calledElement', 'ProcessId-' . $packageProcess->id);
        Utils::setAttributeAtXPath($parentProcess, '/bpmn:definitions/bpmn:process/bpmn:callActivity[2]', 'calledElement', 'ProcessId-' . $subProcess->id);
        $parentProcess->save();

        $this->runExportAndImport($parentProcess, ProcessExporter::class, function () use ($parentProcess, $subProcess, $packageProcess) {
            $subProcess->forceDelete();
            $parentProcess->forceDelete();
            $packageProcess->forceDelete();
        });

        $parentProcess = Process::where('name', 'parent')->firstOrFail();
        $subProcess = Process::where('name', 'sub')->firstOrFail();
        $definitions = $parentProcess->getDefinitions(true);
        $element = Utils::getElementByPath($definitions, '/bpmn:definitions/bpmn:process/bpmn:callActivity[2]');

        $this->assertEquals('ProcessId-' . $subProcess->id, $element->getAttribute('calledElement'));
        $this->assertEquals('ProcessId-' . $subProcess->id, Utils::getPmConfig($element)['calledElement']);
        $this->assertEquals($subProcess->id, Utils::getPmConfig($element)['processId']);
        $this->assertEquals(0, Process::where('name', 'package')->count());
    }

    /**
     * @group fix
     */
    public function testExportImportAssignments()
    {
        // Create users and groups
        $users = User::factory(12)->create();
        $groups = Group::factory(10)->create();

        // Assign three users to group 1, assign two users to group 2, assign one user to group 3
        foreach ($users as $key => $user) {
            if ($key <= 2) {
                $group = $groups[0];
            }
            if ($key > 2 and $key <= 4) {
                $group = $groups[1];
            }
            if ($key > 4 and $key <= 5) {
                $group = $groups[2];
            }

            // Assign last user to last group
            if ($key == 11) {
                $group = $groups[9];
            }

            if ($key > 5) {
                continue;
            }

            GroupMember::factory()->create([
                'member_type' => User::class,
                'member_id' => $user->id,
                'group_id' => $group->id,
            ]);
        }

        $this->addGlobalSignalProcess();

        // Create process
        $process = $this->createProcess('process-with-different-kinds-of-assignments', ['name' => 'processTest']);

        // Assign users to process assignments
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:task[1]', 'pm:assignedUsers', implode(',', [$users[0]->id, $users[1]->id , $users[2]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:task[2]', 'pm:assignedUsers', implode(',', [$users[3]->id, $users[4]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:manualTask[1]', 'pm:assignedUsers', implode(',', [$users[5]->id, $users[6]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:manualTask[2]', 'pm:assignedUsers', implode(',', [$users[7]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:callActivity[1]', 'pm:assignedUsers', implode(',', [$users[8]->id, $users[9]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:callActivity[2]', 'pm:assignedUsers', implode(',', [$users[10]->id]));

        // Assign groups to process assignments
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:task[1]', 'pm:assignedGroups', implode(',', [$groups[0]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:task[2]', 'pm:assignedGroups', implode(',', [$groups[1]->id, $groups[2]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:manualTask[1]', 'pm:assignedGroups', implode(',', [$groups[3]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:manualTask[2]', 'pm:assignedGroups', implode(',', [$groups[4]->id, $groups[5]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:callActivity[1]', 'pm:assignedGroups', implode(',', [$groups[6]->id, $groups[7]->id, $groups[8]->id]));
        Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:callActivity[2]', 'pm:assignedGroups', implode(',', [$groups[9]->id]));

        $process->save();

        $this->runExportAndImport($process, ProcessExporter::class, function () use ($process) {
            User::query()->forceDelete();
            Group::query()->forceDelete();
            GroupMember::query()->forceDelete();
            Process::query()->forceDelete();

            $this->assertEquals(0, User::get()->count());
            $this->assertEquals(0, Group::get()->count());
            $this->assertEquals(0, GroupMember::get()->count());
            $this->assertEquals(0, Process::get()->count());
        });


        // 11 from 12 created users should be exported ..
        $this->assertEquals(11, User::whereIn('username', $users->pluck('username'))->get()->count());
        $this->assertEquals(10, Group::whereIn('name', $groups->pluck('name'))->get()->count());
        $this->assertDatabaseHas('processes', ['name' => $process->name]);
        $process = Process::where('name', $process->name)->firstOrFail();

        // reasignar los ids de los usuarios nuevos al bpmn del nuevo proceso
        dd($process->bpmn);
        // fijarse en $process->bpmn y hacer assert que existe 
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:task[1]', 'pm:assignedUsers', implode(',', [$users[0]->id, $users[1]->id, $users[2]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:task[2]', 'pm:assignedUsers', implode(',', [$users[3]->id, $users[4]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:manualTask[1]', 'pm:assignedUsers', implode(',', [$users[5]->id, $users[6]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:manualTask[2]', 'pm:assignedUsers', implode(',', [$users[7]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:callActivity[1]', 'pm:assignedUsers', implode(',', [$users[8]->id, $users[9]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:callActivity[2]', 'pm:assignedUsers', implode(',', [$users[10]->id]));

        // // Assign groups to process assignments
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:task[1]', 'pm:assignedGroups', implode(',', [$groups[0]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:task[2]', 'pm:assignedGroups', implode(',', [$groups[1]->id, $groups[2]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:manualTask[1]', 'pm:assignedGroups', implode(',', [$groups[3]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:manualTask[2]', 'pm:assignedGroups', implode(',', [$groups[4]->id, $groups[5]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:callActivity[1]', 'pm:assignedGroups', implode(',', [$groups[6]->id, $groups[7]->id, $groups[8]->id]));
        // Utils::setAttributeAtXPath($process, '/bpmn:definitions/bpmn:process/bpmn:callActivity[2]', 'pm:assignedGroups', implode(',', [$groups[9]->id]));
    }
}
