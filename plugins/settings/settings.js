$(document).ready(function(){
  if(rcmail.env.skin == 'larry'){
    if($('.minmodetoggle').get(0)){
      var minmode = rcmail.get_cookie('minimalmode');
      if(parseInt(minmode) || (minmode === null && $(window).height() < 850)){
        $('#mainscreen').css('top', '55px');
      }
      $(window).resize(function(){
        var minmode = rcmail.get_cookie('minimalmode');
        if(parseInt(minmode) || (minmode === null && $(window).height() < 850)){
          $('#mainscreen').css('top', '55px');
        }
        else{
          $('#mainscreen').css('top', '132px');
        }
      });
    }
  }
  if(parent.location.href != document.location.href){
    if(rcmail.env.skin == 'larry'){
      $('.formbuttons').hide();
    }
    else{
      $('#formfooter').hide();
    }
  }
});