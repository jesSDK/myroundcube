<?php

/**
 * Save password plugin
 *
 *
 * @version 3.0.2 - 10.08.2014
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.com
 *
 **/
 
/**
 *
 * Usage: http://myroundcube.com
 *
 *        Get the password when user is not logged in:
 *     
 *        $password = savepassword::getpw("myuser@mydomain.com");
 *
 * NOTICE:
 * main.inc.php:
 * $rcmail_config['des_key'] = 'rcmail-!24ByteDESkey*Str';
 *
 * If you change the key after first time usage, saved passwords gets invalid !!!
 * Also make sure to use same key on server rcube installation with access to a common database
 * 
 **/
 
class savepassword extends rcube_plugin
{
    /* unified plugin properties */
    static private $plugin = 'savepassword';
    static private $author = 'myroundcube@mail4us.net';
    static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?savepassword" target="_blank">Documentation</a>';
    static private $version = '3.0.2';
    static private $date = '10-08-2014';
    static private $licence = 'GPL';
    static private $requirements = array(
      'Roundcube' => '1.0',
      'PHP' => '5.3',
      'required_plugins' => array(
        'db_version' => 'require_plugin',
      )
    );
    static private $prefs = null;
    static private $config_dist = null;
    static private $tables = array('users');
    static private $db_version = array(
      'initial'
    );
    
    function init()
    {
      /* DB versioning */
      if(is_dir(INSTALL_PATH . 'plugins/db_version')){
        $this->require_plugin('db_version');
        if(!$load = db_version::exec(self::$plugin, self::$tables, self::$db_version)){
          return;
        }
      }
      $this->add_hook('login_after', array($this, 'savepw'));
      $this->add_hook('password_change', array($this, 'savepw'));
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
        'db_version' => self::$db_version,
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
    
    function savepw($args){
      $rcmail = rcmail::get_instance();
      if($_SESSION['user_id']){ // user has been authenticated successfully
        $query = "UPDATE " . get_table_name('users') . " SET password=? WHERE user_id=?";
        $ret = $rcmail->db->query($query,$_SESSION['password'],$_SESSION['user_id']);
      }
      return $args;
    }
    
    function getpw($username){
      $rcmail = rcmail::get_instance();
      $rcmail->db->query("SELECT * FROM " . get_table_name('users') . " WHERE  username=?", $username);
      $user = $rcmail->db->fetch_assoc();
      if(isset($user['password'])){
        return $rcmail->decrypt($user['password']);
      }
      else{
        return false;
      }
  }
}

?>