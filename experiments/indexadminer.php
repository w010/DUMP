<?php
die();
// this file is required to run with plugins. try to include and run this code from DUMP inside
    
    /*$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = 'www_devel';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = 'www_devel';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'project_app';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = 'mysql';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'] = '3306';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver'] = 'mysqli';*/
    
    
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = 'typo3';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = 'typo3';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'typo3_v11';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = 'localhost';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'] = '3306';
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver'] = 'mysqli';
    
require_once ('./lib/adminer-loader.php');
exit;



$dbAuth = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'];

$host = $dbAuth['host'];
$ADM_driver = 'server';





// Get signon uri for redirect
$ADM_SignonURL = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];


// Redirect to adminer (should use absolute URL here!), setting default database
$autologinRedirectUri = $ADM_SignonURL . '?' 
    /*. 'lang=' . 'en' 
    . '&' */. 'db=' . rawurlencode($dbAuth['dbname'])
    . '&' . rawurlencode($ADM_driver) . '=' . rawurlencode($host
        . ($dbAuth['port'] ? ':' . $dbAuth['port'] : '')
    )
    . '&' . 'username=' . rawurlencode($dbAuth['user']);

if ($ADM_driver !== 'server') {
    $autologinRedirectUri .= '&driver=' . rawurlencode($ADM_driver);
}


//print $redirectUri; die();


// redirect with autologin params. exclude for file disposition
if (!$_GET['username'] && !$_GET['file']) {
//if (!$_GET) {
    // Build and set cache-header header
    foreach([
        'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'private',
        'Location' => $autologinRedirectUri
    ] as $header => $value)   {
        header("$header: $value");
    }
    exit;
}


            
            

function adminer_objectxx() {

    // plugins and helper stacked into one file
    include_once "./lib/adminer-plugins.php";

    
    $plugins = [
        new AdminerEditTextarea(),
        //new AdminerVersionNoverify(), // causes errors
    ];
    
    // combine customization and plugins:
    class AdminerDUMPCustomizationAndPlugins extends AdminerPlugin {
        
        public function name() {
            return 'WTP DUMP ADMINER';
        }
        
        function credentials() {
            global $dbAuth;
            return [$dbAuth['host'], $dbAuth['user'], $dbAuth['password']];
        }
        
        public function database() {
            global $dbAuth;
            return $dbAuth['dbname'];
        }

        function login($login, $password) {
            return true;
        }

        function loginForm() {
            global $autologinRedirectUri;
            print '<a href="'.$autologinRedirectUri.'">'.$autologinRedirectUri.'</a>';
            //parent::loginForm();
        }

        function css() {
            $I = array();
            $q = "?dump_disposition=adminer.css";
            if (file_exists($q)) $I[] = "$q?v=" . crc32(file_get_contents($q));
            return $I;
        }
    }

    //return new AdminerPlugin($plugins);   // call mine instead
    return new AdminerDUMPCustomizationAndPlugins($plugins);
}



// (copy of code from Adminer)
// init session the same way Adminer does it, but we need it here. then set SID to prevent calling this code again there
$initSession = function() {
    $ba = ($_SERVER["HTTPS"] && strcasecmp($_SERVER["HTTPS"], "off")) || ini_bool("session.cookie_secure");
    @ini_set("session.use_trans_sid", false);
    session_cache_limiter("");
    session_name("adminer_sid");
    $Qf = array(0, preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"]), "", $ba);
    if (version_compare(PHP_VERSION, '5.2.0') >= 0) $Qf[] = true;
    call_user_func_array('session_set_cookie_params', $Qf);
    session_start();
    define('SID', '');
};
$initSession();


// inject credentials into pwds array 
$_SESSION['pwds'][$ADM_driver][$dbAuth['host'] . ':' . $dbAuth['port']][$dbAuth['user']] = $dbAuth['password'];

// needed to be set to trigger autoload
$_GET['username'] = $dbAuth['user'];



// include original Adminer /  use "en", because multilang is broken somehow with output of lang labels if included like this
include "./lib/adminer-4.8.1-en.php";
