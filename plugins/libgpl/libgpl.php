<?php
# 
# This file is part of MyRoundcube "libgpl" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2014 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class libgpl extends rcube_plugin
{

  /* unified plugin properties */
  static private $plugin = 'libgpl';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?libgpl" target="_blank">Documentation</a>';
  static private $version = '1.0.21';
  static private $date = '19-12-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3',
    'required_plugins' => array(
      'jqueryui' => 'require_plugin',
     ),
  );
  static private $f;

  function init(){
    self::$f = $this;
    $this->require_plugin('jqueryui');
    $this->include_stylesheet($this->local_skin_path() . '/calendar.css');
    $this->include_script('timepicker2/jquery.timepicker.js');
    $this->include_stylesheet($this->local_skin_path() .  '/timepicker2.css');
    $this->add_hook('render_page', array($this, 'render_page'));
    $this->add_hook('send_page', array($this, 'send_page'));
    $this->add_hook('preferences_list', array($this, 'preferences_list'));
    if(!class_exists('MyRCHttp')){
      require_once('http_request/class.http.php');
    }
  }

  static public function include_js($js){
    self::$f->include_script($js);
  }
  
  static public function include_php($php){
    require_once INSTALL_PATH . $php;
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
  
  public function render_page($p){
    $this->include_script('dialogextend/jquery.dialogextend.js');
    $this->include_script('libcalendaring/libcalendaring.js');
    if($this->rc->user->data['username']){
      $this->rc->output->set_env('username', $this->rc->user->data['username']);
    }
    if($p['template'] == 'calendar.calendar' || $p['template'] == 'calendar.print' || $p['template'] == 'tasklist.mainview'){
      $this->include_script('querystring/querystring.js');
      $this->include_script('date/date.js');
      $this->include_stylesheet($this->local_skin_path() . '/jquery.contextMenu.css');
      $this->include_script('contextmenu/jquery.contextMenu.js');
      $this->include_script('contextmenu/jquery.ui.position.js');
    }
    else if($p['template'] == 'sticky_notes.sticky_notes'){
      $this->include_stylesheet('fancybox/jquery.fancybox-1.3.4.css');
      $this->include_script("fancybox/jquery.fancybox-1.3.4.pack.js");
      $this->include_script('date/date.js');
      $this->include_stylesheet($this->local_skin_path() . '/jquery.contextMenu.css');
      $this->include_script('contextmenu/jquery.contextMenu.js');
      $this->include_script('contextmenu/jquery.ui.position.js');
    }
    return $p;
  }
  
  public function send_page($args)
  {
    $args['content'] = preg_replace('/<script type=\"text\/javascript\" src=\"plugins\/libcalendaring\/libcalendaring.js\?s\=[0-9]*\"><\/script>([\r\n\t])/', '', $args['content']);
    return $args;
  }
  
  public function preferences_list($args){
    if($args['section'] == 'calendarsharing'|| $args['section'] == 'addressbooksharing'){
      $rcmail = rcube::get_instance();
      if(!$args['current']){
        $args['blocks']['view']['content'] = true;
        return $args;
      }
      if($dsn = $rcmail->config->get('db_sabredav_dsn')){
        $this->include_script('flashclipboard/flashclipboard_libgpl.js');
      }
    }
    return $args;
  }
}
?>