<?php
/**
 * DUMP - WTP DUMP/MIGRATION/BACKUP TOOL FOR TYPO3
 * wolo.pl '.' studio
 * 2013-2022
 *
 * Remember that this conf is included twice (before init and after to make the constant conditions work)
 * so if you need to define any constants always check if they are defined already
 */

defined ('DUMP_VERSION') or die ('DUMP Config: Access denied.');


/**
 * config compatible with DUMP version:
 * 3.8.0 - 3.9.x
 */



/*
 * in some projects there's need to include some missing classes which are used in AdditionalConfiguration
 */
//require_once('../vendor/psr/log/Psr/Log/LogLevel.php');
//require_once('../typo3/sysext/core/Classes/Log/LogLevel.php');
//require_once('../typo3/sysext/core/Classes/Utility/GeneralUtility.php');



/**
 * Settings for DUMP
 */
$optionsCustom = [

	// if not set, is auto determined from first part of domain name
	'defaultProjectName' => '',


	// Prepare local paths etc. using own simple method (borrowed from one of classic Typo versions)
    // - instead of trying to use Typo3's initialize
    // Use if you don't have working typo3 instance in parent dir, or having any issues (common thing)
	// (then must set TYPO3_MAJOR_BRANCH_VERSION and/or hardcode database credentials here)
	//'dontUseTYPO3Init' => true,


	// Simulate - don't exec compiled commands, only prepare, to check the result, or to call manually  (overwrites checkbox selection)
	'dontExecCommands' => getenv('TYPO3_CONTEXT') === 'Development' ? 0 : 0,


	// Exec commands, but don't show them (ie. to not reveal passwords used in mysql commands)
	//'dontShowCommands' => getenv('TYPO3_CONTEXT') === 'Production' ? 0 : 0,


	// Query database using cli bin execute or mysqli connection
	// 'defaultDatabaseQueryMethod' => Dump::DATABASE_QUERY_METHOD__CLI,


	// Generate command lines prepended with "docker exec -it [containername]"
	'docker' => Dump::isDocker(),
	'docker_containerSql' => Dump::detectDockerContainerName('mysql'),
	'docker_containerPhp' => Dump::detectDockerContainerName('php'),


	// Urls of files to fetch will have the domain replaced with this one
	'fetchFiles_defaultSourceDomain' => 'http://wolo.pl',

    // Adminer enable
    'adminer' => true,

	// Default preselection of files and dirs for filesystem archive
	// 'defaultIncludeFilesystem' => [],
	// 'defaultExcludeFilesystem' => [],

	// In exclude selector show items from these directories
	// 'defaultExcludeFilesystem_listItemsFromDirs' => [],


	// Default tables for "Dump database / omit tables" action, if not specified
	// 'defaultOmitTables' => ['index_rel', 'sys_log', 'sys_history', 'index_fulltext', 'sys_refindex', 'index_words', 'tx_extensionmanager_domain_model_extension'],


    // Preconfigured lists of domains for environments (Domains Update action)
	'updateDomains_defaultDomainSet' => [
        'LOCAL' => '
        ',
        'DEV' => '
        ',
        'STAGE' => '
        ',
    ],

    // Prefill input with domains from this key domain-set
    // 'updateDomains_defaultDomainSetFrom' => 'STAGE',
    // 'updateDomains_defaultDomainSetTo' => 'DEV',

];



/*
 * in case of problems with native base typo3 init, define branch version here and disable init using dontUseTYPO3Init => true
 */


    $optionsCustom['dontUseTYPO3Init'] = true;
    //defined('TYPO3_MAJOR_BRANCH_VERSION') or define('TYPO3_MAJOR_BRANCH_VERSION', 9);
    //defined('TYPO3_MAJOR_BRANCH_VERSION') or define('TYPO3_MAJOR_BRANCH_VERSION', 0);

    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = 'typo3';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = 'typo3';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'typo3_v11';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = 'localhost';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'] = '3306';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver'] = 'mysqli';

/*
	Here you can hardcode database credentials, if not using typo3 local configuration.
	define TYPO3_MAJOR_BRANCH_VERSION = 0  - to only use conf from here and omit all LocalConfiguration... automatics
	
	defined('TYPO3_MAJOR_BRANCH_VERSION') or define('TYPO3_MAJOR_BRANCH_VERSION', 7);
	$GLOBALS['TYPO3_CONF_VARS']['DB']['password'] = ...
		or
	defined('TYPO3_MAJOR_BRANCH_VERSION') or define('TYPO3_MAJOR_BRANCH_VERSION', 9);
	$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = ...
*/


