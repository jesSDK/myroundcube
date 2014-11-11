<?php

/**
 * Archive (archivefolder)
 *
 * Sample plugin that adds a new button to the mailbox toolbar
 * To move messages to a special archive folder.
 * Based on Mark As Junk sample plugin.
 *
 * @version 2.9.6 - 09.11.2014
 * @author Andre Rodier, Thomas Bruederli, Roland 'rosali' Liebl
 * @website http://myroundcube.com 
 */

class archivefolder extends rcube_plugin
{
  public $task = 'mail|settings';
  
  private $done = false;
  
  /* unified plugin properties */
  static private $plugin = 'archivefolder';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/archivefolder-plugin" target="_blank">Documentation</a>';
  static private $version = '2.9.6';
  static private $date = '09-11-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3',
  );
  static private $prefs = array('archive_mbox');
  static private $config_dist = 'config.inc.php.dist';

  function init()
  {
    $rcmail = rcmail::get_instance();
    if(!in_array('global_config', $rcmail->config->get('plugins'))){
      $this->load_config();
    }

    $this->add_texts('localization/');
    $this->register_action('plugin.archive', array($this, 'request_action'));

    if ($rcmail->task == 'mail' && ($rcmail->action == '' || $rcmail->action == 'show')
      && ($archive_folder = $rcmail->config->get('archive_mbox'))) {
      $skin_path = $this->local_skin_path();
      
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
      
      // add label for contextmenu
      // find me: do it only if contextmenu is registered
      $rcmail->output->add_label(
        'archivefolder.buttontitle'
      );  

      $rcmail->output->set_env('archive_folder', $archive_folder);
      $rcmail->output->set_env('archive_folder_icon', $this->url($skin_path.'/foldericon.png'));
      
      $this->include_stylesheet($skin_path . '/archivefolder.css');
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
    $config = array();
    if(is_string(self::$config_dist)){
      if(is_file($file = INSTALL_PATH . 'plugins/' . self::$plugin . '/' . self::$config_dist))
        include $file;
      else
        write_log('errors', self::$plugin . ': ' . self::$config_dist . ' is missing!');
    }
    $ret = array(
      'plugin' => self::$plugin,
      'version' => self::$version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'requirements' => $requirements,
    );
    if(is_array(self::$prefs))
      $ret['config'] = array_merge($config, array_flip(self::$prefs));
    else
      $ret['config'] = $config;
    if(is_array($keys)){
      $return = array('plugin' => self::$plugin);
      foreach($keys as $key){
        $return[$key] = $ret[$key];
      }
      return $return;
    }
    else{
      return $ret;
    }
  }
  
  function archivefoldersection($args)
  {
    $skin = rcmail::get_instance()->config->get('skin');
    if($skin != 'larry'){
      $this->add_texts('localization');  
      $args['list']['folderslink']['id'] = 'folderslink';
      $args['list']['folderslink']['section'] = $this->gettext('archivefolder.folders');
    }
    return $args;
  }

  function render_mailboxlist($p)
  {
    if($this->done){
      return $p;
    }
    
    $this->done = true;
    
    $this->include_script('archivefolder.js');

    $rcmail = rcmail::get_instance();

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
        $p['list'][$archive_folder]['name'] = $this->gettext('archivefolder.archivefolder');
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
        $this->_mod_folder_name($p['list'], $archive_folder, $this->gettext('archivefolder.archivefolder'));  
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
    $this->add_texts('localization');
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
      $this->add_texts('localization');
      
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