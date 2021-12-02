<?php

namespace Tests\Feature\Api;

use ProcessMaker\Jobs\CatchSignalEventProcess;
use ProcessMaker\Jobs\ImportProcess;
use ProcessMaker\Jobs\ThrowSignalEvent;
use ProcessMaker\Managers\WorkflowManager;
use ProcessMaker\Models\Process;
use ProcessMaker\Models\ProcessRequest;
use Tests\TestCase;

class SignalEventTest extends TestCase
{
    public function testSignalEventMustTriggeredWhenProcessActive()
    {
        //Create a signal process that TRIGGER a signal with ACTIVE status by default
        ImportProcess::dispatchNow(
            file_get_contents(__DIR__ . '/../../Fixtures/signal_event_process_trigger.json')
        );

        //Get created TRIGGER process
        $triggerProcess = Process::orderBy('id', 'desc')->first();

        //Get excluded process
        $excludedProcess = $triggerProcess->id;

        //Get signalRef
        $signalRef = $triggerProcess->signal_events[0];

        //Create a signal process that CATCH a signal with ACTIVE status by default
        ImportProcess::dispatchNow(
            file_get_contents(__DIR__ . '/../../Fixtures/signal_event_process_catcher.json')
        );

        //Evaluates that CatchSignalEventProcess is triggering because catcher process is ACTIVE
        $this->expectsJobs(CatchSignalEventProcess::class);

        $throwSignalEventJob = new ThrowSignalEvent($signalRef, [], [$excludedProcess], []);
        $throwSignalEventJob->handle();
    }

    public function testSignalEventMustNotTriggeredWhenProcessInactive()
    {
        //Create a signal process that TRIGGER a signal with ACTIVE status by default
        ImportProcess::dispatchNow(
            file_get_contents(__DIR__ . '/../../Fixtures/signal_event_process_trigger.json')
        );

        //Get created TRIGGER process
        $triggerProcess = Process::orderBy('id', 'desc')->first();

        //Get excluded process
        $excludedProcess = $triggerProcess->id;

        //Get signalRef
        $signalRef = $triggerProcess->signal_events[0];

        //Create a signal process that CATCH a signal with ACTIVE status by default
        ImportProcess::dispatchNow(
            file_get_contents(__DIR__ . '/../../Fixtures/signal_event_process_catcher.json')
        );

        //Get created CATCHER process
        $catcherProcess = Process::orderBy('id', 'desc')->first();

        //Change it's status to INACTIVE
        $catcherProcess->status = 'INACTIVE';
        $catcherProcess->save();

        //Evaluates that CatchSignalEventProcess is NOT triggering because catcher process is INACTIVE
        $this->doesntExpectJobs(CatchSignalEventProcess::class);

        $throwSignalEventJob = new ThrowSignalEvent($signalRef, [], [$excludedProcess], []);
        $throwSignalEventJob->handle();
    }

    public function testSignalStarEventWithPayloadToRequestVariable()
    {
        // The process used for the tests
        ImportProcess::dispatchNow(
            file_get_contents(__DIR__ . '/../../Fixtures/signal_catch_with_payload.json')
        );

        // Payload for all the signals in the test
        $payload = ['payload1' => 1, 'payload2' => 2];

        // Dispatch the job synchronously for the test (signal 3 triggers the "Payload in variable" start event)
        ThrowSignalEvent::dispatchNow('Signal3', $payload, []);

        // Verify that the created request has the payload stored in the request variable "incoming_data"
        $createdRequestData = ProcessRequest::all()->first()->data;
        $this->assertEquals($createdRequestData['incoming_data'], $payload);
    }
}
