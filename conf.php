<?php
/**
 * DUMP - WTP DUMP/BACKUP TOOL FOR TYPO3 - wolo.pl '.' studio
 * 2013-2019
 *
 * Remember that this conf is included twice (before init and after to make the constant conditions work)
 * so if you need to define any constants always check if they are defined already
 */

defined ('DUMP_VERSION') or die ('DUMP Config: Access denied.');


/**
 * config compatible with:
 * 3.6.0
 */


/*
 * in case of problems with native base typo3 init, define branch version here and disable init using dontUseTYPO3Init => true
 */
//defined('T3_MAJOR_BRANCH_VERSION') or define('T3_MAJOR_BRANCH_VERSION', 7);

/*
 * in some projects there's need to include some missing classes which are used in AdditionalConfiguration
 */
//require_once('../typo3/sysext/core/Classes/Log/LogLevel.php');



/*
 * options for this script operation
 */
$optionsCustom = [

	// if not set, is auto determined from first part of domain name
	'defaultProjectName' => '',


	// determines paths using own classic method, use if you don't have working typo3 instance in parent dir
	// (then must set T3_MAJOR_BRANCH_VERSION and/or hardcode database credentials here)
	//'dontUseTYPO3Init' => true,


	// script only displays generated command line, but doesn't execute it (overwrites checkbox selection)
	'dontExecCommands' => getenv('TYPO3_CONTEXT') === 'Development' ? 0 : 0,


	// exec commands, but don't show them
	//'dontShowCommands' => getenv('TYPO3_CONTEXT') === 'Production' ? 0 : 0,


	// query database using cli bin execute or mysqli connection
	//'defaultDatabaseQueryMethod' => Dump::DATABASE_QUERY_METHOD__CLI,


	// generates command lines prepended with "docker exec -it [containername]"
	'docker' => Dump::isDocker(),
	'docker_containerSql' => Dump::detectDockerContainerName('mysql'),
	'docker_containerPhp' => Dump::detectDockerContainerName('php'),


	// urls of files to fetch will have the domain replaced with this one
	'fetchFiles_defaultSourceDomain' => 'http://wolo.pl',


	// default preselection of files and dirs for filesystem archive
	// 'defaultIncludeFilesystem' => [],
	// 'defaultExcludeFilesystem' => [],

	// list items in exclude selector from these directories
	//'defaultExcludeFilesystem_listItemsFromDirs' => [],


	// default tables for "Dump with omit" action, if not specified
	// 'defaultOmitTables' => ['index_rel', 'sys_log', 'sys_history', 'index_fulltext', 'sys_refindex', 'index_words', 'tx_extensionmanager_domain_model_extension'],


    // preconfigured lists of domains for environments (Domains Update action)
	'updateDomains_defaultDomainSet' => [
        'LOCAL' => '
        ',
        'DEV' => '
        ',
        'STAGE' => '
        ',
    ],

    // prefill input with domains from this key domain-set
//    'updateDomains_defaultDomainSetFrom' => 'STAGE',
//    'updateDomains_defaultDomainSetTo' => 'DEV',

];



/*
	Here you can hardcode database credentials, if not using typo3 local configuration.
	Remember to define T3_MAJOR_BRANCH_VERSION, otherwise conf structure will be interpreted as this from 8/9 branch
	
	defined('T3_MAJOR_BRANCH_VERSION') or define('T3_MAJOR_BRANCH_VERSION', 7);
	$GLOBALS['TYPO3_CONF_VARS']['DB']['password'] = ...
		or
	defined('T3_MAJOR_BRANCH_VERSION') or define('T3_MAJOR_BRANCH_VERSION', 9);
	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = ...
*/


