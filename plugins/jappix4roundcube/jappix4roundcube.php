<?php
/**
 * jappix4roundcube
 *
 * @version 2.0.19 - 14.07.2014
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.com
 *
 *
 * Forked from: see below
 *
 **/

/**
 * jappix4roundcube
 *
 * Plugin to integrate Mini jappix in roundcube
 * Mini jappix : https://mini.jappix.com/get
 *
 * @version 1.0
 * @author RD
 * @url https://code.google.com/p/jappix4roundcube/
 */
 
class jappix4roundcube extends rcube_plugin {

  public $task = 'mail|settings|addressbook|jappix|dummy|logout';
  
  public $ttl = 604800;
  
  /* unified plugin properties */
  static private $plugin = 'jappix4roundcube';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/jappix4roundcube-plugin" target="_blank">Documentation</a>';
  static private $version = '2.0.19';
  static private $date = '14-07-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3',
    'required_plugins' => array(
      'http_request' => 'require_plugins',
      'db_version' => 'require_plugin',
      'gibberish' => 'require_plugin',
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
    $rcmail = rcmail::get_instance();
    
    /* DB versioning */
    if(is_dir(INSTALL_PATH . 'plugins/db_version')){
      $this->require_plugin('db_version');
      if(!$load = db_version::exec(self::$plugin, self::$tables, self::$db_version)){
        return;
      }
    }
    
    if($rcmail->action == 'compose' && $_GET['_extwin'] == 1){
      return;
    }
    
    $this->require_plugin('gibberish');

    $this->load_config();
    $this->add_texts('localization/', true);
    
    if($rcmail->task == 'settings'){
      $this->add_hook('preferences_sections_list', array($this, 'preferences_section'));
      $this->add_hook('preferences_list', array($this, 'preferences_list'));
      $this->add_hook('preferences_save', array($this, 'preferences_save'));
      $this->add_hook('password_change', array($this, 'password_change'));
    }
    
    if($rcmail->action != 'jappix.loadmini'){
      $this->include_stylesheet('skins/' . $rcmail->config->get('skin', 'larry') . '/jappix4roundcube.css');
    }

    $this->register_task('jappix');
    $this->register_action('jappix.getfile', array($this, 'getfile'));

    if($rcmail->config->get('jabber_domain') == '%d'){
      $rcmail->config->set('jabber_domain', end(explode('@', $rcmail->user->data['username'])));
    }
    if(!$rcmail->config->get('jabber_username') && $rcmail->config->get('jappix_inherit')){
      $rcmail->config->set('jabber_username', current(explode('@', $rcmail->user->data['username'])));
    }
    
    if(!$rcmail->config->get('jabber_enc') && $rcmail->config->get('jappix_inherit')){
      gibberish::include_php();
      $key = md5($rcmail->user->data['username'] . ':' . $rcmail->decrypt($_SESSION['password']));
      $rcmail->config->set('jabber_enc', GibberishAES::enc($rcmail->decrypt($_SESSION['password']), $key));
    }

    if(!$rcmail->config->get('jabber_username') || !$rcmail->config->get('jabber_domain', 'jappix.com')){
      return;
    }

    $this->register_action('jappix.loadmini', array($this, 'loadmini'));

    $skin = $rcmail->config->get('skin', 'larry');
    $lg = explode('_', $_SESSION['language']);
    $lg = $lg[0];
    $src  = unslashify($rcmail->config->get('jappix_url', 'https://jappix.com'));
    gibberish::include_js();
    $display = $rcmail->config->get('jappix_full', 0) ? '' : 'display:none';
    $this->add_button(array(
      'command' => 'tjappix',
      'type' => 'link',
      'onclick' =>"$.get('./', { _task : 'jappix', _action : 'jappix.return_loginkey' }, function(key){
            GibberishAES.size(256);
            var dec = GibberishAES.dec('" . $rcmail->config->get('jabber_enc') . "', $.trim(key));
            rcmail.open_window('" . $src . "?u=" . $rcmail->config->get('jabber_username') . '@' . $rcmail->config->get('jabber_domain') . "&q=' + dec + '&l=" . $lg . "&h=1');
          });",
      'class' => 'button-jappix4roundcube',
      'classsel' => 'button-jappix4roundcube button-selected',
      'innerclass' => 'button-inner',
      'label' => 'jappix4roundcube.task',
      'style' => $display,
    ), 'taskbar');
    
    if($rcmail->action == 'jappix.loginkey'){
      $this->loginkey();
    }
    
    if($rcmail->action == 'jappix.return_loginkey'){
      $this->return_loginkey();
    }
    
    $this->add_hook('render_page', array($this, 'redirect'));
    $this->add_hook('logout_after', array($this, 'breakframe'));
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
  
  function breakframe($args){
    if(class_exists('tabbed')){
      return $p;
    }
    $rcmail = rcmail::get_instance();
    $query = '?';
    foreach($_GET as $key => $val){
      $query .= $key . '=' . $val . '&';
    }
    $query = substr($query, 0, strlen($query) - 1);
    $rcmail->output->add_script('parent.location.href="./' . $query . '";');
    $rcmail->output->send('iframe');
    exit;
  }
  
  function redirect($p){
    if(class_exists('tabbed')){
      $this->cache();
      return $p;
    }
    $rcmail = rcmail::get_instance();
    if($p['template'] != 'summary.summary'){
      if($rcmail->config->get('jappix_mini', true) && (
                                                          $p['template'] == 'mail' ||
                                                          $p['template'] == 'addressbook' ||
                                                          $p['template'] == 'settings' ||
                                                          $p['template'] == 'compose' ||
                                                          $p['template'] == 'calendar.calendar' ||
                                                          $p['template'] == 'sticky_notes.sticky_notes' ||
                                                          $p['template'] == 'planner.planner' ||
                                                          $p['template'] == 'jappix4roundcube.jappix4roundcube'
                                                                                                                ) && !$_SESSION['jappixminiframe']){
        if($rcmail->action != 'plugin.summary' && !isset($_GET['_framed'])){
          $_SESSION['jappixminiframe'] = true;
          header('Location: ./?_task=jappix&_action=jappix.loadmini&_origtask=' . $rcmail->task . '&_origaction=' . $rcmail->action);
          exit;
        }
      }
      else if($p['template'] != 'login' && $p['template'] != 'jappix4roundcube.jappixmini4roundcube'){
        $rcmail->output->add_script('function sync_parent(){ var t = document.title; t = t.replace("' . $rcmail->config->get('product_name', '') . ' :: ' . '", ""); parent.document.title = t; parent.rc_task = rcmail.env.task; parent.rc_action = rcmail.env.action; window.setTimeout("sync_parent();", 1000); }; sync_parent();');
      }
      $rcmail->output->add_script("if(rcmail.env.task == 'jappix'){ parent.$('.jm_position').hide(); } else { if(parent.rcmail.env.jabber_mini == 1) { parent.$('.jm_position').show(); } };");
    }
    return $p;
  }
    
  function loadmini(){
    $rcmail = rcmail::get_instance();
    if(!$_SESSION['jappixmini']){
      $this->cache();
    }
    if($_SESSION['jappixmini']){
      $lg = explode('_', $_SESSION['language']);
      $lg = $lg[0];
      if($rcmail->config->get('jappix_cache')){
        $rcmail->output->add_header(html::tag('script', array('type'=>'text/javascript', 'src' => 'https://' . $_SERVER['HTTP_HOST'] . '/?_task=jappix&_action=jappix.getfile&_file=mini.js&_l=' . $lg)));
      }
      else{
        $rcmail->output->add_header(html::tag('script', array('type'=>'text/javascript', 'src' => slashify($rcmail->config->get('jappix_url', 'https://jappix.com')) . 'php/get.php?l=' . $lg . '&t=js&g=mini.xml')));
      }
      $rcmail->output->set_env('jabber_username', $rcmail->config->get('jabber_username'));
      $rcmail->output->set_env('jabber_domain', $rcmail->config->get('jabber_domain'));
      $rcmail->output->set_env('jabber_enc', $rcmail->config->get('jabber_enc'));
      $rcmail->output->set_env('jabber_mini', $rcmail->config->get('jappix_mini', true) ? 1 : 0);
      $rcmail->output->set_env('jabber_autologon', $rcmail->config->get('jappix_mini_autologon', true));
      $this->include_script('jappix.js');
      gibberish::include_js();
      $file = 'mini.css';
      $browser = new rcube_browser();
      if($browser->ie && $browser->ver < 7){
        $file = 'mini-ie.css';
      }
      if($rcmail->config->get('jappix_cache')){
        $this->include_stylesheet('https://' . $_SERVER['HTTP_HOST'] . '/?_task=jappix&_action=jappix.getfile&_file=' . $file . '&_l=' . $lg);
      }
      else{
        $this->include_stylesheet(slashify($rcmail->config->get('jappix_url', 'https://jappix.com')) . 'php/get.php?l=' . $lg . '&t=css&f=' . $file);
      }
      $this->include_stylesheet('jappix.css');
    }
    else{
      $rcmail->output->show_message('jappix4roundcube.failure', 'warning');
    }
    if(!$rcmail->config->get('jappix_mini')){
      $rcmail->output->add_script('parent.location.href="./?_task=' . $rcmail->task . '&_action=' . $rcmail->action . '"');
    }
    $rcmail->output->send('jappix4roundcube.jappixmini4roundcube');
  }
  
  function getfile(){
    $rcmail = rcmail::get_instance();
    $file = get_input_value('_file', RCUBE_INPUT_GET);
    $lang = get_input_value('_l', RCUBE_INPUT_GET);
    $sql = 'SELECT * FROM ' . get_table_name('jappix') . ' WHERE file=? AND lang=?';
    $res = $rcmail->db->limitquery($sql, 0, 1, $file, $lang);
    $content = $rcmail->db->fetch_assoc($res);
    header('Content-Type: ' . $content['contenttype']);
    header('Content-Length: ' . strlen($content['content']));
    $rcmail->output->future_expire_header(strtotime($content['ts']) - time() + $this->ttl);
    echo $content['content'];
    exit;
  }

  function preferences_section($args){
    if(!rcmail::get_instance()->config->get('jappix_lock_settings')){
      $args['list']['jabber'] = array(
        'id'      => 'jabber',
        'section' => Q($this->gettext('jappixSection'))
      );
    }
    return($args);
  }

  function preferences_list($args){
    if($args['section'] == 'jabber'){
      $rcmail = rcmail::get_instance();
      
      $jabber_username = $rcmail->config->get('jabber_username');
      $jabber_domain = $rcmail->config->get('jabber_domain', 'jappix.com');
      $jabber_enc = $rcmail->config->get('jabber_enc');
      
      if((!$jabber_username || !$jabber_enc || !$jabber_domain) && $rcmail->config->get('jappix_register_url')){
        $field_id = 'rcmfd_register';
        $args['blocks']['jabber']['options']['jappix_register'] = array(
          'title' => html::label($field_id, @($this->gettext('requirement'))),
          'content' => html::tag('a', array('href' => $rcmail->config->get('jappix_register_url'), 'target' => '_blank'), $this->gettext('clickhere')) . ' ' . $this->gettext('registeraccount')
        );
      }
      
      $field_id_user = 'rcmfd_username';
      $input_user = new html_inputfield(array('name' => '_jabber_username', 'id' => $field_id_user, 'size' => 25));
      if($jabber_domain == '%d'){
        $jabber_domain = end(explode('@', $rcmail->user->data['username']));
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

      $jappix_full = $rcmail->config->get('jappix_full', 1);
      $field_id = 'rcmfd_use_full';
      $checkbox = new html_checkbox(array('name' => '_jappix_full', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.$('.button-jappix4roundcube').show(); } else { parent.$('.button-jappix4roundcube').hide(); }; $('.mainaction').hide(); document.forms.form.submit()"));
      $args['blocks']['jabber']['options']['jappix_full'] = array(
        'title' => html::label($field_id, Q($this->gettext('usefulljappix'))),
        'content' => $checkbox->show($jappix_full ? 1:0),
      );

      $jappix_mini = $rcmail->config->get('jappix_mini', 1);
      $field_id = 'rcmfd_use_mini';
      $checkbox = new html_checkbox(array('name' => '_jappix_mini', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.parent.$('.jm_position').show(); } else { parent.parent.$('.jm_position').hide(); }; $('.mainaction').hide(); document.forms.form.submit()"));
      $args['blocks']['jabber']['options']['jappix_mini'] = array(
        'title' => html::label($field_id, Q($this->gettext('useminijappix'))),
        'content' => $checkbox->show($jappix_mini ? 1:0),
      );
      
      $jappix_mini_autologon = $rcmail->config->get('jappix_mini_autologon', 1);
      $field_id = 'rcmfd_use_mini_autologon';
      $checkbox = new html_checkbox(array('name' => '_jappix_mini_autologon', 'value' => 1, 'id' => $field_id, 'onclick' => "if(this.checked){ parent.parent.$('.jm_pane').trigger('click'); } else { if(parent.parent.JappixMini) parent.parent.JappixMini.disconnect(); }; $('.mainaction').hide(); document.forms.form.submit()"));
      $args['blocks']['jabber']['options']['jappix_mini_autologon'] = array(
        'title' => html::label($field_id, Q($this->gettext('minijappixautologon'))),
        'content' => $checkbox->show($jappix_mini_autologon ? 1:0),
      );
      
      /*
      $field_id = 'rcmfd_use_manager';
      $args['blocks']['jabber']['options']['jabber_manager'] = array(
        'title' => html::label($field_id, Q($this->gettext('manager'))),
        'content' => '<a target=\'_blank\' href=\''.$rcmail->config->get('jappix_url').'/?m=manager\'>'.Q($this->gettext('manager')).'</a>',
      );
      */
    }
    return $args;
  }

  function preferences_save($args){
    if($args['section'] == 'jabber'){
      gibberish::include_php();
      $rcmail = rcmail::get_instance();
      $username = trim(get_input_value('_jabber_username', RCUBE_INPUT_POST));
      if(preg_match('/[@\/\\ ]/', $username)){
        $rcmail->output->show_message('jappix4roundcube.usernameinvalid', 'error');
        $rcmail->output->set_env('jabber_username', $rcmail->config->get('jabber_username', ''));
        $args['abort'] = true;
      }
      else{
        $args['prefs']['jabber_username'] = $username;
        $rcmail->output->set_env('jabber_username', $args['prefs']['jabber_username']);
      }
      $domain = trim(get_input_value('_jabber_domain', RCUBE_INPUT_POST));
      if(!$this->is_valid_domain_name($domain)){
        $rcmail->output->show_message('jappix4roundcube.domaininvalid', 'error');
        $rcmail->output->set_env('jabber_domain', $rcmail->config->get('jabber_domain', 'jappix.com'));
        $args['abort'] = true;
      }
      else{
        $args['prefs']['jabber_domain'] = $domain;
        $rcmail->output->set_env('jabber_domain', $args['prefs']['jabber_domain']);
      }
      $password = trim(get_input_value('_jabber_password', RCUBE_INPUT_POST));
      if(!$password && $rcmail->config->get('jappix_inherit')){
        $password =  $rcmail->decrypt($_SESSION['password']);
      }
      if($password){
        $key = md5($rcmail->user->data['username'] . ':' . $rcmail->decrypt($_SESSION['password']));
        $enc = GibberishAES::enc($password, $key);
        $args['prefs']['jabber_enc'] = $enc;
        $rcmail->output->set_env('jabber_enc', $enc);
      }
      $args['prefs']['jappix_full'] = get_input_value('_jappix_full', RCUBE_INPUT_POST);
      $args['prefs']['jappix_full'] = $args['prefs']['jappix_full'] ? 1 : 0;
      $args['prefs']['jappix_mini'] = get_input_value('_jappix_mini', RCUBE_INPUT_POST);
      $args['prefs']['jappix_mini'] = $args['prefs']['jappix_mini'] ? 1 : 0;
      $args['prefs']['jappix_mini_autologon'] = get_input_value('_jappix_mini_autologon', RCUBE_INPUT_POST);
      $args['prefs']['jappix_mini_autologon'] = $args['prefs']['jappix_mini_autologon'] ? 1 : 0;
      $rcmail->output->set_env('jabber_mini',  $args['prefs']['jappix_mini'] ? true : false);
      $rcmail->output->set_env('jabber_autologon', $args['prefs']['jappix_mini_autologon'] ? true : false);
    }
    return $args;
  }
  
  function password_change($args){
    gibberish::include_php();
    $rcmail = rcmail::get_instance();
    $key = md5($rcmail->user->data['username'] . ':' . $args['old_pass']);
    $dec = GibberishAES::dec($rcmail->config->get('jabber_enc'), $key);
    $key = md5($rcmail->user->data['username'] . ':' . $args['new_pass']);
    $enc = GibberishAES::enc($args['new_pass'], $key);
    $rcmail->user->save_prefs(array('jabber_enc' => $enc));
    return $args;
  }
  
  function loginkey(){
    $rcmail = rcmail::get_instance();
    $key = md5($rcmail->user->data['username'] . ':' . $rcmail->decrypt($_SESSION['password']));
    $rcmail->output->command('jappix_loginkey', $key);
    $rcmail->output->send('mail');
  }
  
  function return_loginkey(){
    $rcmail = rcmail::get_instance();
    $key = md5($rcmail->user->data['username'] . ':' . $rcmail->decrypt($_SESSION['password']));
    echo $key;
    exit;
  }
  
  function cache(){
    $rcmail = rcmail::get_instance();
    if(!$rcmail->config->get('jappix_cache')){
      $_SESSION['jappixmini'] = true;   
      return;
    }
    $lg = explode('_', $_SESSION['language']);
    $lg = $lg[0];
    $sql = 'SELECT * FROM ' . get_table_name('jappix') . ' WHERE file=? AND lang=? AND ts > ?';
    $ts = date('Y-m-d H:i:s', time() - $this->ttl);
    $res = $rcmail->db->limitquery($sql, 0, 1, 'mini.js', $lg, $ts);
    $props = $rcmail->db->fetch_assoc($res);
    if(is_array($props)){
      $_SESSION['jappixmini'] = true;
    }
    else{
      $sql = 'DELETE FROM ' . get_table_name('jappix') . ' WHERE lang=?';
      $rcmail->db->query($sql, $lg);
      $this->require_plugin('http_request');
      $domain = slashify($rcmail->config->get('jappix_url', 'https://jappix.com'));
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
        $rcmail->db->query($sql, $lg, date('Y-m-d H:i:s', time()), 'mini.js', 'application/javascript', $minijs);
        $url = $domain . 'php/get.php?t=css&f=mini.css';
        $httpConfig['target'] = $url;
        $http->initialize($httpConfig);
        $http->execute();
        if($minicss = $http->result){
          $minicss = str_replace('./get.php?', 'plugins/jappix4roundcube/get.php?', $minicss);
          $sql = 'INSERT INTO ' . get_table_name('jappix') . ' (lang, ts, file, contenttype, content) VALUES (?, ?, ?, ?, ?)';
          $rcmail->db->query($sql, $lg, date('Y-m-d H:i:s', time()), 'mini.css', 'text/css', $minicss);
          $url = $domain . 'php/get.php?t=css&f=mini-ie.css';
          $httpConfig['target'] = $url;
          $http->initialize($httpConfig);
          $http->execute();
          if($minicss = $http->result){
            $minicss = str_replace('./get.php?', 'https://jappix.myroundcube.com/php/get.php?', $minicss);
            $sql = 'INSERT INTO ' . get_table_name('jappix') . ' (lang, ts, file, contenttype, content) VALUES (?, ?, ?, ?, ?)';
            $rcmail->db->query($sql, $lg, date('Y-m-d H:i:s', time()), 'mini-ie.css', 'text/css', $minicss);
            $res = $rcmail->db->affected_rows();
            $sql = 'SELECT * FROM ' . get_table_name('jappix') . ' WHERE lang=?';
            $res = $rcmail->db->query($sql, $lg);
            $i = 0;
            while($res && $prop = $rcmail->db->fetch_assoc($res)){
              $i++;
            }
            if($i < 3){
              $sql = 'DELETE FROM ' . get_table_name('jappix') . ' WHERE lang=?';
              $rcmail->db->query($sql, $lg);
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