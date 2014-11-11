<?php
/**
 * vkeyboard
 *
 * @version 1.8.5 - 17.02.2014
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.com
 * 
 **/
 
class vkeyboard extends rcube_plugin
{
  public $task = 'login|logout|settings';
  public $noajax = true;
  private $rcmail;
  
  /* unified plugin properties */
  static private $plugin = 'vkeyboard';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/vkeyboard-plugin" target="_blank">Documentation</a>';
  static private $download = 'http://myroundcube.googlecode.com';
  static private $version = '1.8.5';
  static private $date = '17-02-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3'
  );
  static private $prefs = null;
  static private $config_dist = null;
  
  function init()
  {
    $this->rcmail = rcmail::get_instance();
    if($this->rcmail->task == 'settings' || $this->rcmail->task == 'login' || $this->rcmail->task == 'logout'){
      $lang = get_input_value('_lang_sel', RCUBE_INPUT_GET);
      if($lang){
        if(file_exists('./plugins/vkeyboard/localization/' . $lang . '.inc')){
          @include('./plugins/vkeyboard/localization/' . $lang . '.inc');
        }
        $this->rcmail->load_language($lang,$labels);
      }
      $this->add_texts('localization/', true);
      $this->add_hook('render_page', array($this, 'render_page'));
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
      'download' => self::$download,
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
  
  function render_page($args){
      $skin = $this->rcmail->config->get('skin','classic');
      if(!file_exists('plugins/vkeyboard/skins/' . $skin . '/keyboard.png')){
        $skin = 'classic';
      }
      $this->rcmail->output->set_env('vkskin',$skin);
      $this->include_script('vkeyboard.js');
      $this->include_stylesheet('skins/'. $skin . '/keyboard.css');
      $temp = getimagesize(INSTALL_PATH . 'plugins/vkeyboard/skins/' . $skin . '/keyboard.png');
      $this->rcmail->output->set_env('vkwidth',$temp[0]);
      $this->rcmail->output->set_env('vkheight',$temp[1]);
      $this->include_script('keyboard/keyboard.js');
      $this->rcmail->output->add_script("VKI_buildKeyboardInputs();", 'docready');
      $lang = $_SESSION['language'];
      $this->rcmail->output->set_env('vklang',$lang);
    return $args;
  }
}
?>