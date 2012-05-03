<?php
/**
 * dko-fblogin/fb_link.php
 * This file is included by plugin.php as a function body.
 */

/* private function fb_link() { */

/* ==|== no haxors ========================================================== */
// just in case
if (@!get_class($this)) {
  exit;
}
if (array_key_exists('error_msg', $_REQUEST)) {
  // @TODO wp_die($msg, $title, $args=array())
  throw new Exception(htmlspecialchars($_REQUEST['error_msg']));
}
if (!array_key_exists('state', $_REQUEST) || !array_key_exists(DKOFBLOGIN_SLUG.'_state', $_SESSION)) {
  // @TODO wp_die($msg, $title, $args=array())
  throw new Exception('Missing state, maybe CSRF');
}
if ($_REQUEST['state'] != $_SESSION[DKOFBLOGIN_SLUG.'_state']) {
  // @TODO wp_die($msg, $title, $args=array())
  throw new Exception('Invalid state, maybe CSRF');
}

/**
 * Get Facebook User Data and try to match with a WP_User
 */
$this->fb_data = $this->graphapi->get_object('me');
$found_user_data = null;
$found_user_data = apply_filters(
  DKOFBLOGIN_SLUG.'_find_user',
  $found_user_data,
  $this->fb_data
);

// the following hooks need to redirect after completion!
if ($found_user_data) { // found associated WordPress user
  do_action( // hooked actions should redirect, ending termination
    DKOFBLOGIN_SLUG.'_user_found',
    $found_user_data->ID,
    $this->fb_data,
    $this->get_access_token(),
    $found_user_data
  );
}
else {
  // FB ID not found in meta data -- hook into this action with priority < 10
  // if you want to authenticate from other sources before associating with an
  // existing user or creating a new user
  do_action( // hooked actions should redirect, ending termination
    DKOFBLOGIN_SLUG.'_user_not_found',
    $this->fb_data,
    $this->get_access_token()
  );
}

// 403 unauthorized, shouldn't ever get to this point
header('location: ' . site_url(), true, 403); exit;

/* } private function fb_link() */
