<?php
/**
 * Plugin Name:   DKO FB Login
 * Plugin URI:    https://github.com/davidosomething/dko-fblogin
 * Description:   Facebook Login Button and integration with WordPress user system
 * Author:        David O'Trakoun (@davidosomething)
 * Author Email:  me@davidosomething.com
 * Author URI:    http://www.davidosomething.com/
 * Version:       1.5.2
 *
 * Loosely adapted from Otto42's Simple Facebook Connect
 * http://www.facebook.com/ottopress
 *
 */
define('DKOFBLOGIN_PLUGIN_NAME',        'DKO FB Login');
define('DKOFBLOGIN_PLUGIN_VERSION',     '1.6.2'); // med increment on add options/url rewrites
define('DKOFBLOGIN_SLUG',               'dkofblogin');
define('DKOFBLOGIN_ENDPOINT_SLUG',      DKOFBLOGIN_SLUG . '-endpoint');
define('DKOFBLOGIN_ENDPOINT_URL',       site_url('/' . DKOFBLOGIN_ENDPOINT_SLUG));
define('DKOFBLOGIN_DEAUTHORIZE_SLUG',   DKOFBLOGIN_SLUG . '-deauthorize');
define('DKOFBLOGIN_DEAUTHORIZE_URL',    site_url('/' . DKOFBLOGIN_DEAUTHORIZE_SLUG));
define('DKOFBLOGIN_OPTIONS_KEY',        DKOFBLOGIN_SLUG . '_options');
define('DKOFBLOGIN_USERMETA_KEY_FBID',  DKOFBLOGIN_SLUG . '_fbid');
define('DKOFBLOGIN_USERMETA_KEY_TOKEN', DKOFBLOGIN_SLUG . '_token');

require_once dirname( __FILE__ ) . '/graphapi.php';
require_once dirname( __FILE__ ) . '/plugin.php';
if (is_admin()) {
  require_once dirname( __FILE__ ) . '/admin.php';
  $dkofblogin = new DKOFBLogin_Admin( __FILE__ );
}
else {
  $dkofblogin = new DKOFBLogin( __FILE__ );
}
require_once dirname( __FILE__ ) . '/functions.php';
