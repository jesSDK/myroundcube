var mh_cur_row;

$(document).ready(function() {
  if(window.rcmail) {
    $('.color').each(function(){
      $(this).blur(function(){
        mh_notsaved_warning();
      });
    });
    $('.formbuttons').click(function(){
      parent.$('.notice').remove();
    });
    rcmail.addEventListener('plugin.mh_receive_row', mh_receive_row);

    rcmail.addEventListener('insertrow', function(evt) {
      var message = rcmail.env.messages[evt.row.uid];
  
      // check if our color info is present
      if(message.flags && message.flags.plugin_mh_color) {
        $(evt.row.obj).addClass('rcmfd_mh_row')
        evt.row.obj.style.backgroundColor = message.flags.plugin_mh_color;
      }
    });  

  
    $('.mh_delete').on('click', function() {
      mh_delete(this);
    });

    $('.mh_add').on('click', function() {
      mh_add(this);
    });
  }
});

function mh_delete(button) {
  if(confirm(rcmail.get_label('message_highlight.deleteconfirm'))) {
    $(button).closest('tr', '#prefs-details').remove();
    parent.$('.notice').remove();
    document.forms.form.submit();
  }
}

// do an ajax call to get a new row
function mh_add(button) {
  mh_cur_row = $(button).closest('tr', '#prefs-details');
  lock = rcmail.set_busy(true, 'loading');
  rcmail.http_request('plugin.mh_add_row', '', lock);
}

function mh_notsaved_warning() {
  rcmail.display_message(rcmail.get_label('message_highlight.notsaved'),'notice');
}

// ajax return call
function mh_receive_row(data) {
  var row = data.row;
  $(mh_cur_row).after('<tr><td class="title">' + row + '</td><td></tr>');
  jscolor.init();
  if(typeof jscolor_removeHexStrings == 'function'){
    jscolor_removeHexStrings();
  }
  mh_notsaved_warning();
  $('.boxcontent').click(function(){
    mh_notsaved_warning();
  });
  $('.boxcontent').keypress(function(){
    mh_notsaved_warning();
  });
  $('.color').each(function(){
    $(this).blur(function(){
      mh_notsaved_warning();
    });
  });
}
