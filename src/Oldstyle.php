<?php

namespace etiqa\Oldstyle;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Oldstyle
 */
class Oldstyle extends Application
{
    /**
     * Used to separate between meta data and content in object files.
     *
     * @var string
     */
    public static $contentSeparator = "\n-----\n\n";
    /**
     * @var \modX
     */
    public static $modx;

    public $environment = array();
    public $repository;
    public $config;

    /**
     * Takes in an array of data, and turns it into blissful YAML using Symfony's YAML component.
     *
     * @param array $data
     * @return string
     */
    public static function toYAML(array $data = array())
    {
        return Yaml::dump($data, 4);
    }

    /**
     * Takes YAML, and turns it into an array using Symfony's YAML component.
     *
     * @param string $input
     * @return array
     */
    public static function fromYAML($input = '')
    {
        return Yaml::parse($input);
    }

    /**
     * Loads a new modX instance
     *
     * @throws \RuntimeException
     * @return \modX
     */
    public static function loadMODX()
    {
        if (self::$modx) {
            return self::$modx;
        }

        if (!file_exists(OLDSTYLE_WORKING_DIR . 'config.core.php')) {
            throw new \RuntimeException('There does not seem to be a MODX installation here. ');
        }

        require_once(OLDSTYLE_WORKING_DIR . 'config.core.php');
        require_once(MODX_CORE_PATH . 'model/modx/modx.class.php');

        $modx = new \modX();
        $modx->initialize('mgr');
        $modx->getService('error', 'error.modError', '', '');
        $modx->setLogTarget('ECHO');

        self::$modx = $modx;

        return $modx;
    }

    /**
     * @throws \RuntimeException
     */
    public function loadConfig()
    {
        if (!file_exists(OLDSTYLE_WORKING_DIR . '.Oldstyle')) {
            throw new \RuntimeException("Directory is not a Oldstyle directory: " . OLDSTYLE_WORKING_DIR);
        }

        $config = Oldstyle::fromYAML(file_get_contents(OLDSTYLE_WORKING_DIR . '.Oldstyle'));
        if (!$config || !is_array($config)) {
            throw new \RuntimeException("Error: " . OLDSTYLE_WORKING_DIR . ".Oldstyle file is not valid YAML, or is empty.");
        }

        $this->config = $config;
        return $config;
    }

    /**
     * @return \GitRepo|bool
     */
    public function getGitRepository()
    {
        try {
            if (!$this->repository) {
                $repositoryPath = self::loadMODX()->getOption('Oldstyle.repository_path', null, MODX_BASE_PATH, true);
                $this->repository = \Git::open($repositoryPath);
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return $this->repository;
    }

    /**
     * Returns the current environment based on the HTTP HOST.
     *
     * @return array
     */
    public function getEnvironment ()
    {
        if (!empty($this->environment)) {
            return $this->environment;
        }

        $config = $this->loadConfig();

        $envs = array();

        if (isset($config['environments']) && is_array($config['environments'])) {
            $envs = $config['environments'];
        }

        $defaults = array(
            'name' => '-unidentified environment-',
            'branch' => 'develop',
            'auto_commit_and_push' => true,
            'remote' => 'origin',
            'partitions' => array(
                'modResource' => 'content',
                'modTemplate' => 'templates',
                'modCategory' => 'categories',
                'modTemplateVar' => 'template_variables',
                'modChunk' => 'chunks',
                'modSnippet' => 'snippets',
                'modPlugin' => 'plugins'
            )
        );

        if (isset($envs['defaults']) && is_array($envs['defaults'])) {
            $defaults = array_merge($defaults, $envs['defaults']);
        }

        $host = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : MODX_HTTP_HOST;
        if (substr($host, 4) === 'www.') {
            $host = substr($host, 0, 4);
        }

        $environment = (isset($envs[$host])) ? $envs[$host] : array();
        $environment = array_merge($defaults, $environment);
        $this->environment = $environment;
        return $environment;
    }

    /**
     * Gets the default input definition.
     *
     * @return InputDefinition An InputDefinition instance
     */
    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),

            new InputOption('--help',           '-h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption('--verbose',        '-v|vv|vvv', InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.'),
            new InputOption('--version',        '-V', InputOption::VALUE_NONE, 'Display the Oldstyle version.'),
        ));
    }
}
