<?php
#
# https://code.google.com/p/jappix4roundcube
#
class jappix4roundcube_core extends rcube_plugin
{
  private $rc;
  
  function init() {
    
    $this->rc = rcube::get_instance();
    
    if($this->rc->task == 'settings'){
      $this->add_hook('preferences_sections_list', array($this, 'preferences_sections_list'));
      $this->add_hook('preferences_list', array($this, 'preferences_list'));
      $this->add_hook('preferences_save', array($this, 'preferences_save'));
      $this->add_hook('password_change', array($this, 'password_change'));
    }
  }
  
  function preferences_sections_list($args){
    if(!$this->rc->config->get('jappix_lock_settings')){
      $args['list']['jabber'] = array(
        'id'      => 'jabber',
        'section' => Q($this->gettext('jappixSection'))
      );
    }
    return($args);
  }

  function preferences_list($args){
    if($args['section'] == 'jabber'){
      $jabber_username = $this->rc->config->get('jabber_username');
      $jabber_domain = $this->rc->config->get('jabber_domain', 'jappix.com');
      $jabber_enc = $this->rc->config->get('jabber_enc');
      
      if((!$jabber_username || !$jabber_enc || !$jabber_domain) && $this->rc->config->get('jappix_register_url')){
        $field_id = 'rcmfd_register';
        $args['blocks']['jabber']['options']['jappix_register'] = array(
          'title' => html::label($field_id, @($this->gettext('requirement'))),
          'content' => html::tag('a', array('href' => $this->rc->config->get('jappix_register_url'), 'target' => '_blank'), $this->gettext('clickhere')) . ' ' . $this->gettext('registeraccount')
        );
      }
      
      $jappix_enabled = $this->rc->config->get('jappix_enabled', 0);
      $field_id = 'rcmfd_enabled';
      $checkbox = new html_checkbox(array('name' => '_jappix_enabled', 'value' => 1, 'id' => $field_id, 'onclick' => "if($(this).prop('checked') == false || $('input[name=\'_jabber_username\']').val() != ''){ $('.mainaction').hide(); document.forms.form.submit(); };"));
      $args['blocks']['jabber']['options']['jappix_enabled'] = array(
        'title' => html::label($field_id, Q($this->gettext('enabled'))),
        'content' => $checkbox->show($jappix_enabled ? 1:0),
      );
      
      $field_id_user = 'rcmfd_username';
      $input_user = new html_inputfield(array('name' => '_jabber_username', 'id' => $field_id_user, 'size' => 25));
      if($jabber_domain == '%d'){
        $jabber_domain = end(explode('@', $this->rc->user->data['username']));
      }
      $field_id_domain = 'rcmfd_domain';
      $input_domain = new html_inputfield(array('name' => '_jabber_domain', 'id' => $field_id_domain, 'size' => 25));
      $args['blocks']['jabber']['options']['jabber_username'] = array(
        'title' => html::label($field_id, Q($this->gettext('jappixUsername'))),
        'content' => $input_user->show($jabber_username) . '&nbsp;@&nbsp;' . $input_domain->show($jabber_domain),
      );
      
      $field_id = 'rcmfd_enc';
      $input = new html_passwordfield(array('name' => '_jabber_password', 'id' => $field_id, 'size' => 25, 'placeholder' => $jabber_enc ? $this->gettext('passwordisset') : $this->gettext('pleaseenterpassword')));
      $args['blocks']['jabber']['options']['jabber_password'] = array(
        'title' => html::label($field_id, Q($this->gettext('jappixPassword'))),
        'content' => $input->show(),
      );

      $jappix_full = $this->rc->config->get('jappix_full', 1);
      $field_id = 'rcmfd_use_full';
      $checkbox = new html_checkbox(array('name' => '_jappix_full', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.$('.button-jappix4roundcube').show(); } else { parent.$('.button-jappix4roundcube').hide(); }; $('.mainaction').hide(); document.forms.form.submit();"));
      $args['blocks']['jabber']['options']['jappix_full'] = array(
        'title' => html::label($field_id, Q($this->gettext('usefulljappix'))),
        'content' => $checkbox->show($jappix_full ? 1:0),
      );

      $jappix_mini = $this->rc->config->get('jappix_mini', 1);
      $field_id = 'rcmfd_use_mini';
      $checkbox = new html_checkbox(array('name' => '_jappix_mini', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.parent.$('.jm_position').show(); } else { parent.parent.$('.jm_position').hide(); }; $('.mainaction').hide(); document.forms.form.submit();"));
      $args['blocks']['jabber']['options']['jappix_mini'] = array(
        'title' => html::label($field_id, Q($this->gettext('useminijappix'))),
        'content' => $checkbox->show($jappix_mini ? 1:0),
      );
      
      $jappix_mini_autologon = $this->rc->config->get('jappix_mini_autologon', 1);
      $field_id = 'rcmfd_use_mini_autologon';
      $checkbox = new html_checkbox(array('name' => '_jappix_mini_autologon', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.parent.$('.jm_pane').trigger('click'); } else { if(parent.parent.JappixMini) parent.parent.JappixMini.disconnect(); }; $('.mainaction').hide(); document.forms.form.submit();"));
      $args['blocks']['jabber']['options']['jappix_mini_autologon'] = array(
        'title' => html::label($field_id, Q($this->gettext('minijappixautologon'))),
        'content' => $checkbox->show($jappix_mini_autologon ? 1:0),
      );
      
      /*
      $field_id = 'rcmfd_use_manager';
      $args['blocks']['jabber']['options']['jabber_manager'] = array(
        'title' => html::label($field_id, Q($this->gettext('manager'))),
        'content' => '<a target=\'_blank\' href=\''.$this->rc->config->get('jappix_url').'/?m=manager\'>'.Q($this->gettext('manager')).'</a>',
      );
      */
    }
    return $args;
  }

  function preferences_save($args){
    if($args['section'] == 'jabber'){
      libgpl::include_php('plugins/libgpl/gibberish/GibberishAES.php');
      $enabled = get_input_value('_jappix_enabled', RCUBE_INPUT_POST);
      $username = trim(get_input_value('_jabber_username', RCUBE_INPUT_POST));
      if(!$username && $enabled){
        $this->rc->output->show_message('jappix4roundcube.usernameempty', 'error');
        $args['abort'] = true;
      }
      if(preg_match('/[@\/\\ ]/', $username)){
        $this->rc->output->show_message('jappix4roundcube.usernameinvalid', 'error');
        $this->rc->output->set_env('jabber_username', $this->rc->config->get('jabber_username', ''));
        $args['abort'] = true;
      }
      else{
        $args['prefs']['jabber_username'] = $username;
        $this->rc->output->set_env('jabber_username', $args['prefs']['jabber_username']);
      }
      $domain = trim(get_input_value('_jabber_domain', RCUBE_INPUT_POST));
      if(!$this->is_valid_domain_name($domain)){
        $this->rc->output->show_message('jappix4roundcube.domaininvalid', 'error');
        $this->rc->output->set_env('jabber_domain', $this->rc->config->get('jabber_domain', 'jappix.com'));
        $args['abort'] = true;
      }
      else{
        $args['prefs']['jabber_domain'] = $domain;
        $this->rc->output->set_env('jabber_domain', $args['prefs']['jabber_domain']);
      }
      $password = trim(get_input_value('_jabber_password', RCUBE_INPUT_POST));
      if(!$password && $this->rc->config->get('jappix_inherit')){
        $password =  $this->rc->decrypt($_SESSION['password']);
      }
      if($password){
        $key = md5($this->rc->user->data['username'] . ':' . $this->rc->decrypt($_SESSION['password']));
        $enc = GibberishAES::enc($password, $key);
        $args['prefs']['jabber_enc'] = $enc;
        $this->rc->output->set_env('jabber_enc', $enc);
      }
      $args['prefs']['jappix_enabled'] = $enabled;
      $args['prefs']['jappix_enabled'] = $args['prefs']['jappix_enabled'] ? 1 : 0;
      if($args['prefs']['jappix_enabled'] == 0){
        $this->rc->session->remove('jappixminiframe');
      }
      $args['prefs']['jappix_full'] = get_input_value('_jappix_full', RCUBE_INPUT_POST);
      $args['prefs']['jappix_full'] = $args['prefs']['jappix_full'] ? 1 : 0;
      $args['prefs']['jappix_mini'] = get_input_value('_jappix_mini', RCUBE_INPUT_POST);
      $args['prefs']['jappix_mini'] = $args['prefs']['jappix_mini'] ? 1 : 0;
      $args['prefs']['jappix_mini_autologon'] = get_input_value('_jappix_mini_autologon', RCUBE_INPUT_POST);
      $args['prefs']['jappix_mini_autologon'] = $args['prefs']['jappix_mini_autologon'] ? 1 : 0;
      $this->rc->output->set_env('jabber_mini',  $args['prefs']['jappix_mini'] ? true : false);
      $this->rc->output->set_env('jabber_autologon', $args['prefs']['jappix_mini_autologon'] ? true : false);
    }
    return $args;
  }
}
?>