<?php
//declare(strict_types=1);

//die('no access');


/**
 *  WTP DUMP/BACKUP TOOL FOR TYPO3 - wolo.pl '.' studio
 *  2013-2022
 *
 *  Supported TYPO3 versions: 4, 6, 7, 8, 9, 10 (and probably 11 too)
 *
 *  (Note, that recently I don't test it in older TYPO3, so if it doesn't work try older DUMP version)
 */

// ! you should change default password !

//					  WARNING!   CRITICAL
//
// THIS SCRIPT IS FOR PRIVATE DEV USE ONLY, DO NOT PUT IT ON PUBLIC UNSECURED!
// IT DOES LOW LEVEL DB / FILESYSTEM OPERATIONS AND DOESN'T HAVE ANY USER-INPUT SECURITY CHECK.
// IF THIS IS YOUR SITE AND RUNNING IN PUBLIC/PRODUCTION ENVIRONMENT AND YOU ARE
// NOT SURE IF THIS FILE SHOULD BE HERE, PLEASE DELETE THIS SCRIPT IMMEDIATELY
// Please remember, this is my script for my use and I'm giving it to you that you could save some
// time at work on repetitive system operations. There is no guarantee that it works as you want

/**
 * todo:
 * conf: omit tables, include directories - make them work also as linebreak-separated lists instead of array
 * password in mysqldump and other params should be in single quotes (makes problems ie. when ! is in password and double quotes are used)
 * "Archive filesystem" fix linked checkbox "Ignore selection" with dir selectors deactivity (now are disabled but checkbox is unchecked)
 * in action typo3 & system actions: make the checkboxes buttons to instantly call them, also expand the action panel after reload
 * also mark actions somehow depending of compat with current version or existence of typo3_console etc.
 * BUG: something wrong with caught output of domains update action/query
 */



const DUMP_VERSION = '3.8.91';




// this was needed here for something. [check and describe! probably in 9.x]
use TYPO3\CMS\Core\Core\ApplicationContext;




// on some projects / envs might be needed to see what's happening when you 500)
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_STRICT ^ E_DEPRECATED);
//error_reporting(E_ALL);
ini_set('display_errors', 'On'); // shows all errors
//ini_set('display_startup_errors', 'On');
//ini_set('log_errors', 'On');








// custom options may be included, if conf exists (is merged with default and stored in Dump::options)
Dump::readAndSetConf();
// get the config
// (in its initial form - because some values may depend on some constants defined below - so the config will be included again after)  
$options = Dump::getConf();


// this simple filename check is pretty sure v.4 detection test
if (file_exists('../typo3conf/localconf.php')  &&  !file_exists('../typo3conf/LocalConfiguration.php'))    {
	define('TYPO3_MAJOR_BRANCH_VERSION', 4);
}

// $_InitEnv_encaps

// try to use native path and version detection, but without running many other initial things
if (!$options['dontUseTYPO3Init']  &&  ( !defined('TYPO3_MAJOR_BRANCH_VERSION')  ||  ( defined('TYPO3_MAJOR_BRANCH_VERSION')  &&  TYPO3_MAJOR_BRANCH_VERSION > 4  &&  TYPO3_MAJOR_BRANCH_VERSION != 10  ) ) )  {

    // TODO check if we can remove these includes when using autoload
    @include_once('../typo3/sysext/core/Classes/Core/SystemEnvironmentBuilder.php');
    @include_once('../typo3/sysext/core/Classes/Utility/GeneralUtility.php');
	@include_once('../typo3/sysext/core/Classes/Core/Environment.php');			// needed in branch 9
    @include_once('../typo3/sysext/core/Classes/Utility/PathUtility.php');		// needed in branch 9
	@include_once('../typo3/sysext/core/Classes/Core/ApplicationContext.php');	// needed in branch 9
	@include_once('../typo3/sysext/core/Classes/Log/LogLevel.php');				// used in many projects, so try to include always
	
	if (file_exists(dirname(__DIR__).'/vendor/autoload.php'))	{
    	$classLoader = require dirname(__DIR__).'/vendor/autoload.php';
	}
	else if (file_exists(dirname(__DIR__).'/typo3_src/vendor/autoload.php'))	{
		$classLoader = require dirname(__DIR__).'/typo3_src/vendor/autoload.php';
	}
	else	{
		die ('Cannot include class loader - /vendor/autoload.php not found');
	}
	
    if (class_exists('\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder'))   {
        class SystemEnvironmentBuilder extends \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder	{
            // trick to call private methods
            public static function run_defineBaseConstants() {
                self::defineBaseConstants();
                
                // in 9 is defined at this moment, but in 10 there's no such const anymore
                if (!defined('TYPO3_MAJOR_BRANCH_VERSION'))   {
                    if (class_exists('TYPO3\\CMS\\Core\\Information\\Typo3Version')) {
                        new \TYPO3\CMS\Core\Information\Typo3Version();
                        
                        if (defined('TYPO3_branch')) {
                            preg_match('#(.+?)\.#', TYPO3_version, $matches);
                            define('TYPO3_MAJOR_BRANCH_VERSION', intval($matches[1]));
                        }
                        if (TYPO3_MAJOR_BRANCH_VERSION === 10)   {
                            $classLoader = require dirname(__DIR__).'/vendor/autoload.php';
                            \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(1, \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE);
                            \TYPO3\CMS\Core\Core\Bootstrap::init($classLoader)->get(\TYPO3\CMS\Backend\Http\Application::class);
                        }
                    }
                }
            }
	        public static function run_definePaths($relativePathPart) {
				// note that in some versions (9?) TYPO3_MAJOR_BRANCH_VERSION may be a string!
            	if (intval(TYPO3_MAJOR_BRANCH_VERSION) === 9)	{
		        	self::definePaths($relativePathPart, self::REQUESTTYPE_BE);
				}
            	// = 10
            	else if (intval(TYPO3_MAJOR_BRANCH_VERSION) === 10)	{
            	    // don't do anything
                }
            	// <= 9
            	else	{
		        	self::definePaths($relativePathPart);
				}
	        }
        }
        
        
        \SystemEnvironmentBuilder::run_defineBaseConstants();

        if (!defined('TYPO3_MAJOR_BRANCH_VERSION')) {
	    	preg_match('#(.+?)\.#', TYPO3_version, $matches);
        	define('TYPO3_MAJOR_BRANCH_VERSION', $matches[1]);
        }

        define('TYPO3_REQUESTTYPE', 2); // 2 = TYPO3_REQUESTTYPE_BE (must be set in >= 7)
        if (TYPO3_MAJOR_BRANCH_VERSION <= 7)  {
	        SystemEnvironmentBuilder::run_definePaths('DUMP/');
        }
        if (TYPO3_MAJOR_BRANCH_VERSION > 8)   {
            SystemEnvironmentBuilder::run_definePaths(1);
        }
        if (intval(TYPO3_MAJOR_BRANCH_VERSION) === 9)   {
        	// here we must run whole builder to set publicPath in Environment object - without that we don't have value in Environment::$publicPath
			// which is possible used in config utilities in AdditionalConfiguration
			SystemEnvironmentBuilder::run(1);
        }
    }
}



// do the classic init
if (!defined('PATH_site'))	{
	define('PATH_thisScript', str_replace('//', '/', str_replace('\\', '/',
		(PHP_SAPI == 'fpm-fcgi' || PHP_SAPI == 'cgi' || PHP_SAPI == 'isapi' || PHP_SAPI == 'cgi-fcgi') &&
		($_SERVER['ORIG_PATH_TRANSLATED'] || $_SERVER['PATH_TRANSLATED']) ?
		($_SERVER['ORIG_PATH_TRANSLATED'] ?: $_SERVER['PATH_TRANSLATED']) :
		($_SERVER['ORIG_SCRIPT_FILENAME'] ?: $_SERVER['SCRIPT_FILENAME']))));
	define('PATH_site', realpath(dirname(PATH_thisScript).'/../').'/');
}


const PATH_dump = PATH_site . 'DUMP/';
// in some projects needs to be defined
const TYPO3_MODE = 'BE';


// if version not detected or preconfigured, set to 0 - script still is usable if db is set manually
if (!defined('TYPO3_MAJOR_BRANCH_VERSION'))
	define('TYPO3_MAJOR_BRANCH_VERSION', 0);






/**
 * Init system/Typo configuration
 * Encapsulated from the global scope
 * 	// [for now, encapsulation is only tested here]
 */
$_InitConfig_encaps = function() {

	global $typo_db_username, $typo_db_password, $typo_db_host, $typo_db;



	// note that in some versions (9?) TYPO3_MAJOR_BRANCH_VERSION may be a string!
	switch (TYPO3_MAJOR_BRANCH_VERSION)    {

		case 4:
			if (file_exists(PATH_site.'typo3conf/localconf.php')) {
				include_once(PATH_site . 'typo3conf/localconf.php');
			}
			Dump::setDbConfVar('username', 		$typo_db_username);
			Dump::setDbConfVar('password',		$typo_db_password);
			Dump::setDbConfVar('host',			$typo_db_host);
			Dump::setDbConfVar('database',		$typo_db);
			break;

		case 6:
		case 7:
			if (file_exists(PATH_site.'typo3conf/LocalConfiguration.php'))	{
				$GLOBALS['TYPO3_CONF_VARS'] = include_once(PATH_site.'typo3conf/LocalConfiguration.php');
				// may be used sometimes in AdditionalConfiguration
				@include_once(PATH_site.'typo3/sysext/core/Classes/Utility/ExtensionManagementUtility.php');
				@include_once(PATH_site.'typo3conf/AdditionalConfiguration.php');
			}
			// in general Dump class database config structure/naming is basing on this one from 6 and 7 branches - so it expects keys: username, password, host, database
			Dump::setDbConfVar('username', 		$GLOBALS['TYPO3_CONF_VARS']['DB']['username']);
			Dump::setDbConfVar('password',		$GLOBALS['TYPO3_CONF_VARS']['DB']['password']);
			Dump::setDbConfVar('host',			$GLOBALS['TYPO3_CONF_VARS']['DB']['host']);
			Dump::setDbConfVar('database',		$GLOBALS['TYPO3_CONF_VARS']['DB']['database']);
			break;

		case 8:
		case 9:
			if (file_exists(PATH_site.'typo3conf/LocalConfiguration.php'))	{
				$GLOBALS['TYPO3_CONF_VARS'] = include_once(PATH_site.'typo3conf/LocalConfiguration.php');
				// may be used sometimes in AdditionalConfiguration
				@include_once(PATH_site.'typo3/sysext/core/Classes/Utility/ExtensionManagementUtility.php');
				@include_once(PATH_site.'typo3conf/AdditionalConfiguration.php');
			}
			Dump::setDbConfVar('username', 		$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user']);
			Dump::setDbConfVar('password',		$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password']);
			Dump::setDbConfVar('host',			$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host']);
			Dump::setDbConfVar('database',		$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname']);
			break;

		case 10:
			// dontUseTYPO3Init in 10.x just won't work in projects that uses classes calls in AdditionalConfiguration, first problem is \Environment::getPublicPath() which is empty and no way to set the value from here
			// in such case you have to use your manual db config in conf.php and forget about including typo3 config
			/*if ($options['dontUseTYPO3Init']  &&  file_exists(PATH_site.'typo3conf/LocalConfiguration.php'))	{
				$GLOBALS['TYPO3_CONF_VARS'] = include_once(PATH_site.'typo3conf/LocalConfiguration.php');
				@include_once(PATH_site.'typo3/sysext/core/Classes/Core/Environment.php');
				@include_once(PATH_site.'typo3conf/AdditionalConfiguration.php');
			}*/

			// when using full init, we don't need to do anything, the whole conf is already included and available here
		default:
			Dump::setDbConfVar('username', 		$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user']);
			Dump::setDbConfVar('password',		$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password']);
			Dump::setDbConfVar('host',			$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host']);
			Dump::setDbConfVar('database',		$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname']);
			break;
	}



	// some old constants I used in many projects
	//if (!defined('DEV'))				  define('DEV',   false);
	//if (!defined('LOCAL'))			  define('LOCAL', false);

	// set contexts as const for shorthand. use defined env var, if not found check old projects fallback to GLOBALS
	if (!defined('TYPO3_CONTEXT'))      define('TYPO3_CONTEXT',     getenv('TYPO3_CONTEXT') ? getenv('TYPO3_CONTEXT') : $GLOBALS["STAGE_IDENTIFIER"]);
	if (!defined('INSTANCE_CONTEXT'))   define('INSTANCE_CONTEXT',  getenv('INSTANCE_CONTEXT') ? getenv('INSTANCE_CONTEXT') : $GLOBALS["CONTEXT_IDENTIFIER"]);


	// reinclude config (overwrite settings which uses conditions on some constants defined above)
	Dump::readAndSetConf();


	// as for now, this encapsulation is only tested here 
};
$_InitConfig_encaps();
unset($_InitConfig_encaps);



// output Adminer, if included from adminer.php
if ($GLOBALS['dump_adminer_load'])	{
	require_once (PATH_dump . '/lib/adminer-loader.php');
	exit;
}




$Dump = new Dump();
$Dump->main();



/** 
 * Damn
 * Useful
 * Maintenance
 * Program 
 */
class Dump  {

	// define some variables. all can be public, this tool was intended for private admin use, so no need to control such things.

	/**
	 * Default options - to be merged with incoming settings from conf.
	 * (settings ref)
	 */
	protected static $optionsDefault = [

		// default project name is generated from subdomain, but it's not always ok - may be set
		'defaultProjectName' => '',
	
		// don't use native path and version detection (it needs typo3 sources to work. otherwise uses internal PATH_site detection)
		'dontUseTYPO3Init' => false,
	
		// script generates command line, may display it, but doesn't exec it
		'dontExecCommands' => 0,
	
		// exec generated commands, but don't display them
		'dontShowCommands' => 0,
	
		// query database using cli bin execute or mysqli connection
		'defaultDatabaseQueryMethod' => Dump::DATABASE_QUERY_METHOD__MYSQLI,
	
		// default tables for "Omit these tables" (Database Export action)
		'defaultOmitTables' => ['index_rel', 'sys_log', 'sys_history', 'index_fulltext', 'sys_refindex', 'index_words', 'tx_extensionmanager_domain_model_extension'],
	
		// default preselection of files and dirs (Filesystem Pack action)
		'defaultIncludeFilesystem' => ['typo3conf'],
		'defaultExcludeFilesystem' => ['fileadmin/content', 'fileadmin/_processed_', 'fileadmin/_temp_', 'fileadmin/user_upload', 'typo3conf/AdditionalConfiguration_host.php'],
	
		// list items in exclude selector from these directories (Filesystem Pack action)
		'defaultExcludeFilesystem_listItemsFromDirs' => ['fileadmin', 'typo3conf'],
	
		// adds docker exec on container to command line
		'docker' => false,
	
		// if docker=true, container name must be specified
		'docker_containerSql' => '',
		'docker_containerPhp' => '',
	
		// domain to replace for fetch file urls (Manually Fetch Files action)
		'fetchFiles_defaultSourceDomain' => '',
	
		// preconfigured lists of domains for environments ['key' => 'domains linebreak-separated-list'] (Domains Update action)
		'updateDomains_defaultDomainSet' => [],
	
		// prefill input with domains from this key domain-set ('key')
		'updateDomains_defaultDomainSetFrom' => '',
		'updateDomains_defaultDomainSetTo' => '',
	];

	// working configuration
	protected static $options = [];
	protected static $dbConf = [];

	// html content to display before form
	public $configInfoHeader = '';

	// context info
	public $sysContextInfo = '';

	// message/error to show
	public $messages = [];

	// stored commands to show and/or execute
	public $cmds = [];

	// input values
	public $projectName = '';
	public $projectVersion = '';
	public $action = '';

	// directories as variables
	public $PATH_site;
	public $PATH_dump;

	// docker exec cmd prefix
	public $dockerContainerCmd = [];

	// database connection
	public $dbConnection = null; 

	// system components versions
	public $databaseVersion = '';
	public $phpVersion = '';


	const CONFIG_FILENAME = 'conf.php';
	const DATABASE_QUERY_METHOD__MYSQLI = 'mysqli';
	const DATABASE_QUERY_METHOD__CLI = 'cli';



	/*protected */function __construct() {
		$this->PATH_site = PATH_site;
		$this->PATH_dump = PATH_dump;

		// if docker is used, docker exec CONTAINER must be prepended before mysqldump etc.
		if ($this->option('docker'))   {
		    // docker exec -it causes some tty error in console
			$this->dockerContainerCmd['sql'] = "docker exec -i {$this->option('docker_containerSql')}   ";
			$this->dockerContainerCmd['php'] = "docker exec -i {$this->option('docker_containerPhp')}   ";
		}

		$this->databaseConnect();
		$this->phpVersion = phpversion();
	}

    /**
	 * Expecting vars: username, password, host, database
     */
	public static function setDbConfVar($var, $value) {
		static::$dbConf[$var] = $value;
	}

    /**
	 * Return database config, or its single var
     * @param string $var
     * @return array|string
     */
	public function getDbConf($var = '') {
		return $var ? static::$dbConf[$var] : static::$dbConf;
	}


	public static function readAndSetConf() {
		$optionsCustom = [];
		try {
			include Dump::CONFIG_FILENAME;
			//var_export($optionsCustom); die('stop');
			static::$options = array_replace(Dump::$optionsDefault, $optionsCustom);
		} catch (\Throwable $e){
			// todo later: nice display of this message, instead of ugly direct print
			print "<pre>"; print_r($e); print "</pre>";
		}
		return static::$options;
	}

    /**
	 * Return final working configuration - full or single var
	 * (static - can be called before instantiation and used by some external/sub objects)
     * @param string $optionName
     * @return array|mixed
     */
	public static function getConf($optionName = '') {
		if ($optionName)	{
			return static::$options[$optionName];
		}
		return static::$options;
	}

	/**
	 * Get single option value
	 * @internal
     * @param string $optionName
     * @return mixed
     */
	public function option(string $optionName = '') {
		if ($optionName)	{
			return static::$options[$optionName];
		}
	}

	function main() {

		if (!$this->getDbConf('username') || !$this->getDbConf('host') || !$this->getDbConf('database'))
			$this->msg('CHECK DATABASE CONFIG. Looks like authorization data is missed. See your '.(TYPO3_MAJOR_BRANCH_VERSION === 4 ? 'localconf' : 'LocalConfiguration'), 'error');

		$this->addEnvironmentMessage();

		// set input values
		$this->projectName = $_POST['projectName'];
		$this->projectVersion = $_POST['v'];
		$this->action = $_POST['action'];


		// predicted project name, taken from domain name or conf (only when not submitted, on first run)
		if (!$_POST['submit']  &&  !$_POST['projectName']) {
			[$this->projectName] = preg_split('@\.@', $_SERVER['HTTP_HOST']);
			if ($this->option('defaultProjectName'))
				$this->projectName = $this->option('defaultProjectName');
		}

		// add some header system & conf informations
		$this->configInfoHeader .= '<p>database: <span class="info"><b>' . $this->getDbConf('database') . '</b></span> / db server: <span class="info">' . $this->databaseVersion . '</span> / connection test  - status: '.$this->databaseTest() 
			. '<i class="tooltip clickable" title="For credentials used go to \'Database - exec QUERY\'" onclick="document.getElementById(\'action_databaseQuery\').click();"></i>'
			. (file_exists(PATH_dump.'adminer.php') ? ' / <a href="adminer.php">ADMINER</a>' : '')
			.'</p>';
		if ($this->option('docker'))   {
			$this->configInfoHeader .= '<p>docker sql: <span class="info">' . $this->option('docker_containerSql') . '</span></p>';
			$this->configInfoHeader .= '<p>docker php: <span class="info">' . $this->option('docker_containerPhp') . '</span></p>';
		}
		$this->configInfoHeader .= '<p>TYPO3 major detected: <span class="info"><b>' . TYPO3_MAJOR_BRANCH_VERSION
            . (defined('TYPO3_version') ? '</b></span> / version: <b><span class="info">' . TYPO3_version . '</span></b>' : '') . '</b></span></p>';
		
		$this->configInfoHeader .= '<p>PHP: <span class="info"><b>' . $this->phpVersion . '</b></span>';

		// TODO: move this to results! (div.results) it doesn't belong here

		// check if action is given if submitted
		if (!$_POST['submit']  ||  ($_POST['submit'] && !$this->paramsRequiredPass(['action' => $this->action])))
			return;

		$this->configInfoHeader .= '<h4><b>ACTION CALLED:</b> <span class="info"><b>' . $this->action . '</b></span></h4>';

		$this->runAction();
	}


	function runAction()    {

		// RUN
		switch ($this->action) {

			// IMPORT DATABASE
			case 'databaseImport':
				$this->action_databaseImport();
				break;

			// DUMP DATABASE
			case 'databaseDump':
				$this->action_databaseDump();
				break;

			// PACK FILESYSTEM
			case 'filesystemPack':
				$this->action_filesystemPack();
				break;

			// DUMP ALL
			case 'dump_all':
				$this->action_databaseDump(true);
				$this->action_filesystemPack(true);
				break;

			// BACKUP FILESYSTEM
			case 'backup':
				$this->action_backup();
				break;

			// UPDATE DOMAINS
			case 'domainsUpdate':
				$this->action_domainsUpdate();
				break;

			// UPDATE DOMAINS
			case 'filesFetch':
				$this->action_filesFetch();
				break;

			// EXEC QUERY
			case 'databaseQuery':
				$this->action_databaseQuery($_POST['databaseQuery']);
				break;

			// XCLASS GENERATE
            case 'generateXclass':
                $this->action_xClassGenerate($_POST['prefill']);
                break;
            
            // TYPO3 SYSTEM ACTION
            case 'typo3System':
                $this->action_typo3System($_POST['typo3SystemAction']);
                break;
		}
	}




	// ACTIONS

	/**
	 * DATABASE IMPORT
	 */
	private function action_databaseImport()	{
		$dbFilename = $_POST['dbFilename'] ?: $_POST['dbFilenameSel'];
		if (!$this->paramsRequiredPass(['dbFilename' => $dbFilename]))
			return;

		// docker: check, works like that: docker exec -i berglanddev_mysql_1 mysql --user=www_devel --password="www_devel" --database=project_app < kultur-bergischesland-v06-dev.sql  (przynajmniej bedac w tym katalogu) [ jest problem z kodowaniem! sprawdzic to, zobaczyc, jak wywolac to z utf ]
        // (doesn't work called from php container and it probably won't. it's impossible to call host commands from inside some container)

        // classic way
        if ($_POST['importDatabaseOldMethod'])   {
            // slashes even on windows have to be unix-style in execute source
            $query = "SET NAMES 'utf8'; SET collation_connection = 'utf8_unicode_ci'; SET collation_database = 'utf8_unicode_ci'; SET collation_server = 'utf8_unicode_ci'; source " . str_replace('\\', '/', $this->PATH_dump . $dbFilename);
            $mysqlCommand = "--execute=\"{$query}\"";
        }
        else    {
	        $mysqlCommand = " < {$this->PATH_dump}{$dbFilename}";
        }
		$this->exec_control($this->dockerContainerCmd['php'] . "mysql --batch --quick --host={$this->getDbConf('host')} --user={$this->getDbConf('username')} --password=\"{$this->getDbConf('password')}\" --database={$this->getDbConf('database')}  {$mysqlCommand}");
	}


	/**
	 * DATABASE DUMP
	 */
	private function action_databaseDump($allTables = false)	{

		if (!$this->paramsRequiredPass(['projectName' => $this->projectName, 'projectVersion' => $this->projectVersion]))
		//if (!$this->paramsRequiredPass(['projectName' => $this->projectName, 'projectVersion' => $this->projectVersion, 'omitTables' => $_POST['omitTables']]))
			return;
		$dumpFilename = str_replace(' ', '_', $this->projectName);
		//$omitTables = array_diff(explode(chr(10), $_POST['omitTables']), ['', "\r", "\n"]);
		$omitTables = preg_split('/\n|\r\n?/', $_POST['omitTables']);



		// todo: czy PATH_dump jest potrzebny, czy dziala pod linuxem, pod win, w dockerze
		// todo: for docker try  /var/www/htdocs/_docker/dump_local_db.sh
		// old full dump
		//$this->exec_control($this->dockerCmdPart['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --skip-set-charset --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->getDbConf('host')} --user={$this->getDbConf('username')} --password=\"{$this->getDbConf('password')}\"  {$this->getDbConf('database')}  >  \"{$this->PATH_dump}{$this->projectName}-v{$this->projectVersion}.sql\"; ");


		// example - ignored without data but structure:
		// mysqldump -u user -p db --ignore-table=ignoredtable1 --ignore-table=ignoredtable2 > structure.sql;  mysqldump -u user -p --no-data db ignoredtable1 ignoredtable2 >> structure.sql;

		$ignoredTablesPart = '';
		$dumpOnlyStructureQuery = '';

		if ($_POST['omitTablesIncludeInQuery']  &&  $omitTables  &&  !$allTables) {
			$ignoredTablesPart = ' \\' . chr(10) . "--ignore-table={$this->getDbConf('database')}."
				. implode (' \\' . chr(10) . "--ignore-table={$this->getDbConf('database')}.", $omitTables);

			$dumpOnlyStructureQuery = ';'     // end previous command only if needed
				. chr(10) . chr(10)
				//. $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --skip-set-charset --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->getDbConf('host')} --user={$this->getDbConf('username')} --password=\"{$this->getDbConf('password')}\"  {$this->getDbConf('database')}  "
				. $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->getDbConf('host')} --user={$this->getDbConf('username')} --password=\"{$this->getDbConf('password')}\"  {$this->getDbConf('database')}  "
				. " --no-data \\"
				. chr(10) . implode(' ', $omitTables)
				. "  >>  \"{$this->PATH_dump}{$this->projectName}-v{$this->projectVersion}.sql\"  2>/dev/null";
		} 

		// dziala na dockerze (wywolany recznie)
        // dziala na linux
		// na win teoretycznie powinno tez ze stream output
		//$cmd = $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --skip-set-charset --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->getDbConf('host')} --user={$this->getDbConf('username')} --password=\"{$this->getDbConf('password')}\"  {$this->getDbConf('database')}  "
		// in case of encoding problems / if no SET NAMES in dump: use --set-charset
		//$cmd = $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --quick --lock-tables --add-locks --default-character-set=utf8 --set-charset --host={$this->getDbConf('host')} --user={$this->getDbConf('username')} --password=\"{$this->getDbConf('password')}\"  {$this->getDbConf('database')}  "
		// docker exec kickstartert310ff_dev_php_1 bash -c 'mysql -hmysql -uroot -pdbmaster --default-character-set=utf8 project_app < $(dump)'
		$cmd = $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->getDbConf('host')} --user={$this->getDbConf('username')} --password=\"{$this->getDbConf('password')}\"  {$this->getDbConf('database')}  "
			. $ignoredTablesPart
			. "  >  \"{$this->PATH_dump}{$this->projectName}-v{$this->projectVersion}.sql\"  2>/dev/null" 
			. $dumpOnlyStructureQuery;

		$this->exec_control($cmd);


		// TAR

		// dziala na dockerze (wywolany recznie)
        // dziala na linux
		$this->exec_control($this->dockerContainerCmd['php'] . "cd \"{$this->PATH_dump}\";  tar  -zcf  \"{$dumpFilename}-v{$this->projectVersion}.sql.tgz\"  \"{$this->projectName}-v{$this->projectVersion}.sql\" ");

		// todo: ktory dziala na windowsie? jakos zaden nie chce
		//$this->exec_control("tar  -zcf  \"{$this->PATH_dump}{$dumpFilename}-v{$this->projectVersion}.sql.tgz\"  \"{$this->PATH_dump}{$this->projectName}-v{$this->projectVersion}.sql\" ");
		//$this->exec_control("tar -C \"{$this->PATH_dump}\" -zcf  {$dumpFilename}-v{$this->projectVersion}.sql.tgz  {$this->projectName}-v{$this->projectVersion}.sql");
        //$this->exec_control("tar -C \"{$this->PATH_dump}\" -zcf ./{$dumpFilename}-v{$this->projectVersion}.sql.tgz  {$this->projectName}-v{$this->projectVersion}.sql");


		// display download link
		$this->cmds[] = ['command' => "<br><a href=\"{$dumpFilename}-v{$this->projectVersion}.sql.tgz\">{$dumpFilename}-v{$this->projectVersion}.sql.tgz</a><br>"];
	}


	/**
	 * FILESYSTEM PACK
	 */
	private function action_filesystemPack($all = false)	{

		if (!$this->paramsRequiredPass(['projectName' => $this->projectName, 'projectVersion' => $this->projectVersion]))
			return;

		$dumpFilename = str_replace(' ', '_', $this->projectName);

		// todo: ktory dziala pod linuxem, ktory pod win, ktory w dockerze?
		// dziala w dockerze prawidlowo
		$cmd = "tar -C \"{$this->PATH_site}\" -zcf {$dumpFilename}-v{$this->projectVersion}.tgz ";

		if (!$all  &&  !$_POST['ignoreSelectionAndPackAll']) {

			$included = is_array($_POST['filenameSelectionInclude']) ? $_POST['filenameSelectionInclude'] : [];
			$excluded = is_array($_POST['filenameSelectionExclude']) ? $_POST['filenameSelectionExclude'] : [];

			if ($included)
				$cmd .= implode(' ', $included) . ' ';
			else
				$cmd .= ' .  --exclude="DUMP" --exclude-vcs --exclude="deprecation*.log"';

			foreach($excluded as $exclude)  {
                [$excludeInDir] = explode('/', $exclude);
                if (in_array($excludeInDir, $included))
                    $cmd .= ' --exclude="'.$exclude.'"';
				/*$cmd .= ' --exclude="';
				$cmd .= implode('" --exclude="', $excluded);
				$cmd .= '" ';*/
			}
		}
		else	{
			$cmd .= ' .  --exclude="DUMP" --exclude-vcs';
		}

		if ($_POST['dereferenceSymlinks'])	{
            $cmd .= ' --dereference';
		}

		$this->exec_control($cmd);
		/* $this->exec_control ('tar -zcf '.{$this->PATH_site}.'DUMP/'.$dumpFilename.'-v'.$this->projectVersion.'.tgz ./../* --exclude="typo3temp" --exclude="DUMP" --exclude="uploads" -exclude="typo3_src-*"  '); */

		// display download link
		$this->cmds[] = ['command' => "<br><a href=\"{$dumpFilename}-v{$this->projectVersion}.tgz\">{$dumpFilename}-v{$this->projectVersion}.tgz</a><br>"];
	}


	/**
	 * BACKUP
	 */
	private function action_backup()   {
		$backupDir = "{$this->PATH_site}../{$this->projectName}_backup_".time()."/";
		$this->exec_control("mkdir $backupDir");
		//$this->exec_control("cp -R $this->PATH_site/* $backupDir");
		$this->exec_control("rsync -av --exclude='DUMP' --exclude='.git' {$this->PATH_site} {$backupDir}");
	}


	/**
	 * UPDATE DOMAINS
	 */
	private function action_domainsUpdate() {

		$queryMethod = $_POST['databaseQuery_method']  OR  $this->option('defaultDatabaseQueryMethod');

		if (!$this->paramsRequiredPass(['domainsFrom' => $_POST['domainsFrom'], 'domainsTo' => $_POST['domainsTo'], 'databaseQuery_method' => $queryMethod]))
			return;

		//	UPDATE sys_domain SET domainName = 'project.de.localhost' WHERE domainName = 'project.de';
		//	UPDATE pages SET url = REPLACE(url, 'project.de', 'project.de.localhost') WHERE url LIKE 'project.de%';

		$query = '';

        $domains_from = array_diff(explode(chr(10), $_POST['domainsFrom']), ['', "\r"]);
        $domains_to = array_diff(explode(chr(10), $_POST['domainsTo']), ['', "\r"]);


        if (count($domains_from) !== count($domains_to)) {
	        $this->msg('error: number of items in <b>domainsFrom</b> is different than <b>domainsTo</b>  ', 'error');
	        return;
        }

        foreach($domains_from as $i => $from)   {
            $from = trim($from);
            $to = trim($domains_to[$i]);
            $query .= "UPDATE sys_domain SET domainName = '{$to}' WHERE domainName = '{$from}'; \n";
            $query .= "UPDATE pages SET url = REPLACE(url, '{$from}', '{$to}') WHERE url LIKE '{$from}/%'; \n";
        }

		$this->action_databaseQuery($query);
	}


	/**
	 * FETCH FILES
	 */
	private function action_filesFetch()    {
		if (!$this->paramsRequiredPass(['fetchFilesUrls' => $_POST['fetchFilesUrls']]))
			return;

		// todo: should be configurable
		if (INSTANCE_CONTEXT !== 'local-docker') {
		    $this->msg('Fetching not allowed in context: ' . INSTANCE_CONTEXT, 'error');
		}

		if (!class_exists('\TYPO3\CMS\Core\Utility\GeneralUtility'))
		    include_once($this->PATH_site.'typo3/sysext/core/Classes/Utility/GeneralUtility.php');
		$filesToFetch = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(chr(10), $_POST['fetchFilesUrls']);

		foreach ($filesToFetch as $fileUrl) {
            // if given, replace the domain
			$sourceDomain = trim($_POST['fetchFilesDomainFrom']);

            $fileUrlParts = parse_url($fileUrl);
			$sourceDomainParts = parse_url($sourceDomain );

			// if no domain given, fetch original url
			$finalFileUrl = $fileUrl;

			// no scheme given, only domain - parse_url puts domain into 'path' key and nothing else
			if ($sourceDomainParts['path'] && !$sourceDomainParts['host'])
			    $finalFileUrl = $fileUrlParts['scheme'] . '://' . $sourceDomainParts['path'] . $fileUrlParts['path'];
			// scheme found
			else if ($sourceDomainParts['scheme'])
				$finalFileUrl = $sourceDomainParts['scheme'] . '://' . $sourceDomainParts['host'] . $fileUrlParts['path'];

            // fetch file and save
            $this->checkFile($finalFileUrl, $fileUrlParts);
		}
    }


	/**
	 * DATABASE QUERY EXEC
	 */
	private function action_databaseQuery($databaseQuery)	{

	    $queryMethod = $_POST['databaseQuery_method']  ?:  $this->option('defaultDatabaseQueryMethod');

		if (!$this->paramsRequiredPass(['databaseQuery' => $databaseQuery, 'databaseQuery_method' => $queryMethod]))
			return;

		switch ($queryMethod)   {
            case 'cli':
                // slashes even on windows have to be unix-style in execute source
                //$query = escapeshellarg($_POST['databaseQuery']);
                // manually escape double quotes (escapeshellarg doesn't do it as expected in this case) and remove linebreaks
                $query = str_replace([/*"'",*/ '"', "\n", "\r"], [/*"\'",*/ '\"', ' ', ' '], $_POST['databaseQuery']);
                $this->exec_control($this->dockerContainerCmd['php'] . "mysql --batch --quick --host={$this->getDbConf('host')} --user={$this->getDbConf('username')} --password=\"{$this->getDbConf('password')}\" --database={$this->getDbConf('database')}  --execute=\"{$query}\"");
                break;

            default:
            case 'mysqli':
                $this->mysqliExecQuery($databaseQuery);
        }
	}


    /**
     * XCLASS GENERATE
     */
	private function action_xClassGenerate($previewInfo = false)	{

        $originalClassFullName = $_POST['originalClassFullName'] ?: $this->mapClassNamespaceAndPath($_POST['originalClassFullPath']);
        // strip leading backslash if exists, to keep proper segment number after exploding
        $originalClassFullName = ltrim($originalClassFullName, '\\');
		$originalClassNamespaceParts = explode('\\', $originalClassFullName);
        $originalClassName = array_pop($originalClassNamespaceParts);
		$originalVendor = array_shift($originalClassNamespaceParts);
		if ($originalVendor === 'TYPO3')	{
			// in this case strip one more segment (usually 'CMS')
            array_shift($originalClassNamespaceParts);
			// should we put such classes into Classes/TYPO3/ or into additional TYPO3 namespace segment like in some projects? decision for now: NO
		}
		// only ext key UpperCammelCase
		$originalNamespaceExtKey = $originalClassNamespaceParts[0];

		$xclassName = $originalClassName;
		$xclassNamespace = $_POST['extSaveXclassNamespace'] . '\\' . implode('\\', $originalClassNamespaceParts);
		$xclassSavePath = $this->PATH_site . 'typo3conf/ext/' . $_POST['extSaveXclassKey'] . '/Classes/Ext/' . implode('/', $originalClassNamespaceParts).'/';
		$localconfSavePath = $this->PATH_site . 'typo3conf/ext/' . $_POST['extSaveXclassKey'] . '/Configuration/Ext/' . $originalNamespaceExtKey . '/';

		if ($previewInfo && $originalClassFullName)	{
			$this->msg("xClass full name: $xclassNamespace\\$xclassName", 'info');
			$this->msg("xClass save path: $xclassSavePath{$xclassName}.php", 'info');
			$this->msg("localconf: {$localconfSavePath}ext_localconf.php", 'info');
			return;
		}

        if (!$this->paramsRequiredPass(['originalClassFullName' => $_POST['originalClassFullName'], 'extSaveXclassNamespace' => $_POST['extSaveXclassNamespace']]))
            return;

        // clear file_exists status etc.. (still doesn't work well sometimes) // todo: if problem still occurs, add option to force overwrite
        clearstatcache();


        // make class directory structure
        if (!is_dir($xclassSavePath))	{
			$this->msg("Make class directory structure: {$xclassSavePath}", 'info');
            if (!mkdir($xclassSavePath, 0777, true))
            	$this->msg("- error: Cannot create dir!", 'error');
        }

        $xclassFilename = $xclassName . ".php";


        // make empty or copy original class
		switch ($_POST['xclassGenerate_method'])	{
			case 'empty':
                $this->msg("Generate empty XClass: {$xclassSavePath}{$xclassFilename}", 'info');

				// get original class to extend, to copy some contents
				$originalClassPath = $this->mapClassNamespaceAndPath('', $originalClassFullName);
				$originalClassContent = file_get_contents($this->PATH_site . $originalClassPath);
				preg_match_all('/use (.*?);/', $originalClassContent, $contentUseNamespace);
				$useNamespaces = implode("\n", $contentUseNamespace[0]);

				// create empty class with proper name, namespace and class operator (class ... extends ...)
                if (!file_exists($xclassSavePath . $xclassFilename))	{
                    $xclassContent = "<?php

namespace {$xclassNamespace};

{$useNamespaces}


class {$xclassName} extends \\{$originalClassFullName}	{
	
}

";
					// todo: copy all "use" namespaces from original class - it's handy to have them there anyway
                    if (!file_put_contents($xclassSavePath . $xclassFilename, $xclassContent))
                    	$this->msg("- error: Update XClass file problem, check write permissions", 'error');
                }
                else	{
                    $this->msg("- error: XClass file already exists!", 'error');
                }
                break;

			case 'copy':
			default:
                $this->msg("Copy class to xclass file: {$xclassSavePath}{$xclassFilename}", 'info');

				// get original class to extend, copy it to your ext's Classes dir, modify namespace and class operator line
				$originalClassPath = $this->mapClassNamespaceAndPath('', $originalClassFullName);

				if (!file_exists($xclassSavePath . $xclassFilename))	{
					if (copy($this->PATH_site . $originalClassPath, $xclassSavePath . $xclassFilename))	{
                    	// modify copied file contents
						$xclassContent = file_get_contents($xclassSavePath . $xclassFilename);

						// replace namespace
                        $xclassContent = preg_replace('#namespace (.*?)[\n]#m', "namespace {$xclassNamespace};\n", $xclassContent);

						// replace class name
						preg_match('#class (.*?)[\n]#m', $xclassContent, $matchesClass);
                        $xclassContent = preg_replace('#class (.*?)[\n]#m', "class {$xclassName} extends \\{$originalClassFullName}"
							// if there's bracket on end of the line, put it there back
							. (strpos($matchesClass[0], '{') ? ' {' : '')  . "\n", $xclassContent);

						if (!file_put_contents($xclassSavePath . $xclassFilename, $xclassContent))
                    		$this->msg("- error: Update class write problem, check permissions", 'error');
					}
                    else	{
                        $this->msg("- error: Copy problem! - Check directory structure or write permissions", 'error');
                    }
				}
				else	{
					$this->msg("- error: Class file already exists!", 'error');
				}
				break;
		}

		// put config with xclass register
		// make dir structure
        if (!is_dir($localconfSavePath))	{

        	// sometimes there's situation where ext directories are in lowercase like original keys, instead of UpperCamel. on windows docker it might cause a problem
			// where a single-word ext like "core" should be in "Core" dir but "core" already exists and is_dir returns false, but mkdir can't make this dir
			$localconfSavePathTest = str_replace('/'.$originalNamespaceExtKey.'/', '/'.strtolower($originalNamespaceExtKey).'/', $localconfSavePath);
            if (is_dir($localconfSavePathTest))	{
                $localconfSavePath = $localconfSavePathTest;
            	$this->msg("Found lowercase config directory structure: " . $localconfSavePathTest . ' - using this one.', 'info');
			}
			else	{
            	$this->msg("Make config directory structure: " . $localconfSavePath, 'info');
            	if (!mkdir($localconfSavePath, 0777, true))
            		$this->msg("- error: Cannot make config directory structure", 'error');
			}
        }


		// create ext_localconf if not exists
        if (!file_exists($localconfSavePath . 'ext_localconf.php'))	{
			$localconfContent = "<?php\n\n";
			$this->msg("Config file not found, create: " . $localconfSavePath . 'ext_localconf.php', 'info');
			if (!file_put_contents($localconfSavePath . 'ext_localconf.php', $localconfContent))
				$this->msg("- error: Config file create problem, check permissions", 'error');
        }
        // check again if file is there, before writing
        if (file_exists($localconfSavePath . 'ext_localconf.php')) {
            $localconfContentAdd = "

\$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\\{$originalClassFullName}::class] = [
	'className' => \\{$xclassNamespace}\\{$xclassName}::class
];

";

            // check if localconf has php closing tag on end and remove it to be able safely append new code on end
            $localconfContent = file_get_contents($localconfSavePath . 'ext_localconf.php');
            file_put_contents($localconfSavePath . 'ext_localconf.php', str_replace('?>', '', $localconfContent));

        	// add our new config on end
			$this->msg("Update localconf: " . $localconfSavePath . 'ext_localconf.php','info');
            if (!file_put_contents($localconfSavePath . 'ext_localconf.php', $localconfContentAdd, FILE_APPEND))
            	$this->msg("- error: Update localconf file write problem, check permissions", 'error');
        }
	}


	/**
	 * TYPO3 SYSTEM ACTION
	 */
	private function action_typo3System($systemActions)   {
	    foreach ((array) $systemActions as $systemAction)   {
	        switch ($systemAction)  {
                case 'enableInstallTool':
                	if (defined('TYPO3_OS') && TYPO3_OS === 'WIN')	{
		            	$this->exec_control("\$null > {$this->PATH_site}typo3conf/ENABLE_INSTALL_TOOL");
					}
                	else	{
		            	$this->exec_control("touch {$this->PATH_site}typo3conf/ENABLE_INSTALL_TOOL");
					}
		            break;
                case 'clearCache':
                    $this->exec_control("{$this->PATH_site}typo3cms cache:flush");
                    break;
                case 'clearTempDirectory':
                    if (TYPO3_MAJOR_BRANCH_VERSION >= 8) {
                        $this->exec_control("rm -R {$this->PATH_site}typo3temp/var/Cache/*");
                    }
                    else    {
                        $this->exec_control("rm -R {$this->PATH_site}typo3temp/Cache/*");
                    }
                    break;
                case 'clearAutoload':
                    if (TYPO3_MAJOR_BRANCH_VERSION >= 8) {
                        $this->exec_control("rm -R {$this->PATH_site}typo3conf/autoload/*");
                    }
                    else    {
                        $this->exec_control("rm -R {$this->PATH_site}typo3temp/autoload/*");
                    }
                    break;
                case 'regenerateAutoload':
                    $this->exec_control("{$this->PATH_site}typo3/cli_dispatch.phpsh extbase extension:dumpclassloadinginformation");
                    break;
				case 'phpPhpInfo':
					phpinfo();
                    break;
                case 'phpClearOpcache':
                    if (function_exists('opcache_reset')) {
                        $result = opcache_reset();
                        $this->msg('opcache_reset() called. result: ' . (string) $result, $result ? 'info' : 'warning'); 
                    }
                    else    {
                        $this->msg('opcache functions not supported', 'error');
                    }
                    break;
                
            }
        }
        // test
	    // $this->exec_control("ls -la {$this->PATH_site}");
	}
	
	
	

	/* exec shell command */
	private function exec_control($cmd, $saveCmd = true) {
	    $output = '';
		if ($this->option('dontExecCommands')) {
			$this->msg('command not executed - exec is disabled - @see option dontExecCommands', 'info');
		}
		elseif ($_POST['dontExec']) {
			$this->msg('(command not executed)', 'info');
        }
		else    {
		    if (ini_get('safe_mode')){
			    exec($cmd, $outputArray, $return);
			    $output = implode ("\n", $outputArray);
            }
		    else    {
			    // $output = shell_exec($cmd);
                // 2>&1 displays output even on error, which normally outputs null. comment if causes troubles
                // note that it doesn't work properly when && is used to join multiple commands in one line call
			    $output = shell_exec($cmd . ' 2>&1');
            }
        }

// var_dump($return);
// var_dump($output);
// var_dump($outputArray);
/*echo exec('whoami');
echo exec('groups');
echo exec('sudo -v');
echo exec('/usr/bin/docker -v');*/



		/*if ($this->option('docker'))   {
			$this->msg('running on docker - cmd probably didn\'t run. execute manually', 'info');
        }*/

		if ($saveCmd)   {
			$this->cmds[] = ['command' => $cmd, 'output' => $output];
        }
		
		return $output;
	}


    /* exec query on database connection */
	private function mysqliExecQuery($query)  {
		if ($this->option('dontExecCommands'))
			$this->msg('query not executed - exec is disabled - @see option dontExecCommands', 'info');
        elseif ($_POST['dontExec'])
			$this->msg('(query not executed)', 'info');
        else    {
            $dbConnection = new mysqli($this->getDbConf('host'), $this->getDbConf('username'), $this->getDbConf('password'), $this->getDbConf('database'));
            $affected = 0;
            if ($dbConnection->multi_query($query)) {
                do  {
                    $affected += $dbConnection->affected_rows;
                } while($dbConnection->more_results() && $dbConnection->next_result());
            }

            if ($dbConnection->connect_error)   $this->msg('Database connection error: <br>' . $dbConnection->connect_error, 'error');
            if ($dbConnection->error)           $this->msg('Database query error: <br>' . $dbConnection->error, 'error');
            else                                $this->msg('Mysqli multi_query called successfully. Affected rows: ' . $affected, 'info');

            $dbConnection->close();
	        $this->cmds[] = ['command' => htmlentities($query)];
        }
    }


	// INPUT CONTROL

	/* control required params for action */
	function paramsRequiredPass($params) {
		$pass = true;
		foreach ($params as $param => $value) {
			if (!$value) {
				$this->msg('error: <b>' . $param . '</b> must be set. ', 'error', $param);
				$pass = false;
			}
		}
		return $pass;
	}

	/* check if error of given field exists */
	function checkFieldError($param)	{
		if (array_key_exists($param, $this->messages))
			return true;
	}

	/* prints error class on form input, if present */
	function checkFieldError_printClass($param, $classes = '')	{
		if ($this->checkFieldError($param))
			$classes .= ' error';
		return ' class="'.$classes.'"';
	}


	// INFO DISPLAY

	private function getSysContextInfo()	{
		$infoLevel = 'info';

			//if (defined('DEV') && DEV)							$environment = 'DEV';
			//if (defined('LOCAL') && LOCAL)						$environment = 'LOCAL';
			//if (getenv('TYPO3_CONTEXT') == 'Development')   		$environment = 'Development';
			//if (getenv('TYPO3_CONTEXT') && !$environment)   		$environment = getenv('TYPO3_CONTEXT');

		$typo3_context = getenv('TYPO3_CONTEXT');
		if (!$typo3_context)	{
			$typo3_context = 'assumed Production';	// if not detected at this point, assume and warn that it's public
			$infoLevel = 'important';
		}
		if ($typo3_context == 'Production' || $typo3_context == 'PUBLIC')	{
			$infoLevel = 'important';
		}

		$instance_context = getenv('INSTANCE_CONTEXT');	// q3i: putenv("INSTANCE_CONTEXT=local-docker");

		return [
			'typo3_context' => $typo3_context,
			'instance_context' => $instance_context,
			'infoLevel' => $infoLevel,
		];
    }

	private function addEnvironmentMessage()	{
		$sys = $this->getSysContextInfo();
		$typo3_context_info = $sys['typo3_context'];

		// additional mark important contexts
		if (in_array($sys['typo3_context'], ['Production', 'assumed Production', 'PUBLIC']))
            $typo3_context_info .= ' !!!!';

		$message = '<span class="'.$sys['infoLevel'].'">'.$typo3_context_info.'</span>';

		if ($sys['instance_context'])
			$message .= '&nbsp;&nbsp; instance: <span class="info"><b>' . $sys['instance_context'] . '</b></span>';
		$this->sysContextInfo .= '<h4>running on: ' . $message . '</h4>';
	}

	/**
	 * add message/notice
	 * @param string $message
	 * @param string $class - class for notice p, may be error or info
	 * @param string $index - index can be checked in tag markup, to indicate error class in form element
	 */
    public function msg($message, $class = '', $index = '') {
		if ($index)	 $this->messages[$index] = [$message, $class];
		else			$this->messages[] = [$message, $class];
	}

	/* display generated messages with class if set */
	public function displayMessages()  {
		$content = '';
		foreach ($this->messages as $message) {
			$content .= '<p'.($message[1] ? ' class="'.$message[1].'">':'>') . $message[0] . '</p>';
		}
		return $content;
	}

	/* display generated command lines */
	public function displayGeneratedCommands()  {
		$content = '';
		if ($this->cmds  &&  !$this->option('dontShowCommands'))   {
            $cmdLines = [];
		    foreach ($this->cmds as $i => $cmd) {
			    $cmdLines[] = "&gt; <span class=\"cmdLine\" id=\"commandLineGenerated{$i}\" onclick=\"selectText('commandLineGenerated{$i}');\">" . $cmd['command'] . "</span>";
			    if ($cmd['output']) {
			        $cmdLines[] = "<pre>".$cmd['output']."</pre>";
                }
		    }
			$content .= "<p>- commands:</p>
                         <p><pre>" . implode('<br><br>', $cmdLines) . "</pre></p>";
        }
		return $content;
	}

	public function displayTooltip($title, $content = '', $additionalClass = '')  {
		return '<i class="tooltip'.($additionalClass ? ' '.$additionalClass : ''). '" title="'.htmlspecialchars($title).'">'.($content ? htmlspecialchars($content) : '&nbsp;').'</i>';
    }


    // FORM FIELDS

    public function formField_radio($name, $value, $valueDefault = '', $class = '', $id = '', $additionalParams = [])   {
	    $params = [
	        'type' => 'radio',
	        'name' => $name,
	        'value' => $value,
        ];
	    if ($class)     $params['class'] = $class;
	    if ($id)        $params['id'] = $id;
	    $params = array_merge($params, $additionalParams);
	    if ($_POST[$name] == $value  ||  (!$_POST[$name]  &&  $valueDefault == $value))
	        $params['checked'] = '';
	    $code = "<input ";
	    foreach ($params as $param => $value) {
	        $code .= $param . ($value ? '="'.$value.'"' : '');
	    }
	    $code .= ">";
	    return $code;
    }
    
    public function formField_check($name, $value, $valueDefault = '', $class = '', $id = '', $additionalParams = [])   {
	    $params = [
	        'type' => 'checkbox',
	        'name' => $name,
	        'value' => $value,
        ];
	    if ($class)     $params['class'] = $class;
	    if ($id)        $params['id'] = $id;
	    $params = array_merge($params, $additionalParams);
	    if ($_POST[$name] == $value  ||  (!$_POST[$name]  &&  $valueDefault == $value))
	        $params['checked'] = '';
	    $code = "<input ";
	    foreach ($params as $param => $value) {
	        $code .= $param . ($value ? '="'.$value.'"' : '');
	    }
	    $code .= ">";
	    return $code;
    }


	// CONFIG

	/* returns omit tables value to display in textarea */
	function getOmitTables()	{
		if ($_POST['omitTables'])
			return htmlspecialchars($_POST['omitTables']);
		return implode(chr(10), $this->option('defaultOmitTables'));
	}


	// SYSTEM

	/* list files */
	function getFilesFromDirectory($dir = 'DUMP', $ext = 'sql') {
		$files = glob('*.{' . $ext . '}', GLOB_BRACE);	// GLOB_BRACE: match multiple patterns in {comma,list}. NOTE that it's not regexp!
		if (!is_array($files))  $files = [];
		return $files;
	}

	/* list directories */
	function getFilesAndDirectories($dir = '', $skip = []) {
		$files = scandir ($this->PATH_site . $dir);
		if (!is_array($files))  $files = [];
		foreach ($files as $file) {
			if (preg_match("/(^(([\.]){1,2})$|(\.(svn|git|md))|(Thumbs\.db|\.DS_STORE))$|^deprecation_/iu", $file, $match))
				$skip[] = $file;
		}
		$files = array_diff($files, $skip);
		return $files;
	}

	/* returns dump filenames, to see what version number should be next */
	function getExistingDumpsFilenames()	{
		return chr(10) . implode (chr(10), $this->getFilesFromDirectory());
	}

	/* check if is runinng on docker (local-docker, local_docker etc.) */
	static function isDocker()	{
		if (defined('INSTANCE_CONTEXT'))	{
			return strstr(INSTANCE_CONTEXT, 'docker');
		}
		return false;
	}

	/* tries to extract container name from docker config dir */
	static function detectDockerContainerName($containerType, $dockerEnv = '') {
		if (!static::isDocker())
			return '';
	    $filename = '../_docker/php_proxy' . ($dockerEnv ? '__'.$dockerEnv : '') . '.sh';
	    if (file_exists($filename))  {
            $filecontent = file_get_contents($filename);
            preg_match('/docker exec -it (.*) \$@/m', $filecontent, $matches);
            if ($containerName = $matches[1])   {
	            switch ($containerType) {
                    case 'php':
                        return $containerName;
                    case 'mysql':
                        return str_replace('_php_', '_mysql_', $containerName);
	            }
            }
        }
        return 'CONTAINER NAME NOT DETECTED!';
    }

    /* database connection */
    function databaseConnect() {
        if ($this->getDbConf('host'))  {
	        $this->dbConnection = new mysqli($this->getDbConf('host'), $this->getDbConf('username'), $this->getDbConf('password'), $this->getDbConf('database'));
	        $this->databaseVersion = $this->dbConnection->get_server_info();
        }
    }

    /* try database */
    function databaseTest() {
        $msg = '<span class="error">unknown</span>';
        if (is_object($this->dbConnection))	{
	        if ($this->dbConnection->connect_error)
		        $msg = '<span class="error">error<br>'.$this->dbConnection->connect_error.'</span>';
	        else
		        $msg = '<span class="info">OK</span>';
        }
	    return $msg;
    }

    /* try to find class namespace from given relative path / relative path from namespace. it needs autoload to be generated */
    function mapClassNamespaceAndPath($pathRel = '', $namespace = '')	{

    	// load autoload data
		switch (TYPO3_MAJOR_BRANCH_VERSION)	{
			case 7:
				$autoloadPath = $this->PATH_site . 'typo3temp/autoload/';
				break;
			case 8:
			case 9:
			default:
				$autoloadPath = $this->PATH_site . 'typo3conf/autoload/';
		}
		$autoloadTemp = (array) @include($autoloadPath . 'autoload_classmap.php');
		$autoloadPSR4 = (array) @include($autoloadPath . 'autoload_psr4.php');
		$autoloadVendor = (array) @include($this->PATH_site . 'vendor/composer/autoload_classmap.php');
		$autoloadAll = array_merge($autoloadTemp, $autoloadVendor);

		// find NAMESPACE
		if ($pathRel && !$namespace)	{
			// strip path to proper project root dir if your IDE project root is one level higher (like it is by default on git or svn)
            $originalClassFullPath = str_replace(['htdocs/', 'trunk/', 'httpdocs/', 'public_html/'], '', $_POST['originalClassFullPath']);
			// make sure to strip leading slash
            $originalClassFullPath = ltrim($originalClassFullPath, '/');
			// vendorDir & baseDir are defined in vendor classmap
			if (isset($baseDir) && isset($vendorDir))
            	$originalClassFullPath = str_replace(['typo3/sysext/', 'typo3conf/', 'vendor/'], [$baseDir.'/typo3/sysext/', $this->PATH_site.'typo3conf/', $vendorDir.'/'], $originalClassFullPath);
			else
                $originalClassFullPath = $this->PATH_site . $originalClassFullPath;
			$namespace = array_search($originalClassFullPath, $autoloadAll);
			if ($namespace)
			    return $namespace;

			// try to find in PSR4
			[$originalClassFullPath_extDir, $originalClassFullPath_classFilePath] = explode('Classes/', $originalClassFullPath);
			// the keys point to the Classes dir, not files itself, so try to find this path (is in array!)
            $psr4NamespaceExt = array_search([$originalClassFullPath_extDir . 'Classes'], $autoloadPSR4);
            // build rest of namespace
            $namespace = $psr4NamespaceExt . str_replace(['/', '.php'], ['\\', ''], $originalClassFullPath_classFilePath);
            return $namespace;
		}

		// find PATH
		if ($namespace && !$pathRel)	{
			$path = $autoloadAll[$namespace];
			$pathRel = str_replace($this->PATH_site, '', $path);
			if ($pathRel)
			    return $pathRel;

			// try to find in PSR4
            $namespaceParts = explode('\\', $namespace);
            // get two first parts
            $extPath_classes = $autoloadPSR4[ implode('\\', array_slice($namespaceParts, 0, 2)) . '\\' ];
            $path = $extPath_classes[0] . '/' . implode('/', array_slice($namespaceParts, 2)) . '.php';
            return str_replace($this->PATH_site, '', $path);
		}
		//Throw new Exception('mapClassNamespaceAndPath(): Wrong use, bad params!');	// no exception handling for now. maybe one day
	}


	// TEMPLATING

	/* typo3-like standard replace marker method */
	function substituteMarkerArray($subject, $markerArray)	{
		return str_replace(array_keys($markerArray), array_values($markerArray), $subject);
	}


	// FILE FETCH

    /**
     * Returns information about a folder.
     * @param string $fileUrl Relative path to the file
     * @param array $fileUrlParts
     * @return void
     */
	public function checkFile($fileUrl, $fileUrlParts)    {

        $allowedExtensions = ['jpg', 'png', 'gif', 'svg', 'txt', 'csv', 'pdf'];
		$requestedFilePathInfo = pathinfo($fileUrlParts['path']);
		$localFileDirectory = $this->PATH_site . $requestedFilePathInfo['dirname'] . '/';
		$localFilePath = $localFileDirectory . $requestedFilePathInfo['basename'];
		// in case of some url problems strip one unnecessary slash

		if (!file_exists($localFilePath)) {

            // if file ext match image
            if (in_array($requestedFilePathInfo['extension'], $allowedExtensions))   {

                // create dir structure
                if (!is_dir($localFileDirectory)) {
                    mkdir($localFileDirectory, 0777, TRUE);
                }
                // get the file and store it locally
                $fetch = $this->fetchFile($fileUrl, $localFilePath);

	            if (file_exists($localFilePath))
                    $this->msg($fetch['result'], 'info');
	            else    {
		            $this->msg('FETCH ERROR! result: ' . print_r($fetch, true), 'error');
	            }
            }
            else    {
                $this->msg('Extension of file '.$requestedFilePathInfo['basename'].' is not on allowed extensions list', 'error');
            }
        }
	}


	/**
	 * borrowed from _docker/get_images.php
	 * @param string $url       Source - File url to fetch
	 * @param string $saveto    Target - Local file path + name to save
	 * @return array            Result log
	 */
	protected function fetchFile($url, $saveto){
		$log = [];
		$ch = curl_init ($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10); //timeout in seconds
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
		$raw = curl_exec($ch);
		$status = curl_getinfo ( $ch, CURLINFO_HTTP_CODE);
		$log[] = "Grab {$url}";
		$log[] = "Status: {$status}";
		if($status == 200 && $raw && !file_exists($saveto)){
			$fp = fopen($saveto,'x');
			fwrite($fp, $raw);
			fclose($fp);
			$log['result'] = "Image {$saveto} saved";
		}
		else    {
			$log['result'] = "Curl error: " . curl_error($ch);
		}
		curl_close ($ch);
		return $log;
	}
}

?><html>
<head>
	<title>DUMP - <?php print $_SERVER['HTTP_HOST']; ?></title>
	<style>
		*, ::after, ::before {box-sizing: border-box;}
		:root	{--w--bg: #fefefe; --w--primary: #111;	--w--link: #03d;  --w--info: #282; --w--error: #d00;  --w--marked: darkorange; --w--gray1: #a9a9a9; --w--gray2: #aaa;  --w--gray3: #bbb;  --w--gray4: #eee;  --w--gray5: gainsboro;  --w--gray6: #999;}
        body    {font-family: Arial, Tahoma, sans-serif; margin-top: 0; padding-top: 170px; background-color: var(--w--bg); color: var(--w--primary);}
		ul  {list-style: none;  float: left;    margin-top: 0;  padding: 0;}
        li  {margin: 4px 0;}
        pre     {line-height: 1.2em; white-space: pre-wrap;}
		label	{clear: both;   display: inline-block;}
        label pre   {margin: 0;}
		label input	{float: left;   cursor: pointer;}
        a	{text-decoration: none;		color: var(--w--link);}
		a:hover	{text-decoration: underline;}

        .to-left	{float: left;}
		.hidden	 {display: none !important;}
		.indent	 {margin-left: 40px;}
        .clear	{clear: both;}
		.error	{color: var(--w--error);}
		.important	{color: var(--w--error);}
		.info	{color: var(--w--info);   font-style: italic;     font-family: monospace;     font-size: 1.2em;   font-weight: 100;}

		.config-env p	{padding-left: 16px;}

        select   {margin-right: 10px;}
		input[type=radio]:checked + .action-sub-options  {display: block;}
		input[type=checkbox]    {margin: 2px 6px 2px 0;}
        input[type=radio]       {margin: 2px 6px 2px 0;     outline: none;     cursor: pointer;}
        input[type=submit]      {padding: 6px 16px; cursor: pointer;}
        input[type=submit]:hover  { background: ;}
        input[disabled], textarea[disabled] {cursor:not-allowed;}
        input[type=text], select, textarea  {border: 1px solid var(--w--gray1);     box-shadow: inset 4px 4px 5px -2px var(--w--gray3);  padding: 6px;}

		.actions ul   {background: var(--w--gray4);    padding: 20px;  box-shadow: 5px 5px 8px -2px  var(--w--gray2);}
		.actions li > label:hover   {color: var(--w--marked);}
		.actions li.active > label  {color: var(--w--marked);}
		.actions-selector > ul > li > label > span	{float: left;   width: 260px;   cursor: pointer;}
		.action-sub-options  {display: none;    padding: 10px 20px 20px;    background: var(--w--gray5);  margin: 10px 0 20px 20px;  box-shadow: 5px 5px 8px -2px  var(--w--gray2);}
		.action-sub-options label   {display: block;        padding: 2px 0 4px;}
		.form-row {display: block;  margin-bottom: 12px;}
        .form-row-checkbox label, .form-row-radio label {cursor: pointer;}
		.selector-tables textarea   {overflow-wrap: normal;}
		.predefined-sets span.link-set:not(:first-child):before	{content: '\00A0 | \00A0';}

        footer	 {font-size: 80%;  margin-top: 70px;}
		.config p	{margin: 8px 0;	line-height: 1.4em;}
		.tooltip	{background: url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCAyNTYgMjU2IiB3aWR0aD0iMjU2IiBoZWlnaHQ9IjI1NiI+CjxwYXRoIGQ9Im0xMjggMjIuMTU4YTEwNS44NCAxMDUuODQgMCAwIDAgLTEwNS44NCAxMDUuODQgMTA1Ljg0IDEwNS44NCAwIDAgMCAxMDUuODQgMTA1Ljg0IDEwNS44NCAxMDUuODQgMCAwIDAgMTA1Ljg0IC0xMDUuODQgMTA1Ljg0IDEwNS44NCAwIDAgMCAtMTA1Ljg0IC0xMDUuODR6bTAgMzIuNzZjNS4xNiAwLjExNyA5LjU1IDEuODc1IDEzLjE4IDUuMjczIDMuMzQgMy41NzUgNS4wNyA3Ljk0IDUuMTkgMTMuMDk2LTAuMTIgNS4xNTYtMS44NSA5LjQwNC01LjE5IDEyLjc0NC0zLjYzIDMuNzUtOC4wMiA1LjYyNS0xMy4xOCA1LjYyNXMtOS40LTEuODc1LTEyLjc0LTUuNjI1Yy0zLjc1LTMuMzQtNS42My03LjU4OC01LjYzLTEyLjc0NHMxLjg4LTkuNTIxIDUuNjMtMTMuMDk2YzMuMzQtMy4zOTggNy41OC01LjE1NiAxMi43NC01LjI3M3ptLTE2LjM1IDUzLjc5MmgzMi43OXY5Mi4zN2gtMzIuNzl2LTkyLjM3eiIgZmlsbC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNzJhN2NmIi8+Cjwvc3ZnPgo=');
            background-size: 16px 16px;     background-position: left center;   background-repeat: no-repeat;   min-height: 16px;   display: inline-block;  padding-left: 16px; cursor: help;   margin-left: 4px;}
		.clickable	{cursor: pointer;}

		header .version	{font-size: 12px; color: var(--w--gray6); margin-left: 10px; vertical-align: super;}
		header h2 {margin: 4px 0;  transition: font-size .1s;}
		header h2 svg {vertical-align: bottom;  transition: width .1s, height .1s;}
		header h4 {margin: 4px 0;  transition: font-size .1s;}
		.sticky	{position: fixed; top: 0; z-index: 1; width: 100%;  background-color: var(--w--bg);}
		.sticky.sticked	{}
			.sticked h2 {float: left; font-size: 1.2em;}
			.sticked h4 {font-size: 1em;}
			.sticked svg {width: 24px; height: 24px;}
			.sticked .config-sys {float: left;  padding-left: 50px;}
			.sticked pre {margin: 4px 0;}

        @media screen and (min-width: 900px) {
            .actions    {position: relative;}
            .action-sub-options {min-width: calc((50% / 3) * 2);     top: 0;     left: 390px;    margin: 0 0 20px;  position: absolute;}
        }
	</style>
    <script>
	let Dump = {

		selectText: (containerId) => {
			let range;
			if (document.selection) {
				range = document.body.createTextRange();
				range.moveToElementText(document.getElementById(containerId));
				range.select();
			} else if (window.getSelection()) {
				range = document.createRange();
				range.selectNode(document.getElementById(containerId));
				window.getSelection().removeAllRanges();
				window.getSelection().addRange(range);
			}
		},

		selectDomainsPredefinedSet: (targetElementId, sourceElementId, setElementsCount) => {
			document.getElementById(targetElementId).innerHTML = document.getElementById(sourceElementId).innerHTML;
			document.getElementById(targetElementId).rows = setElementsCount;
		},

		toggleInput: (triggerId, inputId, reverse) => {
			let trigger = document.getElementById(triggerId);
			let input = document.getElementById(inputId);
			input.disabled = !(reverse ? !trigger.checked : trigger.checked);
		},


		init: () => {
			let stickyHeadPart = document.getElementById('head_part');
            let stickAfter = 5;
            let paddingAdd = 10;
            let stickyHeadPartHeight = parseInt(getComputedStyle(stickyHeadPart, null).height.replace('px', ''));
            document.body.style.paddingTop = stickyHeadPartHeight + paddingAdd + 'px';


            window.addEventListener('scroll', e => {
                let scrollPos = window.scrollY;
                //let bodyTopPadding = 

                if (scrollPos > stickAfter)	{
						stickyHeadPart.classList.add('sticked');
                        // body.style.paddingTop = bodyTopPadding + 'px';
				}
                else	{
						stickyHeadPart.classList.remove('sticked');
                        // body.style.paddingTop = '0';
				}
			});


			// allow unchecking radio buttons using ctrl key
			document.addEventListener('click', e => {
				if (e.ctrlKey === true &&
					e.target.tagName === 'INPUT' &&
					e.target.type === 'radio' &&
					e.target.checked === true) {
						e.target.checked = false;
				}
			});


			// make input box enabled/disabled depending on checkbox checked
			document.addEventListener('DOMContentLoaded', e => {
				Dump.toggleInput('omitTablesIncludeInQuery', 'omitTables');
				Dump.toggleInput('ignoreSelectionAndPackAll', 'filenameSelectionInclude');
				Dump.toggleInput('ignoreSelectionAndPackAll', 'filenameSelectionExclude');
			});
		},
	};
    </script>
</head>
<body>
  	<header class="sticky" id="head_part">

		<h2><svg  width="32"  height="32"   xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128">
				<path fill="#4a4a4a" d="M0 0h128v128H0z"/>
				<path fill="#b4b4b4" d="M36.793,49.874c0.393-2.458,0.896-5.125,1.512-8 c0.614-2.875,1.29-5.825,2.027-8.848c0.738-3.023,1.523-6.021,2.36-8.996c0.835-2.974,1.646-5.789,2.433-8.443H34.064
					c-1.131,2.557-2.372,5.322-3.723,8.295c-1.353,2.975-2.667,5.985-3.945,9.032c-1.279,3.049-2.483,6.022-3.613,8.922 c-1.131,2.901-2.041,5.58-2.728,8.037H36.793z"/>
				<path fill="#b4b4b4" d="M54.748,105.986c0.516,1.131,1.228,2.127,2.138,2.986 c0.909,0.861,1.965,1.537,3.171,2.028c1.204,0.491,2.494,0.737,3.871,0.737c1.425,0,2.74-0.247,3.945-0.737
					c1.204-0.491,2.249-1.167,3.133-2.028c0.885-0.859,1.585-1.855,2.102-2.986c0.517-1.13,0.774-2.334,0.774-3.613 c0-1.327-0.258-2.544-0.774-3.65c-0.516-1.106-1.216-2.076-2.102-2.913c-0.885-0.835-1.93-1.486-3.133-1.954
					c-1.205-0.466-2.52-0.701-3.945-0.701c-1.377,0-2.667,0.234-3.871,0.701c-1.206,0.468-2.262,1.119-3.171,1.954 c-0.91,0.836-1.622,1.807-2.138,2.913s-0.774,2.323-0.774,3.65C53.973,103.652,54.231,104.856,54.748,105.986z"/>
				<path fill="#b4b4b4" d="M90.841,15.734c-0.344,2.458-0.811,5.125-1.401,8 c-0.59,2.875-1.229,5.825-1.917,8.848c-0.689,3.023-1.438,6.022-2.249,8.996c-0.811,2.975-1.659,5.788-2.544,8.443h10.839
					c1.18-2.556,2.457-5.309,3.834-8.258c1.375-2.95,2.716-5.936,4.019-8.959c1.302-3.023,2.519-5.997,3.65-8.922 c1.13-2.924,2.04-5.641,2.729-8.148H90.841z"/>
			'.' </svg>
			DUMP tool <span class="version"><?php	print DUMP_VERSION;	?></span>
		</h2>


		<div class="config-sys">
			<pre>PATH_site = <span id="pre_path_site" onclick="Dump.selectText('pre_path_site');"><?php  print PATH_site;  ?></span></pre>
			<pre>PATH_dump = <span id="pre_path_dump" onclick="Dump.selectText('pre_path_dump');"><?php  print PATH_dump;  ?></span></pre>
		</div>


		<div class="clear  config-context">
			<?php  print $Dump->sysContextInfo;	 ?>
		</div>
  	</header>

	<div class="config-env">
		<?php  print $Dump->configInfoHeader;	 ?>
	</div>

    <div class="results">
        <?php  print $Dump->displayGeneratedCommands();  ?>
        <?php  print $Dump->displayMessages();  ?>
    </div>


    <div class="form">
        <form action='' method='post'>
            <h3<?php print $Dump->checkFieldError('action'); ?>>action:</h3>
            <div class="actions">
                <div class="indent actions-selector">
                    <ul>
                        <?php
                            $actions = [
								[
                                    'label' => 'TYPO3 & system actions',
                                    'name' => 'typo3System',
                                    'options' => [
                                        [
                                            'label' => "TYPO3 & system actions",
                                            'content' => function() use ($Dump) {
                                                $code = "
                                                    <div class='form-row form-row-radio'>
                                                        <label>". $Dump->formField_check('typo3SystemAction[]', 'enableInstallTool')
                                                    . "<pre>> touch typo3conf/ENABLE_INSTALL_TOOL</pre></label>
                                                        <label>". $Dump->formField_check('typo3SystemAction[]', 'clearTempDirectory')
                                                    . "<pre>> rm -R typo3temp/Cache/*  &&  rm -R typo3temp/var/Cache/*</pre></label>
                                                        <label>". $Dump->formField_check('typo3SystemAction[]', 'clearAutoload')
                                                    . "<pre>> rm -R typo3temp/autoload/*  &&  rm -R typo3conf/autoload/*</pre></label>
                                                        <label>". $Dump->formField_check('typo3SystemAction[]', 'phpPhpInfo')
                                                    . "<pre>php: php_info()</pre></label>
                                                        <label>". $Dump->formField_check('typo3SystemAction[]', 'phpClearOpcache')
                                                    . "<pre>php: opcache_reset()</pre></label>
                                                        <label>". $Dump->formField_check('typo3SystemAction[]', 'regenerateAutoload')
                                                    . "<pre>typo3 cli: regenerate autoload</pre></label>
                                                        <label>". $Dump->formField_check('typo3SystemAction[]', 'clearCache')
                                                    . "<pre>typo3 cli: clear cache (ext:typo3_console)</pre></label>
                                                    </div>";
                                                return $code;
                                            }
                                        ],
                                    ],
                                ],
                                [
                                    'label' => 'Database - IMPORT &DoubleLeftArrow;',
                                    'name' => 'databaseImport',
                                    'options' => [
                                        [
                                            'label' => "Import database",
                                            'valid' => !$Dump->checkFieldError('dbFilename'),
                                            'class' => 'selector-database',
                                            'content' => function() use ($Dump) {
                                                $options = "<option></option>";
                                                foreach ($Dump->getFilesFromDirectory() as $file)
	                                                $options .= "<option>".$file.'</option>';
                                                $code = "<label for='dbFilenameSel'>Select filename:</label>
                                                            <div class='form-row'>
                                                            <select name='dbFilenameSel' id='dbFilenameSel'>
                                                                {$options}
                                                            </select>
                                                            or type: <input name='dbFilename' type='text'>
                                                        </div>
                                                        <div class='form-row'>
                                                            <label><input type='checkbox' name='importDatabaseOldMethod'".($_POST['importDatabaseOldMethod'] ? " checked" : '').">
                                                                Use old query method ".$Dump->displayTooltip(
                                                                    'like that: ... --execute="SET NAMES \'utf8\'; SET collation_connection = \'utf8_unicode_ci\'; SET collation_database = \'utf8_unicode_ci\'; SET collation_server = \'utf8_unicode_ci\'; source /var/www/htdocs/DUMP/filename.sql"'
                                                                ) . "
                                                            </label>
                                                        </div>";
	                                            return $code;
                                            }
                                        ],
                                    ],
                                ],
                                [
                                    'label' => 'Database - EXPORT &DoubleRightArrow;',
                                    'name' => 'databaseDump',
                                    'options' => [
                                        [
                                            'label' => 'Export database',
                                            'class' => 'selector-tables',
                                            'content' => function() use ($Dump) {
                                                $code = "<div class='form-row form-row-checkbox'><label>
                                                            <input type='checkbox' name='omitTablesIncludeInQuery' id='omitTablesIncludeInQuery' 
                                                            	onclick='Dump.toggleInput(\"omitTablesIncludeInQuery\", \"omitTables\");'"
																. ($_POST['omitTablesIncludeInQuery'] ? " checked" : '') . ">
                                                            Omit these tables (export only structure):</label>
                                                        </div>
                                                        <div class='form-row'>
                                                            <textarea name='omitTables' id='omitTables' cols='48' rows='8'>{$Dump->getOmitTables()}</textarea>
                                                        </div>";
	                                            return $code;
                                            }
                                        ],
                                    ],
                                ],
                                [
                                    'label' => 'FILESYSTEM pack',
                                    'name' => 'filesystemPack',
                                    'options' => [
                                        [
                                            'label' => "Archive filesystem (tgz)",
                                            'class' => 'selector-pickfiles',
                                            'content' => function() use ($Dump) {

                                                $code = "<p><i>Select directories / files</i></p>
                                                    <div class='form-row'>
                                                        <div class='to-left'>
                                                            <label>INCLUDE:</label>
                                                            <select name='filenameSelectionInclude[]' id='filenameSelectionInclude' size='10' multiple>";

                                                                foreach ($Dump->getFilesAndDirectories('', ['DUMP']) as $dir)  {
                                                                    $included = is_array($_POST['filenameSelectionInclude']) ? $_POST['filenameSelectionInclude'] : $Dump->option('defaultIncludeFilesystem');
                                                                    $selected = in_array($dir, $included) ? ' selected' : '';
                                                                    $code .= "<option{$selected}>".$dir.'</option>';
                                                                }

                                                $code .= "</select>
                                                        </div>
                                                        <div class='to-left'>
                                                            <label>EXCLUDE:</label>
                                                            <select name='filenameSelectionExclude[]' id='filenameSelectionExclude' size='10' multiple>";

                                                                $listSubdirsOf = $Dump->option('defaultExcludeFilesystem_listItemsFromDirs');
                                                                foreach ($listSubdirsOf as $dir)
                                                                    foreach ($Dump->getFilesAndDirectories($dir) as $subdir) {
                                                                        $subdirPath = $dir . '/' . $subdir;
                                                                        $excluded = is_array($_POST['filenameSelectionExclude']) ? $_POST['filenameSelectionExclude'] : $Dump->option('defaultExcludeFilesystem');
                                                                        $selected = in_array($subdirPath, $excluded) ? ' selected' : '';
                                                                        $code .= "<option{$selected}>" . $subdirPath . '</option>';
                                                                    }

                                                $code .= "</select>
                                                        </div>
                                                        <div class='clear'></div>
                                                    </div>
                                                    <div class='form-row form-row-checkbox'>
                                                        <label><input type='checkbox' id='ignoreSelectionAndPackAll' name='ignoreSelectionAndPackAll'
                                                        	onclick='Dump.toggleInput(\"ignoreSelectionAndPackAll\", \"filenameSelectionInclude\", true); Dump.toggleInput(\"ignoreSelectionAndPackAll\", \"filenameSelectionExclude\", true);'"
															. ($_POST['ignoreSelectionAndPackAll'] ? " checked" : '').">
                                                        	Ignore selection and pack all</label>
                                                    </div>
                                                    <div class='form-row form-row-checkbox'>
                                                        <label><input type='checkbox' id='dereferenceSymlinks' name='dereferenceSymlinks'"
                                                        	. ($_POST['dereferenceSymlinks'] ? " checked" : '').">
                                                        	Dereference symlinks ".$Dump->displayTooltip(
																'adds --dereference (-h) to tar command, to archive symlink targets instead of only symlink pointers'
                                                        	) . "</label>
                                                    </div>";
                                                return $code;
                                            }
                                        ],
                                    ],
                                ],
                                [
                                    'label' => 'Dump ALL',
                                    'name' => 'dump_all',
                                    'options' => [
	                                    [
		                                    'label' => 'Export whole project',
		                                    'content' => '<p><i>Dumps whole database (doesn\'t omit any tables) <br>+ archives whole project dir with everything in it (excluding /DUMP dir and --exclude-vcs)</i></p>'
	                                    ],
                                    ],
                                ],
								[
									'label' => 'List dump files',
									'name' => 'list_dumps',
									'options' => [
										[
											'label' => 'List dump files ',
											'content' => function() use ($Dump) {
                            					$files = $Dump->getFilesFromDirectory('DUMP', 'sql,tgz,tar,zip,rar');
                            					$filesContent = '';

                            					if (count($files)) foreach($files as $file)	{
                                                    // human readable size snippet
                                                    $sz = 'BKMGTP';
                                                    $bytes = filesize(PATH_dump . $file);
                                                    $factor = floor((strlen($bytes) - 1) / 3);
                                                    $filesContent .= '<li><a href="' . $file . '">' . $file . ' (' .
														sprintf("%.2f", $bytes / pow(1024, $factor)) . @$sz[$factor]
														. ')</a></li>';
												}

                            					$code = '<p><i>Download files from DUMP directory</i></p>'
                                                . '<ul>'
                                                	. $filesContent
                                                . '</ul>';

                            					return $code;
											}
										],
									],
								],
                                [
                                    'label' => 'Backup project dir',
                                    'name' => 'backup',
                                    'options' => [
	                                    [
		                                    'label' => 'Make a backup of project filesystem',
		                                    'content' => '<p><i>Makes subdir with time in name in parent dir and calls rsync -av (excluding /DUMP and /.git dirs)</i></p>'
	                                    ],
                                    ],
                                ],
                                [
                                    'label' => 'DOMAINS update',
                                    'name' => 'domainsUpdate',
                                    'options' => [
                                        [
                                            'label' => "Updates domains in database",
                                            //'valid' => !$Dump->checkFieldError('domainsFrom'),
                                            'class' => 'selector-domains',
                                            'content' => function() use ($Dump) {
                                                $domainsFrom = (is_array($_POST['domainsFrom']) && count($_POST['domainsFrom']))  ?  $_POST['domainsFrom']  :  trim($Dump->option('updateDomains_defaultDomainSet')[ $Dump->option('updateDomains_defaultDomainSetFrom') ]);
                                                $domainsTo = (is_array($_POST['domainsTo']) && count($_POST['domainsTo']))  ?  $_POST['domainsTo']  :  trim($Dump->option('updateDomains_defaultDomainSet')[ $Dump->option('updateDomains_defaultDomainSetTo') ]);
                                                $countDomainFrom = count(explode("\n", $domainsFrom))  OR  5;
                                                $countDomainTo = count(explode("\n", $domainsTo))  OR  5;

                                                $linksSetsFrom = '';
                                                $linksSetsTo = '';
                                                foreach (is_array($Dump->option('updateDomains_defaultDomainSet')) ? $Dump->option('updateDomains_defaultDomainSet') : [] as $domainSetKey => $domainSet)	{
                                                	$domainSetId = 'placeholder_domainset_'.$domainSetKey;
                                                	$domainSetCount = count(explode("\n", trim($domainSet)));
                                                    $domainSetOnclickFrom = "Dump.selectDomainsPredefinedSet('domainsFrom', '{$domainSetId}', {$domainSetCount}); return false;";
                                                    $domainSetOnclickTo = "Dump.selectDomainsPredefinedSet('domainsTo', '{$domainSetId}', {$domainSetCount}); return false;";
                                                    $linksSetsFrom .= '<span class="link-set"><a href="#" onclick="'.$domainSetOnclickFrom.'">'.$domainSetKey.'</a></span> '
															. '<div class="hidden" id="'.$domainSetId.'">'.trim($domainSet).'</div>';
                                                    $linksSetsTo .= '<span class="link-set"><a href="#" onclick="'.$domainSetOnclickTo.'">'.$domainSetKey.'</a></span> '
                                                            . '<div class="hidden" id="'.$domainSetId.'">'.trim($domainSet).'</div>';
												}
												if ($linksSetsFrom)
                                                    $linksSetsFrom = "<label class='predefined-sets'>Predefined sets: {$linksSetsFrom}</label>";
                                                if ($linksSetsTo)
                                                    $linksSetsTo = "<label class='predefined-sets'>Predefined sets: {$linksSetsTo}</label>";


                                                $code = "
                                                    <p><i>Replace domains in sys_domain records and pages external urls<br>
                                                        Write linebreak-separated domains or choose predefined set </i></p>
                                                    <div{$Dump->checkFieldError_printClass('domainsFrom', 'form-row')}>
                                                        <label>Domains <b>FROM</b>:</label> 
                                                        {$linksSetsFrom}
                                                        <textarea name='domainsFrom' id='domainsFrom' rows='{$countDomainFrom}' cols='50'>".
                                                            $domainsFrom
                                                        ."</textarea>
                                                    </div>
                                                    <div{$Dump->checkFieldError_printClass('domainsTo', 'form-row')}>
                                                        <label>Domains <b>TO</b>:</label>
                                                        {$linksSetsTo}
                                                        <textarea name='domainsTo' id='domainsTo' rows='{$countDomainTo}' cols='50'>".
															$domainsTo
                                                        ."</textarea>
                                                    </div>
                                                    <div class='form-row form-row-radio'>
                                                        <label>{$Dump->formField_radio('domainsUpdate_method', Dump::DATABASE_QUERY_METHOD__MYSQLI, $Dump->option('defaultDatabaseQueryMethod'))}
	                                                	Mysqli - php connection</label>
                                                        <label>{$Dump->formField_radio('domainsUpdate_method', Dump::DATABASE_QUERY_METHOD__CLI, $Dump->option('defaultDatabaseQueryMethod'))}
	                                                	CLI - command line bin execute</label>
                                                    </div>";
                                                return $code;
                                            }
                                        ],
                                    ],
                                ],
                                // example of action options with separate fields validation
                                [
                                    'label' => 'Manually fetch files',
                                    'name' => 'filesFetch',
                                    'options' => [
                                        [
                                            'label' => "Download files",
                                            //'valid' => !$Dump->checkFieldError('fetchFilesUrls'),
                                            //'class' => 'selector-filesfetch',
                                            'content' => function() use ($Dump) {
                                                $code = "
                                                    <p>
                                                        <i>Download these files directly into their target dirs, optionally replace domain in given urls.</i>
                                                        {$Dump->displayTooltip('If you have local instance and some media contents are missing and needed for testing,'.chr(10)
                                                                                . 'you can quickly paste 404 urls here, enter target domain to fetch from - or leave empty to use '.chr(10)  
                                                                                . 'directly given urls. The files will be downloaded from there and saved in their location.')}
                                                    </p>
                                                    
                                                    <div class='form-row'>
                                                        <label>Source domain to fetch from:</label>
                                                        <input name='fetchFilesDomainFrom' id='fetchFilesDomainFrom' value='{$Dump->option('fetchFiles_defaultSourceDomain')}' type='text'>
                                                    </div>
                                                    <div{$Dump->checkFieldError_printClass('fetchFilesUrls', 'form-row')}>
                                                        <label>URLs:</label>
                                                        <textarea name='fetchFilesUrls' id='fetchFilesUrls' rows='10' cols='80'></textarea>
                                                    </div>";
                                                return $code;
                                            }
                                        ],
                                    ],
                                ],
                                [
                                    'label' => 'Database - exec QUERY',
                                    'name' => 'databaseQuery',
                                    'options' => [
                                        [
                                            'label' => 'Database manipulate',
                                            //'valid' => !$Dump->checkFieldError('databaseQuery'),
                                            'content' => function() use ($Dump) {
                                                return "<div{$Dump->checkFieldError_printClass('databaseQuery', 'form-row')}>
                                                        <p><i>Credentials:</i><br>
                                                        	<pre>" .
                                                        	"user: {$Dump->getDbConf('username')}\n" .
                                                        	"pass: {$Dump->getDbConf('password')}\n" .
                                                        	"dbas: {$Dump->getDbConf('database')}\n" .
                                                        	"host: {$Dump->getDbConf('host')}\n" .
                                                        	"</pre>
                                                        </p>
                                                        <label>Query to exec:</label>
                                                        <textarea name='databaseQuery' cols='64' rows='16'>" . htmlspecialchars($_POST['databaseQuery']) . "</textarea>
                                                    </div>
                                                    <div{$Dump->checkFieldError_printClass('databaseQuery_method', 'form-row form-row-radio')}>
                                                        <label>". $Dump->formField_radio('databaseQuery_method', Dump::DATABASE_QUERY_METHOD__MYSQLI, $Dump->option('defaultDatabaseQueryMethod'))
                                                            . "Mysqli - php connection</label>
                                                        <label>". $Dump->formField_radio('databaseQuery_method', Dump::DATABASE_QUERY_METHOD__CLI, $Dump->option('defaultDatabaseQueryMethod'))
                                                            . "CLI - command line bin execute</label>
                                                    </div>";
                                            }
                                        ],
                                    ],
                                ],
                                [
                                    'label' => 'Generate XClass',
                                    'name' => 'generateXclass',
                                    'options' => [
                                        [
                                            'label' => 'Generate extension class',
                                            'content' => function() use ($Dump) {
												if ($_POST['prefill'])	{
													// put data into post array to not have to pass this to action preview
                                                    $_POST['originalClassFullName'] = 	$_POST['originalClassFullName'] ?: $Dump->mapClassNamespaceAndPath($_POST['originalClassFullPath']);
                                                    $_POST['extSaveXclassKey'] = 		$_POST['extSaveXclassKey'] ?: 't3_local';
                                                    $_POST['extSaveXclassNamespace'] = 	$_POST['extSaveXclassNamespace'] ?: 'Q3i\\T3Local';	// todo: try to detect
													// runs action in preview mode
													$Dump->runAction();
                                                    $additionalInfo = $Dump->displayMessages();
												}

                                                $originalClassFullName = 	$_POST['originalClassFullName'];
                                                $originalClassFullPath = 	$_POST['originalClassFullPath'];
                                                $extSaveXclassKey = 		$_POST['extSaveXclassKey'];
                                                $extSaveXclassNamespace = 	$_POST['extSaveXclassNamespace'];

                                                $code = "
                                                    <p>
                                                        <i>Generates extension class and registers it using \$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']'<br>
                                                        	Enter class name or project-relative class file path and click 'Prefill form'.</i>
                                                        {$Dump->displayTooltip('Basically it copies class and renames it, or puts new empty class which extends original, '.chr(10)
                                                        	. 'then puts register into EXT:your_ext/Configuration/[OriginalExt]/ext_localconf.php' . chr(10)
                                                        	. 'It\'s designed for lazy people like me.' . chr(10)	)}
                                                    </p>
                                                    
                                                    <div{$Dump->checkFieldError_printClass('originalClassFullName', 'form-row')}>
                                                        <label>Class NAME (full namespace) to extend:</label>
                                                        <input name='originalClassFullName' id='originalClassFullName' value='{$originalClassFullName}' type='text' style='width: 100%'>
                                                    </div>
                                                    <div class='form-row'>
                                                        <label>...OR class PATH to detect namespace:</label>
                                                        <input name='originalClassFullPath' id='originalClassFullPath' value='{$originalClassFullPath}' type='text' style='width: 100%'>
                                                    </div>
                                                    <div class='form-row'>
                                                        <label>Ext to store XClass:</label>
                                                        Key: 		<input name='extSaveXclassKey' id='extSaveXclassKey' value='{$extSaveXclassKey}' type='text'>
                                                        Namespace:	<input name='extSaveXclassNamespace' id='extSaveXclassNamespace' value='{$extSaveXclassNamespace}' type='text'>
                                                    </div>
                                                    <div class='form-row form-row-radio'>
                                                        <label>". $Dump->formField_radio('xclassGenerate_method', 'copy', 'copy')
                                                    . "Copy original class</label>
                                                        <label>". $Dump->formField_radio('xclassGenerate_method', 'empty', 'copy')
                                                    . "Generate empty</label>
                                                    </div>
                                                    <div class='form-row'>
                                                    	<input type='submit' name='prefill' value='Prefill form'>
                                                    </div>
                                                    <div>
                                                    	{$additionalInfo}
													</div>
                                                    ";
                                                return $code;
                                            }
                                        ],
                                    ],
                                ],
                            ];


                            $actionTmpl = "
                                <li class='###ACTION_CLASS###'>
                                    <label for='action_###ACTION_NAME###'>
                                        <span>###ACTION_LABEL###</span>
                                    </label>
                                    <input name='action' id='action_###ACTION_NAME###' type='radio' value='###ACTION_NAME###'###ACTION_CHECKED###>
                                    <div class='action-sub-options clear ###ACTION_OPTIONS_CLASS###'>
                                        ###OPTIONS###
                                    </div>
                                    <br class='clear'>
                                </li>";
                            $actionOptionTmpl = "
                                        <div class='option ###OPTION_CLASS### ###OPTION_VALID_CLASS###'>
                                            <h3>###OPTION_LABEL###</h3>
                                            ###OPTIONS_CONTENT###
                                        </div>
                                        ";
                            
                            $actionSelectorActive = $_POST['action'] ?? 'typo3System';

                            $codeActions = '';
                            foreach ($actions as $action)   {

                                $codeOptions = '';
                                if (is_array($action['options']))
                                foreach ($action['options'] as $option)   {

                                    $codeOptions .= $Dump->substituteMarkerArray(
                                        $actionOptionTmpl,
                                        [
                                            '###OPTION_LABEL###' => $option['label'],
                                            '###OPTION_CLASS###' => $option['class'],
                                            '###OPTION_VALID_CLASS###' => isset($option['valid']) && !$option['valid']  ?  ' error'  :  '', // this makes red whole action options container. if needed only one field, see fetch_files
                                            '###OPTIONS_CONTENT###' => is_callable($option['content'])  ?  $option['content']()  :  $option['content'],
                                        ]
                                    );
                                }

                                $codeActions .= $Dump->substituteMarkerArray(
                                    $actionTmpl,
                                    [
                                        '###ACTION_NAME###' => $action['name'],
                                        '###ACTION_LABEL###' => $action['label'],
                                        '###OPTIONS###' => $codeOptions,
                                        '###ACTION_OPTIONS_CLASS###' => $codeOptions ? '' : ' hidden',
                                        '###ACTION_CLASS###' => $actionSelectorActive === $action['name'] ? 'active' : '',
                                        '###ACTION_CHECKED###' => $actionSelectorActive === $action['name'] ? ' checked' : '',
                                    ]
                                );
                            }

                            print $codeActions;
                        ?>
                    </ul>
                </div>
                <div class="clear"></div>
            </div>

            <div>
                <h3<?php print $Dump->checkFieldError_printClass('projectName'); ?>><label for='projectName'>project name:</label></h3>
                <div class="indent">
                    <input name='projectName' id='projectName' type='text' size='30' value='<?php print htmlspecialchars($Dump->projectName); ?>'>
                </div>
            </div>
            <div>
                <h3<?php print $Dump->checkFieldError_printClass('projectVersion'); ?>><label for='v'>version:</label></h3>
                <div class="indent">
                    <input name='v' id='v' type='text' size='10' value='<?php print htmlspecialchars($Dump->projectVersion); ?>'>
                    <?php print $Dump->displayTooltip('Usually a number, to build filename.'.chr(10).'Existing dumps: ' . $Dump->getExistingDumpsFilenames()); ?>
                </div>
            </div>

            <br>
            <div class="form-row form-row-checkbox">
                <label>
                    <input name='dontExec' id='dontExec' type='checkbox'<?php print ($_POST['dontExec'] ? ' checked' : ''); ?>> don't exec generated command
                </label>
            </div>

            <br>
            <input type='submit' name='submit' value='  GO!  '>
        </form>
    </div>


	<footer>
		<i>
			<a href="https://github.com/w010/DUMP">DUMP</a> (Damn Useful <abbr title="[Management|Migration|Maintenance]">Magic</abbr> Program)<br>
			Db and filesystem migration tool + some low-level utilities <abbr title="but can be a good helper for other webapps as well">for TYPO3</abbr> devs/maintainers<br>
            v<?php print DUMP_VERSION; ?> | <a href="://wolo.pl/">wolo.pl '.' studio</a> |  2013-2022
        </i>
	</footer>


    <script>

		Dump.init();

    </script>
</body>
</html>