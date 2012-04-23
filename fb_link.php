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

/* ==|== Look for existing user by fb id ==================================== */
if ($this->get_access_token()) {
  $this->fb_data = dkofblogin_graphapi($this->get_access_token(), 'me');
}
if (!$this->fb_data) { // got access token
  // @TODO wp_die($msg, $title, $args=array())
  throw new Exception('Couldn\'t get or parse user data.');
}

$this->user_data = $this->get_user_by_fbdata($this->fb_data);

// found associated WordPress user
if ($this->user_data) {
  do_action(
    DKOFBLOGIN_SLUG.'_user_found',
    $this->fb_data,
    $this->get_access_token(),
    $this->user_data
  );
}

// FB ID not found in meta data -- hook into this action with priority < 10 if
// you want to authenticate from other sources before associating with an
// existing user or creating a new user
do_action(
  DKOFBLOGIN_SLUG.'_user_not_found',
  $this->fb_data,
  $this->get_access_token()
);

// 403 unauthorized, shouldn't ever get to this point
header('location: ' . site_url(), true, 403); exit;

/* } private function fb_link() */
