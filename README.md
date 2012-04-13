DKO FB Login
============

Version 0.1

WordPress plugin that integrates Facebook logins with the WordPress user system.


Functionality
-------------

When someone clicks the button, they will be logged in to their existing account
if they already have an associated Facebook account.

If they don't have an associated FB account, if their FB email matches an
existing WP user's email, they'll be logged in to that account (we depend on
FB's email confirmation mechanism to keep that secure).

If they're missing both FB and WP accounts, a brand new WP account is created
and linked to the FB ID.


Requirements
------------

* WordPress 3.3.1 (may be backwards compatible, but I'm not testing it)


Installation
------------

1. Put this folder into the WordPress plugins folder
2. Activate the plugin

The plugin will create the appropriate tables.


Usage
-----

This plugin provides a shortcode to add the login button anywhere shortcodes
work: ```` [dko-fblogin-button] ````
If you want to use the button outside of a post field (e.g. in your theme) you
can use the helper function: ```` dko_fblogin_button() ````
That function just echoes out ```` do_shortcode('[dko-fblogin-button]') ````


Developer Notes
---------------

### Methods provided

In progress

### Todo

* Convert README into a WordPress AND GitHub compatible syntax


Changelog
---------

* 2011-04-11 - created

