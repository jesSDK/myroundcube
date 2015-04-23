<?php
/**
* @website https://github.com/corbosman/message_highlight/blob/master/message_highlight.php
* @author Cor Bosman (cor@roundcu.be)
*/
class message_highlight_core extends rcube_plugin
{
  public $task = 'mail|settings';
  private $rcmail;

  public function init()
  {
    $rcmail = rcmail::get_instance();
    
    if(!in_array('global_config', $rcmail->config->get('plugins'))){
      $this->load_config();
    }
    
    $skin = $rcmail->config->get('skin', 'classic');
    if(($rcmail->task == 'mail' || $rcmail->task == 'settings') && $rcmail->action != 'compose'){
      if($rcmail->output->type == 'html'){
        $rcmail->output->add_header(html::tag('script', array('type' => 'text/javascript', 'src' => 'plugins/libgpl/message_highlight/message_highlight.js')));
      }
    }
    if($rcmail->task == 'mail'){
      $this->include_stylesheet('skins/' . $skin . '/message_highlight.css');
      $this->add_hook('storage_init', array($this, 'storage_init'));
      $this->add_hook('messages_list', array($this, 'mh_highlight'));
    }
    else{
      $this->include_stylesheet('skins/' . $skin . '/message_highlight_settings.css');
      $this->add_texts('localization/', array('deleteconfirm','notsaved'));
      $this->add_hook('preferences_list', array($this, 'mh_preferences'));
      $this->add_hook('preferences_save', array($this, 'mh_save'));
      $this->add_hook('preferences_sections_list',array($this, 'mh_preferences_section'));
      $this->register_action('plugin.mh_add_row', array($this, 'mh_add_row'));
    }
  }
  
  function storage_init($p)
  {
    $p['fetch_headers'] .= trim($p['fetch_headers']. ' ' . 'CC');
    return($p);
  }
  
  // add color information for all messages
  function mh_highlight($p)
  {
    $rcmail = rcmail::get_instance();
    $this->prefs = $rcmail->config->get('message_highlight', array());

    // dont loop over all messages if we dont have any highlights or no msgs
    if(!count($this->prefs) or !isset($p['messages']) or !is_array($p['messages'])) return $p;

    // loop over all messages and add highlight color to each message
    foreach($p['messages'] as $message) {
      if(($color = $this->mh_find_match($message)) !== false ) {
        $message->list_flags['extra_flags']['plugin_mh_color'] = $color;
      }
    }
    return($p);
  }

  // find a match for this message
  function mh_find_match($message) {
    foreach($this->prefs as $p) {
      if(stristr($message->$p['header'], $p['input'])) {
        // backwards compatibility
        if(substr($p['color'], 0, 1) == '#')
          $ret = $p['color'];
        else
          $ret = '#' . $p['color'];
        return($ret);
      }
    }
    return false;
  }

  // user preferences
  function mh_preferences($args) {
    if(!get_input_value('_framed', RCUBE_INPUT_GPC) && $args['section'] == 'mh_preferences'){
      $args['blocks'][$args['section']]['options'] = array(
        'title'   => '',
        'content' => html::tag('div', array('id' => 'pm_dummy'), '')
      );
      return $args;
    }
    if($args['section'] == 'mh_preferences') {
      $this->add_texts('localization/', false);
      $rcmail = rcmail::get_instance();
      $args['blocks']['mh_preferences'] =  array(
        'options' => array(),
        'name'    => Q($this->gettext('mh_title'))
        );

      $i = 1;
      $prefs = $rcmail->config->get('message_highlight', array());
      
      if($rcmail->output->type == 'html'){
        $rcmail->output->add_header(html::tag('script', array('type' => 'text/javascript', 'src' => 'plugins/libgpl/jscolor/jscolor.js')));
      }
      
      if($rcmail->config->get('message_highlight_remove_hex_string', false)){
        $this->include_script('jscolor.js');
      }
      
      foreach($prefs as $p) {
        $args['blocks']['mh_preferences']['options'][$i++] = array(
          'title'   => $this->mh_get_form_row($p['header'], $p['input'], $p['color'], true),
          'content' => ''
          );
      }

      // no rows yet, add 1 empty row
      if($i == 1) {
        $args['blocks']['mh_preferences']['options'][$i] = array(
          'title'   => $this->mh_get_form_row(),
          'content' => ''
          );
      }
    } 

    return($args);
  }

  function mh_add_row() {
    $rcmail = rcmail::get_instance();
    $rcmail->output->command('plugin.mh_receive_row', array('row' => $this->mh_get_form_row()));
  }

  // create a form row
  function mh_get_form_row($header = 'from', $input = '', $color = 'FFFFFF', $delete = false) {

    // header select box
    $header_select = new html_select(array('name' => '_mh_header[]'));
    $header_select->add(Q($this->gettext('subject')), 'subject');
    $header_select->add(Q($this->gettext('from')), 'from');
    $header_select->add(Q($this->gettext('to')), 'to');
    $header_select->add(Q($this->gettext('cc')), 'cc');

    // input field  
    $input = new html_inputfield(array('name' => '_mh_input[]', 'class' => 'rcmfd_mh_input', 'type' => 'text', 'autocomplete' => 'off', 'value' => $input));

    // color box
    $color = html::tag('input', array('id' => uniqid() ,'name' => '_mh_color[]' ,'type' => 'text' ,'text' => 'hidden', 'class' => 'mh_input color', 'maxlength' => 7, 'size' => 7, 'value' => $color));

    // delete button
    $button = html::tag('input', array('class' => 'button mainaction mh_delete', 'type' => 'button', 'value' => $this->gettext('mh_delete'), 'title' => $this->gettext('mh_delete_description')));

    // add button
    $add_button = html::tag('input', array('class' => 'button mainaction mh_add', 'type' => 'button', 'value' => $this->gettext('mh_add'), 'title' => $this->gettext('mh_add_description')));

    $content =  $header_select->show($header) . ' ' .
      html::span('mh_matches', Q($this->gettext('mh_matches'))) . '&nbsp;' .
      $input->show() . 
      //html::span('mh_color', Q($this->gettext('mh_color'))) . 
      '&nbsp;' . $color . 
      '&nbsp;' . $button . $add_button;

    return($content);
  }

  // add a section to the preferences tab
  function mh_preferences_section($args) {
    $this->add_texts('localization/', false);
    $args['list']['mh_preferences'] = array(
      'id'      => 'mh_preferences',
      'section' => Q($this->gettext('mh_title'))
      );
    return($args);
  }

  // save preferences
  function mh_save($args) {
    if($args['section'] != 'mh_preferences') return;
    
    $rcmail = rcmail::get_instance();

    $header  = get_input_value('_mh_header', RCUBE_INPUT_POST);
    $input   = get_input_value('_mh_input', RCUBE_INPUT_POST);
    $color   = get_input_value('_mh_color', RCUBE_INPUT_POST);


    for($i=0; $i < count($header); $i++) {
      if(!in_array($header[$i], array('subject', 'from', 'to', 'cc'))) {
        $rcmail->output->show_message('message_highlight.headererror', 'error');
        return;
      }
      if(!preg_match('/^[0-9a-fA-F]{2,6}$/', $color[$i])) {
        $rcmail->output->show_message('message_highlight.invalidcolor', 'error');
        return;
      }
      if($input[$i] == '') {
        continue;
      }
      $prefs[] = array('header' => $header[$i], 'input' => $input[$i], 'color' => $color[$i]);
    }

    $args['prefs']['message_highlight'] = $prefs;
    return($args);
  }
}
?>