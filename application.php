<?php

/**
 * Make sure dependencies have been installed, and load the autoloader.
 */
if (!file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    throw new \Exception('Uh oh, it looks like dependencies have not yet been installed with Composer. Please follow Please follow the installation instructions at https://github.com/etiqa/Oldstyle/wiki/1.-Installation');
}
require dirname(__FILE__) . '/vendor/autoload.php';

/**
 * Ensure the timezone is set; otherwise you'll get a shit ton (that's a technical term) of errors.
 */
if (version_compare(phpversion(),'5.3.0') >= 0) {
    $tz = @ini_get('date.timezone');
    if (empty($tz)) {
        date_default_timezone_set(@date_default_timezone_get());
    }
}

/**
 * Specify the working directory, if it hasn't been set yet.
 */
if (!defined('OLDSTYLE_WORKING_DIR')) {
    define ('OLDSTYLE_WORKING_DIR', $cwd = getcwd() . DIRECTORY_SEPARATOR);
}
if (!defined('OLDSTYLE_APP_DIR')){
    define ('OLDSTYLE_APP_DIR',dirname(__FILE__));
}

/**
 * Load all the commands and create the Oldstyle instance
 */
use etiqa\Oldstyle\Oldstyle;
use etiqa\Oldstyle\Command\BackupCommand;
use etiqa\Oldstyle\Command\BuildCommand;
use etiqa\Oldstyle\Command\ExtractCommand;
use etiqa\Oldstyle\Command\InitCommand;
use etiqa\Oldstyle\Command\InstallModxCommand;
use etiqa\Oldstyle\Command\UpgradeModxCommand;
use etiqa\Oldstyle\Command\InstallPackageCommand;
use etiqa\Oldstyle\Command\RestoreCommand;
use etiqa\Oldstyle\Command\MigrateCommand;


$application = new Oldstyle('Oldstyle', '0.1.0');
$application->add(new InitCommand);
$application->add(new InstallModxCommand);
$application->add(new UpgradeModxCommand);
$application->add(new InstallPackageCommand);
$application->add(new BackupCommand);
$application->add(new RestoreCommand);
$application->add(new MigrateCommand);

/**
 * We return it so the CLI controller in /Oldstyle can run it, or for other integrations to
 * work with the Oldstyle api directly.
 */
return $application;
