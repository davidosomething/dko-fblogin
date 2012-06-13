<?php

/**
 * provide helper function to render the button
 */
function dkofblogin_button() {
  echo do_shortcode('[dko-fblogin-button]');
}

function dkofblogin_link() {
  echo $dkofblogin->login_link();
}
