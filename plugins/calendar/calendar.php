<?php
# 
# This file is part of MyRoundcube "calendar" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2014 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
require_once(INSTALL_PATH . 'plugins/calendar/calendar_core.php');

class calendar extends calendar_core
{
  /* unified plugin properties */
  static private $plugin = 'calendar';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = 'This plugin is a fork of <a href="https://git.kolab.org/roundcubemail-plugins-kolab/tree/plugins/calendar" target="_new">Kolab calendar (core)</a> and <a href="https://gitlab.awesome-it.de/kolab/roundcube-plugins/tree/feature_caldav" target="_new">Awesome Information technology CalDAV/iCal drivers implementation</a>.<br /><a href="http://myroundcube.com/myroundcube-plugins/calendar-beta-plugin" target="_blank">Documentation</a>';
  static private $version = '19.0.4';
  static private $date = '19-11-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'extra' => '<span style="color: #ff0000;">IMPORTANT</span> &#8211;&nbsp;<div style="display: inline">Plugin requires Roundcube core files patches</span></div>',
    'Roundcube' => '1.0',
    'PHP' => '5.3 + cURL',
    'required_plugins' => array(
      'settings' => 'require_plugin',
      'db_version' => 'require_plugin',
      'http_request' => 'require_plugin',
      'tasklist' => 'require_plugin',
      'libcalendaring' => 'require_plugin',
      'libgpl'         => 'require_plugin',
    ),
    'recommended_plugins' => array(
      'calendar_plus' => 'require_plugin',
      'google_oauth2' => 'config',
      'savepassword'  => 'config',
    ),
  );
  static private $config_dist = 'config.inc.php.dist';
  static private $prefs = array(
    'hidden_calendars',
    'hidden_tasklists',
    'calendar_caldavs_removed',
  );
  static private $tables = array(
    'calendars',
    'calendars_caldav_props',
    'calendars_ical_props',
    'calendars_google_xml_props',
    'vevent',
    'vevent_caldav_props',
    'vevent_attachments',
    'vevent_ical_props',
    'vtodo',
    'vtodo_caldav_props',
    'vtodo_attachments',
    'itipinvitations',
    'kolab_alarms',
  );
  static private $db_version = array(
    'initial',
    '20141113',
  );
  static private $sqladmin = array('db_dsnw', 'calendars');

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
    $config = self::$prefs;
    if(is_string(self::$config_dist)){
      if(is_file($file = INSTALL_PATH . 'plugins/' . self::$plugin . '/' . self::$config_dist)){
        include $file;
      }
    }
    return array(
      'plugin' => self::$plugin,
      'version' => self::$version,
      'db_version' => self::$db_version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'requirements' => $requirements,
      'sqladmin' => self::$sqladmin,
      'config' => $config,
    );
  }
  
  public function init()
  {
    /* DB versioning */
    if(is_dir(INSTALL_PATH . 'plugins/db_version')){
      $this->require_plugin('db_version');
      if(!$load = db_version::exec(self::$plugin, self::$tables, self::$db_version)){
        return;
      }
    }
    
    parent::init();
    
    /* Calenar plus */
    if(is_dir(INSTALL_PATH . 'plugins/calendar_plus'))
    {
      $this->require_plugin('calendar_plus');
    }

    $this->require_plugin('tasklist');
  }
  
  /**
   * See parent::load_drivers
   */
  public function load_drivers()
  {
    if($this->_drivers == null)
    {
      $this->_drivers = array();

      foreach($this->get_driver_names() as $driver_name)
      {
        $driver_name = trim($driver_name);
        $driver_class = $driver_name . '_driver';
        require_once(INSTALL_PATH . 'plugins/calendar/drivers/calendar_driver.php');
        if (file_exists(INSTALL_PATH . 'plugins/calendar/drivers/' . $driver_name . '/' . $driver_class . '.php')) {
          require_once(INSTALL_PATH . 'plugins/calendar/drivers/' . $driver_name . '/' . $driver_class . '.php');
          $driver = $this->_load_driver($driver_class, $driver_name);
        }
        else if (file_exists(INSTALL_PATH . 'plugins/calendar_plus/calendar_plus.php')) {
          require_once(INSTALL_PATH . 'plugins/calendar_plus/drivers/' . $driver_name . '/' . $driver_class . '.php');
          $driver = $this->_load_driver($driver_class, $driver_name);
        }
        else {
          require_once(INSTALL_PATH . 'plugins/calendar_plus/drivers/database/database_driver.php');
          $driver = $this->_load_driver('database_driver', 'database');
        }
      }
    }
  }
  
  /**
   * Return driver class
   */
  private function _load_driver($driver_class, $driver_name)
  {
    $driver = new $driver_class($this);

    if ($driver->undelete)
      $driver->undelete = $this->rc->config->get('undo_timeout', 0) > 0;

    $this->_drivers[$driver_name] = $driver;
    
    return $driver;
  }
}
?>