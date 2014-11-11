<?php
/**
 * moreuserinfo
 *
 *
 * @version 4.0.34 - 14.09.2014
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.com
 *
 **/

class moreuserinfo extends rcube_plugin
{

  public $task = 'mail|settings|addressbook|dummy|jappix';
  public $noajax = true;
  
  /* unified plugin properties */
  static private $plugin = 'moreuserinfo';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/moreuserinfo-plugin" target="_blank">Documentation</a>';
  static private $version = '4.0.34';
  static private $date = '14-09-2014';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '1.0',
    'PHP' => '5.3'
  );
  static private $prefs = null;
  static private $config_dist = 'config.inc.php.dist';

  function init()
  {
    $rcmail = rcmail::get_instance();
    if($rcmail->action == 'jappix.loadmini'){
      return;
    }
    $this->add_texts('localization/');
    if(!in_array('global_config', $rcmail->config->get('plugins'))){
      $this->load_config();
    }
    $this->register_action('plugin.moreuserinfo_show', array($this, 'frame'));
    $this->register_action('plugin.moreuserinfo', array($this, 'infostep'));
    $this->add_hook('render_page', array($this, 'showuser'));
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
  
  function frame()
  {
    $rcmail = rcmail::get_instance();
    $rcmail->output->add_script('window.setTimeout(\'$("#rcmrowaccountlink").trigger("mousedown").trigger("mouseup")\', 500);', 'docready');
    $rcmail->output->add_script('window.setTimeout(\'document.getElementById(rcmail.env.contentframe).src = "./?_task=settings&_action=plugin.moreuserinfo&_framed=1"\', 600);', 'docready');
    $rcmail->output->send("settings");
    exit;
  }
  
  function infostep()
  {
    $this->register_handler('plugin.moreuserinfo_html', array($this, 'infohtml'));
    rcmail::get_instance()->output->send('moreuserinfo.moreuserinfo');
  }

  function showuser($p)
  {
    $rcmail = rcmail::get_instance();
    if($rcmail->config->get('skin') == 'larry'){
      if(class_exists('tabbed')){
        $href = '#';
        $onclick = 'parent.location.href=\"./?_task=settings&_action=plugin.moreuserinfo_show\"';

      }
      else{
        $href = './?_task=settings&_action=plugin.moreuserinfo_show';
        $onclick = '';
      }
      if(!isset($_GET['_extwin'])){
        $rcmail->output->add_script('$(".topleft").html($(".topleft").html() + "<a onclick=\'' . $onclick . '\'' . ' id=\'accountinformationlink\' href=\'' . $href . '\'>' . $this->gettext('accountinformation') . '</a>");', 'docready');
      }
    }
    if($p['template'] != "mail")
      return $p;

    if(isset($_SESSION['temp']) || strtolower($rcmail->task) != "mail")
      return $p; 

    $skin  = $rcmail->config->get('skin');
    $_skin = get_input_value('_skin', RCUBE_INPUT_POST);

    if($_skin != "")
      $skin = $_skin;

    // abort if there are no css adjustments
    if(!file_exists('plugins/moreuserinfo/skins/' . $skin . '/moreuserinfo.css')){
      if(!file_exists('plugins/moreuserinfo/skins/classic/moreuserinfo.css'))   
        return $p;
      else
        $skin = "classic";
    }

    $this->include_stylesheet('skins/' . $skin . '/moreuserinfo.css');
    $browser = new rcube_browser;
    if($browser->ie && $browser->ver == 6){
      $this->include_stylesheet('skins/' . $skin . '/ie6.css');	
    }
    
    $user = $rcmail->user->data['username'];
    if(strlen($user) > 20)
      $user = substr($user,0,20) . "...";

    $skin = $rcmail->config->get('skin', 'classic');
    if(!class_exists('accounts') && $skin != 'larry')
      $rcmail->output->add_footer('<div id="showusername"><a title="' . $this->gettext('userinfo', 'moreuserinfo') . '" href="./?_task=settings&_action=plugin.moreuserinfo_show">' . $user . '&nbsp;</a></div>');

    return $p;

  }

  function infohtml()
  {
    $rcmail = rcmail::get_instance();

    $user = $rcmail->user;
    $username = $user->data['username'];
    $temp = explode('@', $username);
    $domainpart = $temp[1] ? $temp[1] : 'default';
    $skin = $rcmail->config->get('skin', 'classic');
    $this->include_script('flashclipboard.js');
    $rcmail->output->add_footer(html::tag('div', array('id' => 'zclipdialog', 'title' => $this->gettext('copiedtoclipboard'))));
    $icon = '&nbsp;' . html::tag('img', array('class' => 'zclip', 'src' => 'plugins/moreuserinfo/skins/' . $skin . '/images/clipboard.png', 'title' => $this->gettext('copytoclipboard'), 'alt' => $this->gettext('copytoclipboard'), 'align' => 'baseline'));


    $table = new html_table(array('class' => 'propform propform_settings', 'cols' => 2, 'cellpadding' => 3));
    
    $table->add('title', html::tag('h3', null, Q($this->gettext('mainoptions') . ':')));
    $table->add('', '');
    $date_format = $rcmail->config->get('date_format', 'm/d/Y') . ' ' . $rcmail->config->get('time_format', 'H:i');
    if(date('Y', strtotime($user->data['created'])) > 1970){
      $created = new DateTime($user->data['created']);
      $table->add('title', '&raquo;&nbsp;' . Q($this->gettext('created') . ':'));
      $table->add('', Q(date_format($created, $date_format)));
    }
    $lastlogin = new DateTime($user->data['last_login']);
    $table->add('title', '&raquo;&nbsp;' . Q($this->gettext('lastlogin') . ':'));
    $table->add('', Q(date_format($lastlogin, $date_format)));
    $identity = $user->get_identity();
    $table->add('title', '&raquo;&nbsp;' . Q($this->gettext('defaultidentity') . ':'));
    $table->add('', Q($identity['name'] . ' <' . $identity['email'] . '>'));
    
    $table->add('title', html::tag('h3', null, Q($this->gettext('mailbox') . ':')));
    $table->add('', '');
    $conf = $rcmail->config->get('moreuserinfo', array());
    foreach($conf as $service => $domains){
      $table->add('title', html::tag('b', null, '&raquo;&nbsp;' . Q($service . ':')));
      $table->add('', '&nbsp;');
      $domainpart = $temp[1];
      $domainpart = (!$domains[ $domainpart ] ? 'all' : $temp[1]);
      foreach($domains as $domain => $details){
        $i = 0;
        if($domainpart == $domain){
          foreach($details as $detail => $setting){
            $i ++;
            $label = $this->gettext($detail);
            if(substr($label, 0, 1) == '['){
              $label = $detail;
            }
            $table->add('title', '&nbsp;&raquo;&nbsp;' . $label . ':');
            $label = $this->gettext($setting);
            if(substr($label, 0, 1) == '['){
              $label = $setting;
              $label = str_replace("{DOMAIN.TLD}", $temp[1], $label);
              $label = str_replace("{DOMAIN}", substr($temp[1], 0, strrpos($temp[1],".")), $label);
            }
            $table->add('', html::tag('span', null, Q($label)) . (($i ==2 || $i ==3) ? $icon : ''));
          }
        }
      } 
    }
    $cals = array();
    $user = $username;
    if(class_exists('calendar_plus')){
      $sql = 'SELECT * FROM ' . get_table_name('calendars') . ' WHERE user_id=?';
      $result = $rcmail->db->query($sql, $rcmail->user->ID);
      while($result && $cal = $rcmail->db->fetch_assoc($result)){
        $sql = 'SELECT * FROM ' . get_table_name('calendars_caldav_props') . ' WHERE obj_id=?';
        $result2 = $rcmail->db->limitquery($sql, 0, 1, $cal['calendar_id']);
        if($result2 && $props = $rcmail->db->fetch_assoc($results)){
          $cals[$cal['name']] = array_merge($cal, $props);
        }
      }
      $clients = '';
      $i = 0;
      if(count($cals) > 0){
        ksort($cals);
        $i = 1;
        $table->add('title', html::tag('h3', null, $this->gettext('calendars') . '&nbsp;(CalDAV-URLs)&sup' . $i . ';:'));
        $table->add('', '');
        foreach($cals as $key => $caldav){
          $temp = explode('?', $caldav['url'], 2);
          $url = slashify($temp[0]) . ($temp[1] ? ('?' . $temp[1]) : '');
          if(is_array($repl)){
            foreach($repl as $key1 => $val){
              $url = str_replace($key1, $val, $url);
            }
          }
          $table->add('title', '&raquo; ' . $key);
          $table->add('', html::tag('span', null, str_replace('%u', str_replace('@', urlencode('@'), $user), $url)) . $icon);
        }
      }
      if($i > 0 && $rcmail->config->get('moreuserinfo_show_tutorial_links', true)){
        $clients .= html::tag('hr') . '&sup' . $i . ';&nbsp;' . sprintf($this->gettext('clients'), 'CalDAV') . ':' . html::tag('br') . '&nbsp;&nbsp;- ' . html::tag('a', array('href' => 'http://www.mozilla.org/en-US/thunderbird/all.html', 'target' => '_blank'), 'Thunderbird');
        $clients .= ' + ' . html::tag('a', array('href' => 'http://www.sogo.nu/english/downloads/frontends.html', 'target' => '_blank'), 'Lightning');
        $url = $rcmail->config->get('caldav_thunderbird','http://myroundcube.com/myroundcube-plugins/thunderbird-caldav');
        $clients .= html::tag('a', array('href' => $url, 'target' =>'_blank'), html::tag('div', array('style' => 'display:inline;float:right;'), 'Thunderbird ' . $this->gettext('tutorial')));
        $url = $rcmail->config->get('caldav_android_app','https://play.google.com/store/apps/details?id=org.dmfs.caldav.lib&hl=en');
        $clients .= html::tag('br') . '&nbsp;&nbsp;- ' . html::tag('a', array('href' => 'http://www.android.com/', 'target' => '_blank'), 'Android') . ' + ' . html::tag('a', array('href' => $url, 'target' => '_blank'), 'CalDAV-sync');
        $url = $rcmail->config->get('caldav_android','http://myroundcube.com/myroundcube-plugins/android-caldav');
        $clients .= html::tag('a', array('href' => $url, 'target' =>'_blank'), html::tag('div', array('style' => 'display:inline;float:right;'), 'Android ' . $this->gettext('tutorial')));
        $url = $rcmail->config->get('caldav_iphone','http://myroundcube.com/myroundcube-plugins/iphone-caldav');
        $clients .= html::tag('br') . '&nbsp;&nbsp;- ' . html::tag('a', array('href' => 'http://www.apple.com/iphone/', 'target' => '_blank'), 'iPhone') . html::tag('a', array('href' => $url, 'target' => '_blank'), html::tag('div', array('style' => 'display:inline;float:right;'), 'iPhone ' . $this->gettext('tutorial'))) . html::tag('br');
      }
    }
    $addressbooks = array();
    if(class_exists('carddav')){
      $query = "SELECT user_id, username, password, url, label from " . get_table_name('carddav_server') . " WHERE user_id=?";
      $sql_result = $rcmail->db->query($query, $rcmail->user->ID);
      while ($sql_result && ($sql_arr = $rcmail->db->fetch_assoc($sql_result))) {
        $addressbooks[$sql_arr['label']] = $sql_arr;
      }
    }
    if(count($addressbooks) > 0){
      $i ++;
      $table->add('title', html::tag('h3', null, $this->gettext('addressbooks') . '&nbsp;(CardDAV-URLs)&sup' . $i . ';:'));
      $table->add('', '');
      ksort($addressbooks);
      $repl = $rcmail->config->get('carddav_url_replace', false);
      foreach($addressbooks as $key => $addressbook){
        $temp = explode('?', $addressbook['url'], 2);
        $url = slashify($temp[0]) . ($temp[1] ? ('?' . $temp[1]) : '');
         if(is_array($repl)){
          foreach($repl as $key1 => $val){
            $url = str_replace($key1, $val, $url);
          }
        }
        $table->add('title', '&raquo; ' . $key);
        $table->add('', html::tag('span', null, Q(str_replace('%u', $user, str_replace('@', urlencode('@'), $url)))) . $icon);
      }
      if($clients == '' && $rcmail->config->get('moreuserinfo_show_tutorial_links', true)){
        $clients = html::tag('hr');
      }
      if($i > 0 && $rcmail->config->get('moreuserinfo_show_tutorial_links', true)){
        $clients .= '&sup' . $i . ';&nbsp;' . sprintf($this->gettext('clients'), 'CardDAV') . ':' . html::tag('br') . '&nbsp;&nbsp;- ' . html::tag('a', array('href' => 'http://www.mozilla.org/en-US/thunderbird/all.html', 'target' => '_blank'), 'Thunderbird');
        $url = $rcmail->config->get('carddav_thunderbird','http://myroundcube.com/myroundcube-plugins/thunderbird-carddav');
        $clients .= ' + ' . html::tag('a', array('href' => 'http://www.sogo.nu/english/downloads/frontends.html', 'target' => '_blank'), 'SOGo Connector') . html::tag('a', array('href' => $url, 'target' =>'_blank'), html::tag('div', array('style' => 'display:inline;float:right;'), 'Thunderbird ' . $this->gettext('tutorial')));
        $url = $rcmail->config->get('carddav_android_app','https://play.google.com/store/apps/details?id=org.dmfs.carddav.sync&hl=en');
        $clients .= html::tag('br') . '&nbsp;&nbsp;- ' . html::tag('a', array('href' => 'http://www.android.com/', 'target' => '_blank'), 'Android') . ' + ' . html::tag('a', array('href' => $url, 'target' => '_blank'), 'CardDAV-sync');
        $url = $rcmail->config->get('carddav_android_app_editor','https://play.google.com/store/apps/details?id=org.dmfs.android.contacts&hl=en');
        if($url){
          $clients .=  ' + ' . html::tag('a', array('href' => $url, 'target' => '_blank'), 'Contact Editor');
        }
        $url = $rcmail->config->get('carddav_android','http://myroundcube.com/myroundcube-plugins/android-carddav');
        $clients .= html::tag('a', array('href' => $url, 'target' =>'_blank'), html::tag('div', array('style' => 'display:inline;float:right;'), 'Android ' . $this->gettext('tutorial')));
        $url = $rcmail->config->get('carddav_iphone','http://myroundcube.com/myroundcube-plugins/iphone-carddav');
        $clients .= html::tag('br') . '&nbsp;&nbsp;- ' . html::tag('a', array('href' => 'http://www.apple.com/iphone/', 'target' => '_blank'), 'iPhone') . html::tag('a', array('href' => $url, 'target' => '_blank'), html::tag('div', array('style' => 'display:inline;float:right;'), 'iPhone ' . $this->gettext('tutorial')));
      }
    }
    $out  = $out .= html::tag('fieldset', array('class' => 'main'), html::tag('legend',  null, $this->gettext('userinfo') . ' ::: ' . $_SESSION['username']) . $table->show() . $clients);
    return $out;
  }
}
?>