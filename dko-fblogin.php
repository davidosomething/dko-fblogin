<?php
/**
 * Plugin Name: DKO FB Login
 * Plugin URI: http://davidosomething.github.com/dko-fblogin/
 * Description: Facebook Login Button and integration with WordPress user system
 * Version: 1.0
 * Author: David O'Trakoun (@davidosomething)
 * Author Email: me@davidosomething.com
 * Author URI: http://www.davidosomething.com/
 */

$plugin_path = dirname(__FILE__) . '/';
if (!class_exists('DKOWPPluginFramework')) {
  require_once($plugin_path . 'framework/base.php');
}

class DKOFBLogin extends DKOWPPlugin
{
  const NAME = 'DKO FB Login';
  const SLUG = 'dkofblogin';

  /**
   * run every time plugin loaded
   */
  function __construct() {
    parent::__construct(__FILE__);
    register_activation_hook(__FILE__, array(&$this, 'activate'));
    add_action('init', array(&$this, 'initialize'));
  }

  /**
   * run when plugin first activated
   */
  function activate() {
  }

  /**
   * run during WP initialize - no output plz
   */
  function initialize() {
    add_shortcode('dko-fblogin-button', 'render_button');
  }

  function render_button($atts) {
    // Extract the attributes
    extract(shortcode_atts(array(
      'attr1' => 'foo', //foo is a default value
      'attr2' => 'bar'
      ), $atts));
    // you can now access the attribute values using $attr1 and $attr2
  }

}

$DKOFBLogin = new DKOFBLogin();
