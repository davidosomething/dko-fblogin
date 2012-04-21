<?php
/**
 * Plugin Name:   DKO FB Login
 * Plugin URI:    https://github.com/davidosomething/dko-fblogin
 * Description:   Facebook Login Button and integration with WordPress user system
 * Author:        David O'Trakoun (@davidosomething)
 * Author Email:  me@davidosomething.com
 * Author URI:    http://www.davidosomething.com/
 * Version:       1.5.1
 *
 *
 * Loosely adapted from Otto42's Simple Facebook Connect
 * http://www.facebook.com/ottopress
 *
 *
 * @TODO: check for curl
 * @TODO: check for stream wrappers:

$w = stream_get_wrappers();
echo 'openssl: ',  extension_loaded  ('openssl') ? 'yes':'no', "\n";
echo 'http wrapper: ', in_array('http', $w) ? 'yes':'no', "\n";
echo 'https wrapper: ', in_array('https', $w) ? 'yes':'no', "\n";
echo 'wrappers: ', var_dump($w);
echo "\n";

 */

define('DKOFBLOGIN_PLUGIN_NAME',        'DKO FB Login');
define('DKOFBLOGIN_PLUGIN_VERSION',     '1.5.1');
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
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/graphapi.php';
require_once dirname(__FILE__) . '/plugin.php';
$dkofblogin = new DKOFBLogin(__FILE__);
if (is_admin()) {
  require_once dirname(__FILE__) . '/admin.php';
  $dkofblogin_admin = new DKOFBLogin_Admin();
}
require_once dirname(__FILE__) . '/functions.php';
