<?php
/**
 * dko-fblogin-settings.php
 * make sure WordPress is loaded before requiring this file!
 */

define('DKOFBLOGIN_SLUG',           'dkofblogin');
define('DKOFBLOGIN_ENDPOINT_SLUG',  'dko-fblogin-endpoint');
define('DKOFBLOGIN_ENDPOINT',       site_url('/' . DKOFBLOGIN_ENDPOINT_SLUG));

if (!defined('SERVER_ENVIRONMENT') || SERVER_ENVIRONMENT == 'PROD') {
  $dko_fblogin_http_settings = array(
    CURLOPT_SSL_VERIFYHOST => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSLVERSION => 3
  );
}
else { // local or dev
  $dko_fblogin_http_settings = array(
    CURLOPT_SSL_VERIFYHOST  => false,
    CURLOPT_SSL_VERIFYPEER  => false,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_SSLVERSION      => 3,
    CURLOPT_VERBOSE         => 1
  );
}

// comes from here: https://developers.facebook.com/docs/authentication/permissions/
$dko_fblogin_permissions = array(
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
