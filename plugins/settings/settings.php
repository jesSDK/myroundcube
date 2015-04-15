<?php
# 
# This file is part of MyRoundcube "settings" plugin.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (C) 2012 - 2015 Roland 'Rosali' Liebl
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class settings extends rcube_plugin
{
  public $task = 'settings';
  public $noajax = true;
  
  private $sections = array('userprofile', 'general', 'mailbox', 'compose', 'mailview', 'addressbook', 'folders', 'server');
  
  /* unified plugin properties */
  static private $plugin = 'settings';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/helper-plugin?settings" target="_blank">Documentation</a>';
  static private $version = '5.1.2';
  static private $date = '04-04-2015';
  static private $licence = 'All Rights reserved';
  static private $requirements = array(
    'Roundcube' => '1.1',
    'PHP' => '5.3',
    'required_plugins' => array('myrc_sprites' => 'require_plugin'),
  );
  static private $prefs = null;

  function init(){
    $rcmail = rcmail::get_instance();

    $this->require_plugin('myrc_sprites');

    $this->register_handler('plugin.account_sections', array($this, 'account_sections'));
    $this->add_hook('preferences_sections_list', array($this, 'account_link'));
    $this->add_hook('preferences_list', array($this, 'prefs_table'));
    $this->add_hook('render_page', array($this, 'render_page'));
    
    $skin  = $rcmail->config->get('skin');
    $this->include_stylesheet('skins/' . $skin . '/settings.css');

    $this->add_texts('localization/'); 
    $rcmail->output->add_label('settings.account');
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
  
  function render_page($p){
    if($p['template'] == 'settings'){
      $rcmail = rcmail::get_instance();
      $rcmail->output->add_script('$("#rcmrowserver").remove();', 'docready');
      if(get_input_value('_accountsettings', RCUBE_INPUT_GET)){
        $rcmail->output->add_script('$("#rcmrowaccountlink").trigger("mousedown").trigger("mouseup");', 'docready');
      }
    }
    if($p['template'] == 'settingsedit' && get_input_value('_section', RCUBE_INPUT_GPC) == 'server'){
      $rcmail = rcmail::get_instance();
      $rcmail->output->add_script('$(".boxtitle").html("<a id=\'accountlink\' href=\'./?_task=settings&_action=edit-prefs&_section=accountlink&_framed=1\'>" + rcmail.gettext("settings.account") + "</a>&nbsp;&raquo;&nbsp;" + $(".boxtitle").html());', 'docready');
    }
    return $p;
  }
  
  function account_link($args){
    $rcmail = rcmail::get_instance();
    $skin = $rcmail->config->get('skin');
    $temparr = array();
    foreach($this->sections as $key => $section){
      $temparr[$section] = $args['list'][$section];
      unset($args['list'][$section]);
    }
    $args['list']['userprofile'] = $temparr['userprofile'];
    $args['list']['general'] = $temparr['general'];
    $args['list']['mailbox'] = $temparr['mailbox'];
    $args['list']['compose'] = $temparr['compose'];
    $args['list']['mailview'] = $temparr['mailview'];
    $args['list']['mh_preferences'] = array();
    $args['list']['keyboard_shortcuts'] = array();
    $args['list']['identitieslink'] = array();
    $args['list']['addressbook'] = $temparr['addressbook'];
    $args['list']['addressbooksharing'] = array();
    $args['list']['jabber'] = array();
    $args['list']['folderslink'] = array();
    $args['list']['folders'] =  $temparr['folders'];
    if($skin == 'classic'){
      $args['list']['folders']['section'] = $args['list']['folders']['section'];
    }
    $args['list']['calendar'] = array();
    $args['list']['calendarsharing'] = array();
    $args['list']['plugin_manager'] = array();
    $args['list']['plugin_manager_settings'] = array();
    $args['list']['plugin_manager_admins'] = array();
    $args['list']['plugin_manager_customer'] = array();
    $args['list']['plugin_manager_update'] = array();
    $args['list']['accountslink'] = array();
    $args['list']['server'] = $temparr['server'];

    $parts = (array) $GLOBALS['settingsnav'];
    $temp = array();
    foreach($parts as $plugin => $props){
      if(class_exists($plugin)){
        $temp[$this->gettext($plugin . '.' . $props['label'])][$plugin] = $props;
      }
      else{
        unset($GLOBALS['settingsnav'][$plugin]);
      }
    }
    ksort($temp);
    $parts = $temp;
    $GLOBALS['settingsnav'] = array();
    foreach($parts as $label => $props){
      foreach($props as $plugin => $settings){
        if(class_exists($plugin)){
          $GLOBALS['settingsnav'][$plugin] = $settings;
        }
      }
    }
    $parts = (array) $rcmail->config->get('settingsnav', $GLOBALS['settingsnav']);
    foreach($parts as $plugin => $props){
      if(!class_exists($plugin)){
        unset($parts[$plugin]);
      }
    }
    if($defaults = $_SESSION['plugin_manager_defaults']){
      $active = $rcmail->config->get('plugin_manager_active', array());
      if(is_array($defaults)){
        foreach($defaults as $section => $plugins){
          foreach($plugins as $plugin => $props){
            if($props['active']){
              $active[$plugin] = 1;
            }
          }
        }
      }
      foreach($parts as $plugin => $props){
        if($active[$plugin] != 1){
          unset($parts[$plugin]);
        }
      }
    }
    if(class_exists('mysqladmin') && strtolower($rcmail->user->data['username']) == $rcmail->config->get('mysql_admin')){
      $hm = array('autoban', 'autoresponder', 'forwarding', 'login', 'accounts', 'signature', 'spamfilter');
      $detected = false;
      foreach($hm as $dsn){
        $c = $rcmail->config->get('db_hmail_' . $dsn . '_dsn');
        if(is_string($c)){
          $t = parse_url($c);
          if($t['user'] && $t['pass']){
            $parts = array_merge($parts, array( 'mysqladmin' =>
                array('part' => '', 'label' => 'pluginname', 'href' => './?_action=plugin.mysqladmin&pma_login=1&db=db_hmail_' . $dsn . '_dsn', 'onclick' => 'rcmail.set_cookie("PMA_referrer", document.location.href);', 'descr' => 'mysqladmin')
              )
            );
            $detected = true;
            break;
          }
        }
      }
    }
    if(!$detected){
      $parts = array_merge($parts, array( 'mysqladmin' =>
          array('part' => '', 'label' => 'pluginname', 'href' => './?_action=plugin.mysqladmin&pma_login=1&db=db_dsnw&dbt=users', 'onclick' => 'rcmail.set_cookie("PMA_referrer", document.location.href);', 'descr' => 'mysqladmin')
        )
      );
    }
    $parts = array_merge($parts, array( 'settings' =>
        array('part' => '', 'label' => 'serversettings', 'href' => './?_task=settings&_action=edit-prefs&_section=server&_framed=1', 'descr' => 'serversettings')
      )
    );
    if(count($parts) > 0){
      $_SESSION['settingsnav'] = $parts;
      $args['list']['accountlink']['id'] = 'accountlink';
      $args['list']['accountlink']['section'] = $this->gettext('account');
      if(strtolower($rcmail->user->data['username']) != strtolower($_SESSION['username'])){
        unset($args['list']['accountlink']);
      }
    }
    return $args;
  } 

  function account_sections(){
    $rcmail = rcmail::get_instance();

    //display a message if required by url
    if(isset($_GET['_msg'])){
      $rcmail->output->command('display_message', urldecode($_GET['_msg']), $_GET['_type']);
    }
    $parts = (array) $_SESSION['settingsnav'];
    $out = "<div id=\"userprefs-accountblocks\">\n";
    foreach($parts as $key => $part){
      if(!class_exists($key)){
        continue;
      }
      if(!empty($part['descr'])){
        $i++;
        $out .= "<div class=\"userprefs-accountblock\" id='accountsblock_$i'>\n";
        $out .= "<div class=\"userprefs-accountblock-border\">\n";
        $out .= "&raquo;&nbsp;<a class=\"plugin-description-link\" href=\"" . $part['href'] . "\" onclick='" . $part['onclick'] . "'>" . $this->gettext($part['descr'] . '.' . $part['label']) . "</a>\n";
        $out .= "</div>\n";
        $out .= "</div>\n";
        $out .= '
<script>
var element = $("#accountsblock_' . $i . '");
element.qtip({
  content: {title:\'' . addslashes($this->gettext($part['descr'] . '.' . $part['label'])) . '\', text: \'' . addslashes($this->gettext($part['descr'] . '.description')) . '\'},
  position: {
    my: "top left",
    at: "left bottom",
    target: element,
    viewport: $(window)
  },
  hide: {
    effect: function () { $(this).slideUp(5, function(){ $(this).dequeue(); }); }
  },
  style: {
    classes: "ui-tooltip-light"
  }
});
</script>
';
      }
    }

    $out .= "</div>\n<style>fieldset{border: none;}</style>\n";
    return $out;
  }

  function prefs_table($args){
    if(!get_input_value('_framed', RCUBE_INPUT_GPC) && $args['section'] == 'accountlink'){
      $args['blocks'][$args['section']]['options'] = array(
        'title'   => '',
        'content' => html::tag('div', array('id' => 'pm_dummy'), '')
      );
      return $args;
    }
    if ($args['section'] == 'accountlink') {
      $args['blocks']['main']['options']['accountlink']['title'] = "";
      $args['blocks']['main']['options']['accountlink']['content'] = $this->account_sections("");
      $this->include_script('settings.js');
    }
    return $args;
  }
}

?>