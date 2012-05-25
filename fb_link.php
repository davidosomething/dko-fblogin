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

$is_invalid_request = false;
if (array_key_exists('error', $_REQUEST)) {
  echo '<h1>Error: ', urldecode(filter_var($_REQUEST['error'], FILTER_SANITIZE_STRING)), '</h1>';
  echo '<h2>Reason: ', urldecode(filter_var($_REQUEST['error_reason'], FILTER_SANITIZE_STRING)), '</h2>';
  echo '<p>', urldecode(filter_var($_REQUEST['error_description'], FILTER_SANITIZE_STRING)), '</p>';
  $is_invalid_request = true;
}
elseif (empty($_REQUEST['state']) || empty($_SESSION[DKOFBLOGIN_SLUG.'_state'])) {
  echo '<h1>Error: Missing state</h1>';
  $is_invalid_request = true;
}
elseif ($_REQUEST['state'] != $_SESSION[DKOFBLOGIN_SLUG.'_state']) {
  echo '<h1>Error: Invalid state</h1>';
  $is_invalid_request = true;
}

if ($is_invalid_request) {
  echo '<p><a href="', $this->graphapi->login_link(), '">Click here to try again, accept the terms this time!</a></p>';
  exit;
}

/**
 * Get Facebook User Data and try to match with a WP_User
 */
$found_user_data = null;

// get a new access token for this user
$new_access_token = $this->graphapi->get_access_token(FALSE);

// couldn't get an access token. (maybe missing code).
// Send user back to facebook to reauthenticate.
if (!$new_access_token) {
  header('location: ' . $this->graphapi->login_link());
  exit;
}

// ok we have an access token, get the user data for the user
$this->fb_data = $this->graphapi->get_object('me', $new_access_token); // tries to get an access token too
if ($this->fb_data) {
  $found_user_data = apply_filters(
    DKOFBLOGIN_SLUG.'_find_user',
    $found_user_data,
    $this->fb_data
  );
}

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
