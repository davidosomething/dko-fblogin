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

This plugin provides a shortcode to add the login button somewhere: ```` [dko-fblogin-button] ````
You can use the ```` do_shortcode() ```` function to output the button programmatically.


Developer Notes
---------------

### Methods provided

In progress

### Todo

* Convert README into a WordPress AND GitHub compatible syntax


Changelog
---------

* 2011-04-11 - created

