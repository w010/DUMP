<?php
/**
 * DUMP add-on
 * Adminer with autologin, using DUMP's final configuration read from TYPO3 configs
 *
 * Run DUMP in Adminer Autologin mode - make it initialize env, settings, TYPO3's configs and finally
 * output Adminer instead of DUMP itself. Configuration is passed to loader and there used to inject authorization.
 * 
 * 
 * WTP DUMP/BACKUP TOOL FOR TYPO3 - wolo.pl '.' studio
 * 2013-2021
 */


$GLOBALS['dump_adminer_load'] = true;

require_once ('./index.php');
