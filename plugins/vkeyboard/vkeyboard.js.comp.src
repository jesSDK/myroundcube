$(document).ready(function(){
  $('input:password').each(function(){
    $(this).attr('lang',rcmail.env.vklang);
    $(this).attr('class', 'keyboardInput');
    if($(this).parent().get(0).tagName.toLowerCase() == 'td')
      $(this).parent().attr('nowrap','nowrap');
  });
  if(rcmail.env.skin == 'larry'){
    $('#rcmloginpwd').attr('size', 35);
  }
});