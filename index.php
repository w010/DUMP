<?php

//die('no access');


/**
 *  WTP DUMP/BACKUP TOOL FOR TYPO3 - wolo.pl '.' studio
 *  2013-2018
 */

// ! you should change default password !

//					  WARNING!   CRITICAL
//
// THIS SCRIPT IS FOR PRIVATE DEV USE ONLY, DO NOT PUT IT ON PUBLIC UNSECURED!
// IT DOES LOW LEVEL DB / FILESYSTEM OPERATIONS AND DOESN'T HAVE ANY USER-INPUT SECURITY CHECK.
// IF THIS IS YOUR SITE AND RUNNING IN PUBLIC/PRODUCTION ENVIRONMENT AND YOU ARE
// NOT SURE IF THIS FILE SHOULD BE HERE, PLEASE DELETE THIS SCRIPT IMMEDIATELY

/** todo:
 * - domains update: button to switch values between from/to (or better: selector of domains sets?
 */


define ('DUMP_VERSION', '3.0.2');



error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_STRICT ^ E_DEPRECATED);



// default options for this script operation
$optionsDefault = [
    // don't use native path and version detection (needs typo3 sources)
	'dontUseTYPO3Init' => false,

	// script only displays generated command line, but doesn't exec it
	'dontExecCommands' => 0,

	// exec commands, but don't show them on PUB
	'dontShowCommands' => 0,

    // query database using cli bin execute or mysqli connection
    'defaultDatabaseQueryMethod' => Dump::DATABASE_QUERY_METHOD__MYSQLI,

	// default tables for "Dump with omit" action, if not specified
	'defaultOmitTables' => ['index_rel', 'sys_log', 'sys_history', 'index_fulltext', 'sys_refindex', 'index_words', 'tx_extensionmanager_domain_model_extension'],

    // default preselection of files and dirs for filesystem archive
    'defaultIncludeFilesystem' => ['typo3conf'],
    'defaultExcludeFilesystem' => ['fileadmin/content', 'fileadmin/_processed_', 'fileadmin/_temp_', 'fileadmin/user_upload', 'typo3conf/AdditionalConfiguration_host.php'],

    // list items in exclude selector from these directories
    'defaultExcludeFilesystem_listItemsFromDirs' => ['fileadmin', 'typo3conf'],

	// default project name is generated from subdomain, but it's not always ok
	'defaultProjectName' => '',

	// adds docker exec on container to command line
	'docker' => false,

	// if docker=true, container name must be specified
	'docker_containerSql' => '',
	'docker_containerPhp' => '',

    // domain to replace for fetch file urls
	'fetchFiles_defaultSourceDomain' => '',

    // update domain records preconfigured list
    'updateDomains_defaultDomainsFrom' => '',
    'updateDomains_defaultDomainsTo' => '',
];

// custom options may be included to override
$optionsCustom = [];
include ('conf.php');
$options = array_replace($optionsDefault, $optionsCustom);



// this simple filename check is pretty sure v.4 detection test
if (file_exists('../typo3conf/localconf.php')  &&  !file_exists('../typo3conf/LocalConfiguration.php'))    {
	define('TYPO3_MAJOR_BRANCH_VERSION', 4);
}

// try to use native path and version detection, but without running many other initial things
if (!$options['dontUseTYPO3Init']  &&  ( !defined('TYPO3_MAJOR_BRANCH_VERSION')  ||  ( defined('TYPO3_MAJOR_BRANCH_VERSION')  &&  TYPO3_MAJOR_BRANCH_VERSION > 4 ) ) )  {

    @include('../typo3/sysext/core/Classes/Core/SystemEnvironmentBuilder.php');
    @include_once('../typo3/sysext/core/Classes/Utility/GeneralUtility.php');
    if (class_exists('\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder'))   {
        class SystemEnvironmentBuilder extends \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder	{
            // trick to call private methods
            public static function run_defineBaseConstants() {
                self::defineBaseConstants();
            }
	        public static function run_definePaths($relativePathPart = '') {
		        self::definePaths($relativePathPart);
	        }
        }

        SystemEnvironmentBuilder::run_defineBaseConstants();

        if (!defined('TYPO3_MAJOR_BRANCH_VERSION')) {
	    	preg_match('#(.+?)\.#', TYPO3_version, $matches);
        	define('TYPO3_MAJOR_BRANCH_VERSION', $matches[1]);
        }

        define('TYPO3_REQUESTTYPE', 2); // 2 = TYPO3_REQUESTTYPE_BE (must be set in >= 7)
        if (TYPO3_MAJOR_BRANCH_VERSION <= 7)  {
	        SystemEnvironmentBuilder::run_definePaths('DUMP/');
        }
        if (TYPO3_MAJOR_BRANCH_VERSION >= 8)   {
            SystemEnvironmentBuilder::run_definePaths(1);
        }
    }
}

// do the classic init
if (!defined('PATH_site'))	{
	define('PATH_thisScript', str_replace('//', '/', str_replace('\\', '/',
		(PHP_SAPI == 'fpm-fcgi' || PHP_SAPI == 'cgi' || PHP_SAPI == 'isapi' || PHP_SAPI == 'cgi-fcgi') &&
		($_SERVER['ORIG_PATH_TRANSLATED'] ? $_SERVER['ORIG_PATH_TRANSLATED'] : $_SERVER['PATH_TRANSLATED']) ?
		($_SERVER['ORIG_PATH_TRANSLATED'] ? $_SERVER['ORIG_PATH_TRANSLATED'] : $_SERVER['PATH_TRANSLATED']) :
		($_SERVER['ORIG_SCRIPT_FILENAME'] ? $_SERVER['ORIG_SCRIPT_FILENAME'] : $_SERVER['SCRIPT_FILENAME']))));
	define('PATH_site', realpath(dirname(PATH_thisScript).'/../').'/');
}


define('PATH_dump', PATH_site . 'DUMP/');
// in some projects needs to be defined
define('TYPO3_MODE', 'BE');


// if version not detected or preconfigured, set to 0 - script still is usable if db is set manually
if (!defined('TYPO3_MAJOR_BRANCH_VERSION'))
	define('TYPO3_MAJOR_BRANCH_VERSION', 0);



switch (TYPO3_MAJOR_BRANCH_VERSION)    {

    case 4:
	    if (file_exists(PATH_site.'typo3conf/localconf.php')) {
		    include_once(PATH_site . 'typo3conf/localconf.php');
	    }
	    $databaseConfiguration['username'] = $typo_db_username;
	    $databaseConfiguration['password'] = $typo_db_password;
	    $databaseConfiguration['host'] =     $typo_db_host;
	    $databaseConfiguration['database'] = $typo_db;
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
	    $databaseConfiguration = $GLOBALS['TYPO3_CONF_VARS']['DB'];
        break;

    case 8:
    case 9:
    default:
	    if (file_exists(PATH_site.'typo3conf/LocalConfiguration.php'))	{
		    $GLOBALS['TYPO3_CONF_VARS'] = include_once(PATH_site.'typo3conf/LocalConfiguration.php');
		    // may be used sometimes in AdditionalConfiguration
		    @include_once(PATH_site.'typo3/sysext/core/Classes/Utility/ExtensionManagementUtility.php');
		    @include_once(PATH_site.'typo3conf/AdditionalConfiguration.php');
	    }
	    $databaseConfiguration['username'] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'];
	    $databaseConfiguration['password'] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'];
	    $databaseConfiguration['host'] =     $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'];
	    $databaseConfiguration['database'] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'];
	    break;
}






// some old constants I used in many projects
//if (!defined('DEV'))				  define('DEV',   false);
//if (!defined('LOCAL'))			  define('LOCAL', false);

if (!defined('TYPO3_CONTEXT'))      define('TYPO3_CONTEXT',     getenv('TYPO3_CONTEXT'));
if (!defined('INSTANCE_CONTEXT'))   define('INSTANCE_CONTEXT',  getenv('INSTANCE_CONTEXT'));


// reinclude config (overwrite settings which uses conditions on some constants defined above)
include ('conf.php');
$options = array_replace($options, $optionsCustom);






$Dump = new Dump();
$Dump->main();


class Dump  {

	// define some variables. all are public, because this script is for private use and no need to control such things.

	public $options = [];
	public $dbConf = [];

	// html content to display before form
	public $configInfoHeader = '';

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

	const DATABASE_QUERY_METHOD__MYSQLI = 'mysqli';
	const DATABASE_QUERY_METHOD__CLI = 'cli';


	function __construct() {
		global $options;
		global $databaseConfiguration;
		$this->options = &$options;
		$this->dbConf = &$databaseConfiguration;

		$this->PATH_site = PATH_site;
		$this->PATH_dump = PATH_dump;

		// if docker is used, docker exec CONTAINER must be prepended before mysqldump etc.
		if ($this->options['docker'])   {
		    // docker exec -it causes some tty error in console
			$this->dockerContainerCmd['sql'] = "docker exec -i {$this->options['docker_containerSql']}   ";
			$this->dockerContainerCmd['php'] = "docker exec -i {$this->options['docker_containerPhp']}   ";
		}
	}

	function main() {

		if (!$this->dbConf['username'] || !$this->dbConf['password'] || !$this->dbConf['host'] || !$this->dbConf['database'])
			$this->msg('CHECK DATABASE CONFIG. Looks like authorization data is missed. See your '.(TYPO3_MAJOR_BRANCH_VERSION === 4 ? 'localconf' : 'LocalConfiguration'), 'error');

		$this->addEnvironmentMessage();

		// set input values
		$this->projectName = $_POST['projectName'];
		$this->projectVersion = $_POST['v'];
		$this->action = $_POST['action'];


		// predicted project name, taken from domain name or conf (only when not submitted, on first run)
		if (!$_POST['submit']  &&  !$_POST['projectName']) {
			list($this->projectName) = preg_split('@\.@', $_SERVER['HTTP_HOST']);
			if ($this->options['defaultProjectName'])
				$this->projectName = $this->options['defaultProjectName'];
		}

		// add some header system & conf informations
		$this->configInfoHeader .= '<p>- database: <span class="info"><b>' . $this->dbConf['database'] . '</b></span> / connection test status: '.$this->testDatabaseConnectivity().'</p>';
		if ($this->options['docker'])   {
			$this->configInfoHeader .= '<p>- docker sql: <span class="info">' . $this->options['docker_containerSql'] . '</span></p>';
			$this->configInfoHeader .= '<p>- docker php: <span class="info">' . $this->options['docker_containerPhp'] . '</span></p>';
		}
		$this->configInfoHeader .= '<p>- branch detected: <span class="info"><b>' . TYPO3_MAJOR_BRANCH_VERSION
            . (defined('TYPO3_version') ? '</b></span> / version: <b><span class="info">' . TYPO3_version . '</span></b>' : '') . '</b></span></p>';


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
		$this->exec_control($this->dockerContainerCmd['php'] . "mysql --batch --quick --host={$this->dbConf['host']} --user={$this->dbConf['username']} --password=\"{$this->dbConf['password']}\" --database={$this->dbConf['database']}  {$mysqlCommand}");
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
		//$this->exec_control($this->dockerCmdPart['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --skip-set-charset --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->dbConf['host']} --user={$this->dbConf['username']} --password=\"{$this->dbConf['password']}\"  {$this->dbConf['database']}  >  \"{$this->PATH_dump}{$this->projectName}-v{$this->projectVersion}.sql\"; ");


		// example - ignored without data but structure:
		// mysqldump -u user -p db --ignore-table=ignoredtable1 --ignore-table=ignoredtable2 > structure.sql;  mysqldump -u user -p --no-data db ignoredtable1 ignoredtable2 >> structure.sql;

		$ignoredTablesPart = '';
		$dumpOnlyStructureQuery = '';

		if ($_POST['omitTablesIncludeInQuery']  &&  $omitTables  &&  !$allTables) {
			$ignoredTablesPart = ' \\' . chr(10) . "--ignore-table={$this->dbConf['database']}."
				. implode (' \\' . chr(10) . "--ignore-table={$this->dbConf['database']}.", $omitTables);

			$dumpOnlyStructureQuery = ';'     // end previous command only if needed
				. chr(10) . chr(10)

				//. $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --skip-set-charset --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->dbConf['host']} --user={$this->dbConf['username']} --password=\"{$this->dbConf['password']}\"  {$this->dbConf['database']}  "
				. $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->dbConf['host']} --user={$this->dbConf['username']} --password=\"{$this->dbConf['password']}\"  {$this->dbConf['database']}  "
				. " --no-data \\"
				. chr(10) . implode(' ', $omitTables)
				. "  >>  \"{$this->PATH_dump}{$this->projectName}-v{$this->projectVersion}.sql\" ";
		} 

		// dziala na dockerze (wywolany recznie)
        // dziala na linux
		// na win teoretycznie powinno tez ze stream output
		//$cmd = $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --skip-set-charset --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->dbConf['host']} --user={$this->dbConf['username']} --password=\"{$this->dbConf['password']}\"  {$this->dbConf['database']}  "
		$cmd = $this->dockerContainerCmd['sql'] . "mysqldump --complete-insert --add-drop-table --no-create-db --quick --lock-tables --add-locks --default-character-set=utf8 --host={$this->dbConf['host']} --user={$this->dbConf['username']} --password=\"{$this->dbConf['password']}\"  {$this->dbConf['database']}  "
			. $ignoredTablesPart
			. "  >  \"{$this->PATH_dump}{$this->projectName}-v{$this->projectVersion}.sql\" "
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
		$this->cmds[] = "<br><a href=\"{$dumpFilename}-v{$this->projectVersion}.sql.tgz\">{$dumpFilename}-v{$this->projectVersion}.sql.tgz</a><br>";
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

		if (!$all  &&  !$_POST['omitTablesIncludeInQuery']) {

			$included = is_array($_POST['filenameSelectionInclude']) ? $_POST['filenameSelectionInclude'] : [];
			$excluded = is_array($_POST['filenameSelectionExclude']) ? $_POST['filenameSelectionExclude'] : [];

			if ($included)
				$cmd .= implode(' ', $included) . ' ';
			else
				$cmd .= ' .  --exclude="DUMP" --exclude-vcs --exclude="deprecation*.log"';

			foreach($excluded as $exclude)  {
                list ($excludeInDir) = explode('/', $exclude);
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


		$this->exec_control($cmd);
		/* $this->exec_control ('tar -zcf '.{$this->PATH_site}.'DUMP/'.$dumpFilename.'-v'.$this->projectVersion.'.tgz ./../* --exclude="typo3temp" --exclude="DUMP" --exclude="uploads" -exclude="typo3_src-*"  '); */

		// display download link
		$this->cmds[] = "<br><a href=\"{$dumpFilename}-v{$this->projectVersion}.sql.tgz\">{$dumpFilename}-v{$this->projectVersion}.tgz</a><br>";
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

		$queryMethod = $_POST['databaseQuery_method']  OR  $this->options['defaultDatabaseQueryMethod'];

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

	    $queryMethod = $_POST['databaseQuery_method']  OR  $this->options['defaultDatabaseQueryMethod'];

		if (!$this->paramsRequiredPass(['databaseQuery' => $databaseQuery, 'databaseQuery_method' => $queryMethod]))
			return;

		switch ($queryMethod)   {
            case 'cli':
                // slashes even on windows have to be unix-style in execute source
                //$query = escapeshellarg($_POST['databaseQuery']);
                // manually escape double quotes (escapeshellarg doesn't do it as expected in this case) and remove linebreaks
                $query = str_replace([/*"'",*/ '"', "\n", "\r"], [/*"\'",*/ '\"', ' ', ' '], $_POST['databaseQuery']);
                $this->exec_control($this->dockerContainerCmd['php'] . "mysql --batch --quick --host={$this->dbConf['host']} --user={$this->dbConf['username']} --password=\"{$this->dbConf['password']}\" --database={$this->dbConf['database']}  --execute=\"{$query}\"");
                break;

            default:
            case 'mysqli':
                $this->mysqliExecQuery($databaseQuery);
        }
	}



	/* exec shell command */
	private function exec_control($cmd, $saveCmd = true) {
		if ($this->options['dontExecCommands'])
			$this->msg('command not executed - exec is disabled - @see option dontExecCommands', 'info');
		elseif ($_POST['dontExec'])
			$this->msg('(command not executed)', 'info');
		else
			exec($cmd, $output, $return);

/*echo exec('whoami');
echo exec('groups');
echo exec('sudo -v');
echo exec('/usr/bin/docker -v');*/

/*$content = system('sudo  /usr/bin/docker images', $ret);
var_dump ($content);
print ' ---- ';
print $ret;*/

		// var_dump($output);
		// var_dump($return);

		if ($this->options['docker'])
			$this->msg('running on docker - cmd probably didn\'t run. execute manually', 'info');

		if ($saveCmd)
			$this->cmds[] = htmlentities($cmd);
	}


    /* exec query on database connection */
	private function mysqliExecQuery($query)  {
		if ($this->options['dontExecCommands'])
			$this->msg('query not executed - exec is disabled - @see option dontExecCommands', 'info');
        elseif ($_POST['dontExec'])
			$this->msg('(query not executed)', 'info');
        else    {
            $dbConnection = new mysqli($this->dbConf['host'], $this->dbConf['username'], $this->dbConf['password'], $this->dbConf['database']);
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
	        $this->cmds[] = htmlentities($query);
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

	private function addEnvironmentMessage()	{
		$environment = '';
		if (defined('DEV') && DEV)		$environment = 'DEV';
		if (defined('LOCAL') && LOCAL)	$environment = 'LOCAL';
		if (getenv('TYPO3_CONTEXT') == 'Development')   $environment = 'Development';
		if (getenv('TYPO3_CONTEXT') && !$environment)   $environment = getenv('TYPO3_CONTEXT');
		if (!$environment)					    $environment = 'PUBLIC';
		if ($environment == 'Production' || $environment == 'PUBLIC')
			$environment = '<span class="error">'.$environment.' !!!!</span>';
		else
			$environment = '<span class="info">'.$environment.'</span>';
		if (INSTANCE_CONTEXT)
			$environment .= ' - instance: <span class="info"><b>' . INSTANCE_CONTEXT . '</b></span>';
		$this->configInfoHeader .= '<h4>running on ' . $environment . '</h4>';
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
		if ($this->cmds  &&  !$this->options['dontShowCommands'])   {
            $cmdLines = [];
		    foreach ($this->cmds as $i => $cmd) {
			    $cmdLines[] = "<span class=\"cmdLine\" id=\"commandLineGenerated{$i}\" onclick=\"selectText('commandLineGenerated{$i}');\">" . $cmd . "</span>";
		    }
			$content .= "<p>- commands:</p>
                         <p><pre>" . implode('<br><br>', $cmdLines)."</pre></p>";
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


	// CONFIG

	/* returns omit tables value to display in textarea */
	function getOmitTables()	{
		if ($_POST['omitTables'])
			return htmlspecialchars($_POST['omitTables']);
		return implode(chr(10), $this->options['defaultOmitTables']);
	}


	// SYSTEM

	/* list files */
	function getFilesFromDirectory($dir = 'DUMP', $ext = 'sql') {
		$files = glob('*.' . $ext);
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

	/* tries to extract container name from docker config dir */
	static function detectDockerContainerName($containerType, $dockerEnv = '') {
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

    /* try database connection */
    function testDatabaseConnectivity() {
        $msg = '<span class="error">unknown</span>';
        if ($this->dbConf['host'])  {
	        $dbConnection = new mysqli($this->dbConf['host'], $this->dbConf['username'], $this->dbConf['password'], $this->dbConf['database']);
	        if ($dbConnection->connect_error)
		        $msg = '<span class="error">error<br>'.$dbConnection->connect_error.'</span>';
	        else
		        $msg = '<span class="info">OK</span>';
        }
	    return $msg;
    }


	// TEMPLATING

	/* typo3-like standard replace marker method */
	function substituteMarkerArray($subject, $markerArray)	{
		return str_replace(array_keys($markerArray), array_values($markerArray), $subject);
	}


	// FILE FETCH

	/**
	 * Returns information about a folder.
	 * @param string $folderIdentifier In the case of the LocalDriver, this is the (relative) path to the file.
	 * @return array
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

?>
<html>
<head>
	<title>DUMP - <?php print $_SERVER['HTTP_HOST']; ?></title>
	<style type='text/css'>
        body    {font-family: Arial, Tahoma, sans-serif;}
		label	{clear: both;   display: inline-block;}
		label span	{float: left;   width: 260px;   cursor: pointer;}
		label input	{float: left;   cursor: pointer;}
		.actions li + label  {float: left;}
		.clear	{clear: both;}
		.error	{color: #d00;}
		.info	{color: #282;   font-style: italic;     font-family: monospace;     font-size: 1.2em;   font-weight: 100;}
		ul  {list-style: none;  float: left;    margin-top: 0;  padding: 0;}
		.actions ul   {background: #eee;    padding: 20px;  box-shadow: 5px 5px 8px -2px #aaa;}
		.actions li > label:hover   {color: darkorange;}
		.actions li.active > label  {color: #03d;}
		.action-sub-options  {display: none;    padding: 10px 20px 20px;    background: gainsboro;  margin: 10px 0 0 20px;  box-shadow: 5px 5px 8px -2px #aaa;}
        select   {margin-right: 10px;}
		input[type=radio]:checked + .action-sub-options  {display: block;}
		input[type=checkbox]    {margin: 2px 6px 2px 0;}
        input[type=radio]       {margin: 2px 6px 2px 0;     outline: none;     cursor: pointer;}
        input[type=submit]      {padding: 6px 16px; cursor: pointer;}
        input[type=submit]:hover  { background: darkorange;}
        input[disabled], textarea[disabled] {cursor:not-allowed;}
        input[type=text], select, textarea  {border: 1px solid #a9a9a9;     box-shadow: inset 4px 4px 5px -2px #bbb;  padding: 6px;}
		.action-sub-options label   {display: block;        padding: 2px 0 4px;}
		.form-row {display: block;  margin-bottom: 12px;}
        .form-row-checkbox label, .form-row-radio label {cursor: pointer;}
		.selector-tables textarea   {overflow-wrap: normal;}
		footer	  {font-size: 80%;  margin-top: 70px;}
		pre		 {line-height: 1.2em; white-space: pre-wrap;}
		.config p	{margin: 8px 0;}
		.to-left	{float: left;}
		.hidden	 {display: none !important;}
		.indent	 {margin-left: 40px;}
		.tooltip	{background: url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCAyNTYgMjU2IiB3aWR0aD0iMjU2IiBoZWlnaHQ9IjI1NiI+CjxwYXRoIGQ9Im0xMjggMjIuMTU4YTEwNS44NCAxMDUuODQgMCAwIDAgLTEwNS44NCAxMDUuODQgMTA1Ljg0IDEwNS44NCAwIDAgMCAxMDUuODQgMTA1Ljg0IDEwNS44NCAxMDUuODQgMCAwIDAgMTA1Ljg0IC0xMDUuODQgMTA1Ljg0IDEwNS44NCAwIDAgMCAtMTA1Ljg0IC0xMDUuODR6bTAgMzIuNzZjNS4xNiAwLjExNyA5LjU1IDEuODc1IDEzLjE4IDUuMjczIDMuMzQgMy41NzUgNS4wNyA3Ljk0IDUuMTkgMTMuMDk2LTAuMTIgNS4xNTYtMS44NSA5LjQwNC01LjE5IDEyLjc0NC0zLjYzIDMuNzUtOC4wMiA1LjYyNS0xMy4xOCA1LjYyNXMtOS40LTEuODc1LTEyLjc0LTUuNjI1Yy0zLjc1LTMuMzQtNS42My03LjU4OC01LjYzLTEyLjc0NHMxLjg4LTkuNTIxIDUuNjMtMTMuMDk2YzMuMzQtMy4zOTggNy41OC01LjE1NiAxMi43NC01LjI3M3ptLTE2LjM1IDUzLjc5MmgzMi43OXY5Mi4zN2gtMzIuNzl2LTkyLjM3eiIgZmlsbC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNzJhN2NmIi8+Cjwvc3ZnPgo=');
            background-size: 16px 16px;     background-position: left center;   background-repeat: no-repeat;   min-height: 16px;   display: inline-block;  padding-left: 16px; cursor: help;   margin-left: 4px;}

        @media screen and (min-width: 900px) {
            .actions    {position: relative;}
            .action-sub-options {min-width: calc((50% / 3) * 2);     top: 0;     left: 390px;    margin: 0;  position: absolute;}
        }
	</style>
    <script>
        document.addEventListener('click', function(e){
            if (e.ctrlKey === true &&
                e.target.tagName === 'INPUT' &&
                e.target.type === "radio" &&
                e.target.checked === true) {
                e.target.checked = false;
            }
        });
        document.addEventListener("DOMContentLoaded", function(event) {
            toggleInput('omitTablesIncludeInQuery', 'omitTables');
        });

        function selectText(containerId) {
            if (document.selection) {
                var range = document.body.createTextRange();
                range.moveToElementText(document.getElementById(containerId));
                range.select();
            } else if (window.getSelection()) {
                var range = document.createRange();
                range.selectNode(document.getElementById(containerId));
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
            }
        }
        function toggleInput(triggerId, inputId)   {
            var trigger = document.getElementById(triggerId);
            var input = document.getElementById(inputId);
            if (trigger.checked)     input.disabled = false;
            else                     input.disabled = true;
        }
    </script>
</head>
<body>

	<header>
		<h2>WTP '.' TYPO3 Dump tool</h2>
	</header>


    <div class="config">
		<pre>
PATH_site = <?php  print PATH_site;  ?>

PATH_dump = <?php  print PATH_dump;  ?>
		</pre>

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
                                                            <input type='checkbox' name='omitTablesIncludeInQuery' id='omitTablesIncludeInQuery' onclick='toggleInput(\"omitTablesIncludeInQuery\", \"omitTables\");'" . ($_POST['omitTablesIncludeInQuery'] ? " checked" : '') . ">
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
                                                                    $included = is_array($_POST['filenameSelectionInclude']) ? $_POST['filenameSelectionInclude'] : $Dump->options['defaultIncludeFilesystem'];
                                                                    $selected = in_array($dir, $included) ? ' selected' : '';
                                                                    $code .= "<option{$selected}>".$dir.'</option>';
                                                                }

                                                $code .= "</select>
                                                        </div>
                                                        <div class='to-left'>
                                                            <label>EXCLUDE:</label>
                                                            <select name='filenameSelectionExclude[]' id='filenameSelectionExclude' size='10' multiple>";

                                                                $listSubdirsOf = $Dump->options['defaultExcludeFilesystem_listItemsFromDirs'];
                                                                foreach ($listSubdirsOf as $dir)
                                                                    foreach ($Dump->getFilesAndDirectories($dir) as $subdir) {
                                                                        $subdirPath = $dir . '/' . $subdir;
                                                                        $excluded = is_array($_POST['filenameSelectionExclude']) ? $_POST['filenameSelectionExclude'] : $Dump->options['defaultExcludeFilesystem'];
                                                                        $selected = in_array($subdirPath, $excluded) ? ' selected' : '';
                                                                        $code .= "<option{$selected}>" . $subdirPath . '</option>';
                                                                    }

                                                $code .= "</select>
                                                        </div>
                                                        <div class='clear'></div>
                                                    </div>
                                                    <div class='form-row form-row-checkbox'>
                                                        <label><input type='checkbox' name='omitTablesIncludeInQuery'".($_POST['omitTablesIncludeInQuery'] ? " checked" : '').">Ignore selection and pack all</label>
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
                                    'label' => 'Domains update',
                                    'name' => 'domainsUpdate',
                                    'options' => [
                                        [
                                            'label' => "Updates domains in database",
                                            'valid' => !$Dump->checkFieldError('domainsFrom'),
                                            'class' => 'selector-domains',
                                            'content' => function() use ($Dump) {
                                                $domainsFrom = count($_POST['domainsFrom'])  ?  $_POST['domainsFrom']  :  $Dump->options['updateDomains_defaultDomainsFrom'];
                                                $domainsTo = count($_POST['domainsTo'])  ?  $_POST['domainsTo']  :  $Dump->options['updateDomains_defaultDomainsTo'];
                                                $countDomainFrom = count(explode("\n", $domainsFrom))  OR  5;
                                                $countDomainTo = count(explode("\n", $domainsTo))  OR  5;
                                                $code = "
                                                    <p><i>Replace domains in sys_domain records and pages external urls</i></p>
                                                    <div class='form-row'>
                                                        <label>Domains FROM:</label>
                                                        <textarea name='domainsFrom' id='domainsFrom' rows='".$countDomainFrom."' cols='50'>"
                                                            . $domainsFrom
                                                        ."</textarea>
                                                    </div>
                                                    <div class='form-row'>
                                                        <label>Domains TO:</label>
                                                        <textarea name='domainsTo' id='domainsTo' rows='".$countDomainTo."' cols='50'>"
                                                            . $domainsTo
                                                        ."</textarea>
                                                    </div>
                                                    <div class='form-row form-row-radio'>
                                                        <label>". $Dump->formField_radio('domainsUpdate_method', Dump::DATABASE_QUERY_METHOD__MYSQLI, $Dump->options['defaultDatabaseQueryMethod'])
	                                                . "Mysqli - php connection</label>
                                                        <label>". $Dump->formField_radio('domainsUpdate_method', Dump::DATABASE_QUERY_METHOD__CLI, $Dump->options['defaultDatabaseQueryMethod'])
	                                                . "CLI - command line bin execute</label>
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
                                                        <input name='fetchFilesDomainFrom' id='fetchFilesDomainFrom' value='{$Dump->options['fetchFiles_defaultSourceDomain']}' type='text'>
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
                                                        <label>Query to exec:</label>
                                                        <textarea name='databaseQuery' cols='64' rows='16'>" . htmlspecialchars($_POST['databaseQuery']) . "</textarea>
                                                    </div>
                                                    <div{$Dump->checkFieldError_printClass('databaseQuery_method', 'form-row form-row-radio')}>
                                                        <label>". $Dump->formField_radio('databaseQuery_method', Dump::DATABASE_QUERY_METHOD__MYSQLI, $Dump->options['defaultDatabaseQueryMethod'])
                                                            . "Mysqli - php connection</label>
                                                        <label>". $Dump->formField_radio('databaseQuery_method', Dump::DATABASE_QUERY_METHOD__CLI, $Dump->options['defaultDatabaseQueryMethod'])
                                                            . "CLI - command line bin execute</label>
                                                    </div>";
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
                                        '###ACTION_CLASS###' => $_POST['action'] === $action['name'] ? 'active' : '',
                                        '###ACTION_CHECKED###' => $_POST['action'] === $action['name'] ? ' checked' : '',
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
		<i>DUMP (Damn Usable Management Program)<br>
            Database and filesystem migration tool for TYPO3<br>
            WTP - wolo.pl '.' studio 2013-2018<br>
            v<?php print DUMP_VERSION; ?>
        </i>
	</footer>

</body>
</html>