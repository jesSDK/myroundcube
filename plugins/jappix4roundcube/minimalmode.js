function jappix_adjust_iframe(){
  if(window.$ && document.body){
    $('#mainscreen').css('overflow', 'hidden');
    var minmode = rcmail.get_cookie('minimalmode');
    if (parseInt(minmode) || (minmode === null && $(window).height() < 850)) {
      $('.minimal #mainscreen').css('top', '46px');
      $('#hidelogout').css('top', '46px');
    }
    else{
      $('#mainscreen').css('top', '68px');
      $('#hidelogout').css('top', '68px');
    }
  }
}

$(document).ready(function(){
  jappix_adjust_iframe();
});

$(window).resize(function(){
  jappix_adjust_iframe();
});