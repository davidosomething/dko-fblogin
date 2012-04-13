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

    // hook settings pages
    if (current_user_can('manage_options')) {
      add_action('admin_menu', array(&$this, 'admin_menu'));
      add_action('admin_init', array(&$this, 'admin_init'));
    }

    // hook for shortcode
    add_shortcode('dko-fblogin-button', array(&$this, 'shortcode'));

    // hooks for site
    if (!is_admin()) {
      session_start();
      if (empty($_REQUEST['code'])) { // don't generate new session state if have code
        $_SESSION['dko_fblogin_state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
      }
      add_filter('language_attributes', array(&$this, 'html_fb_language_attributes'));
      add_action('wp_footer', array(&$this, 'html_fbjs'), 20);
    }
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
    add_action("admin_print_styles-$this->menu_hook", array(&$this, 'admin_print_styles'));
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
      DKOFBLOGIN_SLUG                          // slug of the PAGE
    );

    // begin adding fields
    add_settings_field(
      'app_id',                           // ID used to identify the field throughout the theme
      'App ID/key',                       // The label to the left of the option interface element
      array(&$this, 'html_field_api'),    // html callback to render field
      DKOFBLOGIN_SLUG,                    // page name
      $section_slug,                      // section name
      array('field' => 'app_id')          // pass whatever you want to the html_callback function (arg[3])
    );
    add_settings_field(
      'app_secret', 'App Secret', array(&$this, 'html_field_api'),
      DKOFBLOGIN_SLUG, $section_slug, array('field' => 'app_secret')
    );
    add_settings_field(
      'permissions', 'Permissions', array(&$this, 'html_field_permissions'),
      DKOFBLOGIN_SLUG, $section_slug, array('field' => 'permissions')
    );

    // make sure WP knows to save our options
    register_setting(
      $this->options_key,       // name of option group
      $this->options_key,       // add to this key in the group
      array(&$this, 'sanitize_api_field')
    );
  } // admin_init()

  function html_section_header_api($args) {
    echo '<p>Get this stuff from your facebook application\'s settings page.</p>';
  }

  function html_field_api($args) {
    $options = get_option($this->options_key);
    $field_id = DKOFBLOGIN_SLUG . '-' . $args['field'];
    $field_name = $this->options_key . '[' . $args['field'] . ']';
    $field_value = isset($options[$args['field']]) ? $options[$args['field']] : '';
    echo '<input id="', $field_id, '" type="text" name="', $field_name, '" value="', $field_value, '" />';
  }

  function html_field_permissions($args) {
    global $dko_fblogin_permissions;
    $options = get_option($this->options_key);
    $permissions = array_key_exists('permissions', $options) ? $options['permissions'] : array();
    $field_name = $this->options_key . '[permissions][]';
    echo <<<STREAM
<p>
  Select which permissions to request from the user. This is passed in the "scope"
  query parameter for the oAuth link, e.g.,
  <code>scope=user_birthday,user_location,email,xmpp_login,publish_stream,publish_actions</code>
</p>
<p>
  See
  <a target="_blank" href="https://developers.facebook.com/docs/authentication/permissions/">https://developers.facebook.com/docs/authentication/permissions/</a>
  for a description of what each one does.
</p>
STREAM;
    foreach ($dko_fblogin_permissions as $p) {
      $field_id = DKOFBLOGIN_SLUG . '-' . $p;
      $field_value = in_array($p, $permissions);

      echo '<label class="', DKOFBLOGIN_SLUG, '-permission" for="', $field_id, '">';
      echo '<input id="', $field_id, '" name="' . $field_name;
        echo '" type="checkbox" value="', $p, '" ';
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
      elseif ($key == 'permissions') {
        $output[$key] = $val;
      }
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

  function shortcode($atts) {
    $options = get_option($this->options_key);
    $html = '<a class="dko-fblogin-button" href="https://www.facebook.com/dialog/oauth?client_id='.$options['app_id'];
    if (array_key_exists('permissions', $options)) {
      $html .= '&amp;scope=' . implode(',', $options['permissions']);
    }
    $html .= '&amp;redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT);
    $html .= '&amp;state=' . $_SESSION['dko_fblogin_state'];
    $html .= '">Login through facebook</a>';
    return $html;
  } // shortcode()

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

    $this->data = array('args' => $args);
    echo $this->render('fbjs', $this->data);
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

} // end of class

/**
 * provide helper function to render the button
 */
function dko_fblogin_button() {
  echo do_shortcode('[dko-fblogin-button]');
}

$DKOFBLogin = new DKOFBLogin();
