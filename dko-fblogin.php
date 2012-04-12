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

  private $options_key;

  /**
   * run every time plugin loaded
   */
  function __construct() {
    parent::__construct(__FILE__);

    $this->options_key = self::SLUG.'_options';

    register_activation_hook(__FILE__, array(&$this, 'activate'));
    add_action('init', array(&$this, 'initialize'));
    add_action('init', array(&$this, 'html_channel_file'));
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
      add_action('admin_menu', array(&$this, 'admin_menu'));
      add_action('admin_init', array(&$this, 'admin_init'));
      // set help tabs, enqueue scripts&styles here:
      // add_action("load-$this->page", array(&$this, 'admin_load'));
      // add things to <head> for options page
      // add_action("admin_head-$this->page", array(&$this, 'admin_header'), 51);
    }

    add_shortcode('dko-fblogin-button', 'render_button');

    if (!is_admin()) {
      add_filter('language_attributes', array(&$this, 'html_language_attributes'));
      add_action('wp_footer', array(&$this, 'html_fbjs', 20));
    }
  }

  /* create admin menu item */
  function admin_menu() {
    // admin options page
    $this->page = add_options_page(
      __(self::NAME . ' Options'),
      __(self::NAME),
      'manage_options',
      self::SLUG,
      array(&$this, 'admin_page')
    );
  }

  /* callback function for add_options_page(), include html for admin options page */
  function admin_page() {
    echo $this->render('admin');
  }

  /* set up options and populate admin menu */
  function admin_init() {
    if (get_option($this->options_key) === FALSE) {
      add_option($this->options_key);
    }

    // create a new section on the page
    add_settings_section(
      self::SLUG.'_api',                  // slug of the SECTION
      'Facebook API Settings',            // Title of this section
      array(&$this, 'html_api_section_header'),
      self::SLUG                          // slug of the PAGE
    );

    // begin adding fields
    add_settings_field(
      'api_id',                           // ID used to identify the field throughout the theme
      'API ID',                           // The label to the left of the option interface element
      array(&$this, 'html_field_api'),    // html callback to render field
      self::SLUG,                         // page name
      self::SLUG.'_api',                  // section name
      array('field' => 'api_id')          // pass whatever you want to the html_callback function (arg[3])
    );
    // begin adding fields
    add_settings_field(
      'api_secret',                       // ID used to identify the field throughout the theme
      'API Secret',                       // The label to the left of the option interface element
      array(&$this, 'html_field_api'),    // html callback to render field
      self::SLUG,                         // page name
      self::SLUG.'_api',                  // section name
      array('field' => 'api_secret')      // pass whatever you want to the html_callback function (arg[3])
    );

    // make sure WP knows to save our options
    register_setting(
      $this->options_key,       // name of option group
      $this->options_key,       // add to this key in the group
      array(&$this, 'sanitize_api_field')
    );
  } // admin_init()

  function html_api_section_header($args) {
    echo '<p>Get this stuff from your facebook application\'s settings page.</p>';
  }

  function html_field_api($args) {
    $options = get_option($this->options_key);
    $field_id = self::SLUG . '-' . $args['field'];
    $field_name = $this->options_key . '[' . $args['field'] . ']';
    $field_value = isset($options[$args['field']]) ? $options[$args['field']] : '';
    echo '<input id="', $field_id, '" type="text" name="', $field_name, '" value="', $field_value, '" />';
  }

  /* @TODO: fix... not working */
  function sanitize_api_field($input) {
    $output = array();
    foreach ($input as $key => $val) {
      $output[$key] = '';
      $val = trim($val);
      if ($key == 'app_secret') {
        // secrets are 32 bytes long and made of hex values
        if (preg_match('/^[a-f0-9]{32}$/i', $val)) {
          $output[$key] = $val;
        }
      }
      elseif ($key == 'app_id') {
        // app ids are big integers
        if (preg_match('/^[0-9]+$/i', $val)) {
          $output[$key] = $val;
        }
      }
      else {
        $output[$key] = strip_tags(stripslashes($val));
      }
    }
    return apply_filters('sanitize_api_field', $output, $input);
  }

  function render_button($atts) {
    // Extract the attributes
    extract(shortcode_atts(array(
      'attr1' => 'foo', //foo is a default value
      'attr2' => 'bar'
      ), $atts));
    // you can now access the attribute values using $attr1 and $attr2
  }

  // fix up the html tag to have the FBML extensions
  function html_language_attributes($lang) {
    return ' xmlns:fb="http://ogp.me/ns/fb#" xmlns:og="http://ogp.me/ns#" '.$lang;
  }

  function html_fbjs() {
    $options = get_option($this->options_key);
    $defaults = array(
      'appId'       => $options['api_id'],
      'channelUrl'  => home_url('?fbchannel=1'),
      'status'      => true,
      'cookie'      => true,
      'xfbml'       => true,
      'oauth'       => true,
    );
    $args = wp_parse_args($args, $defaults);
    ?>
    <div id="fb-root"></div>
    <script>
      window.fbAsyncInit = function() {
        FB.init(<?php echo json_encode($args); ?>);
      };
      (function(d){
        var js, id = 'facebook-jssdk', ref = d.getElementsByTagName('script')[0];
        if (d.getElementById(id)) {return;}
        js = d.createElement('script'); js.id = id; js.async = true;
        js.src = "//connect.facebook.net/en_US/all.js";
        ref.parentNode.insertBefore(js, ref);
      }(document));
    </script>
    <?php
  } // html_fbjs()

  function html_channel_file() {
    if (!empty($_GET['fbchannel'])) {
      $cache_expire = 60*60*24*365;
      header("Pragma: public");
      header("Cache-Control: max-age=".$cache_expire);
      header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$cache_expire) . ' GMT');
      echo '<script src="//connect.facebook.net/en_US/all.js"></script>';
      exit;
    }
  }

} // end of class

$DKOFBLogin = new DKOFBLogin();
