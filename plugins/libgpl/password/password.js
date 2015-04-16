/**
 * Password plugin script
 * @version @package_version@
 */

if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {

    // register command handler
    rcmail.register_command('plugin.password-save', function() { 
      var input_curpasswd = rcube_find_object('_curpasswd'),
        input_newpasswd = rcube_find_object('_newpasswd'),
        input_confpasswd = rcube_find_object('_confpasswd');
        input_pwstrength = rcube_find_object('_pwstrength');
        
      if (input_curpasswd && input_curpasswd.value == '') {
          rcmail.display_message(rcmail.gettext('nocurpassword', 'password'), 'error');
          input_curpasswd.focus();
      } else if (input_newpasswd && input_newpasswd.value == '') {
          rcmail.display_message(rcmail.gettext('nopassword', 'password'), 'error');
          input_newpasswd.focus();
      } else if (input_confpasswd && input_confpasswd.value == '') {
          rcmail.display_message(rcmail.gettext('nopassword', 'password'), 'error');
          input_confpasswd.focus();
      } else if (input_newpasswd && input_confpasswd && input_newpasswd.value != input_confpasswd.value) {
          rcmail.display_message(rcmail.gettext('passwordinconsistency', 'password'), 'error');
          input_newpasswd.focus();
      } else if (input_pwstrength.value == 0) {
          rcmail.display_message(rcmail.gettext('passwordweak', 'pwstrength'), 'error');
          input_newpasswd.focus();
      } else {
          rcmail.gui_objects.passform.submit();
      }
    }, true);
  })
}
