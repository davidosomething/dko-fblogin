<h3><?php _e('Facebook', DKOFBLOGIN_SLUG); ?></h3>
<table class="form-table">
  <tr>
    <th>Associated Facebook account</th>
    <td>
      <a href="https://graph.facebook.com/<?php echo $data->username; ?>" target="_blank" style="display: inline-block; padding: 1em; border: 1px #999 solid; overflow: hidden;">
        <img src="https://graph.facebook.com/<?php echo $data->username; ?>/picture"
          alt="<?php echo $data->username; ?>" style="float: left; margin-right: 1em;" />
        <p style="float: left; line-height: 50px; margin: 0; white-space: nowrap;"><?php echo $data->name; ?> (<?php echo $data->username; ?>)</p>
      </a>
    </td>
  </tr>
</table>
