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

  const PLUGIN_VERSION  = '1.0';

  /**
   * run every time plugin loaded
   */
  function __construct() {
    parent::__construct(__FILE__);
    register_activation_hook(__FILE__, array(&$this, 'activate'));
    add_action('init', array(&$this, 'initialize'));
    add_action('plugins_loaded', array(&$this, 'update'));
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

    if (current_user_can('manage_options')) {
      // admin options page
      $this->page = $page = add_options_page(
        __('DKO FB Login Options'),
        __('DKO FB Login'),
        'manage_options',
        self::SLUG,
        array(&$this, 'admin_page')
      );

      // set help tabs, enqueue scripts&styles here:
      // add_action("load-$page", array(&$this, 'admin_load'));

      // process form here:
      add_action("load-$page", array(&$this, 'admin_submitted'), 49);

      // add things to <head> for options page
      // add_action("admin_head-$page", array(&$this, 'admin_header'), 51);
    }

    add_shortcode('dko-fblogin-button', 'render_button');
  }

  /* include html for admin options page */
  function admin_page() {
    $this->render('admin');
  }

  /* process options page form */
  function admin_submitted() {
    if (empty($_POST)) { return; }

    if (isset($_POST['api_key'])) {
//      update_option();
    }

    if (isset($_POST['api_secret'])) {
//      update_option();
    }

    $this->updated = true;
    return;

  }

  function render_button($atts) {
    // Extract the attributes
    extract(shortcode_atts(array(
      'attr1' => 'foo', //foo is a default value
      'attr2' => 'bar'
      ), $atts));
    // you can now access the attribute values using $attr1 and $attr2
  }

} // end of class

$DKOFBLogin = new DKOFBLogin();
