<?php
/**
 * Admin class for DKO FB Login
 * Adds things to the WordPress admin interface (settings page and profile
 * fields). Doesn't do any API stuff!
 */

if (!class_exists('DKOFBLogin')) {
  exit;
}

if (!class_exists('DKOFBLogin_Admin')):
class DKOFBLogin_Admin extends DKOFBLogin
{
  // comes from here: https://developers.facebook.com/docs/authentication/permissions/
  private $available_permissions = array( 
    'user_about_me',
    'user_activities',
    'user_birthday',
    'user_checkins',
    'user_education_history',
    'user_events',
    'user_groups',
    'user_hometown',
    'user_interests',
    'user_likes',
    'user_location',
    'user_notes',
    'user_photos',
    'user_questions',
    'user_relationships',
    'user_relationship_details',
    'user_religion_politics',
    'user_status',
    'user_videos',
    'user_website',
    'user_work_history',
    'email',
    'read_friendlists',
    'read_insights',
    'read_mailbox',
    'read_requests',
    'read_stream',
    'xmpp_login',
    'ads_management',
    'create_event',
    'manage_friendlists',
    'manage_notifications',
    'user_online_presence',
    'friends_online_presence',
    'publish_checkins',
    'publish_stream',
    'rsvp_event',
    'publish_actions'
  );

  public function __construct($plugin_file) {
    parent::__construct($plugin_file); // run DKOFBLogin's construct
    add_action('admin_menu',          array(&$this, 'admin_menu'));
    add_action('admin_init',          array(&$this, 'admin_init'));
    add_action('admin_print_styles',  array(&$this, 'admin_print_styles'));
    add_action('show_user_profile',   array(&$this, 'user_profile_fields'), 10);
    add_action('edit_user_profile',   array(&$this, 'user_profile_fields'), 10);
  }

  /**
   * create admin menu item
   */
  public function admin_menu() {
    // admin options page
    add_options_page(
      __(DKOFBLOGIN_PLUGIN_NAME . ' Options'),
      __(DKOFBLOGIN_PLUGIN_NAME),
      'manage_options',
      DKOFBLOGIN_SLUG,
      array(&$this, 'admin_page')
    );
  }

  /**
   * Add css to admin menu page
   */
  public function admin_print_styles() {
    wp_enqueue_style(
      DKOFBLOGIN_SLUG . '-admin',
      plugin_dir_url(__FILE__). 'css/admin.css'
    );
  } // admin_print_styles()


  /**
   * callback function for add_options_page(),
   * include html for admin options page
   */
  public function admin_page() {
    echo $this->render('admin');
  }

  /**
   * Unlink all meta data if admin requested
   * set up options and populate admin menu
   */
  public function admin_init() {
    $this->confirm_unlink_all();

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
    $this->add_settings_textfield($section_slug, 'Denial Redirect', array(
      'after' => 'Specify a relative URL to go to if the user rejects authorization of the app. Defaults to debugging error message!'
    ));
    $this->add_settings_textfield($section_slug, 'Login Redirect', array(
      'after' => 'Specify a relative URL to go to after logging in (e.g., user profile\'s page). Defaults to ' . admin_url('profile.php') . ' when blank. Use %current_page% to stay on the current page (and let your own backend handle redirection).'
    ));
    $this->add_settings_textfield($section_slug, 'Register Redirect', array(
      'after' => 'Specify a relative URL to go to after logging in as a new facebook user (e.g., a registration page to capture additional data). Defaults to ' . admin_url('profile.php') . ' when blank. Use %current_page% to stay on the current page (and let your own backend handle redirection).'
    ));

    $section_slug = DKOFBLOGIN_SLUG.'_email';
    add_settings_section(
      $section_slug, 'Registration email confirmation',
      array(&$this, 'html_section_header_email'),
      DKOFBLOGIN_SLUG
    );
    add_settings_field(
      'register_email', 'Email Body', array(&$this, 'html_field_email_body'),
      DKOFBLOGIN_SLUG, $section_slug, array('field' => 'email_body')
    );

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
      DKOFBLOGIN_OPTIONS_KEY,       // name of option group
      DKOFBLOGIN_OPTIONS_KEY,       // add to this key in the group
      array(&$this, 'sanitize_api_field')
    );
  } // admin_init()

  public function add_settings_textfield($section_slug, $field_name, $args = array()) {
    $field_slug = strtolower(str_replace(' ', '_', $field_name));
    $callback_args = array_merge(array('field' => $field_slug), $args);
    add_settings_field(
      $field_slug, $field_name, array(&$this, 'html_textfield'),
      DKOFBLOGIN_SLUG, $section_slug, $callback_args
    );
  }

  public function html_section_header_api($args)      { echo $this->render('admin-header'); }
  public function html_section_header_destroy($args)  { echo $this->render('admin-header-destroy'); }
  public function html_section_header_email($args)    { echo $this->render('admin-header-email'); }

  /* @TODO: can make this portable, move to framework */
  public function html_textfield($args) {
    $field_id = DKOFBLOGIN_SLUG . '-' . $args['field'];
    $field_name = DKOFBLOGIN_OPTIONS_KEY . '[' . $args['field'] . ']';
    $field_value = isset($this->options[$args['field']]) ? $this->options[$args['field']] : '';
    if (array_key_exists('pre', $args)) {
      echo $args['pre'];
    }
    echo '<input id="', $field_id, '" type="text" name="', $field_name, '" value="', $field_value, '" size="40"';
    $is_disabled = false;
    if ( ($args['field'] == 'app_id' && defined('DKOFBLOGIN_APP_ID'))
      || ($args['field'] == 'app_secret' && defined('DKOFBLOGIN_APP_SECRET'))
      || ($args['field'] == 'denial_redirect' && defined('DKOFBLOGIN_DENIAL_REDIRECT'))
      || ($args['field'] == 'login_redirect' && defined('DKOFBLOGIN_LOGIN_REDIRECT'))
      || ($args['field'] == 'register_redirect' && defined('DKOFBLOGIN_REGISTER_REDIRECT'))
    ) {
      echo ' disabled="disabled"';
      $is_disabled = true;
    }
    echo ' />';
    if (array_key_exists('after', $args)) {
      echo ' <span class="description">', $args['after'], '</span>';
    }
    if ($is_disabled) {
      echo ' <strong class="description">Overridden: DKOFBLOGIN_' . strtoupper($args['field']) . ' defined in wp-config.</strong>';
    }
  }

  /**
   * html for the registration email confirmation textarea
   */
  public function html_field_email_body($args) {
    $field_id = DKOFBLOGIN_SLUG . '-' . $args['field'];
    $field_name = DKOFBLOGIN_OPTIONS_KEY . '[' . $args['field'] . ']';
    $email_body = array_key_exists('email_body', $this->options) ? $this->options['email_body'] : '';
    echo '<textarea name="', $fieldname, '" rows="4" cols="32" id="dkofblogin_email_body">', $email_body, '</textarea>';

    $dummy_userdata = array();
    $dummy_userdata['first_name'] = 'FIRST_NAME';
    $dummy_userdata['last_name']  = 'LAST_NAME';
    $dummy_userdata['user_login'] = 'USERNAME';
    $dummy_userdata['user_pass']  = 'PASSWORD';
    $dummy_userdata['user_email'] = 'EMAIL@DOMAIN.COM';
    $preview = $this->replace_email_tokens($email_body, $dummy_userdata);
    echo '<h4 id="dkofblogin_email_preview_header">Preview</h4>';
    echo '<div id="dkofblogin_email_preview">', $preview, '</div>';
  } // html_field_permissions()

  /**
   * html for the destroy all fb data confirmation checkbox
   */
  public function html_field_confirm_destroy($args) {
    $field_id = DKOFBLOGIN_SLUG . '-' . $args['field'];
    $field_name = DKOFBLOGIN_OPTIONS_KEY . '[' . $args['field'] . ']';
    echo '<label for="', $field_id, '">';
    echo '<input id="', $field_id, '" name="' . $field_name . '" type="checkbox" value="1" />';
    echo ' Confirm destruction of fblogin metadata?</label>';
  } // html_field_permissions()

  /**
   * html for the permissions checkboxes
   */
  public function html_field_permissions($args) {
    $permissions = array_key_exists('permissions', $this->options) ? $this->options['permissions'] : array();
    $field_name = DKOFBLOGIN_OPTIONS_KEY . '[permissions][]';
    echo $this->render('admin-permissions');
    foreach ($this->available_permissions as $p) {
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
   * @param array $input array of values from submitted options page form
   * @return function, returns array of sanitized input
   */
  public function sanitize_api_field($input) {
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
      elseif ($key == 'login_redirect' || $key == 'register_redirect') {
        $output[$key] = esc_url_raw($val);
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
   * show fb info on profile edit page
   * @param object $user WP User data for profile page you're on
   */
  public function user_profile_fields($user) {
    if (get_current_user_id() !== $user->ID) { return; }
    $access_token = get_the_author_meta(DKOFBLOGIN_USERMETA_KEY_TOKEN, $user->ID);
    $fb_data = $access_token ? $this->graphapi->get_object('me', $access_token) : false;
    echo $this->render('admin-profile', $fb_data);
  }

  /**
   * check for confirm_destroy option and unlink all accounts if correct
   */
  private function confirm_unlink_all() {
    if (array_key_exists('confirm_destroy', $this->options) && $this->options['confirm_destroy']) {
      $this->options['confirm_destroy'] = false;
      update_option(DKOFBLOGIN_OPTIONS_KEY, $this->options);
      $this->unlink_all_accounts();
    }
  }

  /**
   * remove all facebook metadata
   */
  private function unlink_all_accounts() {
    global $wpdb;
    $where = array ('1' => '1');
    $result = $wpdb->query("UPDATE $wpdb->usermeta SET "
      . "meta_value='' WHERE meta_key='" . DKOFBLOGIN_USERMETA_KEY_TOKEN . "'");
    $result = $wpdb->query("UPDATE $wpdb->usermeta SET "
      . "meta_value='' WHERE meta_key='" . DKOFBLOGIN_USERMETA_KEY_FBID . "'");
    $this->destroyed = true;
  }

} // class
endif;
