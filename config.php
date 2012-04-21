<?php
/**
 * dko-fblogin-settings.php
 * make sure WordPress is loaded before requiring this file!
 */

define('DKOFBLOGIN_PLUGIN_NAME',        'DKO FB Login');
define('DKOFBLOGIN_PLUGIN_VERSION',     '1.5'); // don't forget to update comment in dko-fblogin.php
define('DKOFBLOGIN_SLUG',               'dkofblogin');
define('DKOFBLOGIN_ENDPOINT_SLUG',      DKOFBLOGIN_SLUG . '-endpoint');
define('DKOFBLOGIN_ENDPOINT_URL',       site_url('/' . DKOFBLOGIN_ENDPOINT_SLUG));
define('DKOFBLOGIN_DEAUTHORIZE_SLUG',   DKOFBLOGIN_SLUG . '-deauthorize');
define('DKOFBLOGIN_DEAUTHORIZE_URL',    site_url('/' . DKOFBLOGIN_DEAUTHORIZE_SLUG));
define('DKOFBLOGIN_OPTIONS_KEY',        DKOFBLOGIN_SLUG . '_options');
define('DKOFBLOGIN_USERMETA_KEY_FBID',  DKOFBLOGIN_SLUG . '_fbid');
define('DKOFBLOGIN_USERMETA_KEY_TOKEN', DKOFBLOGIN_SLUG . '_token');

if (!defined('SERVER_ENVIRONMENT') || SERVER_ENVIRONMENT == 'PROD') {
  $dkofblogin_http_settings = array(
    CURLOPT_SSL_VERIFYHOST => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSLVERSION => 3
  );
}
else { // local or dev
  $dkofblogin_http_settings = array(
    CURLOPT_SSL_VERIFYHOST  => false,
    CURLOPT_SSL_VERIFYPEER  => false,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_SSLVERSION      => 3,
    CURLOPT_VERBOSE         => 1
  );
}
