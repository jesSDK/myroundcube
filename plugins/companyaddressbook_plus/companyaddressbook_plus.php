<?php

/**
 * companyaddressbook_plus
 * - IMAP backend drivers for companyaddressbook plugin
 *
 * @version 1.2 - 02.01.2014
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.com
 */

class companyaddressbook_plus extends rcube_plugin{
  public $task = 'settings';
  public $noframe = true;
  public $noajax = true;
  
  /* unified plugin properties */
  static private $plugin = 'companyaddressbook_plus';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?companyaddressbook_plus" target="_blank">Documentation</a><br />This plugin requires <a href="#companyaddressbook" class="anchorLink">companyaddressbook</a> plugin.<br/>Currently supported drivers:<ol><li>hmail_com (hMailserver COM driver - Roundcube and hMailserver are running on the same Windows server and PHP COM is available)</li><li>hmail_db (hMailserver database driver - PHP COM is not available on Roundcube\'s server and Roundcube\'s server is able to connect to hMailserver database)</li></ol>';
  static private $version = '1.2';
  static private $date = '02-01-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3'
  );
  static private $prefs = array(
  );
  static private $config_dist = null;

  function init(){

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
    $rcmail_config = array();
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
      $ret['config'] = array_merge($rcmail_config, array_flip(self::$prefs));
    else
      $ret['config'] = $rcmail_config;
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