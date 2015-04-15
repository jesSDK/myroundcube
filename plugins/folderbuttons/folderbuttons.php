<?php
# 
# This file is part of MyRoundcube "folderbuttons" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class folderbuttons extends rcube_plugin
{
  public $task = 'mail|settings';
  
  /* unified plugin properties */
  static private $plugin = 'folderbuttons';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/folderbuttons-plugin" target="_blank">Documentation</a>';
  static private $version = '2.0.1';
  static private $date = '24-02-2015';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3'
  );
  static private $prefs = null;
  static private $config_dist = null;
  
  function init(){
    $this->include_script('folderbuttons.js');
    $this->add_hook('template_container', array($this, 'html_output'));
    $this->add_texts('localization', true);
  }
  
  static public function about($keys = false){
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

    $ret = array(
      'plugin' => self::$plugin,
      'version' => self::$version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'requirements' => $requirements,
    );

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

  function html_output($p) {
    if ($p['name'] == "mailboxoptions") {
      if(rcmail::get_instance()->task == 'mail'){
        $c .= "<li class=' separator_above'><a href='#' class='active folderbuttons' onclick='return expandall()'>" . $this->gettext("expandall") . "</a></li>\r\n";
        $d .= "<li><a href='#' class='active folderbuttons' onclick='return collapseall()'>" .$this->gettext("collapseall") . "</a></li>";
        $p['content'] = $c . $d . $p['content'];
      }
    }
    return $p;
  }
}
?>