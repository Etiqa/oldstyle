<?php
namespace etiqa\Oldstyle\Command;

use etiqa\Oldstyle\Oldstyle;
use etiqa\Oldstyle\BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class InitCommand
 *
 * Initiates a new Oldstyle project by asking some questions and creating the .oldstyle file.
 *
 * @package etiqa\Oldstyle\Command
 */
class InitCommand extends BaseCommand
{
    public $loadConfig = false;
    public $loadMODX = false;

    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Generates the .oldstyle file to set up a new Oldstyle project. Optionally installs MODX as well.')

            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'When a .oldstyle file already exists, and this flag is set, it will be overwritten.'
            )
        ;
    }

    /**
     * Runs the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Make sure we're not overwriting existing configuration by checking for existing .oldstyle files
        if (file_exists(OLDSTYLE_WORKING_DIR . '.oldstyle'))
        {
            // If the overwrite option is set we'll warn the user but continue anyway
            if ($input->getOption('overwrite'))
            {
                $output->writeln('<comment>A Oldstyle project already exists in this directory. If you continue, this will be overwritten.</comment>');
            }
            // .. otherwise, error out.
            else
            {
                $output->writeln('<error>Error: a Oldstyle project already exists in this directory.</error> If you wish to continue anyway, specify the --overwrite flag.');
                return 1;
            }
        }

        $helper = $this->getHelper('question');

        // Where we'll store the configuration
        $data = array();

        /**
         * Ask the user for the data directory to store object files in
         */
        $question = new Question('Please enter the name of the data directory (defaults to _data/): ', '_data');
        $directory = $helper->ask($input, $output, $question);
        if (empty($directory)) $directory = '_data/';
        $directory = trim($directory, '/') . '/';
        $data['data_directory'] = $directory;
        if (!file_exists($directory)) {
            mkdir($directory);
        }

        /**
         * Ask the user for a backup directory to store database backups in
         */
        $question = new Question('Please enter the name of the backup directory (defaults to _backup/): ', '_backup');
        $directory = $helper->ask($input, $output, $question);
        if (empty($directory)) $directory = '_backup/';
        $directory = trim($directory, '/') . '/';
        $data['backup_directory'] = $directory;
        if (!file_exists($directory)) {
            mkdir($directory);
        }

        /**
         * Ask the user for a migrate directory used to store migrate sql
         */
        $question = new Question('Please enter the name of the migrate directory (defaults to _migrate/): ', '_migrate');
        $directory = $helper->ask($input, $output, $question);
        if (empty($directory)) $directory = '_migrate/';
        $directory = trim($directory, '/') . '/';
        $data['migrate_directory'] = $directory;
        if (!file_exists($directory)) {
            mkdir($directory);
        }


        $question = new Question('Please enter the name the file you want to use to ignore tables in backup (defaults to .backup_ignore_tables): ', '.backup_ignore_tables');
        $ignore_file = $helper->ask($input, $output, $question);
        $data['backup_ignore_table_file'] = $ignore_file;
        if (!file_exists($ignore_file)) {
            fopen($ignore_file,"w");
        }

        /**
         * Ask if we want to include some default data types
         */

        if (file_exists(OLDSTYLE_WORKING_DIR . 'config.core.php')) {
            $question = new ConfirmationQuestion('Would you like to include a list of <info>Currently Installed Packages</info>? <comment>(Y/N)</comment> ', true);
            if ($helper->ask($input, $output, $question)) {
                $modx = false;
                try {
                    $modx = Oldstyle::loadMODX();
                } catch (\RuntimeException $e) {
                    $output->writeln('<error>Could not get a list of packages because MODX could not be loaded: ' . $e->getMessage() . '</error>');
                }

                if ($modx) {
                    $providers = array();

                    foreach ($modx->getIterator('transport.modTransportProvider') as $provider) {
                        /** @var \modTransportProvider $provider */
                        $name = $provider->get('name');
                        $providers[$name] = array(
                            'service_url' => $provider->get('service_url')
                        );
                        if ($provider->get('description')) {
                            $providers[$name]['description'] = $provider->get('description');
                        }
                        if ($provider->get('username')) {
                            $providers[$name]['username'] = $provider->get('username');
                        }
                        if ($provider->get('api_key')) {
                            $key = $provider->get('api_key');
                            file_put_contents(OLDSTYLE_WORKING_DIR . '.' . $name . '.key', $key);
                            $providers[$name]['api_key'] = '.' . $name . '.key';
                        }

                        $c = $modx->newQuery('transport.modTransportPackage');
                        $c->where(array('provider' => $provider->get('id')));
                        $c->groupby('package_name');
                        foreach ($modx->getIterator('transport.modTransportPackage', $c) as $package) {
                            $packageName = $package->get('signature');
                            $providers[$name]['packages'][] = $packageName;
                        }
                    }

                    $data['packages'] = $providers;
                }
            }
        }

        /**
         * Turn the configuration into YAML, and write the file.
         */
        $config = Oldstyle::toYAML($data);
        file_put_contents(OLDSTYLE_WORKING_DIR . '.oldstyle', $config);
        $output->writeln('<info>Oldstyle Project initiated and .oldstyle file written.</info>');

        /**
         * Check if we already have MODX installed, and if not, offer to install it right away.
         */
        if (!file_exists(OLDSTYLE_WORKING_DIR . 'config.core.php')) {

            $question = new ConfirmationQuestion('No MODX installation found in the current directory. Would you like to install the latest stable version? <comment>(Y/N)</comment> ', false);
            if ($helper->ask($input, $output, $question)) {

                $command = $this->getApplication()->find('modx:install');
                $arguments = array(
                    'command' => 'modx:install'
                );
                $input = new ArrayInput($arguments);
                return $command->run($input, $output);
            }

        }

        return 0;
    }
}
