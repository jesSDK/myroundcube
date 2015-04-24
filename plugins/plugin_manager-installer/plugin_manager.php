<?php
# 
# This file is part of Roundcube "plugin_manager" plugin.
# 
# Your are not allowed to distribute this file or parts of it.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2013 Roland 'Rosali' Liebl - all rights reserved.
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class plugin_manager extends rcube_plugin{

  private $rcmail;
  private $out;
  private $svn = 'https://myroundcube.com/myroundcube-plugins/plugin-manager';
  
  function init(){
    $this->rcmail = rcmail::get_instance();
    $this->out = html::tag('div', array('style' => 'font-size: 12px; text-align: justify; position: absolute; margin-left: auto; left: 50%; margin-left: -250px; width: 500px;'),
      html::tag('h3', null, 'Welcome to MyRoundcube Plugins - Plugin Manager Installer') .
      html::tag('span', null, 'Please ' .
        html::tag('a', array('href' => $this->svn), 'download') .
          ' Plugin Manager package and upload the entire package to your Roundcube\'s plugin folder.' . html::tag('br') . html::tag('br') .
          ' If you are prompted to overwrite <i>"./plugins/plugin_manager"</i> please do so.'
        ) . html::tag('br') . html::tag('br') .
        html::tag('div', array('style' => 'display: inline; float: left'),
        html::tag('a', array('href' => 'javascript:void(0)', 'onclick' => 'document.location.href=\'./\''), $this->gettext('done'))
      )
    );
    $this->register_handler('plugin.body', array($this, 'download'));
    $this->rcmail->output->send('plugin');
  }
  
  function download($p){
    return $this->out;
  }
}
?>