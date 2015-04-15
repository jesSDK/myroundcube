<?php
# 
# This file is part of MyRoundcube "tinymce" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class tinymce extends rcube_plugin
{
  public $task = 'mail|settings';

  /* unified plugin properties */
  static private $plugin = 'tinymce';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/tinymce-plugin" target="_blank">Documentation</a>';
  static private $version = '2.0.11';
  static private $date = '24-02-2015';
  static private $licence = 'All Rights reserved';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3',
  );

  function init(){
    $this->add_hook('html_editor', array($this, 'config'));
  }

  function config($args){
    $rcmail = rcube::get_instance();
    
    if($rcmail->task == 'settings'){
      $env = 'minimal';
    }
    else{
      $env = 'full';
    }

    $config = array(
      'toolbar' => (($rcmail->config->get('enable_spellcheck', true) && $env == 'full') ? ' spellchecker | ' : '') . $rcmail->config->get('tinymce4_' . $env . '_toolbar', ''),
      'plugins' => $rcmail->config->get('tinymce4_' . $env . '_plugins', '') . (($rcmail->config->get('enable_spellcheck', true) && $env == 'full') ? ' spellchecker' : ''),
    );
    
    $etc = $rcmail->config->get('tinymce4_' . $env . '_etc', array());
    foreach($etc as $key => $value){
      $config[$key] = $value;
    }

    $script = sprintf('window.rcmail_editor_settings = %s;', json_encode($config));

    $rcmail->output->add_script($script, 'foot');

    return $args;
  }
  
  static public function about($keys = false){
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
}
