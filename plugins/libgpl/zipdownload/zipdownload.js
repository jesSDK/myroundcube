/**
 * ZipDownload plugin script
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (c) 2013-2014, The Roundcube Dev Team
 *
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

window.rcmail && rcmail.addEventListener('init', function(evt) {
    // register additional actions
    rcmail.register_command('download-eml', function() { rcmail_zipdownload('eml'); });
    rcmail.register_command('download-mbox', function() { rcmail_zipdownload('mbox'); });
    rcmail.register_command('download-maildir', function() { rcmail_zipdownload('maildir'); });

    // commands status
    rcmail.message_list && rcmail.message_list.addEventListener('select', function(list) {
        var selected = list.get_selection().length;

        rcmail.enable_command('download', selected > 0);
        rcmail.enable_command('download-eml', selected == 1);
        rcmail.enable_command('download-mbox', 'download-maildir', selected > 1);
    });

    // hook before default download action
    rcmail.addEventListener('beforedownload', rcmail_zipdownload_menu);

    // find and modify default download link/button
    $.each(rcmail.buttons['download'] || [], function() {
        var link = $('#' + this.id),
            span = $('span', link);

        if (!span.length) {
            span = $('<span>');
            link.html('').append(span);
        }

        span.text(rcmail.gettext('zipdownload.download'));
        rcmail.env.download_link = link;
    });
  });

var zipdownload_timeout_stack = [];
var zipdownload_mode;
function rcmail_zipdownload(mode)
{
    zipdownload_mode = mode;
    // default .eml download of single message
    if (mode == 'eml') {
        var uid = rcmail.get_single_uid();
        rcmail.goto_url('viewsource', {_uid: uid, _mbox: rcmail.get_message_mailbox(uid), _save: 1});
        return;
    }

    // multi-message download, use hidden form to POST selection
    if (rcmail.message_list && rcmail.message_list.get_selection().length > 1) {
        var inputs = [], form = $('#zipdownload-form'),
            post = rcmail.selection_post_data();

        post._mode = mode;
        post._token = rcmail.env.request_token;

        $.each(post, function(k, v) {
            if (typeof v == 'object' && v.length > 1) {
              for (var j=0; j < v.length; j++)
                  inputs.push($('<input>').attr({type: 'hidden', name: k+'[]', value: v[j]}));
            }
            else {
                inputs.push($('<input>').attr({type: 'hidden', name: k, value: v}));
            }
        });
        
        var ts = new Date().getTime();
        $('<iframe class="zipdownload_container" frameborder="0" height="0" width="0" name="_zipdownload_container_' + ts + '"></iframe>').appendTo('body');
        if (!form.length) {
            form = $('<form>').attr({
                    style: 'display: none',
                    method: 'POST',
                    action: '?_task=mail&_action=plugin.zipdownload.messages',
                    target: '_zipdownload_container_' + ts
                })
                .appendTo('body');
        }
        form.html('').append(inputs).submit();
        rcmail.set_busy(true, 'loading');
        rcmail.enable_command('download', false);
        rcmail_zipdownload_resume();
    }
}

function rcmail_zipdownload_resume()
{
    var zipdownload_timeout = window.setTimeout("rcmail_zipdownload(zipdownload_mode);", 10 * 1000);
    zipdownload_timeout_stack.push(zipdownload_timeout);
}

// display download options menu
function rcmail_zipdownload_menu(e)
{
    // show (sub)menu for download selection
    rcmail.command('menu-open', 'zipdownload-menu', e && e.target ? e.target : rcmail.env.download_link, e);

    // abort default download action
    return false;
}
