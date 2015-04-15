<?php
# 
# This file is part of MyRoundcube "pwstrength" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class pwstrength extends rcube_plugin
{
  public $noajax = true;
  
  /* unified plugin properties */
  static private $plugin = 'pwstrength';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?pwstrength" target="_blank">Documentation</a>';
  static private $version = '1.0.2';
  static private $date = '26-02-2015';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3'
  );
  static private $prefs = array(
  );
  static private $config_dist = null;

  function init(){
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
    $config = array();
    if(is_string(self::$config_dist)){
      if(is_file($file = INSTALL_PATH . 'plugins/' . self::$plugin . '/' . self::$config_dist))
        include $file;
      else
        write_log('errors', self::$plugin . ': ' . self::$config_dist . ' is missing!');
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
    $rcmail = rcmail::get_instance();
    if($p['template'] == 'register.register' || $rcmail->action == 'plugin.password'){
      $rcmail->output->set_env('pwstrength_required', $rcmail->config->get('pwstrength', 50));
      $this->add_texts('localization/');
      $rcmail->output->add_label(
        'pwstrength.pwstrength',
        'pwstrength.passwordweak',
        'pwstrength.continue'
      );
      if($p['template'] == 'register.register'){
        $rcmail->output->set_env('pwstrength_fieldname', '_pass');
        $rcmail->output->set_env('pwstrength_fieldname_confirm', '_confirm_pass');
      }
      else{
        $rcmail->output->set_env('pwstrength_fieldname', '_newpasswd');
        $rcmail->output->set_env('pwstrength_fieldname_confirm', '_confpasswd');
        $rcmail->output->add_script('$("input[name=\'_newpasswd\']").attr(\'data-display\', \'pwstrengthDisplay\');$("<tr><td class=\'title\'></td><td><span id=\'pwstrengthDisplay\' class=\'title\'></span><input type=\hidden\ value=\'0\' name=\'_pwstrength\'></td></tr>").insertAfter("tr:last");', 'foot');
      }
      $this->include_script('pwstrength.js');
    }
    else if($p['template'] == 'hmail_password.hmail_password'){
      $rcmail = rcmail::get_instance();
      $rcmail->output->set_env('pwstrength_required', $rcmail->config->get('pwstrength', 50));
      $this->add_texts('localization/');
      $rcmail->output->add_label(
        'pwstrength.pwstrength',
        'pwstrength.passwordweak',
        'pwstrength.continue'
      );
      $rcmail->output->set_env('pwstrength_fieldname', '_newpasswd');
      $rcmail->output->set_env('pwstrength_fieldname_confirm', '_confpasswd');
      $this->include_script('pwstrength.js');
    }
    return $p;
  }
}
?>