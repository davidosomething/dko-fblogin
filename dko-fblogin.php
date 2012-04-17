<?php
/**
 * Plugin Name: DKO FB Login
 * Plugin URI: http://davidosomething.github.com/dko-fblogin/
 * Description: Facebook Login Button and integration with WordPress user system
 * Version: 1.0
 * Author: David O'Trakoun (@davidosomething)
 * Author Email: me@davidosomething.com
 * Author URI: http://www.davidosomething.com/
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

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/fbapi.php';
if (!class_exists('DKOWPPluginFramework')) {
  require_once dirname(__FILE__) . '/framework/base.php';
}

class DKOFBLogin extends DKOWPPlugin
{
  const NAME            = 'DKO FB Login';
  const PLUGIN_VERSION  = '1.0';

  private $menu_hook    = '';
  private $options      = array();
  private $options_key  = '';
  protected $destroyed    = false;

  /**
   * run every time plugin loaded
   */
  function __construct() {
    parent::__construct(__FILE__);
    register_activation_hook(__FILE__, array(&$this, 'activate'));
    register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));
    add_action('init', array(&$this, 'initialize'));
    add_action('init', array(&$this, 'html_channel_file'));
  }

  function activate() {
    add_rewrite_rule('dko-fblogin-endpoint/?', 'wp-content/plugins/dko-fblogin/endpoint.php', 'top');
    add_rewrite_rule('dko-fblogin-deauthorize/?', 'wp-content/plugins/dko-fblogin/deauthorize.php', 'top');
    flush_rewrite_rules(true);
  }

  function deactivate() {
    flush_rewrite_rules(true);
  }

  /**
   * run during WP initialize - no output plz
   */
  function initialize() {
    // setup plugin options
    $this->options_key = DKOFBLOGIN_SLUG.'_options';
    if (get_option($this->options_key) === FALSE) {
      add_option($this->options_key);
    }
    $this->options = get_option($this->options_key);
    /* unlink all facebook accounts */
    if (array_key_exists('confirm_destroy', $this->options) && $this->options['confirm_destroy']) {
      $this->options['confirm_destroy'] = false;
      update_option($this->options_key, $this->options);
      $this->unlink_accounts();
    }


    /* start a session up and create a new state (nonce for facebook auth) */
    session_start();
    if (empty($_REQUEST['code'])) { // don't generate new session state if have code
      $_SESSION['dko_fblogin_state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
    }

    if (is_admin()) { // hook settings pages
      add_action("admin_print_styles", array(&$this, 'admin_print_styles'));
      add_action('show_user_profile', array(&$this, 'user_profile_fields'), 10);
      add_action('edit_user_profile', array(&$this, 'user_profile_fields'), 10);
      if (current_user_can('manage_options')) {
        add_action('admin_menu', array(&$this, 'admin_menu'));
        add_action('admin_init', array(&$this, 'admin_init'));
      }
    }
    else { // hooks for site
      add_filter('language_attributes', array(&$this, 'html_fb_language_attributes'));
      add_action('wp_footer', array(&$this, 'html_fbjs'), 20);
    }

    // hook for shortcode
    add_shortcode('dko-fblogin-button',   array(&$this, 'login_button_shortcode'));
    add_shortcode('dko-fblink-button',    array(&$this, 'link_button_shortcode'));
    add_shortcode('dko-fblogout-button',  array(&$this, 'logout_button_shortcode'));
  }

  /* show fb info on profile edit page */
  function user_profile_fields($user) {
    // @TODO don't assume access_token is valid
    $access_token = get_the_author_meta(DKOFBLOGIN_USERMETA_KEY_TOKEN, $user->ID);
    $fb_data = false;
    if ($access_token) {
      $fb_data = dkofblogin_get_fb_userdata($access_token);
    }
    echo $this->render('admin-profile', $fb_data);
  }

  /* create admin menu item */
  function admin_menu() {
    // admin options page
    $this->menu_hook = add_options_page(
      __(self::NAME . ' Options'),
      __(self::NAME),
      'manage_options',
      DKOFBLOGIN_SLUG,
      array(&$this, 'admin_page')
    );
  }

  function admin_print_styles() {
    wp_enqueue_style(DKOFBLOGIN_SLUG . '-admin', $this->plugin_abspath . '/css/admin.css');
  } // admin_print_styles()


  /* callback function for add_options_page(), include html for admin options page */
  function admin_page() {
    echo $this->render('admin');
  }

  /* set up options and populate admin menu */
  function admin_init() {
    // create a new section on the page
    $section_slug = DKOFBLOGIN_SLUG.'_api';
    add_settings_section(
      $section_slug, 'Facebook API Settings',
      array(&$this, 'html_section_header_api'),
      DKOFBLOGIN_SLUG
    );

    // begin adding fields
    $this->add_settings_textfield($section_slug, 'App ID');
    $this->add_settings_textfield($section_slug, 'App Secret');
    add_settings_field(
      'permissions', 'Permissions', array(&$this, 'html_field_permissions'),
      DKOFBLOGIN_SLUG, $section_slug, array('field' => 'permissions')
    );
    $this->add_settings_textfield($section_slug, 'Login Redirect', array(
      'pre'   => 'Specify a URL to go to after logging in (e.g., user profile\'s page).<br />',
      'after' => 'Defaults to ' . admin_url('profile.php') . ' when blank.'
    ));
    $this->add_settings_textfield($section_slug, 'Register Redirect', array(
      'pre'   => 'Specify a URL to go to after logging in as a new facebook user (e.g., a registration page to capture additional data).<br />',
      'after' => 'Defaults to ' . admin_url('profile.php') . ' when blank.'
    ));

    $section_slug = DKOFBLOGIN_SLUG.'_destroy';
    add_settings_section(
      $section_slug, 'Wipe all associated accounts',
      array(&$this, 'html_section_header_destroy'),
      DKOFBLOGIN_SLUG
    );
    add_settings_field(
      'confirm_destroy', 'Confirm Destroy Metadata', array(&$this, 'html_field_confirm_destroy'),
      DKOFBLOGIN_SLUG, $section_slug, array('field' => 'confirm_destroy')
    );

    // make sure WP knows to save our options
    register_setting(
      $this->options_key,       // name of option group
      $this->options_key,       // add to this key in the group
      array(&$this, 'sanitize_api_field')
    );
  } // admin_init()

  function add_settings_textfield($section_slug, $field_name, $args = array()) {
    $field_slug = strtolower(str_replace(' ', '_', $field_name));
    $callback_args = array_merge(array('field' => $field_slug), $args);
    add_settings_field(
      $field_slug, $field_name, array(&$this, 'html_textfield'),
      DKOFBLOGIN_SLUG, $section_slug, $callback_args
    );
  }

  /* @TODO: can make this portable, move to framework */
  function html_section_header_destroy($args) {
    echo $this->render('admin-header-destroy');
  }

  /* @TODO: can make this portable, move to framework */
  function html_section_header_api($args) {
    echo $this->render('admin-header');
  }

  /* @TODO: can make this portable, move to framework */
  function html_textfield($args) {
    $options = get_option($this->options_key);
    $field_id = DKOFBLOGIN_SLUG . '-' . $args['field'];
    $field_name = $this->options_key . '[' . $args['field'] . ']';
    $field_value = isset($options[$args['field']]) ? $options[$args['field']] : '';
    if (array_key_exists('pre', $args)) {
      echo $args['pre'];
    }
    echo '<input id="', $field_id, '" type="text" name="', $field_name, '" value="', $field_value, '" size="40" />';
    if (array_key_exists('after', $args)) {
      echo $args['after'];
    }
  }

  /**
   * html for the permissions checkboxes
   */
  function html_field_confirm_destroy($args) {
    $field_id = DKOFBLOGIN_SLUG . '-confirm_destroy';
    $field_name = $this->options_key . '[' . $args['field'] . ']';
    echo '<label class="', DKOFBLOGIN_SLUG, '-confirm_destroy" for="', $field_id, '">';
    echo '<input id="', $field_id, '" name="' . $field_name . '" type="checkbox" value="1" />';
    echo ' Confirm destruction of fblogin metadata?</label>';
  } // html_field_permissions()

  /**
   * html for the permissions checkboxes
   */
  function html_field_permissions($args) {
    global $dkofblogin_permissions;
    $options = get_option($this->options_key);
    $permissions = array_key_exists('permissions', $options) ? $options['permissions'] : array();
    $field_name = $this->options_key . '[permissions][]';
    echo $this->render('admin-permissions');
    foreach ($dkofblogin_permissions as $p) {
      $field_id = DKOFBLOGIN_SLUG . '-' . $p;
      $field_value = in_array($p, $permissions);

      echo '<label class="', DKOFBLOGIN_SLUG, '-permission" for="', $field_id, '">';
      echo '<input id="', $field_id, '" name="', $field_name, '" type="checkbox" value="', $p, '" ';
        checked($field_value, true);
        echo ' /> ', $p, '</label>';
    }
  } // html_field_permissions()

  /**
   * input goes into here, comes out sanitized-ish
   * @return function, returns array of sanitized input
   */
  function sanitize_api_field($input) {
    $output = array();
    foreach ($input as $key => $val) {
      $output[$key] = '';
      if ($key == 'app_secret') {
        // secrets are 32 bytes long and made of hex values
        $val = trim($val);
        if (preg_match('/^[a-f0-9]{32}$/i', $val)) {
          $output[$key] = $val;
        }
      }
      elseif ($key == 'app_id') {
        // app ids are big integers
        $val = trim($val);
        if (preg_match('/^[0-9]+$/i', $val)) {
          $output[$key] = $val;
        }
      }
      else {
        $output[$key] = $val;
      }
    }

    if ($output['app_id'] && $output['app_secret']) {
      update_option(DKOFBLOGIN_SLUG . '_is_configured', true);
    }

    return apply_filters('sanitize_api_field', $output, $input);
  } // sanitize_api_field()

  /**
   * @return string comma-delimited list of fb permissions
   */
  function permissions_list() {
    $options = get_option($this->options_key);
    if (array_key_exists('permissions', $options)) {
      return implode(',', $options['permissions']);
    }
    return '';
  }

  /**
   * Shows a login link
   */
  function login_button_shortcode($atts) {
    if (is_user_logged_in()) {
      return '';
    }

    $options = get_option($this->options_key);

    $html = '<a class="dko-fblogin-button" href="https://www.facebook.com/dialog/oauth?client_id='.$options['app_id'];
    if (array_key_exists('permissions', $options)) {
      $html .= '&amp;scope=' . implode(',', $options['permissions']);
    }
    $html .= '&amp;redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT);
    $html .= '&amp;state=' . $_SESSION['dko_fblogin_state'];
    $html .= '">Login through facebook</a>';

    return $html;
  } // login_button_shortcode()

  /**
   * This is ALWAYS a link to auth, as opposed to the login button which shows
   * the user's profile if already logged in
   */
  function link_button_shortcode($atts) {
    $options = get_option($this->options_key);
    $html = '<a class="dko-fblogin-button" href="https://www.facebook.com/dialog/oauth?client_id='.$options['app_id'];
    if (array_key_exists('permissions', $options)) {
      $html .= '&amp;scope=' . implode(',', $options['permissions']);
    }
    $html .= '&amp;redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT);
    $html .= '&amp;state=' . $_SESSION['dko_fblogin_state'];
    $html .= '">Login to facebook and link this account</a>';
    return $html;
  } // link_button_shortcode()

  /**
   * This is always a link to logout (but not deauthorize) from facebook
   */
  function logout_button_shortcode($atts) {
    $options = get_option($this->options_key);
    $html = '<a class="dko-fblogin-button" href="https://www.facebook.com/dialog/oauth?client_id='.$options['app_id'];
    if (array_key_exists('permissions', $options)) {
      $html .= '&amp;scope=' . implode(',', $options['permissions']);
    }
    $html .= '&amp;redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT);
    $html .= '&amp;state=' . $_SESSION['dko_fblogin_state'];
    $html .= '">Login through facebook</a>';
    return $html;
  } // logout_button_shortcode()

  function html_social_plugin_login_button() {
    // Extract the attributes
    extract(shortcode_atts(array(
      'show_faces'        => 'false',
      'max_rows'          => '1',
      'width'             => '200'//,
      //'registration_url'  => //$this->endpoint
    ), $atts));
    $html = '<div class="fb-login-button" data-show-faces="' . $show_faces . '" data-max-rows="' . $max_rows . '" data-width="' . $width . '" ';
    if (isset($registration_url)) {
      $html .= 'data-registration-url="' . $registration_url . '"';
    }
    $html .= '></div>';
    return $html;
  } // html_social_plugin_login_button()

  // fix up the html tag to have the FBML extensions
  function html_fb_language_attributes($lang) {
    return ' xmlns:fb="http://ogp.me/ns/fb#" xmlns:og="http://ogp.me/ns#" ' . $lang;
  }

  /**
   * output html for fbjs
   */
  function html_fbjs($args = array()) {
    $options = get_option($this->options_key);
    $defaults = array(
      'appId'       => $options['app_id'],
      'channelUrl'  => home_url('?fbchannel=1'),
      'status'      => true,
      'cookie'      => true,
      'xfbml'       => true
    );
    $args = wp_parse_args($args, $defaults);
    echo $this->render('fbjs', $args);
  } // html_fbjs()

  /**
   * if requesting the channel file, output html and headers and exit
   */
  function html_channel_file() {
    if (!empty($_GET['fbchannel'])) {
      $cache_expire = 60*60*24*365;
      header("Pragma: public");
      header("Cache-Control: max-age=".$cache_expire);
      header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$cache_expire) . ' GMT');
      echo '<script src="//connect.facebook.net/en_US/all.js"></script>';
      exit;
    }
  } // html_channel_file()

  /**
   * remove all facebook metadata
   */
  function unlink_accounts() {
    global $wpdb;
    $where = array ('1' => '1');
    $result = $wpdb->query("UPDATE $wpdb->usermeta SET "
      . "meta_value='' WHERE meta_key='" . DKOFBLOGIN_USERMETA_KEY_TOKEN . "'");
    $result = $wpdb->query("UPDATE $wpdb->usermeta SET "
      . "meta_value='' WHERE meta_key='" . DKOFBLOGIN_USERMETA_KEY_FBID . "'");
    $this->destroyed = true;
  }

} // end of class

/**
 * provide helper function to render the button
 */
function dko_fblogin_button() {
  echo do_shortcode('[dko-fblogin-button]');
}

$DKOFBLogin = new DKOFBLogin();
