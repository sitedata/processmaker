<?php

namespace ProcessMaker\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use ProcessMaker\Exception\ScriptException;
use ProcessMaker\Facades\WorkflowManager;
use ProcessMaker\Managers\DataManager;
use ProcessMaker\Models\Process as Definitions;
use ProcessMaker\Models\ProcessRequest;
use ProcessMaker\Models\ProcessRequestToken;
use ProcessMaker\Models\Script;
use ProcessMaker\Models\ScriptExecutor;
use ProcessMaker\Nayra\Contracts\Bpmn\ScriptTaskInterface;
use Throwable;

class RunScriptTask extends BpmnAction implements ShouldQueue
{
    public $definitionsId;

    public $instanceId;

    public $tokenId;

    public $data;

    public $tries = 3;

    public $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param \ProcessMaker\Models\Process $definitions
     * @param \ProcessMaker\Models\ProcessRequest $instance
     * @param \ProcessMaker\Models\ProcessRequestToken $token
     * @param array $data
     */
    public function __construct(Definitions $definitions, ProcessRequest $instance, ProcessRequestToken $token, array $data)
    {
        $this->onQueue('bpmn');
        $this->definitionsId = $definitions->getKey();
        $this->instanceId = $instance->getKey();
        $this->tokenId = $token->getKey();
        $this->elementId = $token->getProperty('element_ref');
        $this->data = $data;
    }

    /**
     * Execute the script task.
     *
     * @return void
     */
    public function action(ProcessRequestToken $token = null, ScriptTaskInterface $element = null, ProcessRequest $instance)
    {
        // Exit if the task was completed or closed
        if (!$token || !$element) {
            return;
        }
        $scriptRef = $element->getProperty('scriptRef');
        $configuration = json_decode($element->getProperty('config'), true);

        // Check to see if we've failed parsing.  If so, let's convert to empty array.
        if ($configuration === null) {
            $configuration = [];
        }
        try {
            if (empty($scriptRef)) {
                $code = $element->getScript();
                if (empty($code)) {
                    throw new ScriptException(__('No code or script assigned to ":name"', ['name' => $element->getName()]));
                }
                $language = Script::scriptFormat2Language($element->getProperty('scriptFormat', 'application/x-php'));
                $script = new Script([
                    'code' => $code,
                    'language' => $language,
                    'run_as_user_id' => Script::defaultRunAsUser()->id,
                    'script_executor_id' => ScriptExecutor::initialExecutor($language)->id,
                ]);
            } else {
                $script = Script::findOrFail($scriptRef)->versionFor($instance);
            }

            $this->unlock();
            $dataManager = new DataManager();
            $data = $dataManager->getData($token);
            $response = $script->runScript($data, $configuration, $token->getId());

            $this->withUpdatedContext(function ($engine, $instance, $element, $processModel, $token) use ($response) {
                // Exit if the task was completed or closed
                if (!$token || !$element) {
                    return;
                }
                // Update data
                if (is_array($response['output'])) {
                    // Validate data
                    WorkflowManager::validateData($response['output'], $processModel, $element);
                    $dataManager = new DataManager();
                    $dataManager->updateData($token, $response['output']);
                    $engine->runToNextState();
                }
                $element->complete($token);
                $this->engine = $engine;
                $this->instance = $instance;
            });
        } catch (Throwable $exception) {
            $token->setStatus(ScriptTaskInterface::TOKEN_STATE_FAILING);

            $error = $element->getRepository()->createError();
            $error->setName($exception->getMessage());

            $token->setProperty('error', $error);
            $token->logError($exception, $element);

            Log::error('Script failed: ' . $scriptRef . ' - ' . $exception->getMessage());
            Log::error($exception->getTraceAsString());
        }
    }

    /**
     * When Job fails
     */
    public function failed(Throwable $exception)
    {
        if (!$this->tokenId) {
            Log::error('Script failed: ' . $exception->getMessage());

            return;
        }
        Log::error('Script (#' . $this->tokenId . ') failed: ' . $exception->getMessage());
        $token = ProcessRequestToken::find($this->tokenId);
        if ($token) {
            $element = $token->getBpmnDefinition();
            $token->setStatus(ScriptTaskInterface::TOKEN_STATE_FAILING);
            $error = $element->getRepository()->createError();
            $error->setName($exception->getMessage());
            $token->setProperty('error', $error);
            Log::error($exception->getTraceAsString());
            $token->save();
        }
    }
}
