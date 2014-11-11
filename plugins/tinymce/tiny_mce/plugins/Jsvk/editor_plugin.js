/**
 * $Id: editor_plugin.js 643 2009-07-09 15:19:14Z wingedfox $
 * $HeadURL: http://svn.debugger.ru/repos/jslibs/Virtual%20Keyboard/tags/VirtualKeyboard.v3.7.0/plugins/tinymce3/editor_plugin.js $
 *
 * Virtual Keyboard plugin for TinyMCE v3 editor.
 * (C) 2006-2007 Ilya Lebedev <ilya@lebedev.net>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * See http://www.gnu.org/copyleft/lesser.html
 *
 * Do not remove this comment if you want to use script!
 * Не удаляйте данный комментарий, если вы хотите использовать скрипт!
 *
 * @author Ilya Lebedev <ilya@lebedev.net>
 * @version $Rev: 643 $
 * @lastchange $Author: wingedfox $
 */
 
/**
 *
 * Modified for RoundCube TinyMCE plugin by Roland 'rosali' Liebl
 *
 */
function tinyMCE_height(e) {
  return document.getElementById(e);
}
function getEditorHeight() {
  return parseInt(tinyMCE_height(tinyMCE.activeEditor.id+'_ifr').style.height);
}
function setEditorHeight(h) {
  if(rcmail.env.skin == 'larry')
    h = h - 16;
  $('#composebody_path_row').hide();
  var old_h = tinyMCE_height(tinyMCE.activeEditor.id+'_ifr').style.height;
  old_h = old_h.replace('px','');
  rcmail.env.editor_height = old_h;
  var new_h = parseInt(old_h) + parseInt(h);
  new_h = Math.max(80, new_h);
  tinyMCE_height(tinyMCE.activeEditor.id+'_ifr').style.height = new_h +'px';
}

function adjust_css(state, skin, id){
  switch (skin) {
    case "winxp": height = 170;
      break;
    case "textual": height = 183;
      break;
    case "soberTouch": height = 252;
      break;
    case "small": height = 146;
      break;
    case "flat_grey": height = 232;
      break;
    case "air_small": height = 212;
      break;
    case "air_mid": height = 292;
      break;
    case "air_large": height = 372;
      break;
    default: height = 200;
  }
  
  if(state){
    setEditorHeight(-parseInt(height));
  }
  else{
    tinyMCE_height(tinyMCE.activeEditor.id+'_ifr').style.height = rcmail.env.editor_height +'px';
  }
} 
 
// Load plugin specific language pack
tinymce.PluginManager.requireLangPack('Jsvk');

tinymce.create('tinymce.plugins.VirtualKeyboard', new function () {
    var self = this
       ,_vk_skin = "winxp"
       ,_vk_layout = ""
       ,_vk_mode = ""
       ,_curId = null
       ,loaded

    /**
     *  external method 
     *
     *
     */
    self.VirtualKeyboard = function(ed, url) {
        ed.addCommand("mceVirtualKeyboard", function(){toggleKeyboard(ed)});

        _vk_skin = ed.getParam('vk_skin', _vk_skin);
        _vk_layout = ed.getParam('vk_layout', _vk_layout);
        _vk_mode = ed.getParam('vk_mode', _vk_mode);
    
        // Register buttons
        ed.addButton('Jsvk', { title: 'Jsvk.desc'
                              ,cmd: 'mceVirtualKeyboard'
                              ,image : url + '/img/jsvk.gif'
        });
        ed.onInit.add(_init);
    }

    self.getInfo = function() {
        return {
                longname : 'VirtualKeyboard plugin',
                author : 'Ilya Lebedev AKA WingedFox',
                authorurl : 'http://www.debugger.ru',
                infourl : 'http://www.debugger.ru/projects/virtualkeyboard/',
                version : "1.1"
        };
    }

    var _init = function() {
        if (loaded) return;
        loaded = true;

        var s = document.createElement('script');
        s.src = tinymce.baseURL +'/plugins/Jsvk/jscripts/vk_'+(_vk_mode.toLowerCase()||'loader')+'.js?vk_skin='+_vk_skin+'&vk_layout='+_vk_layout;
        s.type= "text/javascript";
        s.charset="UTF-8";
        document.getElementsByTagName('head')[0].appendChild(s);
    }

    /**
     *  Toggles keyboard state and moves it between the editors
     *
     *
     *
     *
     */
    var toggleKeyboard = function (ed) {
        var el
           ,vk = window[_vk_mode+'VirtualKeyboard'];
        if (this._curId === ed.editorId && vk.isOpen()) {
//mod
adjust_css(false, _vk_skin);
            vk.close();
            this._curId = null;
        } else {
            if (null != this._curId && (el = document.getElementById('VirtualKeyboard_'+this._curId))) {
                vk.close();
            }
            if (!(el = document.getElementById('VirtualKeyboard_'+ed.editorId))) {
                el = document.getElementById(ed.editorId+"_parent").getElementsByTagName('table')[0];
                el.insertRow(el.rows.length)
                el = el.rows[el.rows.length-1];
                el.id = 'VirtualKeyboard_' + ed.editorId;
                el.align = 'center';
                el.appendChild(document.createElement('td'));
            }
            el = el.firstChild;
            vk.open(ed.editorId+'_ifr',el);
//mod
adjust_css(true, _vk_skin);
            this._curId = ed.editorId;
        }
    }
});

// Register plugin
tinymce.PluginManager.add('Jsvk', tinymce.plugins.VirtualKeyboard);
