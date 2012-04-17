<div id="fb-root"></div><script>
window.fbAsyncInit = function() {
  FB.init(<?php echo json_encode($data); ?>);
};
(function(d, s, id){
  var js, fjs = d.getElementsByTagName(s)[0]; if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id; js.async = true;
  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&amp;appId=<?php echo $data['appId']; ?>"
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
