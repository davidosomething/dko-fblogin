<div class="wrap" id="dkofblogin">
  <?php screen_icon(); ?>
  <h2><?php _e('DKO FB Login'); ?></h2>
  <?php if ( !empty($this->updated) ) { ?>
    <div id="message" class="updated">
      <p><?php _e('Settings updated.'); ?></p>
    </div>
  <?php } ?>

  <h3><?php _e('Settings') ?></h3>
  <form method="post" action="">
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row"><?php _e( 'Facebook API' ); ?></th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span><?php _e( 'Facebook API' ); ?></span></legend>

              <p>
                <label for="api-id">API ID</label>
                <input type="text" name="api-id" id="api-id" value="<?php echo esc_attr('') ?>" />
              </p>

              <p>
                <label for="api-secret">API Secret</label>
                <input type="text" name="api-secret" id="api-secret" value="<?php echo esc_attr('') ?>" />
              </p>
            </fieldset>
          </td>
        </tr>
      </tbody>
    </table>

    <?php wp_nonce_field('update-settings'); ?>
    <?php submit_button( null, 'primary', 'save-settings' ); ?>
  </form>

</div><!-- /#dkofblogin -->
