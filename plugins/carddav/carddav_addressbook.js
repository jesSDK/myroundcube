if(window.rcmail){
  rcmail.addEventListener('init', function(evt){
    rcmail.enable_command('plugin.carddav-addressbook-sync', true);
    rcmail.addEventListener('plugin.carddav_addressbook_message', carddav_addressbook_message);
    rcmail.addEventListener('plugin.carddav_addressbook_message_copied', carddav_addressbook_message_copied);
    if(rcmail.env.task == 'addressbook'){
      rcmail.register_command('plugin.carddav-addressbook-sync', function(){
        $('#carddavsyncbut').removeClass('carddavsync').removeClass('myrc_sprites');
        $('#carddavsyncbut').addClass('myrc_loading');
        rcmail.http_post(
          'force/refresh',
          '',
          rcmail.display_message(rcmail.gettext('addressbook_sync_loading', 'carddav'), 'loading')
        );
      }, true);
    }
    if(rcmail.env.action != 'plugin.summary'){
      if(new Date().getTime() - rcmail.env.carddav_last_replication * 1000 > rcmail.env.carddav_sync_interval * 60 * 1000){
        window.setTimeout('rcmail.command("plugin.carddav-addressbook-sync");', 1000);
      }
    }
  });

  function carddav_addressbook_message(response){
    if(!rcmail.env.framed){
      var type = 'confirmation';
      var trigger = false;
      $('.syncwarning').attr('title', '');
      $('.syncwarning').removeClass('syncwarning');
      $('.syncincomplete').attr('title', '');
      $('.syncincomplete').removeClass('syncincomplete');
      $('#carddavsyncbut').addClass('carddavsync').addClass('myrc_sprites');
      $('#carddavsyncbut').removeClass('myrc_loading');
      if(response.failure){
        for(var i in response.failure){
          if(response.incomplete){
            type = 'warning';
            trigger = true;
            $('a[rel="' + response.failure[i] + '"]').addClass('syncincomplete');
            $('a[rel="' + response.failure[i] + '"]').attr('title', new Date().toLocaleString() + ': ' + response.message);
          }
          else{
            type = 'error';
            $('a[rel="' + response.failure[i] + '"]').addClass('syncwarning');
            $('a[rel="' + response.failure[i] + '"]').attr('title', new Date().toLocaleString() + ': ' + response.message);
          }
        }
        if(trigger == true){
          rcmail.command('plugin.carddav-addressbook-sync');
        }
      }
      if(rcmail.env.task == 'addressbook' || (rcmail.env.task != 'addressbook' && type != 'confirmation')){
        rcmail.display_message(response.message, type);
      }
    }
  }
  
  function carddav_addressbook_message_copied(response){
    $('li.selected').children().trigger('click');
    $('#message .notice').remove();
    rcmail.display_message(response.message, 'notice');
  }
}