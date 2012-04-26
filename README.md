DKO FB Login
============

**Original URL**: https://github.com/davidosomething/dko-fblogin

WordPress plugin that integrates Facebook logins with the WordPress user system.

When someone clicks the button, they will be logged in to their existing account
if they already have an associated Facebook account.

If they don't have an associated FB account, if their FB email matches an
existing WP user's email, they'll be logged in to that account (we depend on
FB's email confirmation mechanism to keep that secure).

If they're missing both FB and WP accounts, a brand new WP account is created
and linked to the FB ID.

Hooks are provided to redefine how unique usernames are generated and what to
do after registration or login.

Requirements
------------

* WordPress 3.3+ (may be backwards compatible, but I'm not testing it)
* CURL and OpenSSL installed on the server.
* The plugin depends on URL rewriting, so make sure you have ```mod_rewrite```
and .htaccess is writable by the server.

Installation
------------

1. Put this folder into the WordPress plugins folder
2. Activate the plugin
3. Configure the plugin from Settings > DKO FB Login

Usage
-----

### Shortcodes

``` [dko_fblogin_button] ```

* Creates a login link.

### Methods provided

``` dko_fblogin_button() ``` 

* Wrapper for ``` do_shortcode('[dko_fblogin_button]'); ```

### Action Hooks

``` dkofblogin_user_found ```

* User was found. The default hook logs the user in and redirects (terminating
  the current state). If your hook does not redirect or exit, the default hook
  will still happen.
* param object of fb_data,
* param string facebook access token
* param object of user data for the WP User we found

``` dkofblogin_user_not_found ```

* User was found. The default hook logs the user in and redirects (terminating
  the current state). If your hook does not redirect or exit, the default hook
  will still happen.
* Default hooks: associate_user_fbmeta() and register_new_user(), both redirect
  upon completion.
* param object of fb_data
* param string facebook access token

``` dkofblogin_user_registered ```

* Run after registering a new user and setting the fb meta data for the user.

``` wp_login ```

* Run the WordPress login hooks after logging in a user via facebook. The hook
  tag is provided by WordPress, but is used by this plugin.

### Filter Hooks

``` dkofblogin_find_user ```

* Hook into this filter with higher priority than default if you want to check
  another source for users. I.e., check twitter for that user, then create and
  return a WP User based on that twitter user and you the FB ID will be
  associated with that WP+Twitter user.
* A good practice is to return the current $userdata if it already exists.
* Default filters: get_user_by_fbid(), get_user_by_fbemail()
* param $userdata is an object containing the found user's data.
* return object WordPress user or false if user not found

``` dkofblogin_generate_user ```

* Hook in with higher priority than default if you want to access the default
  generated userdata. You can also just remove the default callback function
  ``` generate_user_from_fbdata() ``` if you have your own method of generating.
* param $userdata WP_User
* return array of user data appropriate for use in ``` wp_insert_user() ```

``` dkofblogin_generate_username ```

* return string a filtered unique username.
* This filter tag is introduced in the ``` dkofblogin_generate_user() ```
  function. Hook into this *early* (high priority) if you want to create a
  username using some other method. Then, when the filter gets to the default
  callback (priority 10), it will already have a unique username and just fall
  through after sanitization.


``` dkofblogin_username_available ```

* return boolean, whether or not the username (provided as an argument) is
available.
* param string $username the username to check
* Hook into this if you need to check multiple sources for username availability.

``` dkofblogin_email_message ```

* return string to email as the message body

``` dkofblogin_email_headers ``` 

* return string of email headers to send

----

Todo
====

* Namespace files
* Handle expired access tokens
* Terminate properly on error
* uninstall.php that deletes options and stored user metadata
* Use wp_remote_get/post and fallback to curl on fail
  * why? Because you can specify SSL version 3 with curl
  * Check for stream wrappers before using curl
* Convert README into a WordPress AND GitHub compatible syntax
