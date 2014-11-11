<?php

/**
 * imap_hooks
 *
 * @version 1.0 - 01.06.2014
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.com
 */

class imap_hooks extends rcube_plugin
{
  public $task = 'mail';
  
  /* unified plugin properties */
  static private $plugin = 'imap_hooks';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a onclick="alert(\'This plugin requires Roundcube Core Patches.\')" href="#pmu_Roundcube_Core_Patches"><font color="red">IMPORTANT</font></a><br /><a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?imap_hooks" target="_blank">Documentation</a>';
  static private $version = '1.0';
  static private $date = '01-06-2014';
  static private $licence = 'All Rights reserved';
  static private $requirements = array(
    'extra' => '<span style="color: #ff0000;">IMPORTANT</span> &#8211;&nbsp;<div style="display: inline">Plugin requires Roundcube core files patches</span></div>',
    'Roundcube' => '1.0',
    'PHP' => '5.3'
  );
  static private $prefs = array(
  );
  static private $config_dist = null;

  function init(){
    $rcmail = rcube::get_instance();
    if($rcmail->task == 'mail'){
      require_once INSTALL_PATH . 'plugins/imap_hooks/rcube_imap_hooks_generic.php';
      require_once INSTALL_PATH . 'plugins/imap_hooks/rcube_imap_hooks.php';
      $rcmail->config->set('storage_driver', 'imap_hooks');
    }
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
}
?>