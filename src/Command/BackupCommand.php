 <?php
namespace etiqa\Oldstyle\Command;
use etiqa\Oldstyle\Oldstyle;
use etiqa\Oldstyle\BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class BackupCommand
 *
 * Used for creating a quick timestamped mysql dump of the database.
 *
 * @package etiqa\Oldstyle\Command
 */
class BackupCommand extends BaseCommand
{
    public $loadConfig = true;
    public $loadMODX = true;

    protected function configure()
    {
        $this
            ->setName('backup')
            ->setDescription('Creates a quick backup of the entire MODX database. Runs automatically when using `Oldstyle build --force`, but can also be used manually.')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Optionally the name of the backup file, useful for milestone backups. If not specified the file name will be a full timestamp.'
            )
            ->addOption(
                'exclude-tables',
                null,
                InputOption::VALUE_NONE,
                'Exclude table from db backup based on exclude table file configuration [default .backup_ignore_tables]'
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

        // Grab the directory to place the backup
        $backupDirectory = isset($this->config['backup_directory']) ? $this->config['backup_directory'] : '_backup/';
        $targetDirectory = OLDSTYLE_WORKING_DIR . $backupDirectory;

        // Make sure the directory exists
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory);
            if (!is_dir($targetDirectory)) {
                $output->writeln('<error>Could not create {$backupDirectory} folder</error>');
                return 1;
            }
        }

        // Compute the name
        $file = $input->getArgument('name');
        if (!empty($file)) {
            $file = $this->modx->filterPathSegment($file);
        } else {
            $file = strftime('%Y-%m-%d-%H%M%S-%z');
        }
        if (substr($file, -4) != '.sql') {
            $file .= '.sql';
        }

        // Full target directory and file
        $targetFile = $targetDirectory . $file;

        if (file_exists($targetFile)) {
            $output->writeln('<error>A file with the name {$file} already exists in {$backupDirectory}.</error>');
            return 1;
        }

        $output->writeln('Writing database backup to <info>' . $file . '</info>...');
        $database_password = str_replace("'", '\'', $database_password);

        $password_parameter = '';
        if ($database_password != '') {
            $password_parameter = "-p'{$database_password}'";
        }
        if ($input->getOption("exclude-tables")) {
            $tables = $config = Oldstyle::fromYAML(file_get_contents(OLDSTYLE_WORKING_DIR . $this->config['backup_ignore_table_file']));
            $exclude_tables = "";
            foreach ($tables as $table) {
                $exclude_tables .= " ";
                $exclude_tables .= "--ignore-table={$dbase}.{$table}";
            }
            exec("mysqldump -u {$database_user} {$password_parameter} -h {$database_server} {$dbase} {$exclude_tables}> {$targetFile} ");
        }else{
            exec("mysqldump -u {$database_user} {$password_parameter} -h {$database_server} {$dbase} > {$targetFile} ");
        }
        return 0;
    }
}
