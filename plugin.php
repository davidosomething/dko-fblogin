<?php
if (!class_exists('DKOWPPluginFramework')):
  require_once dirname(__FILE__) . '/framework/base.php';
endif;

if (!class_exists('DKOFBLogin')):
class DKOFBLogin extends DKOWPPlugin
{
  private $options  = array();
  private $defaults = array(
    'plugin_version'    => DKOFBLOGIN_PLUGIN_VERSION,
    'app_id'            => '',
    'app_secret'        => '',
    'confirm_destroy'   => false,
    'permissions'       => array(),
    'login_redirect'    => '',
    'register_redirect' => ''
  );
  protected $destroyed = false;

  /**
   * run every time plugin loaded
   */
  public function __construct() {
    parent::__construct(__FILE__);

    register_activation_hook(   __FILE__, array(&$this, 'activate'));
    register_deactivation_hook( __FILE__, array(&$this, 'deactivate'));

    $this->setup_options();
    $this->setup_session();
    $this->check_update();

    add_action('init', array(&$this, 'initialize'));
    add_action('init', array(&$this, 'html_channel_file'));
  }

  /**
   * generate the options if for some reason they don't exist
   */
  private function setup_options() {
    $this->options = get_option(DKOFBLOGIN_OPTIONS_KEY);
    if ($this->options === false) {
      add_option(DKOFBLOGIN_OPTIONS_KEY, $this->defaults);
    }
  }

  /**
   * start a session and generate a state nonce for FB API
   */
  private function setup_session() {
    if (!isset($_SESSION)) { session_start(); }
    if (empty($_REQUEST['code'])) { // don't generate new session state if have code
      $_SESSION[DKOFBLOGIN_SLUG.'_state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
    }
  }

  /**
   * Check to see if the plugin was updated, do maintenance
   */
  private function check_update() {
    if (!array_key_exists('plugin_version', $this->options) || $this->options['plugin_version'] !== $this->defaults['plugin_version']) {
      $this->options = array_merge($this->defaults, $this->options);
      update_option(DKOFBLOGIN_OPTIONS_KEY, $this->options);
      $this->activate(); // run the activation hook again!
    }
  }

  /**
   * read the request superglobal to see if a link or unlink action was requested
   */
  function do_endpoints() {
    if (!empty($_REQUEST[DKOFBLOGIN_SLUG.'_link'])) {
      $this->fb_link();
    }

    if (!empty($_REQUEST[DKOFBLOGIN_SLUG.'_unlink'])) {
      $this->fb_unlink();
    }
  }

  /**
   * callback for activation_hook
   * also called whenever the plugin is updated
   */
  public function activate() {
    add_rewrite_rule(DKOFBLOGIN_ENDPOINT_SLUG.'/?',     '?' . DKOFBLOGIN_SLUG . '_link=1',    'top');
    add_rewrite_rule(DKOFBLOGIN_DEAUTHORIZE_SLUG.'/?',  '?' . DKOFBLOGIN_SLUG . '_unlink=1',  'top');
    flush_rewrite_rules(true);
  }

  /**
   * callback for deactivation_hook
   */
  public function deactivate() {
    flush_rewrite_rules(true);
  }

  /**
   * run during WP initialize - no output plz
   */
  public function initialize() {
    add_filter('language_attributes',     array(&$this, 'html_fb_language_attributes'));
    add_action('wp_footer',               array(&$this, 'html_fbjs'), 20);
    add_shortcode('dko-fblogin-button',   array(&$this, 'html_shortcode_login_button'));
    add_shortcode('dko-fblink-button',    array(&$this, 'html_shortcode_link_button'));
    add_shortcode('dko-fblogout-button',  array(&$this, 'html_shortcode_logout_button'));

    $this->do_endpoints();
  } // initialize()

  /**
   * @return string comma-delimited list of fb permissions
   */
  public function permissions_list() {
    if (array_key_exists('permissions', $this->options)) {
      return implode(',', $this->options['permissions']);
    }
    return '';
  }

  /**
   * @return string link to login via facebook
   */
  public function login_link() {
    $link = 'https://www.facebook.com/dialog/oauth?client_id=' . $this->options['app_id'];
    if (array_key_exists('permissions', $this->options)) {
      $link .= '&amp;scope=' . implode(',', $this->options['permissions']);
    }
    $link .= '&amp;redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT_URL);
    $link .= '&amp;state=' . $_SESSION[DKOFBLOGIN_SLUG.'_state'];
    return $link;
  }

  /**
   * Shows a login link
   * @return string html link for facebook login
   */
  public function html_shortcode_login_button($atts) {
    if (is_user_logged_in()) {
      return '';
    }
    return '<a class="dko-fblogin-button" href="' . $this->login_link() . '">Login through facebook</a>';
  } // login_button_shortcode()

  /**
   * This is ALWAYS a link to auth, as opposed to the login button which shows
   * the user's profile if already logged in
   */
  public function html_shortcode_link_button($atts) {
    $html = '<a class="dko-fblogin-button" href="https://www.facebook.com/dialog/oauth?client_id='.$this->options['app_id'];
    if (array_key_exists('permissions', $this->options)) {
      $html .= '&amp;scope=' . implode(',', $this->options['permissions']);
    }
    $html .= '&amp;redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT_URL);
    $html .= '&amp;state=' . $_SESSION[DKOFBLOGIN_SLUG.'_state'];
    $html .= '">Login to facebook and link this account</a>';
    return $html;
  } // link_button_shortcode()

  /**
   * This is always a link to logout (but not deauthorize) from facebook
   */
  public function html_shortcode_logout_button($atts) {
    $html = '<a class="dko-fblogin-button" href="https://www.facebook.com/dialog/oauth?client_id='.$this->options['app_id'];
    if (array_key_exists('permissions', $this->options)) {
      $html .= '&amp;scope=' . implode(',', $this->options['permissions']);
    }
    $html .= '&amp;redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT_URL);
    $html .= '&amp;state=' . $_SESSION[DKOFBLOGIN_SLUG.'_state'];
    $html .= '">Login through facebook</a>';
    return $html;
  } // logout_button_shortcode()

  /**
   *
   */
  public function html_social_plugin_login_button() {
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
  public function html_fb_language_attributes($lang) {
    return ' xmlns:fb="http://ogp.me/ns/fb#" xmlns:og="http://ogp.me/ns#" ' . $lang;
  }

  /**
   * output html for fbjs
   * public since called by add_filter
   */
  public function html_fbjs($args = array()) {
    $fbdefaults = array(
      'appId'       => $this->options['app_id'],
      'channelUrl'  => home_url('?fbchannel=1'),
      'status'      => true,
      'cookie'      => true,
      'xfbml'       => true
    );
    $args = wp_parse_args($args, $fbdefaults);
    echo $this->render('fbjs', $args);
  } // html_fbjs()

  /**
   * if requesting the channel file, output html and headers and exit
   * public since called by add_filter
   */
  public function html_channel_file() {
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
   * do facebook login
   */
  private function fb_link() {
    require_once dirname(__FILE__) . '/fb_link.php';
  }

  /**
   * by using the update_user_meta we ensure only one FB acct per WP user
   * @param int     $user_id      WordPress user id
   * @param string  $fbid         Facebook user id
   * @param string  $access_code  Facebook access code
   */
  private function setfbmeta($user_id = 0, $fbid = '', $access_token = '') {
    if (!$user_id) {
      throw new Exception('setfbmeta: need user id');
    }
    update_user_meta($user_id, DKOFBLOGIN_USERMETA_KEY_FBID, $fbid);
    update_user_meta($user_id, DKOFBLOGIN_USERMETA_KEY_TOKEN, $access_token);
  }

  /**
   * log a user in to wordpress
   * only logs in subscribers, just in case
   * @param int $user_id  ID of user to login as
   */
  private function wp_login($user_id = 0) {
    if (!$user_id) {
      throw new Exception('login: Invalid user_id');
    }

    // make sure user is only a subscriber
    $user_data = get_userdata($user_id);

    if (count($user_data->roles) > 1 && $user_data->roles[0] !== 'subscriber') {
      return;
    }

    // log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    // in case anything is hooked to this function
    do_action('wp_login', $user_data->user_login);
  }

  /**
   * redirect to $location or $default with $status
   * @param string  $location  URL to redirect to
   * @param string  $default   URL to redirect to if $location is blank
   * @param int     $status    HTTP status code to return
   */
  public static function redirect($location = '', $default = '', $status = 302) {
    echo 'supposed to redirect!';
    if ($location) {
      header('location: ' . $location, true, $status); // send 302 Found header
      exit; // just in case
    }
    // no login location defined, go to user's wordpress admin profile
    if (!$default) {
      $default = admin_url('profile.php');
    }
    header('location: ' . $default, true, $status); // send 302 Found header
    exit; // just in case
  }

  /**
   * @param object $fb_data fb data from graph api
   * @return object WordPress User object or false
   */
  public static function get_user_by_fbdata($fb_data) {
    $user_query = new WP_User_Query(array(
      'meta_key'      => DKOFBLOGIN_USERMETA_KEY_FBID,
      'meta_value'    => $fb_data->id,
      'meta_compare'  => '='
    ));
    if ($user_query->total_users) {
      return $user_query->results[0];
    }

    return get_user_by('email', $fb_data->email);
  }

  /**
   * checks if $username is taken, if so add a number. Recurse until not taken.
   * @TODO this could take a while, consider another method
   * @param string $username  what username to try
   * @param string $prefix    some number to append to the end of the username
   * @return string some valid untaken WordPress username
   */
  public static function create_username($username = '', $prefix = '') {
    if (!$username) {
      throw new Exception('create_username: username not specified');
    }
    $username = preg_replace("/[^0-9a-zA-Z ]/m", '', $username);
    $attempt = $username . $prefix;
    $taken = get_user_by('login', $attempt);
    if (!$taken) {
      return $attempt;
    }
    if (!$prefix) {
      $prefix = 0;
    }
    $prefix = $prefix + 1;
    return self::create_username($username, $prefix);
  }

  /**
   * Get access token from API or user meta if logged in
   * if user_id passed use meta otherwise request from api
   * @param int user_id optional user_id to get meta from
   */
  function get_access_token($user_id = 0) {
    if ($user_id) {
      return get_the_author_meta(DKOFBLOGIN_USERMETA_KEY_TOKEN, $user_id);
    }

    global $dkofblogin_http_settings;
    $is_configured = get_option(DKOFBLOGIN_SLUG . '_is_configured');
    if (!$is_configured) {
      throw new Exception('dko-fblogin is not configured properly');
    }

    $options = get_option(DKOFBLOGIN_OPTIONS_KEY);
    $token_url = 'https://graph.facebook.com/oauth/access_token?'
      . 'client_id=' . $options['app_id']
      . '&redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT_URL)
      . '&client_secret=' . $options['app_secret']
      . '&code=' . $_REQUEST['code'];
    $ch = curl_init($token_url);
    curl_setopt_array($ch, $dkofblogin_http_settings);
    $result = curl_exec($ch);
    if (curl_errno($ch) == 60) { // CURLE_SSL_CACERT
      // Invalid or no certificate authority found, using bundled information
      curl_setopt_array($ch, $dkofblogin_http_settings);
      curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/fb_ca_chain_bundle.crt');
      $result = curl_exec($ch);
    }

    if (curl_errno($ch)) {
      throw new Exception(curl_error($ch));
    }

    curl_close($ch);
    return str_replace('access_token=', '', $result);
  } // dkofblogin_get_access_token()

  /**
   * clear user fb meta and then redirect user to profile page (as if they just logged in)
   * @return void
   */
  private function fb_unlink() {
    if (is_user_logged_in()) {
      $user_id = get_current_user_id();
      $this->setfbmeta($user_id, '', '');
    }
    $this->redirect($options['login_redirect'], admin_url('profile.php'));
  }
} // end of class
endif;
