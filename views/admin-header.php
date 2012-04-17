<?php
/* HTML rendered by callback
 */
?>
<h4>Set up your deauthorize callback</h4>
<p>
  Set up a facebook application to get a app key and app secret if you don't
  already have one. Then, set up the app's deauthorize callback (App Settings ->
  Advanced) to the one below (don't edit this URL).
</p>
<table class="form-table">
  <th>
    <label for="dkofblogin-deauthorize-url">Deauthorize Callback:</label>
  </th>
  <td>
    <input id="dkofblogin-deauthorize-url" type="text" value="<?php echo home_url('/dko-fblogin-deauthorize'); ?>" size="64" onclick="this.select();" /> (copy this, don't edit)
  </td>
</table>

<h4>Give this plugin access to the facebook API</h4>
<p>Get this stuff from your facebook app's settings page and fill it in here.</p>
