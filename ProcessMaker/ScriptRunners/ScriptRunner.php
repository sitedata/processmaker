<?php

namespace ProcessMaker\ScriptRunners;

use ProcessMaker\Exception\ScriptLanguageNotSupported;

class ScriptRunner
{
    /**
     * Concrete script runner
     *
     * @var \ProcessMaker\ScriptRunners\Base $runner
     */
    private $runner;

    public function __construct($language)
    {
        $this->runner = $this->getScriptRunnerByLanguage($language);
    }

    /**
     * Run a script code.
     *
     * @param string $code
     * @param array $data
     * @param array $config
     * @param integer $timeout
     *
     * @return array
     * @throws \RuntimeException
     */
    public function run($code, array $data, array $config, int $timeout)
    {
        return $this->runner->run($code, $data, $config, $timeout);
    }

    /**
     * Get a runner instance by language
     *
     * @param string $language
     *
     * @return \ProcessMaker\ScriptRunners\Base
     * @throws \ProcessMaker\Exception\ScriptLanguageNotSupported
     */
    private function getScriptRunnerByLanguage($language)
    {
        $language = strtolower($language);
        $runner = config("script-runners.{$language}");
        if (!$runner) {
            throw new ScriptLanguageNotSupported($language);
        } else {
            return new $runner;
        }
    }
}
