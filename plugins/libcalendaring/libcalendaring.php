<?php
# 
# This file is part of MyRoundcube "libcalendaring" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2014 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
require_once(INSTALL_PATH . 'plugins/libcalendaring/libcalendaring_core.php');

class libcalendaring extends libcalendaring_core
{
  /* unified plugin properties */
  static private $plugin = 'libcalendaring';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = 'This plugin is a fork of <a href="https://git.kolab.org/roundcubemail-plugins-kolab/tree/plugins/libcalendaring" target="_new">Kolab libcalendaring (core)</a>.<br /><a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?libcalendaring" target="_blank">Documentation</a>';
  static private $version = '1.0';
  static private $date = '10-11-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
  );
  
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