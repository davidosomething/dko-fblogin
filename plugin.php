<?php
if (!class_exists('DKOWPPluginFramework')):
  require_once dirname(__FILE__) . '/framework/base.php';
endif;

if (!class_exists('DKOFBLogin')):
class DKOFBLogin extends DKOWPPlugin
{
  private $options  = array();
  private $defaults = array(
    'installed_version' => '0',
    'app_id'            => '',
    'app_secret'        => '',
    'confirm_destroy'   => false,
    'permissions'       => array(),
    'login_redirect'    => '',
    'register_redirect' => ''
  );
  protected $destroyed = false;

  private $user_data    = null;
  private $fb_data      = null;

  /**
   * run every time plugin loaded
   */
  public function __construct($plugin_file) {
    parent::__construct($plugin_file);

    register_activation_hook($plugin_file, array(&$this, 'activate'));
    register_deactivation_hook($plugin_file, array(&$this, 'deactivate'));
    // register uninstall, delete options

    $this->setup_options();
    $this->setup_session();
    $this->check_update();

    add_action('init', array(&$this, 'initialize'));
    add_action('init', array(&$this, 'html_channel_file'));
  } // __construct()

  /**
   * generate the options if for some reason they don't exist
   */
  private function setup_options() {
    $this->options = get_option(DKOFBLOGIN_OPTIONS_KEY);
    if ($this->options === false) {
      add_option(DKOFBLOGIN_OPTIONS_KEY, $this->defaults);
    }
  } // setup_options()

  /**
   * start a session and generate a state nonce for FB API
   */
  private function setup_session() {
    if (!isset($_SESSION)) { session_start(); }
    if (empty($_REQUEST['code'])) { // don't generate new session state if have code
      $_SESSION[DKOFBLOGIN_SLUG.'_state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
    }
  } // setup_session()

  /**
   * Check to see if the plugin was updated, do maintenance
   */
  private function check_update() {
    if (!array_key_exists('installed_version', $this->options) || $this->options['installed_version'] !== $this->defaults['installed_version']) {
      $this->options = array_merge($this->defaults, $this->options);
      update_option(DKOFBLOGIN_OPTIONS_KEY, $this->options);
      add_action('init', array(&$this, 'activate')); // run the activation hook again!
    }
  } // check_update()

  /**
   * read the request superglobal to see if a link or unlink action was requested
   */
  function do_endpoints() {
    // @TODO check_nonce
    if (!empty($_REQUEST[DKOFBLOGIN_SLUG.'_link'])) {
      $this->fb_link();
    }

    if (!empty($_REQUEST[DKOFBLOGIN_SLUG.'_unlink'])) {
      $this->fb_unlink();
    }
  } // do_endpoints()

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
  } // permissions_list()

  /**
   * @return string link to login via facebook
   */
  public function login_link() {
    $link_query = array(
      'client_id'     => $this->options['app_id'],
      'redirect_uri'  => DKOFBLOGIN_ENDPOINT_URL,
      'state'         => $_SESSION[DKOFBLOGIN_SLUG.'_state']
    );
    if (array_key_exists('permissions', $this->options)) {
      $link_query['scope'] = implode(',', $this->options['permissions']);
    }

    // build_query does urlencoding.
    $link = 'https://www.facebook.com/dialog/oauth?' . build_query($link_query);
    return $link;
  } // login_link()

  /**
   * Shows a login link
   * @return string html link for facebook login
   */
  public function html_shortcode_login_button($atts) {
    if (is_user_logged_in()) {
      return '';
    }
    return '<a class="dko-fblogin-loginout" href="' . $this->login_link() . '">Login through facebook</a>';
  } // login_button_shortcode()

  /**
   * This is ALWAYS a link to auth, as opposed to the login button which shows
   * the user's profile if already logged in
   */
  public function html_shortcode_link_button($atts) {
    return '<a class="dko-fblogin-login" href="' . $this->login_link() . '">Login to facebook and link this account</a>';
  } // link_button_shortcode()

  /**
   * This is always a link to logout (but not deauthorize) from facebook
   */
  public function html_shortcode_logout_button($atts) {
    return '';
  } // logout_button_shortcode()

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
    add_action(DKOFBLOGIN_SLUG.'_user_found',       array(&$this, 'wp_login_via_fbmeta'));
    add_action(DKOFBLOGIN_SLUG.'_user_not_found',   array(&$this, 'associate_user_fbmeta'));
    add_action(DKOFBLOGIN_SLUG.'_user_not_found',   array(&$this, 'register_new_user'));
    add_action(DKOFBLOGIN_SLUG.'_user_registered',  array(&$this, 'email_after_register'));

    add_filter(DKOFBLOGIN_SLUG.'_username_available', array(&$this, 'username'));
    add_filter(DKOFBLOGIN_SLUG.'_create_username',    array(&$this, 'create_username'));
    add_filter('wp_mail_content_type', function() { return 'text/html'; });

    require_once dirname(__FILE__) . '/fb_link.php';
  } // fb_link()

  /**
   * callback for user_found hook:
   * update token, login, redirect to profile
   */
  public function wp_login_via_fbmeta() {
    if (!$this->user_data || !$this->fb_data || !$this->get_access_token()) { exit; }
    $user_id = $this->user_data->ID;
    $this->setfbmeta($user_id, $this->fb_data->id, $this->get_access_token());
    $this->wp_login($user_id);
    $this->redirect($this->options['login_redirect'], admin_url('profile.php'));
    exit; // just in case
  } // wp_login_via_fbmeta()

  /**
   * Callback for user_not_found hook
   * Check if user logged in, if so, associate fbdata nad access token.
   */
  public function associate_user_fbmeta() {
    $user_id = get_current_user_id();
    if (!$user_id) { return; }
    $this->setfbmeta($user_id, $this->fb_data->id, $this->get_access_token());
    $this->redirect($this->options['login_redirect'], admin_url('profile.php'));
    exit; // just in case
  } // associate_user_fbmeta()

  /**
   * Generates a valid username
   * Inserts a new user into the WordPress users table
   * Runs hooks
   * Logs user in
   * Redirects user to registration page to continue filling out profile
   */
  public function register_new_user() {
    $wp_userdata = array();

    if (property_exists($this->fb_data, 'username')) {
      $username_prefix = $this->fb_data->username;
    }
    else { // use email as username if no fb username
      $username_prefix = strstr($this->fb_data->email, '@', true);
    }

    // hook into this filter if you need to check usernames from any other
    // sources
    $wp_userdata['user_login'] = apply_filters(
      DKOFBLOGIN_SLUG.'create_username',
      $username_prefix
    );
    $wp_userdata['user_pass']   = wp_generate_password();
    $wp_userdata['user_email']  = $this->fb_data->email;
    $wp_userdata['first_name']  = $this->fb_data->first_name;
    $wp_userdata['last_name']   = $this->fb_data->last_name;
    $user_id = wp_insert_user($wp_userdata);
    $this->setfbmeta($user_id, $this->fb_data->id, $this->get_access_token());

    do_action(
      DKOFBLOGIN_SLUG.'_user_registered',
      $wp_userdata
    );

    // login user and redirect
    $this->wp_login($user_id); // log in if subscriber
    $this->redirect($this->options['register_redirect'], admin_url('profile.php'));
  } // register_new_user()

  public function email_after_register($user_args) {
    // email user with non-fb login details
    $message = '<p>Hi ' . $this->fb_data->name . ',</p>';
    $message .= '<p>You logged in via Facebook on ' . bloginfo('name');
    $message .= ' so we created an account for you. Keep this email for reference.</p>';
    $message .= '<p>You can always login via your linked Facebook account or use';
    $message .= ' the following username and password:</p><ul><li>';
    $message .= "username: " . $wp_userdata['user_login'];
    $message .= "</li><li>password: " . $wp_userdata['user_pass'] . '</li></ul>';

    $message = apply_filters(
      DKOFBLOGIN_SLUG.'_email_message',
      $message
    );

    $headers = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . ">\r\n";

    $headers = apply_filters(
      DKOFBLOGIN_SLUG.'_email_headers',
      $headers
    );

    wp_mail(
      $user_args['user_email'],
      'Your account on ' . get_bloginfo('name'),
      $message,
      $headers
    );
  } // email_after_register()

  /**
   * by using the update_user_meta we ensure only one FB acct per WP user
   * @param int     $user_id      WordPress user id
   * @param string  $fbid         Facebook user id
   * @param string  $access_code  Facebook access code
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
   * log a user in to wordpress
   * only logs in subscribers, just in case
   * @param int $user_id  ID of user to login as
   */
  private function wp_login($user_id = 0) {
    if (!$user_id) {
      // @TODO wp_die($msg, $title, $args=array())
      throw new Exception('login: Invalid user_id');
    }

    // make sure user is only a subscriber
    $this->user_data = get_userdata($user_id);

    if (count($this->user_data->roles) > 1 && $this->user_data->roles[0] !== 'subscriber') {
      return;
    }

    // log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    // in case anything is hooked to real login function
    do_action('wp_login', $this->user_data->user_login);
  } // wp_login()

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
   * Gets a WordPress user based on provided facebook id. If the user isn't
   * found by ID, try the user's email address.
   *
   * @param object $fb_data fb data from graph api
   * @return object WordPress User object or false
   */
  public function get_user_by_fbdata() {
    $user_query = new WP_User_Query(array(
      'meta_key'      => DKOFBLOGIN_USERMETA_KEY_FBID,
      'meta_value'    => $this->fb_data->id,
      'meta_compare'  => '='
    ));
    if ($user_query->total_users) {
      return $user_query->results[0];
    }

    return get_user_by('email', $this->fb_data->email);
  } // get_user_by_fbdata()

  /**
   * Callback for DKOFBLOGIN_SLUG.'_create_username' filter.
   * Checks if $username is taken, if so add a number. Recurse until not taken.
   *
   * @TODO this could take a while, consider another method
   *
   * @param string $username  what username to try
   * @param string $prefix    some number to append to the end of the username
   * @return string some valid untaken WordPress username
   */
  public function create_username($username = '', $prefix = '') {
    if (!$username) {
      // @TODO wp_die($msg, $title, $args=array())
      throw new Exception('create_username: username not specified');
    }
    // lazy sanitize username
    $username = preg_replace("/[^0-9a-zA-Z ]/m", '', $username);
    $attempt = $username . $prefix;

    // hook into this filter if you need to check another source for valid
    // usernames, return boolean $is_taken
    $is_taken = apply_filter(DKOFBLOGIN_SLUG.'_username_available', $attempt);

    if (!$is_taken) { // this whole function/filter recurses until this is met
      return $attempt;
    }

    if (!$prefix) {
      $prefix = 0;
    }
    $prefix = $prefix + 1;
    return $this->create_username($username, $prefix);
  } // create_username()

  /**
   * @param string $username to check if exists in database
   * @return object WordPress user if exists, false otherwise
   */
  public function username_available($username) {
    return get_user_by('login', $username);
  } // username_available()

  /**
   * Get access token from API or user meta if logged in
   * if user_id passed use meta otherwise request from api
   * @param int user_id optional user_id to get meta from
   */
  function get_access_token($user_id = 0) {
    static $cached_access_token;

    if ($cached_access_token) {
      return $cached_access_token;
    }

    if ($user_id) {
      $cached_access_token = get_the_author_meta(DKOFBLOGIN_USERMETA_KEY_TOKEN, $user_id);
      return $cached_access_token;
    }

    global $dkofblogin_http_settings;
    $is_configured = get_option(DKOFBLOGIN_SLUG . '_is_configured');
    if (!$is_configured) {
      // @TODO wp_die($msg, $title, $args=array())
      throw new Exception('dko-fblogin is not configured properly');
    }

    // build_query will encode for you
    $token_query = array(
      'client_id'     => $this->options['app_id'],
      'redirect_uri'  => DKOFBLOGIN_ENDPOINT_URL,
      'client_secret' => $this->options['app_secret'],
      'code'          => $_REQUEST['code']
    );
    $token_url = 'https://graph.facebook.com/oauth/access_token?' . build_query($token_query);

    // request the token
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
      // @TODO wp_die($msg, $title, $args=array())
      throw new Exception(curl_error($ch));
    }

    curl_close($ch);
    $cached_access_token = str_replace('access_token=', '', $result);
    return $cached_access_token;
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
    $this->redirect($this->options['login_redirect'], admin_url('profile.php'));
  } // fb_unlink()

} // end of class
endif;
