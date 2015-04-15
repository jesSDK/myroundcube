<?php
# 
# This file is part of MyRoundcube "jappix4roundcube" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (C) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class jappix4roundcube extends rcube_plugin
{
  public $task = '?(?!login|logout).*';
  public $ttl = 604800;
  
  private $rc;
  
  /* unified plugin properties */
  static private $plugin = 'jappix4roundcube';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/jappix4roundcube-plugin" target="_blank">Documentation</a>';
  static private $version = '2.0.26';
  static private $date = '02-04-2015';
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
  static private $tables = array(
    'jappix'
  );
  static private $db_version = array(
    'initial',
  );
  static private $sqladmin = array('db_dsnw', 'jappix');
  static private $prefs = null;
  static private $config_dist = 'config.inc.php.dist';
  
  function init() {
    /* Pre-condidition check */
    if(!class_exists('rcube')){
      return;
    }
    
    $this->rc = rcube::get_instance();
    
    /* DB versioning */
    if(is_dir(INSTALL_PATH . 'plugins/db_version')){
      $this->require_plugin('db_version');
      if(!$load = db_version::exec(self::$plugin, self::$tables, self::$db_version)){
        return;
      }
    }
    
    if($this->rc->action == 'compose' && $_GET['_extwin'] == 1){
      return;
    }
    
    $this->require_plugin('libgpl');

    $this->load_config();
    $this->add_texts('localization/', true);
    
    if($this->rc->task == 'settings'){
      $this->add_hook('preferences_sections_list', array($this, 'preferences_sections_list'));
      $this->add_hook('preferences_list', array($this, 'preferences_list'));
      $this->add_hook('preferences_save', array($this, 'preferences_save'));
      $this->add_hook('password_change', array($this, 'password_change'));
    }
    
    if($this->rc->action != 'jappix.loadmini'){
      $this->include_stylesheet('skins/' . $this->rc->config->get('skin', 'larry') . '/jappix4roundcube.css');
    }

    $this->register_task('jappix');
    $this->register_action('jappix.getfile', array($this, 'getfile'));

    if($this->rc->config->get('jabber_domain') == '%d'){
      $this->rc->config->set('jabber_domain', end(explode('@', $this->rc->user->data['username'])));
    }
    if(!$this->rc->config->get('jabber_username') && $this->rc->config->get('jappix_inherit')){
      $this->rc->config->set('jabber_username', current(explode('@', $this->rc->user->data['username'])));
    }
    
    if(!$this->rc->config->get('jabber_enc') && $this->rc->config->get('jappix_inherit')){
      libgpl::include_php('plugins/libgpl/gibberish/GibberishAES.php');
      $key = md5($this->rc->user->data['username'] . ':' . $this->rc->decrypt($_SESSION['password']));
      $this->rc->config->set('jabber_enc', GibberishAES::enc($this->rc->decrypt($_SESSION['password']), $key));
    }

    if(!$this->rc->config->get('jappix_enabled') || !$this->rc->config->get('jabber_username') || !$this->rc->config->get('jabber_domain', 'jappix.com')){
      return;
    }

    $this->register_action('jappix.loadmini', array($this, 'loadmini'));

    $skin = $this->rc->config->get('skin', 'larry');
    $lg = explode('_', $_SESSION['language']);
    $lg = $lg[0];
    $src  = unslashify($this->rc->config->get('jappix_url', 'https://jappix.com'));
    libgpl::include_js('gibberish/gibberish-aes.js');
    $display = $this->rc->config->get('jappix_full', 0) ? '' : 'display:none';

    $this->require_plugin('myrc_sprites');
    $this->add_button(array(
      'command' => 'tjappix',
      'type' => 'link',
      'onclick' =>"$.get('./', { _task : 'jappix', _action : 'jappix.return_loginkey' }, function(key){
            GibberishAES.size(256);
            var dec = GibberishAES.dec('" . $this->rc->config->get('jabber_enc') . "', $.trim(key));
            rcmail.open_window('" . $src . "?u=" . $this->rc->config->get('jabber_username') . '@' . $this->rc->config->get('jabber_domain') . "&q=' + dec + '&l=" . $lg . "&h=1');
          });",
      'class' => 'button-jappix4roundcube',
      'classsel' => 'button-jappix4roundcube button-selected',
      'innerclass' => 'button-inner myrc_sprites',
      'label' => 'jappix4roundcube.task',
      'style' => $display,
    ), 'taskbar');
    
    if($this->rc->action == 'jappix.loginkey'){
      $this->loginkey();
    }
    
    if($this->rc->action == 'jappix.return_loginkey'){
      $this->return_loginkey();
    }
    
    $this->add_hook('render_page', array($this, 'redirect'));
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
      'db_version' => self::$db_version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'sqladmin' => self::$sqladmin,
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
  
  function redirect($p){
    if($p['template'] != 'summary.summary'){
      if($this->rc->config->get('jappix_mini', true) && (
                                                          $p['template'] == 'mail' ||
                                                          $p['template'] == 'addressbook' ||
                                                          $p['template'] == 'settings' ||
                                                          $p['template'] == 'compose' ||
                                                          $p['template'] == 'calendar.calendar' ||
                                                          $p['template'] == 'sticky_notes.sticky_notes' ||
                                                          $p['template'] == 'planner.planner' ||
                                                          $p['template'] == 'jappix4roundcube.jappix4roundcube'
                                                                                                                ) && !$_SESSION['jappixminiframe']){
        if($this->rc->action != 'plugin.summary' && !isset($_GET['_framed'])){
          $_SESSION['jappixminiframe'] = true;
          header('Location: ./?_task=jappix&_action=jappix.loadmini&_origtask=' . $this->rc->task . '&_origaction=' . $this->rc->action);
          exit;
        }
      }
      else if($p['template'] != 'login' && $p['template'] != 'jappix4roundcube.jappixmini4roundcube'){
        $this->rc->output->add_script('function sync_parent(){ var t = document.title; t = t.replace("' . $this->rc->config->get('product_name', '') . ' :: ' . '", ""); parent.document.title = t; parent.rc_task = rcmail.env.task; parent.rc_action = rcmail.env.action; window.setTimeout("sync_parent();", 1000); }; sync_parent();');
      }
      $this->rc->output->add_script("if(rcmail.env.task == 'jappix'){ parent.$('.jm_position').hide(); } else { if(parent.rcmail.env.jabber_mini == 1) { parent.$('.jm_position').show(); } };", 'head');
    }
    $this->rc->output->add_script("if(parent.location.href != self.location.href){ $('.button-logout').attr('onclick', 'return parent.rcmail.command(\'switch-task\', \'logout\', this, event)'); };", 'foot');
    return $p;
  }
    
  function loadmini(){
    if(!$_SESSION['jappixmini']){
      $this->cache();
    }
    if($_SESSION['jappixmini']){
      $lg = explode('_', $_SESSION['language']);
      $lg = $lg[0];
      if($this->rc->config->get('jappix_cache')){
        $this->rc->output->add_header(html::tag('script', array('type'=>'text/javascript', 'src' => 'https://' . $_SERVER['HTTP_HOST'] . '/?_task=jappix&_action=jappix.getfile&_file=mini.js&_l=' . $lg)));
      }
      else{
        $this->rc->output->add_header(html::tag('script', array('type'=>'text/javascript', 'src' => slashify($this->rc->config->get('jappix_url', 'https://jappix.com')) . 'php/get.php?l=' . $lg . '&t=js&g=mini.xml')));
      }
      $this->rc->output->set_env('jabber_username', $this->rc->config->get('jabber_username'));
      $this->rc->output->set_env('jabber_domain', $this->rc->config->get('jabber_domain'));
      $this->rc->output->set_env('jabber_enc', $this->rc->config->get('jabber_enc'));
      $this->rc->output->set_env('jabber_mini', $this->rc->config->get('jappix_mini', true) ? 1 : 0);
      $this->rc->output->set_env('jabber_autologon', $this->rc->config->get('jappix_mini_autologon', true));
      $this->include_script('jappix.js');
      libgpl::include_js('gibberish/gibberish-aes.js');
      $file = 'mini.css';
      $browser = new rcube_browser();
      if($browser->ie && $browser->ver < 7){
        $file = 'mini-ie.css';
      }
      if($this->rc->config->get('jappix_cache')){
        $this->include_stylesheet('https://' . $_SERVER['HTTP_HOST'] . '/?_task=jappix&_action=jappix.getfile&_file=' . $file . '&_l=' . $lg);
      }
      else{
        $this->include_stylesheet(slashify($this->rc->config->get('jappix_url', 'https://jappix.com')) . 'php/get.php?l=' . $lg . '&t=css&f=' . $file);
      }
      $this->include_stylesheet('jappix.css');
    }
    else{
      $this->rc->output->show_message('jappix4roundcube.failure', 'warning');
    }
    if(!$this->rc->config->get('jappix_mini')){
      $this->rc->output->add_script('parent.location.href="./?_task=' . $this->rc->task . '&_action=' . $this->rc->action . '"');
    }
    $this->rc->output->send('jappix4roundcube.jappixmini4roundcube');
  }
  
  function getfile(){
    $file = get_input_value('_file', RCUBE_INPUT_GET);
    $lang = get_input_value('_l', RCUBE_INPUT_GET);
    $sql = 'SELECT * FROM ' . get_table_name('jappix') . ' WHERE file=? AND lang=?';
    $res = $this->rc->db->limitquery($sql, 0, 1, $file, $lang);
    $content = $this->rc->db->fetch_assoc($res);
    header('Content-Type: ' . $content['contenttype']);
    header('Content-Length: ' . strlen($content['content']));
    $this->rc->output->future_expire_header(strtotime($content['ts']) - time() + $this->ttl);
    echo $content['content'];
    exit;
  }

  function preferences_sections_list($args){
    if(!$this->rc->config->get('jappix_lock_settings')){
      $args['list']['jabber'] = array(
        'id'      => 'jabber',
        'section' => Q($this->gettext('jappixSection'))
      );
    }
    return($args);
  }

  function preferences_list($args){
    if($args['section'] == 'jabber'){
      $jabber_username = $this->rc->config->get('jabber_username');
      $jabber_domain = $this->rc->config->get('jabber_domain', 'jappix.com');
      $jabber_enc = $this->rc->config->get('jabber_enc');
      
      if((!$jabber_username || !$jabber_enc || !$jabber_domain) && $this->rc->config->get('jappix_register_url')){
        $field_id = 'rcmfd_register';
        $args['blocks']['jabber']['options']['jappix_register'] = array(
          'title' => html::label($field_id, @($this->gettext('requirement'))),
          'content' => html::tag('a', array('href' => $this->rc->config->get('jappix_register_url'), 'target' => '_blank'), $this->gettext('clickhere')) . ' ' . $this->gettext('registeraccount')
        );
      }
      
      $jappix_enabled = $this->rc->config->get('jappix_enabled', 0);
      $field_id = 'rcmfd_enabled';
      $checkbox = new html_checkbox(array('name' => '_jappix_enabled', 'value' => 1, 'id' => $field_id, 'onclick' => "if($(this).prop('checked') == false || $('input[name=\'_jabber_username\']').val() != ''){ $('.mainaction').hide(); document.forms.form.submit(); };"));
      $args['blocks']['jabber']['options']['jappix_enabled'] = array(
        'title' => html::label($field_id, Q($this->gettext('enabled'))),
        'content' => $checkbox->show($jappix_enabled ? 1:0),
      );
      
      $field_id_user = 'rcmfd_username';
      $input_user = new html_inputfield(array('name' => '_jabber_username', 'id' => $field_id_user, 'size' => 25));
      if($jabber_domain == '%d'){
        $jabber_domain = end(explode('@', $this->rc->user->data['username']));
      }
      $field_id_domain = 'rcmfd_domain';
      $input_domain = new html_inputfield(array('name' => '_jabber_domain', 'id' => $field_id_domain, 'size' => 25));
      $args['blocks']['jabber']['options']['jabber_username'] = array(
        'title' => html::label($field_id, Q($this->gettext('jappixUsername'))),
        'content' => $input_user->show($jabber_username) . '&nbsp;@&nbsp;' . $input_domain->show($jabber_domain),
      );
      
      $field_id = 'rcmfd_enc';
      $input = new html_passwordfield(array('name' => '_jabber_password', 'id' => $field_id, 'size' => 25, 'placeholder' => $jabber_enc ? $this->gettext('passwordisset') : $this->gettext('pleaseenterpassword')));
      $args['blocks']['jabber']['options']['jabber_password'] = array(
        'title' => html::label($field_id, Q($this->gettext('jappixPassword'))),
        'content' => $input->show(),
      );

      $jappix_full = $this->rc->config->get('jappix_full', 1);
      $field_id = 'rcmfd_use_full';
      $checkbox = new html_checkbox(array('name' => '_jappix_full', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.$('.button-jappix4roundcube').show(); } else { parent.$('.button-jappix4roundcube').hide(); }; $('.mainaction').hide(); document.forms.form.submit();"));
      $args['blocks']['jabber']['options']['jappix_full'] = array(
        'title' => html::label($field_id, Q($this->gettext('usefulljappix'))),
        'content' => $checkbox->show($jappix_full ? 1:0),
      );

      $jappix_mini = $this->rc->config->get('jappix_mini', 1);
      $field_id = 'rcmfd_use_mini';
      $checkbox = new html_checkbox(array('name' => '_jappix_mini', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.parent.$('.jm_position').show(); } else { parent.parent.$('.jm_position').hide(); }; $('.mainaction').hide(); document.forms.form.submit();"));
      $args['blocks']['jabber']['options']['jappix_mini'] = array(
        'title' => html::label($field_id, Q($this->gettext('useminijappix'))),
        'content' => $checkbox->show($jappix_mini ? 1:0),
      );
      
      $jappix_mini_autologon = $this->rc->config->get('jappix_mini_autologon', 1);
      $field_id = 'rcmfd_use_mini_autologon';
      $checkbox = new html_checkbox(array('name' => '_jappix_mini_autologon', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.parent.$('.jm_pane').trigger('click'); } else { if(parent.parent.JappixMini) parent.parent.JappixMini.disconnect(); }; $('.mainaction').hide(); document.forms.form.submit();"));
      $args['blocks']['jabber']['options']['jappix_mini_autologon'] = array(
        'title' => html::label($field_id, Q($this->gettext('minijappixautologon'))),
        'content' => $checkbox->show($jappix_mini_autologon ? 1:0),
      );
      
      /*
      $field_id = 'rcmfd_use_manager';
      $args['blocks']['jabber']['options']['jabber_manager'] = array(
        'title' => html::label($field_id, Q($this->gettext('manager'))),
        'content' => '<a target=\'_blank\' href=\''.$this->rc->config->get('jappix_url').'/?m=manager\'>'.Q($this->gettext('manager')).'</a>',
      );
      */
    }
    return $args;
  }

  function preferences_save($args){
    if($args['section'] == 'jabber'){
      libgpl::include_php('plugins/libgpl/gibberish/GibberishAES.php');
      $enabled = get_input_value('_jappix_enabled', RCUBE_INPUT_POST);
      $username = trim(get_input_value('_jabber_username', RCUBE_INPUT_POST));
      if(!$username && $enabled){
        $this->rc->output->show_message('jappix4roundcube.usernameempty', 'error');
        $args['abort'] = true;
      }
      if(preg_match('/[@\/\\ ]/', $username)){
        $this->rc->output->show_message('jappix4roundcube.usernameinvalid', 'error');
        $this->rc->output->set_env('jabber_username', $this->rc->config->get('jabber_username', ''));
        $args['abort'] = true;
      }
      else{
        $args['prefs']['jabber_username'] = $username;
        $this->rc->output->set_env('jabber_username', $args['prefs']['jabber_username']);
      }
      $domain = trim(get_input_value('_jabber_domain', RCUBE_INPUT_POST));
      if(!$this->is_valid_domain_name($domain)){
        $this->rc->output->show_message('jappix4roundcube.domaininvalid', 'error');
        $this->rc->output->set_env('jabber_domain', $this->rc->config->get('jabber_domain', 'jappix.com'));
        $args['abort'] = true;
      }
      else{
        $args['prefs']['jabber_domain'] = $domain;
        $this->rc->output->set_env('jabber_domain', $args['prefs']['jabber_domain']);
      }
      $password = trim(get_input_value('_jabber_password', RCUBE_INPUT_POST));
      if(!$password && $this->rc->config->get('jappix_inherit')){
        $password =  $this->rc->decrypt($_SESSION['password']);
      }
      if($password){
        $key = md5($this->rc->user->data['username'] . ':' . $this->rc->decrypt($_SESSION['password']));
        $enc = GibberishAES::enc($password, $key);
        $args['prefs']['jabber_enc'] = $enc;
        $this->rc->output->set_env('jabber_enc', $enc);
      }
      $args['prefs']['jappix_enabled'] = $enabled;
      $args['prefs']['jappix_enabled'] = $args['prefs']['jappix_enabled'] ? 1 : 0;
      if($args['prefs']['jappix_enabled'] == 0){
        $this->rc->session->remove('jappixminiframe');
      }
      $args['prefs']['jappix_full'] = get_input_value('_jappix_full', RCUBE_INPUT_POST);
      $args['prefs']['jappix_full'] = $args['prefs']['jappix_full'] ? 1 : 0;
      $args['prefs']['jappix_mini'] = get_input_value('_jappix_mini', RCUBE_INPUT_POST);
      $args['prefs']['jappix_mini'] = $args['prefs']['jappix_mini'] ? 1 : 0;
      $args['prefs']['jappix_mini_autologon'] = get_input_value('_jappix_mini_autologon', RCUBE_INPUT_POST);
      $args['prefs']['jappix_mini_autologon'] = $args['prefs']['jappix_mini_autologon'] ? 1 : 0;
      $this->rc->output->set_env('jabber_mini',  $args['prefs']['jappix_mini'] ? true : false);
      $this->rc->output->set_env('jabber_autologon', $args['prefs']['jappix_mini_autologon'] ? true : false);
    }
    return $args;
  }
  
  function password_change($args){
    libgpl::include_php('plugins/libgpl/gibberish/GibberishAES.php');
    $key = md5($this->rc->user->data['username'] . ':' . $args['old_pass']);
    $dec = GibberishAES::dec($this->rc->config->get('jabber_enc'), $key);
    $key = md5($this->rc->user->data['username'] . ':' . $args['new_pass']);
    $enc = GibberishAES::enc($args['new_pass'], $key);
    $this->rc->user->save_prefs(array('jabber_enc' => $enc));
    return $args;
  }
  
  function loginkey(){
    $key = md5($this->rc->user->data['username'] . ':' . $this->rc->decrypt($_SESSION['password']));
    $this->rc->output->command('jappix_loginkey', $key);
    $this->rc->output->send('mail');
  }
  
  function return_loginkey(){
    $key = md5($this->rc->user->data['username'] . ':' . $this->rc->decrypt($_SESSION['password']));
    echo $key;
    exit;
  }
  
  function cache(){
    if(!$this->rc->config->get('jappix_cache')){
      $_SESSION['jappixmini'] = true;   
      return;
    }
    $lg = explode('_', $_SESSION['language']);
    $lg = $lg[0];
    $sql = 'SELECT * FROM ' . get_table_name('jappix') . ' WHERE file=? AND lang=? AND ts > ?';
    $ts = date('Y-m-d H:i:s', time() - $this->ttl);
    $res = $this->rc->db->limitquery($sql, 0, 1, 'mini.js', $lg, $ts);
    $props = $this->rc->db->fetch_assoc($res);
    if(is_array($props)){
      $_SESSION['jappixmini'] = true;
    }
    else{
      $sql = 'DELETE FROM ' . get_table_name('jappix') . ' WHERE lang=?';
      $this->rc->db->query($sql, $lg);
      $domain = slashify($this->rc->config->get('jappix_url', 'https://jappix.com'));
      $url = $domain . 'php/get.php?l=' . $lg . '&t=js&g=mini.xml';
      $http = new MyRCHttp;
      $httpConfig['method'] = 'GET';
      $httpConfig['user_agent'] = 'MyRoundcube PHP/5.0';
      $httpConfig['referrer'] = 'http' . (rcube_https_check() ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
      $httpConfig['target'] = $url;
      $http->initialize($httpConfig); 
      $http->useCurl(true);
      if(ini_get('safe_mode') || ini_get('open_basedir')){
        $http->useCurl(false);
      }
      $http->SetTimeout(3);
      $http->execute();
      if($minijs = $http->result){
        $minijs = str_replace("jQuery('head').append('<link ", "\r\n//jQuery('head').append('<link ", $minijs);
        $minijs = str_replace('type="text/css" media="all" />\');', 'type="text/css" media="all" />\');' . "\r\n", $minijs);
        $minijs = trim($minijs);
        $sql = 'INSERT INTO ' . get_table_name('jappix') . ' (lang, ts, file, contenttype, content) VALUES (?, ?, ?, ?, ?)';
        $this->rc->db->query($sql, $lg, date('Y-m-d H:i:s', time()), 'mini.js', 'application/javascript', $minijs);
        $url = $domain . 'php/get.php?t=css&f=mini.css';
        $httpConfig['target'] = $url;
        $http->initialize($httpConfig);
        $http->execute();
        if($minicss = $http->result){
          $minicss = str_replace('./get.php?', 'plugins/jappix4roundcube/get.php?', $minicss);
          $sql = 'INSERT INTO ' . get_table_name('jappix') . ' (lang, ts, file, contenttype, content) VALUES (?, ?, ?, ?, ?)';
          $this->rc->db->query($sql, $lg, date('Y-m-d H:i:s', time()), 'mini.css', 'text/css', $minicss);
          $url = $domain . 'php/get.php?t=css&f=mini-ie.css';
          $httpConfig['target'] = $url;
          $http->initialize($httpConfig);
          $http->execute();
          if($minicss = $http->result){
            $minicss = str_replace('./get.php?', 'https://jappix.myroundcube.com/php/get.php?', $minicss);
            $sql = 'INSERT INTO ' . get_table_name('jappix') . ' (lang, ts, file, contenttype, content) VALUES (?, ?, ?, ?, ?)';
            $this->rc->db->query($sql, $lg, date('Y-m-d H:i:s', time()), 'mini-ie.css', 'text/css', $minicss);
            $res = $this->rc->db->affected_rows();
            $sql = 'SELECT * FROM ' . get_table_name('jappix') . ' WHERE lang=?';
            $res = $this->rc->db->query($sql, $lg);
            $i = 0;
            while($res && $prop = $this->rc->db->fetch_assoc($res)){
              $i++;
            }
            if($i < 3){
              $sql = 'DELETE FROM ' . get_table_name('jappix') . ' WHERE lang=?';
              $this->rc->db->query($sql, $lg);
            }
            else{
              $_SESSION['jappixmini'] = true;
            }
          }
        }
      }
    }
  }
  
  function is_valid_domain_name($domain_name){
    return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
            && preg_match("/^.{1,253}$/", $domain_name) //overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)   ); //length of each label
  }
}
?>