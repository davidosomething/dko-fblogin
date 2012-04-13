<?php
/**
 * dko-fblogin-settings.php
 * make sure WordPress is loaded before requiring this file!
 */

define('DKOFBLOGIN_SLUG', 'dkofblogin');
define('DKOFBLOGIN_ENDPOINT', site_url('/register'));

if (!defined('SERVER_ENVIRONMENT') || SERVER_ENVIRONMENT == 'PROD') {
  $dko_fblogin_http_settings = array(
    'sslverify' => 'true'
  );
}
else {
  $dko_fblogin_http_settings = array(
    'timeout'   => '5',
    'sslverify' => 'false'
  );
}
