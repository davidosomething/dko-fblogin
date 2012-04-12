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

  <?php if ( !empty($this->updated) ) { ?>
    <div id="message" class="updated">
      <p><?php _e('Settings updated.'); ?></p>
    </div>
  <?php } ?>

  <form method="post" action="options.php">
    <?php
      settings_fields('dkofblogin_options');      // Output nonce, action, and option_page fields for a settings page.
      do_settings_sections('dkofblogin'); // output form
      submit_button();
    ?>
  </form>

</div><!-- /#dkofblogin -->
