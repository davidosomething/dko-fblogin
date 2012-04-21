<?php
/**
 * Plugin Name: DKO FB Login
 * Plugin URI: http://davidosomething.github.com/dko-fblogin/
 * Description: Facebook Login Button and integration with WordPress user system
 * Version: 1.5
 * Author: David O'Trakoun (@davidosomething)
 * Author Email: me@davidosomething.com
 * Author URI: http://www.davidosomething.com/
 *
 * Loosely adapted from Otto42's Simple Facebook Connect
 * http://www.facebook.com/ottopress
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

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/graphapi.php';
require_once dirname(__FILE__) . '/plugin.php';
$dkofblogin = new DKOFBLogin();
if (is_admin()) {
  require_once dirname(__FILE__) . '/admin.php';
  $dkofblogin_admin = new DKOFBLogin_Admin();
}
require_once dirname(__FILE__) . '/functions.php';
