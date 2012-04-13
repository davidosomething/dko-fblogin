<?php
define('DKOFBLOGIN_SLUG', 'dkofblogin');
if ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off') {
  define('DKOFBLOGIN_ENDPOINT', str_replace('http://', 'https://', site_url('/register')));
}
else {
  define('DKOFBLOGIN_ENDPOINT', site_url('/register'));
}

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
