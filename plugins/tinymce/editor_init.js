// Initialize HTML editor

function rcmail_editor_init(config)
{
  var ret, conf = {
      script_url: 'plugins/tinymce/tiny_mce/tiny_mce_gzip.php',
      mode: 'textareas',
      editor_selector: 'mce_editor',
      apply_source_formatting: true,
      theme: 'advanced',
      language: config.lang,
      content_css: config.skin_path + '/editor_content.css',
      theme_advanced_toolbar_location: 'top',
      theme_advanced_toolbar_align: 'left',
      theme_advanced_buttons3: '',
      extended_valid_elements: 'font[face|size|color|style],span[id|class|align|style]',
      relative_urls: false,
      remove_script_host: false,
      gecko_spellcheck: true,
      convert_urls: false, // #1486944
      external_image_list: window.rcmail_editor_images,
      rc_client: rcmail
    };

  if (config.mode == 'identity') {
    $.extend(conf, {
      plugins:  eval(rcmail.env.tinymce_plugins_identity),
      theme_advanced_buttons1: eval(rcmail.env.tinymce_buttons_identity_row1),
      theme_advanced_buttons2: eval(rcmail.env.tinymce_buttons_identity_row2)
    });
  }
  else { // mail compose
    $.extend(conf, {
      plugins: eval(rcmail.env.tinymce_plugins_compose),
      theme_advanced_buttons1: eval(rcmail.env.tinymce_buttons_compose_row1),
      theme_advanced_buttons2: eval(rcmail.env.tinymce_buttons_compose_row2),
      spellchecker_languages: (rcmail.env.spellcheck_langs ? rcmail.env.spellcheck_langs : 'Dansk=da,Deutsch=de,+English=en,Espanol=es,Francais=fr,Italiano=it,Nederlands=nl,Polski=pl,Portugues=pt,Suomi=fi,Svenska=sv'),
      spellchecker_rpc_url: '?_task=utils&_action=spell_html',
      spellchecker_enable_learn_rpc: config.spelldict,
      accessibility_focus: false,
      oninit: 'rcmail_editor_callback'
    });

    // add handler for spellcheck button state update
    conf.setup = function(ed) {
      ed.onSetProgressState.add(function(ed, active) {
        if (!active)
          rcmail.spellcheck_state();
      });
      ed.onKeyPress.add(function(ed, e) {
          rcmail.compose_type_activity++;
      });
    }
  }

  // support external configuration settings e.g. from skin
  if (window.rcmail_editor_settings)
    $.extend(conf, window.rcmail_editor_settings);
  window.rcmail_editor_settings = conf;
  
  if($("input[name='_is_html']").val() == '0'){
    //rcmail.triggerEvent('editor_init', { mode:'plain' });
  }

  if($('textarea.mce_editor').get(0)){
    rcmail.env.tinymce_initialized = true;
    try{
      $('textarea.mce_editor').tinymce(conf);
    }
    catch(e){
      tinyMCE.init(conf);
    }
  }
}

rcmail.toggle_editor = function(props){
  rcmail.stop_spellchecking();

  if (props.mode == 'html') {
    rcmail.plain2html($('#'+props.id).val(), props.id);
    if(!rcmail.env.tinymce_initialized){
      $('#' + props.id).addClass('mce_editor');
      if($('textarea.mce_editor').get(0)){
        if($('textarea.mce_editor').tinymce){
          $('textarea.mce_editor').tinymce(window.rcmail_editor_settings);
        }
        else{
          tinyMCE.init(window.rcmail_editor_settings);
        }
      }
    }
    else{
      tinyMCE.execCommand('mceAddControl', false, props.id);
    }

    if (rcmail.env.default_font)
      setTimeout(function() {
        $(tinyMCE.get(props.id).getBody()).css('font-family', rcmail.env.default_font);
      }, 500);
  }
  else {
    var thisMCE = tinyMCE.get(props.id), existingHtml;

    if (existingHtml = thisMCE.getContent()) {
      if (!confirm(rcmail.get_label('editorwarning'))) {
        return false;
      }
      rcmail.html2plain(existingHtml, props.id);
    }
    tinyMCE.execCommand('mceRemoveControl', false, props.id);
  }
};

rcmail.change_identity = function(obj, show_sig){
  if (!obj || !obj.options)
    return false;

  if (!show_sig)
    show_sig = rcmail.env.show_sig;

  // first function execution
  if (!rcmail.env.identities_initialized) {
    rcmail.env.identities_initialized = true;
    if (rcmail.env.show_sig_later)
      rcmail.env.show_sig = true;
    if (rcmail.env.opened_extwin)
      return;
  }

  var i, rx, cursor_pos, p = -1,
    id = obj.options[obj.selectedIndex].value,
    input_message = $("[name='_message']"),
    message = input_message.val(),
    is_html = ($("input[name='_is_html']").val() == '1'),
    sig = rcmail.env.identity,
    delim = rcmail.env.recipients_separator,
    rx_delim = RegExp.escape(delim),
    headers = ['replyto', 'bcc'];
  // update reply-to/bcc fields with addresses defined in identities
  for (i in headers) {
    var key = headers[i],
      old_val = sig && rcmail.env.identities[sig] ? rcmail.env.identities[sig][key] : '',
      new_val = id && rcmail.env.identities[id] ? rcmail.env.identities[id][key] : '',
      input = $('[name="_'+key+'"]'), input_val = input.val();
    
    if (!headers.hasOwnProperty(key)) {
      continue;
    }
      
    // remove old address(es)
    if (old_val && input_val) {
      rx = new RegExp('\\s*' + RegExp.escape(old_val) + '\\s*');
      input_val = input_val.replace(rx, '');
    }

    // cleanup
    rx = new RegExp(rx_delim + '\\s*' + rx_delim, 'g');
    input_val = input_val.replace(rx, delim);
    rx = new RegExp('^[\\s' + rx_delim + ']+');
    input_val = input_val.replace(rx, '');

    // add new address(es)
    if (new_val && input_val.indexOf(new_val) == -1 && input_val.indexOf(new_val.replace(/"/g, '')) == -1) {
      if (input_val) {
        rx = new RegExp('[' + rx_delim + '\\s]+$')
        input_val = input_val.replace(rx, '') + delim + ' ';
      }

      input_val += new_val + delim + ' ';
    }

    if (old_val || new_val)
      input.val(input_val).change();
  }

  // enable manual signature insert
  if (rcmail.env.signatures && rcmail.env.signatures[id]) {
    rcmail.enable_command('insert-sig', true);
    rcmail.env.compose_commands.push('insert-sig');
  }
  else
    rcmail.enable_command('insert-sig', false);

  if (!is_html) {
    // remove the 'old' signature
    if (show_sig && sig && rcmail.env.signatures && rcmail.env.signatures[sig]) {
      sig = rcmail.env.signatures[sig].text;
      sig = sig.replace(/\r\n/g, '\n');

      p = rcmail.env.top_posting ? message.indexOf(sig) : message.lastIndexOf(sig);
      if (p >= 0)
        message = message.substring(0, p) + message.substring(p+sig.length, message.length);
    }
    // add the new signature string
    if (show_sig && rcmail.env.signatures && rcmail.env.signatures[id]) {
      sig = rcmail.env.signatures[id].text;
      sig = sig.replace(/\r\n/g, '\n');

      if (rcmail.env.top_posting) {
        if (p >= 0) { // in place of removed signature
          message = message.substring(0, p) + sig + message.substring(p, message.length);
          cursor_pos = p - 1;
        }
        else if (!message) { // empty message
          cursor_pos = 0;
          message = '\n\n' + sig;
        }
        else if (pos = rcmail.get_caret_pos(input_message.get(0))) { // at cursor position
          message = message.substring(0, pos) + '\n' + sig + '\n\n' + message.substring(pos, message.length);
          cursor_pos = pos;
        }
        else { // on top
          cursor_pos = 0;
          message = '\n\n' + sig + '\n\n' + message.replace(/^[\r\n]+/, '');
        }
      }
      else {
        message = message.replace(/[\r\n]+$/, '');
        cursor_pos = !rcmail.env.top_posting && message.length ? message.length+1 : 0;
        message += '\n\n' + sig;
      }
    }
    else
      cursor_pos = rcmail.env.top_posting ? 0 : message.length;

    input_message.val(message);

    // move cursor before the signature
    rcmail.set_caret_pos(input_message.get(0), cursor_pos);
  }
  else if (show_sig && rcmail.env.signatures) {  // html
    var editor = tinyMCE.get(rcmail.env.composebody),
      sigElem = editor.dom.get('_rc_sig');

    // Append the signature as a div within the body
    if (!sigElem) {
      var body = editor.getBody(),
        doc = editor.getDoc();

      sigElem = doc.createElement('div');
      sigElem.setAttribute('id', '_rc_sig');

      if (rcmail.env.top_posting) {
        // if no existing sig and top posting then insert at caret pos
        editor.getWin().focus(); // correct focus in IE & Chrome

        var node = editor.selection.getNode();
        if (node.nodeName == 'BODY') {
          // no real focus, insert at start
          body.insertBefore(sigElem, body.firstChild);
          body.insertBefore(doc.createElement('br'), body.firstChild);
        }
        else {
          body.insertBefore(sigElem, node.nextSibling);
          body.insertBefore(doc.createElement('br'), node.nextSibling);
        }
      }
      else {
        if (bw.ie)  // add empty line before signature on IE
          body.appendChild(doc.createElement('br'));

        body.appendChild(sigElem);
      }
    }

    if (rcmail.env.signatures[id])
      sigElem.innerHTML = rcmail.env.signatures[id].html;
  }

  rcmail.env.identity = id;
  rcmail.triggerEvent('change_identity');
  return true;
};