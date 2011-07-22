<?php
/**
 * @package Airtime
 * @subpackage StorageServer
 * @copyright 2010 Sourcefabric O.P.S.
 * @license http://www.gnu.org/licenses/gpl.txt
 */

set_include_path(__DIR__.'/../../../airtime_mvc/library' . PATH_SEPARATOR . get_include_path());
require_once __DIR__.'/../../../airtime_mvc/application/configs/conf.php';
require_once(dirname(__FILE__).'/../../include/AirtimeInstall.php');
require_once(dirname(__FILE__).'/../../include/AirtimeIni.php');

AirtimeInstall::DbConnect(true);

echo PHP_EOL."*** Updating Database Tables ***".PHP_EOL;

if(AirtimeInstall::DbTableExists('doctrine_migration_versions') === false) {
    $migrations = array('20110312121200', '20110331111708', '20110402164819');
    foreach($migrations as $migration) {
        AirtimeInstall::BypassMigrations(__DIR__, $migration);
    }
}
AirtimeInstall::MigrateTablesToVersion(__DIR__, '20110406182005');

//setting data for new aggregate show length column.
$sql = "SELECT id FROM cc_show_instances";
$show_instances = $CC_DBC->GetAll($sql);

foreach ($show_instances as $show_instance) {
    $sql = "UPDATE cc_show_instances SET time_filled = (SELECT SUM(clip_length) FROM cc_schedule WHERE instance_id = {$show_instance["id"]}) WHERE id = {$show_instance["id"]}";
    $CC_DBC->query($sql);
}
//end setting data for new aggregate show length column.

exec("rm -fr /opt/pypo");
exec("rm -fr /opt/recorder");

const CONF_FILE_AIRTIME = "/etc/airtime/airtime.conf";
const CONF_FILE_PYPO = "/etc/airtime/pypo.cfg";
const CONF_FILE_RECORDER = "/etc/airtime/recorder.cfg";
const CONF_FILE_LIQUIDSOAP = "/etc/airtime/liquidsoap.cfg";

$configFiles = array(AirtimeIni::CONF_FILE_AIRTIME,
                     AirtimeIni::CONF_FILE_PYPO,
                     AirtimeIni::CONF_FILE_RECORDER,
                     AirtimeIni::CONF_FILE_LIQUIDSOAP);

foreach ($configFiles as $conf) {
    if (file_exists($conf)) {
        echo "Backing up $conf to $conf.bak".PHP_EOL;
        exec("cp $conf $conf.bak");
    }
}

/**
* This function creates the /etc/airtime configuration folder
* and copies the default config files to it.
*/
function CreateIniFiles()
{
    global $AIRTIME_SRC;
    global $AIRTIME_PYTHON_APPS;

    if (!file_exists("/etc/airtime/")){
        if (!mkdir("/etc/airtime/", 0755, true)){
            echo "Could not create /etc/airtime/ directory. Exiting.";
            exit(1);
        }
    }

    if (!copy($AIRTIME_SRC."/build/airtime.conf.180", CONF_FILE_AIRTIME)){
        echo "Could not copy airtime.conf to /etc/airtime/. Exiting.";
        exit(1);
    }
    if (!copy($AIRTIME_PYTHON_APPS."/pypo/pypo.cfg", CONF_FILE_PYPO)){
        echo "Could not copy pypo.cfg to /etc/airtime/. Exiting.";
        exit(1);
    }
    if (!copy($AIRTIME_PYTHON_APPS."/show-recorder/recorder.cfg", CONF_FILE_RECORDER)){
        echo "Could not copy recorder.cfg to /etc/airtime/. Exiting.";
        exit(1);
    }
    if (!copy($AIRTIME_PYTHON_APPS."/pypo/liquidsoap_scripts/liquidsoap.cfg", CONF_FILE_LIQUIDSOAP)){
        echo "Could not copy liquidsoap.cfg to /etc/airtime/. Exiting.";
        exit(1);
    }
}

echo "* Creating INI files".PHP_EOL;
CreateIniFiles();

AirtimeInstall::InstallPhpCode();
AirtimeInstall::InstallBinaries();

echo "* Initializing INI files".PHP_EOL;
AirtimeIni::UpdateIniFiles();
global $CC_CONFIG;
$CC_CONFIG = Config::loadConfig($CC_CONFIG);

echo "* Creating default storage directory".PHP_EOL;
AirtimeInstall::InstallStorageDirectory();

$ini = parse_ini_file(__DIR__."/../../include/airtime-install.ini");
$stor_dir = $ini["storage_dir"];

AirtimeInstall::ChangeDirOwnerToWebserver($stor_dir);
AirtimeInstall::CreateSymlinksToUtils();