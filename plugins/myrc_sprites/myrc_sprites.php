<?php
# 
# This file is part of MyRoundcube "myrc_sprites" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class myrc_sprites extends rcube_plugin
{
  /* unified plugin properties */
  static private $plugin = 'myrc_sprites';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?myrc_sprite" target="_blank">Documentation</a>';
  static private $version = '1.1.4';
  static private $date = '13-04-2015';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3'
  );
  
  private $images = array(
    'myrc_sprites'       => 'png',
    'myrc_loading'       => 'gif',
    'myrc_loading_samll' => 'gif',
    'myrc_ajax_loading'  => 'gif',
  );

  function init(){
    /* Pre-condidition check */
    if(!class_exists('rcube')){
      return;
    }

    $skin = rcube::get_instance()->config->get('skin', 'larry');
    if(file_exists(INSTALL_PATH . 'plugins/myrc_sprites/skins/' . $skin . '/myrc_sprites.css')){
      $this->include_stylesheet('skins/' . $skin . '/myrc_sprites.css');
    }
    $this->add_hook('render_page', array($this, 'render_page'));
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
  
  function render_page($p){
    $rcmail = rcube::get_instance();
    if($p['template'] == 'login'){
      $skin = $rcmail->config->get('skin', 'larry');
      $rcmail->output->add_script('/***************************************************/', 'foot');
      $rcmail->output->add_script('/* MyRoundcube myrc_sprites plugin images pre-load */', 'foot');
      $rcmail->output->add_script('/***************************************************/', 'foot');
      foreach($this->images as $name => $type){
        if(file_exists(INSTALL_PATH . 'plugins/myrc_sprites/skins/' . $skin . '/images/' . $name . '.' . $type)){
          $rcmail->output->add_script('var ' . $name . ' = new Image(); ' . $name . '.src = "./plugins/myrc_sprites/skins/' . $skin . '/images/' . $name . '.' . $type . '";', 'foot');
        }
      }
      $rcmail->output->add_script('/***************************************************/', 'foot');
    }
  }
}
?>