<?php
# 
# This file is part of MyRoundcube "panner" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (C) 2011, Lazlo Westerhof
# Lazlo Westerhof <roundcube@lazlo.me> 
# Copyright (C) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class planner extends rcube_plugin
{
  public $task = '?(?!login|logout).*';
  public $noframe = true;

  private $rc;
  private $user;
  private $bd;
  private $skin;
  private $max_results = 1000;
  

  /* unified plugin properties */
  static private $plugin = 'planner';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/planner-plugin" target="_blank">Documentation</a>';
  static private $version = '3.0.28';
  static private $date = '26-02-2015';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3',
    'required_plugins' => array(
      'db_version' => 'require_plugin',
      'libgpl' => 'require_plugin',
      'myrc_sprites' => 'require_plugin',
    ),
  );
  static private $sqladmin = array('db_dsnw', 'planner');
  static private $prefs = array('planner_view', 'planner_filter', 'planner_birthdays');
  static private $tables = array('planner');
  static private $db_version = array('initial');

  function init() {
    $this->rc = rcmail::get_instance();
    if($this->rc->action == 'jappix.loadmini'){
      return;
    }
    $this->user = $this->rc->user->ID;
    
    /* DB versioning */
    if(is_dir(INSTALL_PATH . 'plugins/db_version')){
      $this->require_plugin('db_version');
      if(!$load = db_version::exec(self::$plugin, self::$tables, self::$db_version)){
        return;
      }
    }
    // load localization
    $this->add_texts('localization/', true);
    
    // required plugins
    $this->require_plugin('libgpl');

    // register actions
    $this->register_action('plugin.planner', array($this, 'startup'));
    $this->register_action('plugin.planner_new', array($this, 'planner_new'));
    $this->register_action('plugin.planner_edit', array($this, 'planner_edit'));
    $this->register_action('plugin.planner_done', array($this, 'planner_done'));
    $this->register_action('plugin.planner_star', array($this, 'planner_star'));
    $this->register_action('plugin.planner_unstar', array($this, 'planner_unstar'));
    $this->register_action('plugin.planner_delete', array($this, 'planner_delete'));
    $this->register_action('plugin.planner_remove', array($this, 'planner_remove'));
    $this->register_action('plugin.planner_created', array($this, 'planner_created'));
    $this->register_action('plugin.planner_expunge', array($this, 'planner_expunge'));
    $this->register_action('plugin.planner_retrieve', array($this, 'planner_retrieve'));
    $this->register_action('plugin.planner_prefs', array($this, 'planner_prefs'));
    $this->register_action('plugin.planner_prefsbirthdays', array($this, 'planner_prefsbirthdays'));
    $this->register_action('plugin.planner_getprefs', array($this, 'planner_getprefs'));
    $this->register_action('plugin.planner_uninstall', array($this, 'planner_uninstall'));
    
    // set skin and include button stylesheet
    $this->skin = $this->rc->config->get('skin');
    if(!file_exists($this->home . '/skins/' . $this->skin . '/planner.css')) {
      $this->skin = "classic";
    }
    $this->include_stylesheet('skins/' . $this->skin . '/icon.css');
    if(!isset($_GET['_extwin']) && !isset($_GET['_framed'])){
      $this->include_script('move_button.js');
    }
    
    // add planner button to taskbar
    $this->require_plugin('myrc_sprites');
    $this->add_button(array(
      'name'    => 'planner',
      'class'   => 'button-planner',
      'content'   => html::tag('span', array('class' => 'button-inner myrc_sprites'), $this->gettext('planner.planner')),
      'href'    => './?_task=dummy&_action=plugin.planner',
      'id'      => 'planner_button',
      'style'   => 'display: none;'
       ), 'taskbar');
  }
  
  /**
   * About planner
   **/
  static public function about($keys = false)
  {
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
    $ret = array(
      'plugin' => self::$plugin,
      'version' => self::$version,
      'db_version' => self::$db_version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'sqladmin' => self::$sqladmin,
      'requirements' => $requirements,
    );
    if(is_array(self::$prefs))
      $ret['config'] = array_flip(self::$prefs);
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
  
  /**
   * Startup planner, set pagetitle, include javascript and send output.
   */
  function startup() {
    // set pagetitle
    $this->rc->output->set_pagetitle($this->getText('planner'));
    
    // include stylesheets
    $this->include_stylesheet('skins/' . $this->skin . '/planner.css');
    
    // check browser
    $browser = new rcube_browser();
    if($browser->ie){
      if($browser->ver < 9){
        $this->register_handler('plugin.body', array($this, 'planner_incompatible'));
        $this->rc->output->send('plugin');
      }
    }

    // include javascript
    $this->include_script('jquery.sort.js'); // third party
    $this->include_script('planner.class.js');
    $this->include_script('planner.gui.js');
    $this->include_script('date.format.js'); // third party

    // pass date and time formats to the GUI
    $this->rc->output->set_env('rc_date_format', $this->rc->config->get('date_format', 'm/d/Y'));
    $this->rc->output->set_env('rc_time_format', $this->rc->config->get('time_format', 'H:i'));
    $this->rc->output->set_env('planner_items', $this->rc->config->get('planner_view', 'init'));
    $this->rc->output->set_env('planner_filter', $this->rc->config->get('planner_filter', 'all'));
    $this->rc->output->set_env('planner_birthdays', $this->rc->config->get('planner_birthdays', true));
    
    // send output
    $this->rc->output->send('planner.planner');
  }
  
  /**
   * Incompatible browser
   *
   **/
   function planner_incompatible() {
     return html::tag('div',
       array('style' => 'opacity: 0.85; text-align: center; margin-left: auto; margin-right: auto; width: 600px; padding: 8px 10px 8px 46px; background: url(./skins/classic/images/display/icons.png) 6px -97px no-repeat; background-color: #EF9398; border: 1px solid #DC5757;'),
         $this->gettext('incompatiblebrowser')) .
         html::tag('p', null, '&nbsp;') .
         html::tag('center', null, html::tag('iframe', array('src' => 'http://www.browserchoice.eu', 'frameborder' => 0, 'width' => '830px', 'height' => '500px')));
   }
   
   /**
     * Uninstall
     **/
   function planner_uninstall() {
    if (!empty($this->user)) {
      $query = $this->rc->db->query(
        "DELETE FROM " . $this->table('planner') . " WHERE " . $this->q('user_id') . "=?",
        $this->user
      );
    }
    $this->rc->output->command('plugin.plugin_manager_success', '');
  }
  
  /**
   * Save preferences
   */
  function planner_prefs() {
    $view = trim(get_input_value('_v', RCUBE_INPUT_POST));
    $filter = trim(get_input_value('_f', RCUBE_INPUT_POST));
    $a_prefs['planner_view'] = $view;
    $a_prefs['planner_filter'] = $filter;
    $this->rc->user->save_prefs($a_prefs);
  }
  
  /**
   * Save birthdays choice
   */
  function planner_prefsbirthdays() {
    $val = trim(get_input_value('_v', RCUBE_INPUT_POST));
    $a_prefs['planner_birthdays'] = $val;
    $this->rc->user->save_prefs($a_prefs);
    $this->rc->output->command('plugin.planner_birthdays', $val);
  }
  
  /**
   * Get preferences
   */
  function planner_getprefs() {
    $this->rc->output->command('plugin.planner_getprefs',
      array(
        $this->rc->config->get('planner_view', 'all'),
        $this->rc->config->get('planner_filter', 'all'),
        $this->rc->config->get('planner_birthdays', true)
      )
    );
  }
   
  /**
   * Create new planner item
   */
  function planner_new() {
    if (!empty($this->user)) {
      $class = 'ui-draggable';
      $text = trim(get_input_value('_t', RCUBE_INPUT_POST));
      $date = trim(get_input_value('_d', RCUBE_INPUT_POST));
      $starred = get_input_value('_starred', RCUBE_INPUT_POST);
      if(!$starred)
        $starred = 0;
      $done = get_input_value('_done', RCUBE_INPUT_POST);
      if(!$done)
        $done = 0;
      $deleted = get_input_value('_deleted', RCUBE_INPUT_POST);
      if(!$deleted)
        $deleted = 0;
      $datetime = null;
      if($date) {
        $datetime = date('Y-m-d H:i:s', $this->toGMT(strtotime($date)));
      }
      $created = gmdate('Y-m-d H:i:s');

      $query = $this->rc->db->query(
        "INSERT INTO " . $this->table('planner') . "
        (" . $this->q('user_id') . ", " . $this->q('datetime') . ", " . $this->q('created') . ", " . $this->q('text') . ", " . $this->q('done') . ", " . $this->q('starred') . ", " . $this->q('deleted') . ")
        VALUES (?, ?, ?, ?, ?, ?, ?)",
        $this->user,
        $datetime,
        $created,
        $text,
        $done,
        $starred,
        $deleted
      );
      $id = $this->rc->db->insert_id('planner');
      $plan = array();
      $today = false;
      if($datetime){
        $plan['timestamp'] = $this->toUserTime(strtotime($datetime));
        $class .= ' drag_datetime';
        if(date('Ymd', $plan['timestamp']) === date('Ymd')){
          $class .= ' today';;
        }
      }
      else{
        $class .= ' drag_nodate';
      }
      $plan['text'] = $text;
      $plan['done'] = $done;
      $plan['starred'] = $starred;
      $plan['deleted'] = $deleted;
      $plan['created'] = strtotime(gmdate('Y-m-d H:i:s')) * 1000;
      $plan['id'] = $id;
      $ret = $this->html_list_item($plan, $id);
      $this->rc->output->command('plugin.planner_insert', array($id, $ret[0], $class));
    }
  }
  
  /**
  * Edit a plan
  */
  function planner_edit() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);
      $text = trim(get_input_value('_t', RCUBE_INPUT_POST));
      $date = trim(get_input_value('_d', RCUBE_INPUT_POST));
      $created = trim(get_input_value('_c', RCUBE_INPUT_POST));
      $starred = get_input_value('_starred', RCUBE_INPUT_POST);
      if(!$starred)
        $starred = 0;
      $done = get_input_value('_done', RCUBE_INPUT_POST);
      if(!$done)
        $done = 0;
      $deleted = get_input_value('_deleted', RCUBE_INPUT_POST);
      if(!$deleted)
        $deleted = 0;
      $datetime = null;
      if($date) {
        $datetime = date('Y-m-d H:i:s', $this->toGMT(strtotime($date)));
      }
      $query = $this->rc->db->query(
        "UPDATE " . $this->table('planner') . " SET " . $this->q('datetime') . "=?, " . $this->q('text') . "=?, " . $this->q('done') . "=?, " . $this->q('starred') . "=?, " . $this->q('deleted') . "=? WHERE " . $this->q('id') . "=?",
        $datetime,
        $text,
        $done,
        $starred,
        $deleted,
        $id
      );
      $plan = array();
      $today = false;
      $class = 'drag_nodate';
      if($datetime){
        $class = 'drag_datetime';
        $plan['timestamp'] = $this->toUserTime(strtotime($datetime));
        if(date('Ymd', $plan['timestamp']) === date('Ymd')){
          $today = true;
        }
      }
      $plan['text'] = $text;
      $plan['done'] = $done;
      $plan['starred'] = $starred;
      $plan['deleted'] = $deleted;
      $plan['created'] = $created;
      $plan['id'] = $id;
      $ret = $this->html_list_item($plan, $id);
      $this->rc->output->command('plugin.planner_replace', array($id, $ret[0], $today, $class));
    }
  }
  
  /**
   * Update plan timestamp
   */
  function planner_created() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);
      $created = get_input_value('_c', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE " . $this->table('planner') . " SET " . $this->q('created') . "=? WHERE " . $this->q('id') . "=? AND " . $this->q('user_id') . "=?",
        gmdate('Y-m-d H:i:s', $this->toUserTime($created)), $id, $this->user
      );
      $this->rc->output->command('plugin.planner_success', array('created', $id));
    }
  }


  /**
   * Mark planner item done
   */
  function planner_done() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE " . $this->table('planner') . " SET " . $this->q('done') . "=? WHERE " . $this->q('id') . "=? AND " . $this->q('user_id') . "=?",
        1, $id, $this->user
      );
      $this->rc->output->command('plugin.planner_success', array('done', $id));
    }
  }

  /**
   * Mark planner item starred
   */
  function planner_star() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE " . $this->table('planner') . " SET " . $this->q('starred') . "=? WHERE " . $this->q('id') . "=? AND " . $this->q('user_id') . "=?",
        1, $id, $this->user
      );
      $this->rc->output->command('plugin.planner_success', array('star', $id));
    }
  }

  /**
   * Unmark starred planner item
   */
  function planner_unstar() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE " . $this->table('planner') . " SET " . $this->q('starred') . "=? WHERE " . $this->q('id') . "=? AND " . $this->q('user_id') . "=?",
        0, $id, $this->user
      );
      $this->rc->output->command('plugin.planner_success', array('unstar', $id));
    }
  }

  /**
   * Delete a planner item
   *
   */
  function planner_delete() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE " . $this->table('planner') . " SET " . $this->q('deleted') . "=? WHERE " . $this->q('id') . "=? AND " . $this->q('user_id') . "=?",
        1, $id, $this->user
      );
      $this->rc->output->command('plugin.planner_success', array('delete', $id));
    }
  }
  
  /**
   * Remove a planner item
   *
   */
  function planner_remove() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "DELETE FROM " . $this->table('planner') . " WHERE " . $this->q('id') . "=? AND " . $this->q('user_id') . "=?",
        $id, $this->user
      );
      $notes = false;
      if($this->rc->config->get('sticky_notes_count_img', false)){
        $notes = count((array) sticky_notes::get_sticky_notes());
      }
      $this->rc->output->command('plugin.planner_success', array('remove', $id, $notes));
    }
  }
  
  /**
   * Expunge deleted view
   *
   */
  function planner_expunge() {
    if (!empty($this->user)) {

      $query = $this->rc->db->query(
        "DELETE FROM " . $this->table('planner') . " WHERE " . $this->q('user_id') . "=? AND " . $this->q('deleted') . "=?",
        $this->user, 1
      );
      $this->rc->output->command('plugin.planner_reload', array());
    }
  }

  /**
   * Retrieve planner items and output as html
   */
  function planner_retrieve() {
    if(!empty($this->user)) {
      $type = get_input_value('_p', RCUBE_INPUT_POST);
      $result = $this->rc->db->query("SELECT * FROM " . $this->table('planner') . "
                                      WHERE " . $this->q('user_id') . "=?",
                                      $this->rc->user->ID
                                     );
      // build plans array
      $plans = array();
      while ($result && ($plan = $this->rc->db->fetch_assoc($result))) {
        if(!empty($plan['datetime'])) {
          $timestamp = $this->toUserTime(strtotime($plan['datetime']));
          $plans[$plan['id']] = array('timestamp' => $timestamp, 'text' => $plan['text'], 'starred' => $plan['starred'], 'done' => $plan['done'], 'deleted' => $plan['deleted'], 'created' => strtotime($plan['created']) * 1000);
        }
        else {
          $plans[$plan['id']] = array('timestamp' => 0, 'text' => $plan['text'], 'starred' => $plan['starred'], 'done' => $plan['done'], 'deleted' => $plan['deleted'], 'created' => strtotime($plan['created']) * 1000);
        }
        if(count($plans) >= $this->max_results)
          break;
      }
      // merge plans with birthdays
      if($this->rc->config->get('planner_birthdays', 1)) {
        $birthdays = $this->getBirthdays();
        foreach($birthdays as $birthday){
          $plans[] = $birthday;
          if(count($plans) >= $this->max_results)
            break;
        }
        if(!function_exists('comparePlans')){
          function comparePlans($a, $b) {
            if($a["timestamp"] == $b["timestamp"])
              return 0;
            return ($a["timestamp"] < $b["timestamp"]) ? -1 : 1;
          }
        }
        uasort($plans, "comparePlans");
      }
      // find me: move list building to client
      $list = $this->html($plans);
      // send planner items to client
      $this->rc->output->command('plugin.planner_retrieve',
        array(
          $list[0],
          $list[1],
          $this->rc->config->get('planner_view', 'all'),
          $this->rc->config->get('planner_filter', 'all'),
          $this->rc->config->get('planner_birthdays', 1),
          $type,
          $this->getTimezoneOffset()
        )
      );
    }
  }

  /**
   * Convert plans retrieved from database to formatted html.
   *
   * @param  result    Results from plan retrieval from database
   * @return string    Formatted planner as html
   */
  private function html($plans) {
    // loop over all plans retrieved
    $items = "";
    $cb = 0;
    foreach ($plans as $id => $plan) {
      $ret = $this->html_list_item($plan, $id);
      $html = $ret[0];
      $liclass = $ret[1];
      if($ret[2])
        $cb ++;
      // highlight today's
      if(date('Ymd', $plan['timestamp']) === date('Ymd')) {
        $items .= html::tag('li', array('id' => $id, 'class' => 'highlight today ' . $liclass), $html);
      }
      // highlight starred plans and birthdays
      elseif(!isset($plan['starred']) || $plan['starred']) {
        $items .= html::tag('li', array('id' => $id, 'class' => 'highlight ' . $liclass), $html);
      }
      else {
        $items .= html::tag('li', array('id' => $id, 'class' => $liclass), $html);
      }
    }
    $list = html::tag('ul', array('id' => 'planner_items_list'), $items);
    return array($list, $cb);
  }
  
  /**
   * List item html
   *
   * @param  plan      Results from plan retrieval from database
   * @param  id        plan id
   * @return string    Formatted list item as html
   */
  private function html_list_item($plan, $id) {
    $html = "";
    $cb = false;
    // starred plan
    if(isset($plan['starred'])) {
      if($plan['starred']) {
        $html .= html::a(array('class' => 'myrc_sprites star', 'title' => $this->getText('unstar_plan')), "");
      }
      else {
        $html .= html::a(array('class' => 'myrc_sprites nostar', 'title' => $this->getText('star_plan')), "");
      }
    }
    
    // finished plan // moved to here to solve ellipsis problem
    if($plan['deleted']) {
      $html .= html::a(array('class' => 'myrc_sprites remove', 'title' => $this->getText('remove')), "");
    }
    elseif($plan['done']) {
      $html .= html::a(array('class' => 'myrc_sprites delete', 'title' => $this->getText('delete')), "");
    }
    // not finished plan
    elseif(isset($plan['starred'])) {
      $html .= html::a(array('class' => 'myrc_sprites done', 'title' => $this->getText('done_plan')), "");
    }
    // birthday
    else{
      $html .= html::a(array('class' => 'myrc_sprites nostar'), "");
      $html .= html::a(array('class' => 'myrc_sprites birthday'), "");
    }
    
    $content = "";
    $liclass = "";
    if(!$plan['text'])
      $plan['text'] = '...';
    if(!$plan['created'])
      $plan['created'] = 0;
    // birthday
    if(!isset($plan['starred'])) {
      $email = '';
      if(is_array($plan['emails'])){
        foreach($plan['emails'] as $val){
          if(is_array($val)){
            $email = $val[0];
            break;
          }
        }
      }
      $content .= html::span('date', date('d', $plan['timestamp']) . " ");
      $content .= html::span('date ' . date('M', $plan['timestamp']), date('M', $plan['timestamp']));
      $content .= html::span('time', date($this->rc->config->get('time_format', 'H:i'), mktime(0,0,0)));
      $input = new html_inputfield(array('type' => 'hidden'));
      $date = date('m/d/Y H:i:s', $plan['timestamp']);
      $content .= $input->show($date);
      $input = new html_inputfield(array('type' => 'hidden'));
      $date = date($this->rc->config->get('date_format', 'm/d/Y') . ' ' . $this->rc->config->get('time_format', 'H:i'), $plan['timestamp']);
      $date = str_ireplace(' pm', 'pm', str_ireplace(' am', 'am', $date));
      $content .= $input->show($date);
      $content .= html::span('datetime nocursor', $this->getText('birthday') . " " . $plan['text'] . '<input class="drag_email" value="' . $email . '" type="hidden" />');
      $cb = true;
    }
    // plan with date/time
    elseif(!empty($plan['timestamp'])) {
      $liclass = 'drag_datetime';
      $content .= html::span('date', date('d', $plan['timestamp']) . " ");
      $content .= html::span('date ' . date('M', $plan['timestamp']), date('M', $plan['timestamp']));
      $content .= html::span('time', date($this->rc->config->get('time_format', 'H:i'), $plan['timestamp']));
      $input = new html_inputfield(array('type' => 'hidden'));
      $date = date('m/d/Y H:i:s', $plan['timestamp']);
      $content .= $input->show($date);
      $input = new html_inputfield(array('type' => 'hidden'));
      $date = date($this->rc->config->get('date_format', 'm/d/Y') . ' ' . $this->rc->config->get('time_format', 'H:i'), $plan['timestamp']);
      $date = str_ireplace(' pm', 'pm', str_ireplace(' am', 'am', $date));
      $content .= $input->show($date);
      $content .= html::span('datetime', $plan['text'] . '<input class="drag_id" value="' . $id . '" type="hidden" /><input type="hidden" class="created" value="' . $plan['created'] . '" />');
    }
    // plan without date/time
    else {
      $liclass = 'drag_nodate';
      $content .= html::span('nodate', $plan['text'] . '<input class="drag_id" value="' . $id . '" type="hidden" /><input type="hidden" class="created" value="' . $plan['created'] . '" />');
    }
    if(!isset($plan['starred'])) {
      $liclass = 'drag_birthday';
      $html .= html::span('', $content);
    }
    else {
      $html .= html::span('edit', $content);
    }
    return array($html, $liclass, $cb);
  }
  
  /**
   * Retrieve contact birthdays
   *
   * @return array Contact birthdays
   */
  private function getBirthdays() {
    if($this->rc->config->get('planner_birthdays', 1) && !is_array($this->bd[$type])){
      $birthdays = array();
      $sources = $this->rc->config->get('autocomplete_addressbooks', array('sql'));
      foreach($sources as $source){
        // get all contact records
        $contacts = $this->rc->get_address_book($source);
        $contacts->set_pagesize(9999);

        // get records
        $records = $contacts->list_records();
      
        // loop over all contact records
        while($r = $records->next()) {
          if(!empty($r['name']) && !empty($r['birthday'])) {
            list($year, $month, $day) = explode('-', (string) $r['birthday'][0]);
            if($month < date('m')) {
              $timestamp = gmmktime(0, 0, 0, $month, $day, date('Y') + 1);
            }
            else {
              $timestamp = gmmktime(0, 0, 0, $month, $day); 
            }
            $birthdays[] = array('timestamp' => $timestamp, 'text' => $r['name'], 'emails' => array($r['email:home'], $r['email:other'], $r['email:work']));
          }
        }
      }
      $this->bd = $birthdays;
    }
    return $birthdays;
  }
  
  /**
   * Correct GMT timestamp with timezone to user timestamp
   *
   * @param  timestamp GMT timestamp 
   * @return int       User timestamp
   */
  private function toUserTime($timestamp) {
    return ($timestamp + $this->getTimezoneOffset());
  }
  
  /**
   * Correct user timestamp with timezone to GMT timestamp
   *
   * @param  timestamp User timestamp 
   * @return int       GMT timestamp
   */
  private function toGMT($timestamp) {
    return ($timestamp - $this->getTimezoneOffset());
  }
  
  /**
   * Get offset of user timezone with GMT
   *
   * @return int User timezone offset
   */
   public function getTimezoneOffset() {
    // get timezone provided by the user
    $timezone = 0;
    if(rcmail::get_instance()->config->get('timezone') === "auto") {
      $timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : date('Z')/3600;
    }
    else if(is_numeric(rcmail::get_instance()->config->get('timezone'))){
      $timezone = rcmail::get_instance()->config->get('timezone');
      if(rcmail::get_instance()->config->get('dst_active')) {
        $timezone++;
      }
    }
    else{
      $stz = date_default_timezone_get();
      date_default_timezone_set(rcmail::get_instance()->config->get('timezone'));
      $timezone = date('Z')/3600;
      date_default_timezone_set($stz);
    }
    // calculate timezone offset
    return ($timezone * 3600);
  }
  
  /**
   * Week range
   *
   * @param  integer week number
   * @param  integer year
   * @param  integer first day
   * @param  string date format
   * @return string formatted date
   */
  // find me: remove after counts are moved to client
  function week_start_date($wk_num, $yr, $first = 1, $format = 'Y-m-d') {
    $wk_ts  = strtotime('+' . $wk_num . ' weeks', strtotime($yr . '0101'));
    $mon_ts = strtotime('-' . date('w', $wk_ts) + $first . ' days', $wk_ts);
    return date($format, $mon_ts);
  }
  
  /**
   * Database quotation
   *
   * @param  string string
   * @return string string
   */
  public function q($str)
  {
    return $this->rc->db->quoteIdentifier($str);
  }
  
  /**
   * Get Database table name
   *
   * @param  string string
   * @return string string
   */
  public function table($str)
  {
    return $this->rc->db->quoteIdentifier(get_table_name($str));
  }
}
?>
