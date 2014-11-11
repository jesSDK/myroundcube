<?php
/**
 * tinymce
 *
 * @version 2.0.10 - 27.07.2014
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.com
 * 
 **/
 
class tinymce extends rcube_plugin
{
  public $task = 'mail|settings';
  public $noajax = true;
  
  /* unified plugin properties */
  static private $plugin = 'tinymce';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/tiny_mce-plugin" target="_blank">Documentation</a>';
  static private $version = '2.0.10';
  static private $date = '27-07-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3',
  );
  static private $prefs = null;
  static private $config_dist = null;
  
  function init(){
    $rcmail = rcmail::get_instance();
    if($rcmail->action == 'compose' || $rcmail->action == 'edit-identity' || $rcmail->action == 'plugin.hmail_signature' || $rcmail->action == 'plugin.hmail_signature-save'){
      if(!in_array('global_config', $rcmail->config->get('plugins'))){
        $this->load_config();
      }
      $this->include_script('editor_init.js');
      $this->include_script('tinymce.js');
      $this->add_hook('render_page', array($this, 'render_page'));
      $this->add_hook('send_page', array($this, 'send_page'));
    }
    if($rcmail->task == 'mail'){
      $this->add_hook('message_outgoing_body', array($this, 'message_outgoing_body'));
    }
  }
  
  function render_page($p){
    $rcmail = rcmail::get_instance();
    if($rcmail->action == 'compose'){
      $rcmail->output->set_env('tinymce_plugins_compose', $rcmail->config->get('tinymce_plugins_compose', "if(typeof config == 'undefined'){config = a};'paste,emotions,nonbreaking,table,searchreplace,visualchars,directionality,tabfocus' + (config.spellcheck ? ',spellchecker' : '')"));
      $rcmail->output->set_env('tinymce_buttons_compose_row1', $rcmail->config->get('tinymce_buttons_compose_row1', "'bold,italic,underline,|,justifyleft,justifycenter,justifyright,justifyfull,|,bullist,numlist,outdent,indent,ltr,rtl,blockquote,|,forecolor,backcolor,fontselect,fontsizeselect'"));
      $rcmail->output->set_env('tinymce_buttons_compose_row2', $rcmail->config->get('tinymce_buttons_compose_row2', "if(typeof config == 'undefined'){config = a};'Jsvk,|,link,unlink,table,|,emotions,charmap,image,media,|,code,search' + (config.spellcheck ? ',spellchecker' : '') + ',undo,redo'"));
    }
    else{
      $rcmail->output->set_env('tinymce_plugins_identity', $rcmail->config->get('tinymce_plugins_identity', "'paste,tabfocus'"));
      $rcmail->output->set_env('tinymce_buttons_identity_row1', $rcmail->config->get('tinymce_buttons_identity_row1', "'bold,italic,underline,strikethrough,justifyleft,justifycenter,justifyright,justifyfull,separator,outdent,indent,charmap,hr,link,unlink,code,forecolor'"));
      $rcmail->output->set_env('tinymce_buttons_identity_row2', $rcmail->config->get('tinymce_buttons_identity_row2', "'fontselect,fontsizeselect'"));
    }
    return $p;
  }
  
  function send_page($p){
    $rcmail = rcmail::get_instance();
    if(rcmail::get_instance()->config->get('tinymce_gzip', false)){
      $p['content'] = str_replace("program/js/tiny_mce/tiny_mce.js", "program/js/tiny_mce/jquery.tinymce.js", $p['content']);
    }
    $p['content'] = str_replace("program/js/tiny_mce/", "plugins/tinymce/tiny_mce/", $p['content']);
    return $p;
  }
  
  function message_outgoing_body($args){
    $mime_message = $args['message'];
    $body = $args['body'];
    $body = preg_replace('/\x00/', '', $body);
    $searchstr = 'plugins/tinymce/tiny_mce/plugins/emotions/img/';
    $offset = 0;
    $included_images = array();
    if(preg_match_all('/ src=[\'"]([^\'"]+)/', $body, $matches, PREG_OFFSET_CAPTURE)){
      foreach ($matches[1] as $m){
        if(preg_match('#'.$searchstr.'(.*)$#', $m[0], $imatches)){
          $image_name = $imatches[1];
          $image_name = preg_replace('/[^a-zA-Z0-9_\.\-]/i', '', $image_name);
          $img_file = slashify(INSTALL_PATH) . $searchstr . $image_name;
          if(!in_array($image_name, $included_images)){
            if(!$mime_message->addHTMLImage($img_file, 'image/gif', '', true, $image_name))
              rcmail::get_instance()->output->show_message("emoticonerror", 'error');
            array_push($included_images, $image_name);
          }
          $body = substr_replace($body, $img_file, $m[1] + $offset, strlen($m[0]));
          $offset += strlen($img_file) - strlen($m[0]);
        }
      }
      $args['body'] = $body;
    }
    return $args;
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