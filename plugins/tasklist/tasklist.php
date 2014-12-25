<?php
# 
# This file is part of MyRoundcube "tasklist" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2014 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
require_once(INSTALL_PATH . 'plugins/tasklist/tasklist_core.php');

class tasklist extends tasklist_core
{
  /* unified plugin properties */
  static private $plugin = 'tasklist';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = 'This plugin is a fork of <a href="https://git.kolab.org/roundcubemail-plugins-kolab/tree/plugins/tasklist" target="_new">Kolab tasklist (core)</a>.<br /><a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?tasklist" target="_blank">Documentation</a>';
  static private $version = '1.0.11';
  static private $date = '13-12-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3 + cURL',
  );
  
  public function init(){
    parent::init();
    $this->rc->config->set('tasklist_driver', 'caldav');
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
  
  /**
   * Startup hook
   */
  public function startup($args)
  {
    parent::startup($args);
    if(class_exists('calendar_plus')){
      $this->register_handler('plugin.taskedit_priority_select', array($this, 'priority_select'));
      $this->add_texts('localization/', $args['task'] == 'tasks' && $args['action'] == 'print');
      if($args['task'] == 'tasks' && $args['action'] != 'save-pref'){
        $this->register_action('print', array($this, 'tasklist_print'));
      }
    }
  }
  
  /**
   * Render main view of the tasklist task
   */
  public function tasklist_view($template = 'tasklist.mainview')
  {
    $this->ui->init();
    $this->ui->init_templates();
    $this->rc->output->set_pagetitle($this->gettext('navtitle'));
    $this->rc->output->add_label(
      'libcalendaring.removerecurringtaskwarning',
      'libcalendaring.deletealltasks',
      'libcalendaring.deletealltaskswithchilds'
    );
    $this->rc->output->send($template);
  }
    
  /**
   * Print tasklist
   */
  public function tasklist_print()
  {
    $this->tasklist_view('calendar_plus.tasksprint');
  }
  
  /**
   * Render HTML form for priority selection
   */
  public function priority_select($attrib = array())
  {
    $append = array(
      0 => '---',
      9 => ' - ' . $this->gettext('lowest'),
      8 => ' - ' . $this->gettext('low'),
      7 => '',
      6 => '',
      5 => ' - ' . $this->gettext('normal'),
      4 => '',
      3 => '',
      2 => ' - ' . $this->gettext('high'),
      1 => ' - ' . $this->gettext('highest'),
    );
    $select = new html_select(array('name' => '_priority', 'id' => 'edit-priority'));
    $select->add($append[0], 0);
    for ($i = 1; $i < 10; $i++) {
      $select->add($i . $append[$i], $i);
    }
    return $select->show();
  }
  
  /**
   * Compare function for task list sorting.
   * Nested tasks need to be sorted to the end.
   */
  protected function task_sort_cmp($a, $b)
  {
    $d = $a['_depth'] - $b['_depth'];
    if (!$d) {
      $d = $b['_hasdate'] - $a['_hasdate'];
    }
    if (!$d) {
      $d = $a['datetime'] - $b['datetime'];
    }
    if (!$d) {
      $a_sort_title = $a['title'] ? strtolower($a['title']) : '';
      $b_sort_title = $b['title'] ? strtolower($b['title']) : '';
      $length = max(strlen($a_sort_title), strlen($b_sort_title));
      while (strlen($a_sort_title) < $length || strlen($b_sort_title) < $length) {
        $a_sort_title .= ' ';
        $b_sort_title .= ' ';
        $a_sort_title = substr($a_sort_title, 0, $length);
        $b_sort_title = substr($b_sort_title, 0, $length);
      }
      $a_sort_startdatetime = $a['startdatetime'] ? $a['startdatetime'] : '9999999999'; 
      while (strlen($a_sort_startdatetime) < 10) {
        $a_sort_startdatetime = '0' . $a_sort_startdatetime;
      }
      $b_sort_startdatetime = $b['startdatetime'] ? $b['startdatetime'] : '9999999999'; 
      while (strlen($b_sort_startdatetime) < 10) {
        $b_sort_startdatetime = '0' . $b_sort_startdatetime;
      }
      $a_sort = $a_sort_startdatetime . $a_sort_title;
      $b_sort = $b_sort_startdatetime . $b_sort_title;
      $d = $a_sort > $b_sort;
    } 
    return $d ? $d : 0;
  }
}
?>