<?php
namespace etiqa\Oldstyle\Command;

use etiqa\Oldstyle\Oldstyle;
use etiqa\Oldstyle\BaseCommand;
use etiqa\Oldstyle\Mixing\Flyway;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class MigrateCommand
 *
 * Used for Migrate Custom Db contents
 *
 * @package etiqa\Oldstyle\Command
 */
class MigrateCommand extends BaseCommand
{
    public $loadConfig = true;
    public $loadMODX = true;

    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Migrate your db on flyway style')
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
        /**
         * @var $database_type
         * @var $database_server
         * @var $database_user
         * @var $database_password
         * @var $dbase
         * @var
         */
        include MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';

        if ($database_type !== 'mysql') {
            $output->writeln('<error>Sorry, only MySQL is supported as database driver currently.</error>');
            return 1;
        }

        // Grab the directory the migrate are in
        $migrateDirectory = isset($this->config['migrate_directory']) ? $this->config['migrate_directory'] : '_migrate/';
        $targetDirectory = OLDSTYLE_WORKING_DIR . $migrateDirectory;
        $flywaySchema = OLDSTYLE_WORKING_DIR."flyway/flyway.sql";

        // Make sure the directory exists
        if (!is_dir($targetDirectory) || !is_readable($targetDirectory)) {
            $output->writeln('<error>Cannot read the {$migrateDirectory} folder.</error>');
            return 1;
        }

        $output->writeln('Migration started');

        $database_password = str_replace("'", '\'', $database_password);
        $link = mysqli_connect($database_server,$database_user,$database_password,$dbase) or die("Error " . mysqli_error($link));

        $flyway = new Flyway($link,$targetDirectory,$flywaySchema);
        $flyway->migrate();
        return 0;
    }
}
