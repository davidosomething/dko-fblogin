<?php
/**
 * views/admin.php
 * Options page in WordPress admin
 */
?>
<div class="wrap" id="dkofblogin">
  <?php screen_icon(); ?>
  <h2><?php _e('DKO FB Login'); ?></h2>

  <?php settings_errors(); ?>

  <?php if (!empty($this->updated) || !empty($this->destroyed)): ?>
    <div id="message" class="updated">
      <?php if (!empty($this->updated)): ?>
        <p><?php _e('Settings updated.'); ?></p>
      <?php endif; ?>
      <?php if (!empty($this->destroyed)): ?>
        <p><?php _e('ALL facebook accounts unlinked!'); ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <h3>Usage</h3>
  <ul>
    <li>Use the shortcode <code>[dko-fblogin-button]</code> to show the login button somewhere.</li>
    <li>Use the <code>do_shortcode();</code> function if you want to use it in your theme.</li>
  </ul>

  <form method="post" action="options.php">
    <?php
      settings_fields('dkofblogin_options');  // Output nonce, action, and option_page fields for a settings page.
      do_settings_sections('dkofblogin');     // output form
      submit_button();
    ?>
  </form>

</div><!-- /#dkofblogin -->
