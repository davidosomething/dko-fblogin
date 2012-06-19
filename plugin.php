<?php
/**
 * dko-fblogin/plugin.php
 * The actual plugin class for DKOFBLogin
 * This is also a parent class for DKOFBLogin_Admin
 */

if (!class_exists('DKOWPPlugin')):
  require_once dirname( __FILE__ ) . '/framework/base.php';
endif;

if (!class_exists('DKOFBLogin')):
class DKOFBLogin extends DKOWPPlugin
{
  public $graphapi;

  protected $options  = array();
  protected $defaults = array(
    'installed_version' => '0',
    'app_id'            => '',
    'app_secret'        => '',
    'permissions'       => array(),
    'denial_redirect'   => '',
    'login_redirect'    => '',
    'register_redirect' => '',
    'email_body'        => '<p>Hi {first_name} {last_name},</p><p>You logged in via Facebook on <a href="{site_url}">{site_name}</a> so we created an account for you. Keep this email for reference.</p><p>You can always login via your linked Facebook account or use the following username and password:</p><ul><li><strong>username:</strong> {username}</li><li><strong>password:</strong> {password}</li></ul>',
    'confirm_destroy'   => false,
  );
  protected $destroyed = false;

  protected $current_user_data = null;
  protected $fb_data           = null;

  protected $app_id;
  protected $app_secret;

  /**
   * run every time plugin loaded
   */
  public function __construct($plugin_file) {
    parent::__construct($plugin_file);

    register_activation_hook($plugin_file, array(&$this, 'activate'));
    register_deactivation_hook($plugin_file, array(&$this, 'deactivate'));
    // register uninstall, delete options

    add_action('init', array(&$this, 'initialize'));
    add_action('init', array(&$this, 'html_channel_file'));

    $this->setup_options();
    $this->check_update();
  } // __construct()

  /**
   * generate the options if for some reason they don't exist
   */
  private function setup_options() {
    $this->options = get_option(DKOFBLOGIN_OPTIONS_KEY);
    if ($this->options === false) {
      add_option(DKOFBLOGIN_OPTIONS_KEY, $this->defaults);
      $this->options = $this->defaults;
    }
    else {
      if (!defined('DKOFBLOGIN_APP_ID') || !defined('DKOFBLOGIN_APP_SECRET')) {
        $this->app_id     = $this->options['app_id'];
        $this->app_secret = $this->options['app_secret'];
      }
      else {
        $this->app_id     = DKOFBLOGIN_APP_ID;
        $this->app_secret = DKOFBLOGIN_APP_SECRET;
      }
      $this->graphapi = new DKOFBLogin_Graph_API($this->app_id, $this->app_secret);
    }

    // override redirect values with constants
    if (defined('DKOFBLOGIN_LOGIN_REDIRECT')) {
      $this->options['login_redirect'] = DKOFBLOGIN_LOGIN_REDIRECT;
    }
    if (defined('DKOFBLOGIN_REGISTER_REDIRECT')) {
      $this->options['register_redirect'] = DKOFBLOGIN_REGISTER_REDIRECT;
    }
    if (defined('DKOFBLOGIN_DENIAL_REDIRECT')) {
      $this->options['denial_redirect'] = DKOFBLOGIN_DENIAL_REDIRECT;
    }
  } // setup_options()

  /**
   * Check to see if the plugin was updated, do maintenance
   */
  private function check_update() {
    $has_installed_version = array_key_exists('installed_version', $this->options);
    $is_latest_version = $has_installed_version ? $this->options['installed_version'] == $this->defaults['installed_version'] : false;
    if (!$has_installed_version || !$is_latest_version) {
      $this->options = array_merge($this->defaults, $this->options);
      update_option(DKOFBLOGIN_OPTIONS_KEY, $this->options);

      // run the activation hook again in case any rewrites were added
      add_action('init', array(&$this, 'activate'));
    }
  } // check_update()

  /**
   * callback for activation_hook
   * also called whenever the plugin is updated
   */
  public function activate() {
    add_rewrite_rule(DKOFBLOGIN_ENDPOINT_SLUG.'/?',     '?' . DKOFBLOGIN_SLUG . '_link=1',    'top');
    add_rewrite_rule(DKOFBLOGIN_DEAUTHORIZE_SLUG.'/?',  '?' . DKOFBLOGIN_SLUG . '_unlink=1',  'top');
    flush_rewrite_rules(true);
  } // activate()

  /**
   * callback for deactivation_hook
   */
  public function deactivate() {
    flush_rewrite_rules(true);
  } // deactivate()

  /**
   * run during WP initialize - no output plz
   */
  public function initialize() {
    if (!is_admin()) {
      add_filter('language_attributes', array(&$this, 'html_fb_language_attributes'));
      add_action('wp_footer',           array(&$this, 'html_fbjs'), 20);
    }

    add_shortcode('dkofblogin-button',   array(&$this, 'html_shortcode_login_button'));

    if (!isset($_SESSION)) {
      session_start();
    }
    // @TODO check_nonce
    if (!empty($_REQUEST[DKOFBLOGIN_SLUG.'_link'])) {
      $this->fb_link();
    }
    elseif (!empty($_REQUEST[DKOFBLOGIN_SLUG.'_unlink'])) {
      $this->fb_unlink();
    }
  } // initialize()

  /**
   * do facebook login
   */
  private function fb_link() {
    // user source filters
    add_filter(DKOFBLOGIN_SLUG.'_find_user', array(&$this, 'get_user_by_fbid'),     10);
    add_filter(DKOFBLOGIN_SLUG.'_find_user', array(&$this, 'get_user_by_fbemail'),  10);

    // user creation filters
    add_filter(DKOFBLOGIN_SLUG.'_generate_username', array(&$this, 'generate_username'), 10, 2);
    add_filter(DKOFBLOGIN_SLUG.'_generate_user', array(&$this, 'generate_user_from_fbdata'), 10);
    add_filter(DKOFBLOGIN_SLUG.'_email_message', array(&$this, 'replace_email_tokens'), 10, 2);

    add_action(DKOFBLOGIN_SLUG.'_user_found',       array(&$this, 'login_via_fbmeta'),   10, 4);
    add_action(DKOFBLOGIN_SLUG.'_user_not_found',   array(&$this, 'associate_user_fbmeta'), 10, 2);
    add_action(DKOFBLOGIN_SLUG.'_user_not_found',   array(&$this, 'register_new_user'),     10, 0);
    add_action(DKOFBLOGIN_SLUG.'_user_registered',  array(&$this, 'email_after_register'),  10, 4);

    require_once dirname( __FILE__ ) . '/fb_link.php';
  } // fb_link()

  /**
   * @return string comma-delimited list of fb permissions
   */
  public function permissions_list() {
    if (array_key_exists('permissions', $this->options)) {
      return implode(',', $this->options['permissions']);
    }
    return '';
  } // permissions_list()


  /**
   * login_link
   *
   * @return string href for facebook login link
   */
  public function login_link() {
    return $this->graphapi->login_link();
  }

  /**
   * Shows a login button
   * @return string html link for facebook login
   */
  public function html_shortcode_login_button($atts) {
    if (is_user_logged_in()) {
      return '';
    }
    return '<a class="dko-fblogin-loginout" href="' . $this->login_link() . '">Login through facebook</a>';
  } // login_button_shortcode()


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
      'appId'       => $this->app_id,
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
   * login_via_fbmeta
   *
   * callback for user_found hook: update token, login, redirect to profile
   *
   * @param int     $user_id
   * @param object  $fbdata        json decoded facebook user data
   * @param string  $access_token  access token for this fb user
   * @param object  $userdata      WP_User to login as
   * @return void
   */
  public function login_via_fbmeta($user_id, $fb_data, $access_token, $userdata) {
    $this->setfbmeta($user_id, $fb_data->id, $access_token);
    $this->login($user_id);

    do_action(DKOFBLOGIN_SLUG.'_after_login'); // in case you want to do a custom redirect
    $this->redirect($this->options['login_redirect'], admin_url('profile.php'));
    exit; // just in case
  } // login_via_fbmeta()

  /**
   * associate_user_fbmeta
   *
   * Callback for user_not_found action hook #1
   * Check if user logged in, if so, associate fbdata and access token.
   *
   * @return void
   */
  public function associate_user_fbmeta() {
    $user_id = get_current_user_id();
    if (!$user_id) { return; }
    $this->setfbmeta($user_id, $this->fb_data->id, $this->get_access_token());

    do_action(DKOFBLOGIN_SLUG.'_after_associate'); // in case you want to do a custom redirect
    $this->redirect($this->options['login_redirect'], admin_url('profile.php'));
    exit; // just in case
  } // associate_user_fbmeta()

  /**
   * Default callback (priority 10) for dkofblogin_generate_user filter
   * Remove this filter if you want to generate userdata on your own
   *
   * @param array $userdata extra userdata, $generated takes precedence
   * @return array user data formatted for wp_insert_user()
   */
  public function generate_user_from_fbdata($userdata = array()) {
    if (!is_object($this->fb_data)) {
      return $userdata;
    }

    if (property_exists($this->fb_data, 'username')) {
      $username = $this->fb_data->username;
    }
    else { // use email as username if no fb username
      $username = strstr($this->fb_data->email, '@', true);
    }

    // hook into this filter if you need to check usernames from other sources
    $generated['user_login'] = apply_filters(
      DKOFBLOGIN_SLUG.'_generate_username',
      $username
    );
    $generated['user_pass']   = wp_generate_password();
    $generated['user_email']  = $this->fb_data->email;
    $generated['first_name']  = $this->fb_data->first_name;
    $generated['last_name']   = $this->fb_data->last_name;
    return array_merge($generated, $userdata);
  } // generate_user_from_fbdata()

  /**
   * register_new_user
   *
   * Inserts a new user into the WordPress users table
   * Runs hooks
   * Logs user in
   * Redirects user to registration page to continue filling out profile
   *
   * @param array $userdata data for new user to register
   * @return void
   */
  public function register_new_user($userdata = array()) {
    $userdata = apply_filters(
      DKOFBLOGIN_SLUG.'_generate_user',
      $userdata
    );

    $user_id = wp_insert_user($userdata);
    $this->setfbmeta($user_id, $this->fb_data->id, $this->get_access_token());

    do_action(
      DKOFBLOGIN_SLUG.'_user_registered',
      $user_id,
      $this->fb_data,
      $this->get_access_token(),
      $userdata
    );

    // login user and redirect
    $this->login($user_id); // log in if subscriber

    do_action(DKOFBLOGIN_SLUG.'_after_register');
    $this->redirect($this->options['register_redirect'], admin_url('profile.php'));
  } // register_new_user()

  /**
   * Default callback for dkofblogin_user_registered action
   *
   * @param object $fbdata
   * @param string $access_token
   * @param array $userdata
   */
  public function email_after_register($user_id, $fbdata, $access_token, $userdata) {
    // email user with non-fb login details
    $message = $this->options['email_body'];
    $message = apply_filters(DKOFBLOGIN_SLUG.'_email_message', $message, $userdata);
    $headers = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . ">\r\n";
    $headers = apply_filters(DKOFBLOGIN_SLUG.'_email_headers', $headers);

    // generic registration email filter
    add_filter('wp_mail_content_type', function () { return 'text/html'; });
    wp_mail(
      $userdata['user_email'],
      'Your account on ' . get_bloginfo('name'),
      $message,
      $headers
    );
  } // email_after_register()

  /**
   * replace_email_tokens
   *
   * @param string $message
   * @param object $fbdata
   * @param string $access_token
   * @param array $userdata
   * @return string
   */
  public function replace_email_tokens($message, $userdata) {
    $search   = array('{site_url}',   '{site_name}',        '{first_name}',           '{last_name}',          '{username}',             '{password}',           '{email}');
    $replace  = array(home_url(),     get_bloginfo('name'), $userdata['first_name'],  $userdata['last_name'], $userdata['user_login'],  $userdata['user_pass'], $userdata['user_email']);
    return str_replace($search, $replace, $message);
  } // replace_email_tokens()

  /**
   * by using the update_user_meta we ensure only one FB acct per WP user
   *
   * @param int     $user_id      WordPress user id
   * @param string  $fbid         Facebook user id
   * @param string  $access_token Facebook access token
   */
  private function setfbmeta($user_id = 0, $fbid = '', $access_token = '') {
    if (!$user_id) {
      // @TODO wp_die($msg, $title, $args=array())
      throw new Exception('setfbmeta: need user id');
    }
    update_user_meta($user_id, DKOFBLOGIN_USERMETA_KEY_FBID, $fbid);
    update_user_meta($user_id, DKOFBLOGIN_USERMETA_KEY_TOKEN, $access_token);
  } // setfbmeta()

  /**
   * log a user in to wordpress only if a subscriber
   *
   * @param int $user_id ID of user to login as
   */
  private function login($user_id = 0) {
    if (!$user_id) {
      // @TODO wp_die($msg, $title, $args=array())
      throw new Exception('login: Invalid user_id');
    }

    // only log in if user is exclusively a subscriber
    // i.e., no auto-login admins
    $this->current_user_data = get_userdata($user_id);
    $total_roles = count($this->current_user_data->roles);
    $is_subscriber = $total_roles ? $this->current_user_data->roles[0] == 'subscriber' : false;
    if ($total_roles > 1 || !$is_subscriber) {
      return;
    }

    // log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
    do_action('wp_login', $this->current_user_data->user_login, $this->current_user_data);
  } // login()

  /**
   * redirect to $location or $default with $status
   * @param string  $location  URL to redirect to
   * @param string  $default   URL to redirect to if $location is blank
   * @param int     $status    HTTP status code to return
   */
  public function redirect($location = '', $default = '', $status = 302) {
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
  } // redirect()

  /**
   * If already have a user, don't bother with this hook.
   * Gets a WordPress user based on provided facebook id.
   *
   * @param object $userdata WP_User if already found or null
   * @return object WP_User object or false
   */
  public function get_user_by_fbid($user) {
    if (!$this->fb_data || !$this->fb_data->id) {
      throw new Exception('get_user_by_fbid: missing fbdata');
    }
    if ($user) { return $user; }
    $user_query = new WP_User_Query(array(
      'meta_key'      => DKOFBLOGIN_USERMETA_KEY_FBID,
      'meta_compare'  => '=',
      'meta_value'    => $this->fb_data->id
    ));
    if ($user_query->total_users) {
      return $user_query->results[0];
    }
    return false;
  } // get_user_by_fbdata()

  /**
   * If already have a user, don't bother with this hook.
   *
   * @param object $userdata WP_User if already found or null
   * @return object WordPress User object or false
   */
  public function get_user_by_fbemail($user) {
    if ($user) {
      return $user;
    }
    return get_user_by('email', $this->fb_data->email);
  }

  /**
   * Callback for DKOFBLOGIN_SLUG.'_generate_username' filter.
   * Checks if $username is taken, if so add a number. Recurse until not taken.
   *
   * @TODO this could take a while, consider another method
   *
   * @param string $username  what username to try
   * @param string $prefix    some number to append to the end of the username
   * @return string some valid untaken WordPress username
   */
  public function generate_username($username = '', $prefix = '') {
    if (!$username) {
      // @TODO wp_die($msg, $title, $args=array())
      throw new Exception('generate_username: username not specified');
    }
    // use WordPress' sanitization. We NEED to sanitize here because we compare
    // the attempted username to existing usernames, which are all sanitized.
    // So doesn't matter that wp_insert_user sanitizes again.
    $username = sanitize_user($username, true);
    $attempt = $username . $prefix;

    $is_taken = username_exists($attempt);
    // hook into this filter if you need to check another source for valid
    // usernames, return boolean $is_taken
    $is_taken = apply_filters(
      DKOFBLOGIN_SLUG.'_username_taken',
      $is_taken, $attempt
    );

    // this whole function/filter recurses until this is met
    if (!$is_taken) { return $attempt; }

    if (!$prefix) { $prefix = 0; }
    return $this->generate_username($username, $prefix + 1);
  } // generate_username()

  /**
   * get_access_token
   * checks already loaded if user id given
   *
   * @param int $user_id which user's token to get
   * @return string access token or false
   */
  public function get_access_token($user_id = 0) {
    static $cached_access_tokens = array();
    if ($user_id) {
      if (isset($cached_access_token[$user_id])) {
        return $cached_access_token[$user_id];
      }
      $cached_access_token[$user_id] = get_the_author_meta(DKOFBLOGIN_USERMETA_KEY_TOKEN, $user_id);
    }
    return $this->graphapi->get_access_token();
  } // get_access_token()

  /**
   * clear user fb meta and then redirect user to profile page (as if they just logged in)
   * @return void
   */
  private function fb_unlink() {
    if (is_user_logged_in()) {
      $user_id = get_current_user_id();
      $this->setfbmeta($user_id, '', '');
    }

    do_action(DKOFBLOGIN_SLUG.'_after_unlink');
    $this->redirect($this->options['login_redirect'], admin_url('profile.php'));
  } // fb_unlink()

} // end of class
endif;
