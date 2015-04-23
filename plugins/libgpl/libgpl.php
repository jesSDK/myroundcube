<?php
# 
# This file is part of MyRoundcube "libgpl" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class libgpl extends rcube_plugin
{
  private $labels_merged;
  
  /* unified plugin properties */
  static private $plugin = 'libgpl';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?libgpl" target="_blank">Documentation</a>';
  static private $version = '1.1.34';
  static private $date = '21-04-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3',
    'required_plugins' => array(
      'jqueryui' => 'require_plugin',
     ),
  );
  static private $f;

  function init(){
    self::$f = $this;
    $this->add_texts('localization/');
    $this->require_plugin('jqueryui');
    $this->include_stylesheet('qtip/qtip.css');
    $this->include_stylesheet($this->local_skin_path() . '/calendar.css');
    $this->include_script('timepicker2/jquery.timepicker.js');
    $this->include_stylesheet($this->local_skin_path() .  '/timepicker2.css');
    $this->include_script('dialogextend/jquery.dialogextend.js');
    $this->include_script('jquery_migrate/jquery.migrate.js');
    $this->include_script('qtip/qtip.js');
    $this->add_hook('render_page', array($this, 'render_page'));
    $this->add_hook('send_page', array($this, 'send_page'));
    if(!class_exists('MyRCHttp')){
      require_once('http_request/class.http.php');
    }
    if(!$this->labels_merged){
      $this->labels_merged = true;
      $this->_merge_labels(
        array(
          'calendarusername' => 'calendar',
          'attendeeplaceholder' => 'calendar',
          'events' => 'calendar',
          'tasks' => 'calendar',
          'calendar_kolab' => 'calendar',
          'calendar_database' => 'calendar',
          'calendar_caldav' => 'calendar',
          'calendar_ical' => 'calendar',
          'calendar_webcal' => 'calendar',
          'calendar_google_xml' => 'calendar',
          'errorimportingtask' => 'calendar',
          'treat_as_allday' => 'calendar',
          'hours' => 'calendar',
          'movetotasks' => 'calendar',
          'movetocalendar' => 'calendar',
          'emailevent' => 'calendar',
          'movetonotes' => 'calendar',
          'quit' => 'calendar',
          'eventaction' => 'calendar',
          'gooledisabled' => 'calendar',
          'googledisabled_redirect' => 'calendar',
          'allowfreebusy' => 'calendar',
          'freebusy' => 'calendar',
          'sync_interval' => 'calendar',
          'minute_s' => 'calendar',
          'unabletoadddefaultcalendars' => 'calendar',
          'protected' => 'calendar',
          'list' => 'tasklist',
          'editlist' => 'tasklist',
          'tags' => 'tasklist',
          'subscribe' => 'tasklist',
          'is_subtask' => 'tasklist',
          'due' => 'tasklist',
          'taskaction' => 'tasklist',
          'emailtask' => 'tasklist',
          'subscribed' => 'carddav',
        )
      );
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
  
  static public function load_localization($folder, $env, $add_client = false){
    $id = self::$f->ID;
    self::$f->ID = $env;
    self::$f->add_texts($folder, $add_client);
    self::$f->ID = $id;
  }

  static public function include_js($js){
    self::$f->include_script($js);
  }
  
  static public function include_php($php){
    require_once INSTALL_PATH . $php;
  }
  
  static public function codemirror_ui(){
    switch($GLOBALS['codemirror']['mode']){
      case 'PHP':
        rcube::get_instance()->output->add_header('<style type="text/css">.CodeMirror {height: 90%} .CodeMirror-scroll {height: 100%} </style>');
        break;
      case 'SQL':
        rcube::get_instance()->output->add_header('<style type="text/css">.CodeMirror {height: auto} .CodeMirror-scroll {overflow-y: hidden; overflow-x: auto;} </style>');
    }
    self::$f->include_stylesheet('codemirror_ui/lib/CodeMirror-2.3/lib/codemirror.css');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/lib/codemirror.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/lib/util/searchcursor.js');
    switch($GLOBALS['codemirror']['mode']){
      case 'PHP':
        self::PHP($GLOBALS['codemirror']['elem']);
        break;
      case 'SQL':
        self::SQL($GLOBALS['codemirror']['elem']);
    }
    self::$f->include_stylesheet('codemirror_ui/css/codemirror-ui.css');
    self::$f->include_script('codemirror_ui/js/codemirror-ui.js');
  }
  
  static public function PHP($elem){
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/htmlmixed/htmlmixed.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/xml/xml.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/javascript/javascript.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/css/css.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/clike/clike.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/php/php.js');
    rcube::get_instance()->output->add_script('
      var textarea = document.getElementById("' . $elem . '");
      var uiOptions = {
        path : "js/",
        searchMode : "popup",
        mode: "php",
        imagePath : "plugins/libgpl/codemirror_ui/images/silk",
        buttons : ' . $GLOBALS['codemirror']['buttons'] . ',
        saveCallback : ' . $GLOBALS['codemirror']['save'] . '
      }
      var codeMirrorOptions = {
        readOnly: ' . ($GLOBALS['codemirror']['readonly'] ? 'true' : 'false') . ',
        lineNumbers: true,
        matchBrackets: true,
        mode: "application/x-httpd-php",
        indentUnit: 2,
        indentWithTabs: true,
        enterMode: "keep",
        tabMode: "shift",
        tabSize: 2
      }
      var editor = new CodeMirrorUI(textarea, uiOptions, codeMirrorOptions);
    ', 'docready'
    );
  }
  
  static public function SQL($elem){
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/htmlmixed/htmlmixed.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/xml/xml.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/javascript/javascript.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/css/css.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/clike/clike.js');
    self::$f->include_script('codemirror_ui/lib/CodeMirror-2.3/mode/mysql/mysql.js');
    rcube::get_instance()->output->add_script('
      var textarea = document.getElementById("' . $elem . '");
      var uiOptions = {
        path : "js/",
        searchMode : "popup",
        mode: "mysql",
        imagePath : "plugins/libgpl/codemirror_ui/images/silk",
        buttons : ' . $GLOBALS['codemirror']['buttons'] . ',
        saveCallback : ' . $GLOBALS['codemirror']['save'] . '
      }
      var codeMirrorOptions = {
        readOnly: ' . ($GLOBALS['codemirror']['readonly'] ? 'true' : 'false') . ',
        lineNumbers: true,
        matchBrackets: true,
        mode: "text/x-mysql",
        indentUnit: 2,
        indentWithTabs: true,
        enterMode: "keep",
        tabMode: "shift",
        fixedGutter: true,
        tabSize: 2
      }
      var editor = new CodeMirrorUI(textarea, uiOptions, codeMirrorOptions);
    ', 'docready'
    );
  }

  public function render_page($p){
    if($this->rc->user->data['username']){
      $this->rc->output->set_env('username', $this->rc->user->data['username']);
    }
    $this->include_stylesheet($this->local_skin_path() . '/jquery.contextMenu.css');
    $this->include_script('contextmenu/jquery.contextMenu.js');
    $this->include_script('contextmenu/jquery.ui.position.js');
    if($p['template'] == 'calendar.calendar' || $p['template'] == 'calendar.print' || $p['template'] == 'tasklist.mainview'){
      $this->include_script('querystring/querystring.js');
      $this->include_script('date/date.js');
    }
    else if($p['template'] == 'sticky_notes.sticky_notes'){
      $this->include_stylesheet('fancybox/jquery.fancybox-1.3.4.css');
      $this->include_script("fancybox/jquery.fancybox-1.3.4.js");
      $this->include_script('date/date.js');
      $this->include_stylesheet($this->local_skin_path() . '/jquery.contextMenu.css');
      $this->include_script('contextmenu/jquery.contextMenu.js');
      $this->include_script('contextmenu/jquery.ui.position.js');
    }
    else if($p['template'] == 'register.register'){
      $this->include_script('punycode/punycode.js');
    }
    if(class_exists('password_plus')){
      $this->include_script('password/password.js');
    }
    return $p;
  }
  
  public function send_page($args)
  {
    if(class_exists('password_plus')){
      $args['content'] = preg_replace('/<script type=\"text\/javascript\" src=\"plugins\/password\/password.js\?s\=[0-9]*\"><\/script>([\r\n\t])/', '', $args['content']);
      $args['content'] = preg_replace('/<script type=\"text\/javascript\" src=\"plugins\/password\/password.min.js\?s\=[0-9]*\"><\/script>([\r\n\t])/', '', $args['content']);
    }
    return $args;
  }
  
  private function _merge_labels($labels){
    foreach($labels as $label => $env){
      rcube::get_instance()->load_language(null, array(), array($env . '.' . $label => $this->gettext($label)));
    }
  }

}
?>