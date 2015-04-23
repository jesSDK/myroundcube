<?php
# 
# https://github.com/xrxca/markbuttons
# 
class markbuttons_core extends rcube_plugin
{
  public $task = 'mail';
  public $noajax = true;
  public $noframe = true;
  
  function init(){
    $this->add_hook('template_container', array($this, 'html_output'));
    $this->add_texts('localization/', true);
  }
  
  function mark_button($i, $skin) {
    if($skin == 'classic'){
      $icon = 'plugins/markbuttons/' .$this->local_skin_path(). '/images/' . $i . '.png';
      $temparr = getimagesize(INSTALL_PATH . $icon);
      return("\n      <a class='button markbutton' href='#' title='"
        . Q($this->gettext('mark' . $i))
        . "' onclick=\"return rcmail.command('mark','"
        . $i . "',this)\"><img align='top' src='"
        . $this->url($this->local_skin_path()) . '/images/' . $i
        . ".png' width='" . $temparr[0] . "' height='" . $temparr[1] . "' /> </a>");
    }
    else{
      return("\n      <a class='button markbutton disabled myrc_sprites " . $i . "' href='#' title='"
        . Q($this->gettext('mark' . $i))
        . "' onclick=\"return rcmail.command('mark','"
        . $i . "',this)\"><img align='top' src='program/resources/blank.gif' /></a>");
      }
  }

  function html_output($p) {
    if ($p['name'] == "listcontrols") {
      $rcmail = rcmail::get_instance();
      $skin = $rcmail->config->get('skin', 'larry');
      if($skin != 'classic'){
        $rcmail->output->add_header(html::tag('script', array('type' => 'text/javascript', 'src' => 'plugins/libgpl/markbuttons/markbuttons.js')));
        $this->include_stylesheet($this->local_skin_path()  . '/markbuttons.css');
      }
      $r = '<span id="markbuttons" style="margin-left: 20px">' 
        . Q($this->gettext('markbuttons_label')) . ':&nbsp;</span>';
      $r .= $this->mark_button('read', $skin);
      $r .= $this->mark_button('unread', $skin);
      $r .= $this->mark_button('flagged', $skin);
      $r .= $this->mark_button('unflagged', $skin);
      $p['content'] = $r . $p['content'];
    }
    return $p;
  }
}
?>