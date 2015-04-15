<?php
# 
# This file is part of MyRoundcube "calendar" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 

if(!ini_get('date.timezone')){
  @date_default_timezone_set('UTC');
}

require_once(INSTALL_PATH . 'plugins/calendar/calendar_core.php');

class calendar extends calendar_core
{
  /* unified plugin properties */
  static private $plugin = 'calendar';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = 'This plugin is a fork of <a href="https://git.kolab.org/roundcubemail-plugins-kolab/tree/plugins/calendar" target="_new">Kolab calendar (core)</a> and <a href="https://gitlab.awesome-it.de/kolab/roundcube-plugins/tree/feature_caldav" target="_new">Awesome Information technology CalDAV/iCal drivers implementation</a>.<br /><a href="https://myroundcube.com/myroundcube-plugins/calendar-plugin-19" target="_blank">Documentation</a>';
  static private $version = '21.0.12';
  static private $date = '14-04-2015';
  static private $licence = 'GPL';
  static private $requirements = array(
    'extra' => '<span style="color: #ff0000;">IMPORTANT</span> &#8211;&nbsp;<div style="display: inline">Plugin requires Roundcube core files patches</span></div>',
    'Roundcube' => '1.1',
    'PHP' => '5.3 + cURL',
    'required_plugins' => array(
      'settings' => 'require_plugin',
      'db_version' => 'require_plugin',
      'tasklist' => 'require_plugin',
      'libcalendaring' => 'require_plugin',
      'libgpl'         => 'require_plugin',
      'myrc_sprites'   => 'require_plugin',
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
    '20141122',
    '20141123',
    '20141125',
    '20141205',
    '20141231',
    '20150107',
    '20150128',
    '20150206',
    '20150228',
    '20150319',
    '20150329',
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
    
    $this->add_hook('render_page', array($this, 'render_page'));
    
    /* Calenar plus */
    if(is_dir(INSTALL_PATH . 'plugins/calendar_plus'))
    {
      $this->require_plugin('calendar_plus');
    }
    
    $this->require_plugin('myrc_sprites');

    $this->require_plugin('tasklist');
  }
  
  public function render_page($p)
  {
    if($p['template'] == 'calendar.calendar'){
      $tz = $this->rc->config->get('timezone', 'UTC');
      if($tz == 'auto'){
        $tz = 'UTC';
      }
      $sql = 'SELECT * FROM ' . get_table_name('system') . ' WHERE name=?';
      $result = $this->rc->db->query($sql, 'myrc_calendar_migrate');
      $result = $this->rc->db->fetch_assoc($result);
      if(!is_array($result) || version_compare(self::$version, '21.0', '<')){
        $sql = 'SELECT * FROM ' . get_table_name('calendars') . ' WHERE user_id=?';
        $calendars = array();
        $result = $this->rc->db->query($sql, $this->rc->user->ID);
        while($result && $calendar = $this->rc->db->fetch_assoc($result)){
          $calendars[] = $calendar['calendar_id'];
        }
        $sql = 'UPDATE ' . get_table_name('vevent') . ' SET tzname=? WHERE calendar_id IN (%s) AND tzname is NULL';
        $res = $this->rc->db->query(sprintf($sql, implode(',', $calendars)), $tz);
        $sql = 'INSERT INTO ' . get_table_name('system') . ' (name, value) VALUES (?, ?)';
        $this->rc->db->query($sql, 'myrc_calendar_migrate', self::$version);
      }
      if($count = $this->rc->config->get('calendar_maximal_calendars', false)){
        $sql = 'SELECT COUNT(*) FROM ' . get_table_name('calendars') . ' WHERE user_id=?';
        $res = $this->rc->db->query($sql, $this->rc->user->ID);
        $calendars = $this->rc->db->fetch_assoc($res);
        if($calendars['COUNT(*)'] >= $count){
          $this->rc->output->add_script('$("#calendarcreatemenulink").hide();', 'foot');
        }
      }
      if($this->rc->config->get('calendar_disallow_add', false) || $this->rc->config->get('calendar_protect', false)){
        $this->rc->output->add_script('$("#calendarcreatemenulink").remove();', 'foot');
      }
      if($this->rc->config->get('calendar_hide_ics_url', false)){
        $this->rc->output->add_script('$("#calendaroptionsmenu li").first().next().next().remove();', 'foot');
      }
      if($this->rc->config->get('calendar_protect', false)){
        $this->rc->output->add_script(
          '$("#calendaroptionsmenu li").first().next().remove();' . "\r\n" .
          '$("#calendaroptionsmenu li").first().remove();' . "\r\n" .
          '$("#calendarslist li").unbind("dblclick");' . "\r\n" .
          '$("#calendarslist li input").prop("disabled", true);' . "\r\n", 'docready'
        );
      }
      $sql = 'SELECT calendar_id, unsubscribe FROM ' . get_table_name('calendars') . ' WHERE user_id=?';
      $result = $this->rc->db->query($sql, $this->rc->user->ID);
      while($result && $props = $this->rc->db->fetch_assoc($result)){
        if(is_array($props) && $props['unsubscribe'] == 0){
          $this->rc->output->add_script('$("#rcmlical' . $props['calendar_id'] . ' input[value=\'' . $props['calendar_id'] . '\']").first().prop("disabled", true);', 'foot');
        }
      }
    }
    return $p;
  }
  
  /**
   * See parent::load_drivers
   */
  public function load_drivers()
  {
    if($this->_drivers == null)
    {
      $this->_drivers = array();
      
      require_once(INSTALL_PATH . 'plugins/calendar/drivers/calendar_driver.php');
      
      foreach($this->get_driver_names() as $driver_name)
      {
        $driver_name = trim($driver_name);
        $driver_class = $driver_name . '_driver';

        if (file_exists(INSTALL_PATH . 'plugins/calendar/drivers/' . $driver_name . '/' . $driver_class . '.php')) {
          require_once(INSTALL_PATH . 'plugins/calendar/drivers/' . $driver_name . '/' . $driver_class . '.php');
          $driver = $this->_load_driver($driver_class, $driver_name);
        }
        if (file_exists(INSTALL_PATH . 'plugins/calendar_plus/drivers/' . $driver_name . '/' . $driver_class . '.php')) {
          require_once(INSTALL_PATH . 'plugins/calendar_plus/drivers/' . $driver_name . '/' . $driver_class . '.php');
          $driver = $this->_load_driver($driver_class, $driver_name);
        }
      }
    }
  }
  
  /*
   * Helper method to get configured driver names.
   * @return List of driver names.
   */
  public function get_driver_names()
  {
    $driver_names = $this->rc->config->get('calendar_driver', array());

    if (!is_array($driver_names)) {
      $driver_names = array($driver_names);
    }
    if ($idx = array_search('database', $driver_names)) {
      unset($driver_names[$idx]);
    }
    $driver_names = array_merge(array('database'), $driver_names);
    return $driver_names;
  }
  
  /**
   * Helper function to retrieve the default driver
   *
   * @return mixed Driver object or null if no default driver could be determined.
   */
  public function get_default_driver()
  {
    return $this->get_driver_by_name('database');
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