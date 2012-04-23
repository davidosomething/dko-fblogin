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
class DKOFBLogin_Admin extends DKOWPPlugin
{
  protected $options    = '';
  private $menu_hook    = '';

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

  public function __construct() {
    parent::__construct(__FILE__);
    $this->options = get_option(DKOFBLOGIN_OPTIONS_KEY);
    add_action('admin_menu',          array(&$this, 'admin_menu'));
    add_action('admin_init',          array(&$this, 'admin_init'));
    add_action('admin_print_styles',  array(&$this, 'admin_print_styles'));
    add_action('show_user_profile',   array(&$this, 'user_profile_fields'), 10);
    add_action('edit_user_profile',   array(&$this, 'user_profile_fields'), 10);
  }

  /* create admin menu item */
  public function admin_menu() {
    // admin options page
    $this->menu_hook = add_options_page(
      __(DKOFBLOGIN_PLUGIN_NAME . ' Options'),
      __(DKOFBLOGIN_PLUGIN_NAME),
      'manage_options',
      DKOFBLOGIN_SLUG,
      array(&$this, 'admin_page')
    );
  }

  public function admin_print_styles() {
    wp_enqueue_style(DKOFBLOGIN_SLUG . '-admin', plugin_dir_url(__FILE__). 'css/admin.css');
  } // admin_print_styles()


  /* callback function for add_options_page(), include html for admin options page */
  public function admin_page() {
    echo $this->render('admin');
  }

  /* set up options and populate admin menu */
  public function admin_init() {
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
      'after' => 'Specify a URL to go to after logging in (e.g., user profile\'s page). Defaults to ' . admin_url('profile.php') . ' when blank.'
    ));
    $this->add_settings_textfield($section_slug, 'Register Redirect', array(
      'after' => 'Specify a URL to go to after logging in as a new facebook user (e.g., a registration page to capture additional data). Defaults to ' . admin_url('profile.php') . ' when blank.'
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

  /* @TODO: can make this portable, move to framework */
  public function html_section_header_destroy($args) {
    echo $this->render('admin-header-destroy');
  }

  /* @TODO: can make this portable, move to framework */
  public function html_section_header_api($args) {
    echo $this->render('admin-header');
  }

  /* @TODO: can make this portable, move to framework */
  public function html_textfield($args) {
    $field_id = DKOFBLOGIN_SLUG . '-' . $args['field'];
    $field_name = DKOFBLOGIN_OPTIONS_KEY . '[' . $args['field'] . ']';
    $field_value = isset($this->options[$args['field']]) ? $this->options[$args['field']] : '';
    if (array_key_exists('pre', $args)) {
      echo $args['pre'];
    }
    echo '<input id="', $field_id, '" type="text" name="', $field_name, '" value="', $field_value, '" size="40" />';
    if (array_key_exists('after', $args)) {
      echo '<span class="description">', $args['after'], '</span>';
    }
  }

  /**
   * html for the permissions checkboxes
   */
  public function html_field_confirm_destroy($args) {
    $field_id = DKOFBLOGIN_SLUG . '-confirm_destroy';
    $field_name = DKOFBLOGIN_OPTIONS_KEY . '[' . $args['field'] . ']';
    echo '<label class="', DKOFBLOGIN_SLUG, '-confirm_destroy" for="', $field_id, '">';
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
   */
  public function user_profile_fields($user) {
    if (get_current_user_id() !== $user->ID) { return; }
    // @TODO don't assume access_token is valid
    $access_token = get_the_author_meta(DKOFBLOGIN_USERMETA_KEY_TOKEN, $user->ID);
    $fb_data = false;
    if ($access_token) {
      $fb_data = dkofblogin_graphapi($access_token, 'me');
    }
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
