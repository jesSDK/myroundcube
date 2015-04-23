<?php
# 
# This file is part of MyRoundcube "archivefolder" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Previous authors Andre Rodier, Thomas Bruederli
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
#
class archivefolder_core extends rcube_plugin
{
  public $task = 'mail|settings';
  
  private $done = false;
  
  function init()
  {
    $this->add_texts('localization/');
    
    $this->register_action('plugin.archive', array($this, 'request_action'));
    
    $rcmail = rcube::get_instance();
    
    if ($rcmail->task == 'mail' && ($rcmail->action == '' || $rcmail->action == 'show')
      && ($archive_folder = $rcmail->config->get('archive_mbox'))) {
      $this->add_hook('render_mailboxlist', array($this, 'render_mailboxlist'));
      if($rcmail->config->get('archive_show_button', true)){
        $this->add_button(
        array(
            'type' => 'link',
            'label' => 'buttontitle',
            'command' => 'plugin.archive',
            'class' => 'button buttonPas archive disabled',
            'classact' => 'button archive',
            'width' => 32,
            'height' => 32,
            'title' => 'buttontitle',
            'domain' => $this->ID,
        ),
        'toolbar');
      }
      
      $rcmail->output->set_env('archive_folder', $archive_folder);
      if($rcmail->config->get('skin', 'larry') == 'classic'){
        $this->include_stylesheet('skins/classic/archivefolder.css');
      }
    }
    else if ($rcmail->task == 'settings') {
      $dont_override = $rcmail->config->get('dont_override', array());
      if (!in_array('archive_mbox', $dont_override)) {
        $this->add_hook('preferences_sections_list', array($this, 'archivefoldersection'));
        $this->add_hook('preferences_list', array($this, 'prefs_table'));
        $this->add_hook('preferences_save', array($this, 'save_prefs'));
      }
    }
  }
  
  function archivefoldersection($args)
  {
    $skin = rcmail::get_instance()->config->get('skin');
    if($skin != 'larry'){
      $args['list']['folderslink']['id'] = 'folderslink';
      $args['list']['folderslink']['section'] = $this->gettext('folders');
    }
    return $args;
  }

  function render_mailboxlist($p)
  {
    if($this->done){
      return $p;
    }
    
    $this->done = true;
    
    $rcmail = rcmail::get_instance();

    $rcmail->output->add_header(html::tag('script', array('type' => 'text/javascript', 'src' => 'plugins/libgpl/archivefolder/archivefolder.js')));

    if ($rcmail->task == 'mail' && ($rcmail->action == '' || $rcmail->action == 'show') && ($archive_folder = $rcmail->config->get('archive_mbox', false))) {   
      $rcmail->output->set_env('archive_folder', $archive_folder);
      // add archive folder to the list of default mailboxes
      if (($default_folders = $rcmail->config->get('default_folders')) && !in_array($archive_folder, $default_folders)) {
        $default_folders[] = $archive_folder;
        $rcmail->config->set('default_folders', $default_folders);
      }
    }

    // set localized name for the configured archive folder  
    if ($archive_folder) {  
      if (isset($p['list'][$archive_folder])) {
        $p['list'][$archive_folder]['name'] = $this->gettext('archivefolder');
        $af = $p['list'][$archive_folder];
        unset($p['list'][$archive_folder]);
        $fldrs = array();
        $i = -1;
        foreach($p['list'] as $key => $val){
          $i++;
          if($i == 1){
            $fldrs[$archive_folder] = $af;
          }
          $fldrs[$key] = $val;
        }
        $p['list'] = $fldrs;
      }
      else // search in subfolders  
        $this->_mod_folder_name($p['list'], $archive_folder, $this->gettext('archivefolder'));  
    }  
    return $p;
  }
  
  function _mod_folder_name(&$list, $folder, $new_name)  
  {  
    foreach ($list as $idx => $item) {  
      if ($item['id'] == $folder) {  
        $list[$idx]['name'] = $new_name;  
        return true;  
      } else if (!empty($item['folders']))  
        if ($this->_mod_folder_name($list[$idx]['folders'], $folder, $new_name))  
          return true;  
    }  
    return false;  
  }  

  function request_action()
  {
    $uids = get_input_value('_uid', RCUBE_INPUT_POST);
    $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
    $rcmail = rcmail::get_instance();
    
    // There is no "Archive flags", but I left this line in case it may be useful
    // $rcmail->imap->set_flag($uids, 'ARCHIVE');
    if (($archive_mbox = $rcmail->config->get('archive_mbox')) && $mbox != $archive_mbox) {
      $rcmail->output->command('move_messages', $archive_mbox);
      $rcmail->output->show_message('archivefolder.archived', 'confirmation');
    }
    
    $rcmail->output->send();
  }

  function prefs_table($args)
  {
    if ($args['section'] == 'folders') {
      
      $rcmail = rcmail::get_instance();
      $select = rcmail_mailbox_select(array('noselection' => '---', 'realnames' => true, 'maxlength' => 30));
      
      $args['blocks']['main']['options']['archive_mbox']['title'] = Q($this->gettext('archivefolder'));
      $args['blocks']['main']['options']['archive_mbox']['content'] = $select->show($rcmail->config->get('archive_mbox'), array('name' => "_archive_mbox"));
    }
    if ($args['section'] == 'folderslink') {
      $args['blocks']['main']['options']['folderslink']['title']    = $this->gettext('folders') . " ::: " . $_SESSION['username'];
      $args['blocks']['main']['options']['folderslink']['content']  = "<script type='text/javascript'>\n";
      $args['blocks']['main']['options']['folderslink']['content'] .= "  parent.location.href='./?_task=settings&_action=folders'\n";
      $args['blocks']['main']['options']['folderslink']['content'] .= "</script>\n";
    }
    return $args;
  }

  function save_prefs($args)
  {
    if ($args['section'] == 'folders') {  
      $args['prefs']['archive_mbox'] = get_input_value('_archive_mbox', RCUBE_INPUT_POST);  
      return $args;  
    }
  }

}
?>