<?php
# 
# This file is part of MyRoundcube "sabredav_framework" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class sabredav_framework extends rcube_plugin
{  
  /* unified plugin properties */
  static private $plugin = 'sabredav_framework';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?sabredav_framework" target="_blank">Documentation</a><br />This is a patched distribution of <a href="http://sabre.io" target="_new">SabreDAV</a> Framework 2.1.2.<br /><font color="red">IMPORTANT</font>: If you update this plugin you have to update SabreDAV framework installation too.';
  static private $version = '1.1.2';
  static private $date = '23-03-2015';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3'
  );
  
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
?>