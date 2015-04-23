if(window.rcmail){
  rcmail.addEventListener('listupdate', function(){
    rcmail.message_list.addEventListener('select', function(){
      if(rcmail.message_list.selection.length > 0){
        $('a.markbutton').addClass('enabled');
      }
      else{
        $('a.markbutton').removeClass('enabled');
      }
    });
  });
};