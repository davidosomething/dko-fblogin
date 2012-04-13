<?php
/**
 * dko-fblogin-settings.php
 * make sure WordPress is loaded before requiring this file!
 */

define('DKOFBLOGIN_SLUG', 'dkofblogin');
define('DKOFBLOGIN_ENDPOINT', site_url('/register'));

if (!defined('SERVER_ENVIRONMENT') || SERVER_ENVIRONMENT == 'PROD') {
  $dko_fblogin_http_settings = array(
    CURLOPT_SSL_VERIFYHOST => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSLVERSION => 3
  );
}
else {
  $dko_fblogin_http_settings = array(
    CURLOPT_SSL_VERIFYHOST  => false,
    CURLOPT_SSL_VERIFYPEER  => false,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_SSLVERSION      => 3,
    CURLOPT_VERBOSE         => 1
  );
}
