<?php
/**
 * html rendered by callback for WordPress' profile edit page
 */
if ($data && !property_exists($data, 'error')) {
  $identifier = $data->id;
  if (property_exists($data, 'username')) {
    $identifier = $data->username;
  }
}
?>
<h3><?php _e('Facebook', DKOFBLOGIN_SLUG); ?></h3>
<table class="form-table">
  <tr>
    <th>Associated Facebook account</th>
    <td><?php
      if (!$data || property_exists($data, 'error')):
        echo do_shortcode('[dko-fblink-button]');
      else:
        ?>
        <a id="dkofblogin-associated-profile" href="https://graph.facebook.com/<?php echo $identifier; ?>" target="_blank">
          <img src="https://graph.facebook.com/<?php echo $identifier; ?>/picture"
            alt="<?php echo $identifier; ?>" />
          <span><?php echo $data->name; ?> (<?php echo $identifier; ?>)</span>
        </a>
        <a id="dko-fblogin-deauthorize-link" target="_blank" href="https://www.facebook.com/settings?tab=applications">Click here to go to the App Settings page on facebook if you want to deauthorize this app.</a>
        <?php
      endif;
    ?></td>
  </tr>
</table>
