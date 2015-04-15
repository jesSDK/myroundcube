<?php
# 
# This file is part of MyRoundcube "imap_threads" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
#
class message_highlight extends rcube_plugin
{
  public $task = 'mail|settings';
  private $rcmail;
  private $prefs;
  static private $plugin = 'message_highlight';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/message_highlight-plugin" target="_blank">Documentation</a>';
  static private $version = '1.1.5';
  static private $date = '23-03-2015';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3',
    'required_plugins' => array(
      'jscolor' => 'require_plugin'
    )
  );
  static private $config = null;

  public function init()
  {
    $rcmail = rcmail::get_instance();
    $skin = $rcmail->config->get('skin', 'classic');
    if(($rcmail->task == 'mail' || $rcmail->task == 'settings') && $rcmail->action != 'compose'){
      $this->include_script('message_highlight.js');
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
  
  static public function about($keys = false)
  {
    $requirements = self::$requirements;
    foreach(array('required_', 'recommended_') as $prefix){
      if(is_array($requirements[$prefix.'plugins'])){
        foreach($requirements[$prefix.'plugins'] as $plugin => $method){
          if(class_exists($plugin) && method_exists($plugin, 'about')){
            /* PHP 5.2.x workaround for $plugin::about() */
            $class = new $plugin(false);
            $requirements[$prefix.'plugins'][$plugin] = array(
              'method' => $method,
              'plugin' => $class->about($keys),
            );
          }
          else{
             $requirements[$prefix.'plugins'][$plugin] = array(
               'method' => $method,
               'plugin' => $plugin,
             );
          }
        }
      }
    }
    return array(
      'plugin' => self::$plugin,
      'version' => self::$version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'requirements' => $requirements,
    );
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
      
      $this->require_plugin('jscolor');
      
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
