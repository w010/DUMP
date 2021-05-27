<?php

// this file is required to run with plugins. try to include and run this code from DUMP inside

$GLOBALS["TYPO3_CONF_VARS"]["DB"]["Connections"]["Default"]["user"] = "www_devel";
$GLOBALS["TYPO3_CONF_VARS"]["DB"]["Connections"]["Default"]["password"] = "www_devel";
$GLOBALS["TYPO3_CONF_VARS"]["DB"]["Connections"]["Default"]["dbname"] = "project_app";
$GLOBALS["TYPO3_CONF_VARS"]["DB"]["Connections"]["Default"]["host"] = "mysql";
$GLOBALS["TYPO3_CONF_VARS"]["DB"]["Connections"]["Default"]["port"] = "3306";
$GLOBALS["TYPO3_CONF_VARS"]["DB"]["Connections"]["Default"]["driver"] = "mysqli";


// z module controller:

            session_cache_limiter('');
            // Need to have cookie visible from parent directory
            session_set_cookie_params(0, '/', '', 0);

            // Create signon session
// todo: sprawdzic czy ta nazwa ma znaczenie

            $session_name = 'tx_t3adminer';
            session_name($session_name);
            session_start();

            // raczej zbedne
            // Pass export directory
            $_SESSION['exportDirectory'] = '/var/www/htdocs/';
            // Detect DBMS
            $_SESSION['ADM_driver'] = 'server';

$host = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'];

// Store there credentials in the session
            $_SESSION['ADM_user'] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'];
            $_SESSION['pwds'][$_SESSION['ADM_driver']][][$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user']] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'];
            $_SESSION['pwds'][$_SESSION['ADM_driver']][$host . ':' . $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port']][$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user']] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'];
            $_SESSION['ADM_password'] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'];
            $_SESSION['ADM_server'] = $host;
            $_SESSION['ADM_port'] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'];
            $_SESSION['ADM_db'] = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'];
            
            
            // Get signon uri for redirect
            $_SESSION['ADM_SignonURL'] = '/var/www/htdocs/'
                . ltrim(
                    /*substr(
                        $extPath, strlen($typo3DocumentRoot), strlen($extPath)
                    ),*/
                    '/'
                )
                . 'DUMP/adminer.php';
            
            //$_SESSION['ADM_LogoutURL'] = '/'

$id = session_id();

            // Force to set the cookie
            setcookie($session_name, $id, 0, '/', '');

            // Close that session
            //session_write_close();
            // todo to raczej nie, skoro robimy to w jednym requescie a nie iframe. zrobic to tak, jakby to juz bylo iframe


// Redirect to adminer (should use absolute URL here!), setting default database
            /*$redirectUri = $_SESSION['ADM_SignonURL'] . '?lang=' . $LANG_KEY . '&db='
                . rawurlencode($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname']) . '&'
                . rawurlencode($_SESSION['ADM_driver']) . '='
                . rawurlencode(
                    $host
                    . (
                        $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port']
                        ? ':' . $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port']
                        : ''
                    )
                ) . '&username=' . rawurlencode($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user']);
            if ($_SESSION['ADM_driver'] !== 'server') {
                $redirectUri .= '&driver=' . rawurlencode($_SESSION['ADM_driver']);
            }

            // Build and set cache-header header
            $headers = [
                'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'private',
                'Location' => $redirectUri
            ];*/
     

// koniec controllera

            
            
            

function adminer_object() {
    // required to run any plugin
    include_once "./plugins/plugin.php";
    
    // autoloader
    foreach (glob("plugins/*.php") as $filename) {
        include_once "./$filename";
    }
    
    
//    $_GET['username'] = 'www_devel';
    
    //$_GET["sqlite"] = "";
//$_GET["username"] = "www_devel";
//$_GET["db"] = "../../var/data.db";
    
//    $_POST['auth']['username'] = 'www_devel';
//    $_POST['auth']['password'] = 'www_devel';
//    $_POST['auth']['server'] = 'mysql';
    
    $plugins = [
        new AdminerLoginPasswordLess(password_hash('www_devel', PASSWORD_DEFAULT)),
        //new AdminerEditTextarea(),
        //new AdminerVersionNoverify(),
    ];
    
    // combine customization and plugins:
    class AdminerDUMPCustomizationAndPlugins extends AdminerPlugin {
        
        public function name()
        {
            return 'WTP DUMP ADMINER';
        }
        
        function credentials() {
            return ["mysql", "www_devel", "www_devel"];
        }
        
        public function database()
        {
            return "project_app";
        }
        
        function login($login, $password) {
            return true;
        }
    
        /*function loginForm() {
            print 'LOGINFORM~!';
        }*/
    }
    
    //return new AdminerPlugin($plugins);
    // call mine instead
    return new AdminerDUMPCustomizationAndPlugins($plugins);
}


session_cache_limiter('');
$session_name = 'tx_t3adminer';
session_name($session_name);
session_start();



// include original Adminer or Adminer Editor
include "./adminer.php";
