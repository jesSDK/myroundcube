// find me: Todo
/*
   Move some stuff to class file:
   localization, time/date mapping etc.
*/

var prefs;
function get_pref(key){
  if(!prefs){
    prefs = rcmail.local_storage_get_item('prefs.larry', {});
  }

  if(prefs[key] == null){
    var cookie = rcmail.get_cookie(key);
    if(cookie != null){
      prefs[key] = cookie;
      if(rcmail.local_storage_set_item('prefs.larry', prefs)){
        rcmail.set_cookie(key, cookie, new Date());  // expire cookie
      }
    }
  }
  return prefs[key];
}

function planner_minimalmode(){
  var minmode = $('.minimal #taskbar .button-inner').is(':visible');
  if(minmode == true){
    $('#planner_controls_container').css('top', '62px');
    $('#control_bar').css('top', '92px');
    $('#planner_items').css('top', '112px');
    $('#filter_bar').attr('style', 'top:' + (parseInt($('#filter_bar').css('top')) - 25) + 'px');
  }
  else{
    $('#planner_controls_container').css('top', '85px');
    $('#control_bar').css('top', '115px');
    $('#planner_items').css('top', '135px');
    $('#filter_bar').attr('style', 'top:' + (parseInt($('#filter_bar').css('top')) + 25) + 'px');
  }
}

$(document).ready(function() {
  // disable taskbar button
  $('#planner_button').attr('href', '#');

  // Larry minimalmode
  if(rcmail.env.skin == 'larry'){
    var minmode = $('.minimal #taskbar .button-inner').is(':visible');
    if(minmode == true){
      $('#planner_controls_container').css('top', '62px');
      $('#control_bar').css('top', '92px');
      $('#planner_items').css('top', '112px');
    }
    $('.minmodetoggle').click(function(){
      window.setTimeout('planner_minimalmode();', 100);
    });
  }
  
  $('#planner_button').addClass('button-selected');
  
  // disable right click
  $(document).bind('contextmenu', function(){
    //return false;
  });
  
  // add event listeners
  rcmail.addEventListener('plugin.planner_drop_success', function(response) {
    rcmail.http_post('plugin.planner_remove', '_id=' + response);
  });
  rcmail.addEventListener('plugin.planner_birthdays', function(response) {
    if(rcmail.env.planner_filter == 'birthdays'){
      $('#fall').trigger('click');
    }
    rcmail.http_post('plugin.planner_retrieve', '_p=' + rcmail.env.planner_items);
  });
  rcmail.addEventListener('plugin.planner_getprefs', function(response) {
    rcmail.env.planner_items = response[0];
    $("#controls").val(response[0]);
    $("#controls").val(rcmail.env.planner_items);
    $('.lcontrol').css('font-weight', 'normal');
    $('#c'+ rcmail.env.planner_items).css('font-weight', 'bold');
    rcmail.http_post('plugin.planner_retrieve', '_p=' + rcmail.env.planner_items);
  });
  rcmail.addEventListener('plugin.planner_replace', function(response) {
    $('#' + response[0]).html(response[1]);
    $('#' + response[0]).removeClass('drag_nodate');
    $('#' + response[0]).removeClass('drag_datetime');
    $('#' + response[0]).addClass(response[3]);
    if(response[2] === true){
      $('#' + response[0]).addClass('today');
    }
    planner_init();
    planner_show(rcmail.env.planner_items);
    $("#controls").val(rcmail.env.planner_items);
    $('.lcontrol').css('font-weight', 'normal');
    $('#c'+ rcmail.env.planner_items).css('font-weight', 'bold');
    planner_overlay_toggle(false);
  });
  rcmail.addEventListener('plugin.planner_insert', function(response) {
   $('#planner_items_list').append('<li class="' + response[2] + '" id="' + response[0] + '">' + response[1] + '</li>');
    planner_init();
    planner_drag();
    planner_show(rcmail.env.planner_items);
    $("#controls").val(rcmail.env.planner_items);
    $('.lcontrol').css('font-weight', 'normal');
    $('#c'+ rcmail.env.planner_items).css('font-weight', 'bold');
    planner_overlay_toggle(false);
  });
  rcmail.addEventListener('plugin.planner_retrieve', function(response) {
    rcmail.env.planner_last_filter = false;
    rcmail.env.planner_blink = false;
    
    // inject planner list
    $('#planner_items').html(response[0]);
    
    // init planner list
    planner_init(response[1]);
    
    // show saved view
    planner_show(rcmail.env.planner_items);
    $("#controls").val(rcmail.env.planner_items);
    $('.lcontrol').css('font-weight', 'normal');
    $('#c'+ rcmail.env.planner_items).css('font-weight', 'bold');

    // birthdays
    if(response[4] == 0){
      $('#cbirthdays').prop('checked', false);
      $('#cbirthdays').attr('title', rcmail.gettext('planner.birthdays_yes'));
      $('#birthdays_count').hide();
    }
    else{
      if(response[1] > 0){
        $('#birthdays_count').text(response[1]);
        if(response[1] == 1){
          $('#bdlabel').html(rcmail.gettext('planner.birthdays_singular'));
        }
        else{
          $('#bdlabel').html(rcmail.gettext('planner.birthdays'));
        }
      }
    }
    
    // timezone difference
    rcmail.env.planner_tzoffset = parseInt(response[6]) + new Date().getTimezoneOffset() * 60;

    // localization
    var dplocalization = new Array();
    dplocalization = ($("#planner_datetimepicker").datepicker("option", "monthNamesShort"));
    var enlocalization = new Array();
    enlocalization[0]  = 'Jan';
    enlocalization[1]  = 'Feb';
    enlocalization[2]  = 'Mar';
    enlocalization[3]  = 'Apr';
    enlocalization[4]  = 'May';
    enlocalization[5]  = 'Jun';
    enlocalization[6]  = 'Jul';
    enlocalization[7]  = 'Aug';
    enlocalization[8]  = 'Sep';
    enlocalization[9]  = 'Oct';
    enlocalization[10] = 'Nov';
    enlocalization[11] = 'Dec';
    for(var i in enlocalization){
      rcmail.add_label('planner.' + enlocalization[i], dplocalization[i]);
      if(enlocalization[i] != dplocalization[i]){
        $('.' + enlocalization[i]).html(planner_html_sanitize(dplocalization[i]));
      }
    }

    planner_overlay_toggle(false);
    
    // overdue timer
    window.setTimeout("planner_show_overdue_count()", 1000 * 60);
    
    // drag & drop
    planner_drag();

  });
  rcmail.addEventListener('plugin.planner_reload', function(response) {
    rcmail.env.planner_last_filter = false;
    $('#c' + rcmail.env.planner_items).css('font-weight', 'bold');
    rcmail.http_post('plugin.planner_retrieve', '_p=' + rcmail.env.planner_items);
  });
  rcmail.addEventListener('plugin.planner_success', function(response) {
    if(response[2]){
      // sticky notes drag & drop (function is bundled with sticky_notes plugin)
      if(typeof planner_refresh_notes_count == 'function'){
        planner_refresh_notes_count(response[2]);
      }
    }
    switch(response[0]){
      case 'done':
        $('#' + response[1]).children().next().removeClass('done');
        $('#' + response[1]).children().next().addClass('delete');
        $('#done_count').html(parseInt($('#done_count').html()) + 1);
        $('#all_count').html(Math.max(parseInt($('#all_count').html()) - 1, 0));
        $('#today_count').html(Math.max(parseInt($('#today_count').html()) - 1, 0));
        $('#tomorrow_count').html(Math.max(parseInt($('#tomorrow_count').html()) - 1, 0));
        $('#week_count').html(Math.max(parseInt($('#week_count').html()) - 1, 0));
        rcmail.env.planner_counts.done = rcmail.env.planner_counts.done + 1;
        rcmail.env.planner_counts.today = Math.max(rcmail.env.planner_counts.today - 1, 0);
        rcmail.env.planner_counts.tomorrow = Math.max(rcmail.env.planner_counts.tomorrow - 1, 0);
        rcmail.env.planner_counts.week = Math.max(rcmail.env.planner_counts.week - 1, 0);
        planner_show_overdue_count();
        break;
      case 'unstar':
        $('#' + response[1]).children().removeClass('star');
        $('#' + response[1]).children().addClass('nostar');
        $('#' + response[1]).children().attr('title', rcmail.gettext('planner.star_plan'));
        $('#starred_count').html(Math.max(parseInt($('#starred_count').html()) - 1, 0));
        rcmail.env.planner_counts.starred = Math.max(rcmail.env.planner_counts.starred - 1, 0);
        if(rcmail.env.planner_items == 'starred'){
          $('#' + response[1]).remove();
        }
        break;
      case 'star':
        $('#' + response[1]).children().removeClass('nostar');
        $('#' + response[1]).children().addClass('star');
        $('#' + response[1]).children().attr('title', rcmail.gettext('planner.unstar_plan'));
        rcmail.env.planner_counts.starred = rcmail.env.planner_counts.starred + 1;
        $('#starred_count').html(parseInt($('#starred_count').html()) + 1);
        break;
      case 'delete':
        $('#' + response[1]).children().next().removeClass('delete');
        $('#' + response[1]).children().next().addClass('remove');
        $('#done_count').html(Math.max(parseInt($('#done_count').html()) - 1, 0));
        $('#deleted_count').html(parseInt($('#deleted_count').html()) + 1);
        rcmail.env.planner_counts.done = Math.max(rcmail.env.planner_counts.done - 1, 0);
        rcmail.env.planner_counts.deleted = rcmail.env.planner_counts.deleted + 1;
        planner_items = 0;
        $('#planner_items_list li').each(function(){
          if($(this).is(":visible")){
            planner_items ++;
          }
        });
        if(planner_items == 0){
          $("#controls").val('today');
          rcmail.env.planner_items = 'today';
          $('.lcontrol').css('font-weight', 'normal');
          $('#ctoday').css('font-weight', 'bold');
        }
        break;
      case 'remove':
        $('#' + response[1]).remove();
        $('#deleted_count').html(parseInt($('#deleted_count').html()) - 1);
        rcmail.env.planner_counts.deleted = rcmail.env.planner_counts.deleted - 1;
        planner_items = 0;
        $('#planner_items_list li').each(function(){
          if($(this).is(":visible")){
            planner_items ++;
          }
        });
        if(planner_items == 0){
          $("#controls").val('today');
          rcmail.env.planner_items = 'today';
          $('.lcontrol').css('font-weight', 'normal');
          $('#ctoday').css('font-weight', 'bold');
        }
        break;
      case 'created':
        planner_overlay_toggle(false);
        return;
        break;
    }
    rcmail.env.planner_last_filter = false;
    planner_show(rcmail.env.planner_items);
    planner_overlay_toggle(false);
  });
  
  // map time formats
  js_time_formats['g:i a'] = 'h:mm tt';
  js_time_formats['H:i']   = 'HH:mm';
  js_time_formats['G:i']   = 'h:mm';
  js_time_formats['h:i A'] = 'HH:mm TT';
  
  // map date formats
  js_date_formats['Y-m-d'] = 'yy-mm-dd';
  js_date_formats['m-d-Y'] = 'mm-dd-yy';
  js_date_formats['d-m-Y'] = 'dd-mm-yy';
  js_date_formats['Y/m/d'] = 'yy/mm/dd';
  js_date_formats['m/d/Y'] = 'mm/dd/yy';
  js_date_formats['d/m/Y'] = 'dd/mm/yy';
  js_date_formats['d.m.Y'] = 'dd.mm.yy';
  js_date_formats['j.n.Y'] = 'd.m.yy';

  // load items
  rcmail.http_post('plugin.planner_retrieve', '_p=init');
  
  // listeners
  // use .live() for jQuery 1.7+
  $('#planner_raw').bind('blur', function(){
    $('#planner_raw').focus();
  });
  $('a.done').live("click", function(){
    planner_overlay_toggle(true);
    rcmail.http_post('plugin.planner_done', '_id=' + $(this).parent().attr("id"));
  });
  $('a.star').live("click", function(){
    planner_overlay_toggle(true);
    rcmail.http_post('plugin.planner_unstar', '_id=' + $(this).parent().attr("id"));
  });
  $('a.nostar').live("click", function(){
    if(!$(this).next().hasClass('birthday')){
      planner_overlay_toggle(true);
      rcmail.http_post('plugin.planner_star', '_id=' + $(this).parent().attr("id"));
    }
  });
  $('a.delete').live("click", function(){
    planner_overlay_toggle(true);
    rcmail.http_post('plugin.planner_delete', '_id=' + $(this).parent().attr("id"));
  });
  $('a.remove').live("click", function(){
    planner_overlay_toggle(true);
    rcmail.http_post('plugin.planner_remove', '_id=' + $(this).parent().attr("id"));
  });
  $('#planner_done').click(function(){
    $('#planner_deleted').prop('checked', false);
    if($(this).prop('checked')){
      $('#planner_preview_done').addClass('pdone');
    }
    else{
      $('#planner_preview_done').removeClass('pdone');
    }
  });
  $('#planner_starred').click(function(){
    if($(this).prop('checked')){
      $('#planner_preview_star').addClass('pstar');
    }
    else{
      $('#planner_preview_star').removeClass('pstar');
      $('#planner_preview_star').addClass('pnostar');
    }
  });
  $('#planner_deleted').click(function(){
    $('#planner_done').prop('checked', false);
    if($(this).prop('checked')){
      $('#planner_preview_done').removeClass('pdone');
      $('#planner_preview_done').addClass('pdelete');
    }
    else{
      $('#planner_preview_done').removeClass('pdelete');
    }
  });
  $('.pnostar').live('click', function(){
    $('#planner_preview_star').removeClass('pnostar');
    $('#planner_preview_star').addClass('pstar');
    $('#planner_starred').prop('checked', true);
  });
  $('.pstar').live('click', function(){
    $('#planner_preview_star').removeClass('pstar');
    $('#planner_preview_star').addClass('pnostar');
    $('#planner_starred').prop('checked', false);
  });
  $('.pdone').live('click', function(){
    $('#planner_preview_done').removeClass('pdelete');
    $('#planner_preview_done').removeClass('pdone');
    $('#planner_done').prop('checked', false);
    $('#planner_deleted').prop('checked', false);
  });
  $('.pdelete').live('click', function(){
    $('#planner_preview_done').removeClass('pdelete');
    $('#planner_preview_done').addClass('pdone');
    $('#planner_done').prop('checked', true);
    $('#planner_deleted').prop('checked', false);
  });
  
  // mouse effects
  $('#planner_controls a').mouseover(function(){
    $(this).css('font-style', 'italic');
  });
  $('#planner_controls a').mouseout(function(){
    $(this).css('font-style', 'normal');
  });  
  $('.edit').live('mouseover', function(){
    $(this).css('font-style', 'italic');
  });
  $('.edit').live('mouseout', function(){
    $(this).css('font-style', 'normal');
  });

  // controls
  $('.lcontrol').live('click',function(){
    if(rcmail.env.planner_counts[$(this).attr('id').substr(1)] > 0){
      $('#controls').val($(this).attr('id').substr(1));
      $('.lcontrol').css('font-weight', 'normal');
      $(this).css('font-weight', 'bold');
      var action = $('#controls').val();
      rcmail.env.planner_items = action;
      planner_save_prefs();
      planner_show(action);
    }
  });
  $('#controls').change(function(){
    var action = $(this).val();
    if(rcmail.env.planner_counts[action] > 0){
      $('.lcontrol').css('font-weight', 'normal');
      $('#c' + action).css('font-weight', 'bold');
      rcmail.env.planner_items = action;
      planner_save_prefs();
      planner_show(action);
    }
    else{
      $(this).val(rcmail.env.planner_select);
    }
  });
  $('#all').click(function(){
    if(rcmail.env.planner_counts['all'] > 0){
      $('.lcontrol').css('font-weight', 'normal');
      $('#call').css('font-weight', 'bold');
      rcmail.env.planner_items = 'all';
      planner_save_prefs();
      $('#controls').val('all');
      planner_show('all');
    }
  });
  $('#today').click(function(){
    if(rcmail.env.planner_counts['today'] > 0){
      $('.lcontrol').css('font-weight', 'normal');
      $('#ctoday').css('font-weight', 'bold');
      rcmail.env.planner_items = 'today';
      planner_save_prefs();
      $('#controls').val('today');
      planner_show('today');
    }
  });
  $('#tomorrow').click(function(){
    if(rcmail.env.planner_counts['tomorrow'] > 0){
      $('.lcontrol').css('font-weight', 'normal');
      $('#ctomorrow').css('font-weight', 'bold');
      rcmail.env.planner_items = 'tomorrow';
      planner_save_prefs();
      $('#controls').val('tomorrow');
      planner_show('tomorrow');
    }
  });
  $('#expunge').click(function(){
    rcmail.env.planner_items = 'deleted';
    planner_save_prefs();
    $('#controls').val('deleted');
    planner_overlay_toggle(true);
    rcmail.http_post('plugin.planner_expunge', '');
  });

  // add
  planner_keypress();
  $("#new").click(function(){
    planner_dialog_reset_gui();
    planner_dialog();
  });
  
  // date and time picker
  planner_datetimepicker();
  
  // edit
  $(".nodate").live('click',function(){
    var text = $(this).text();
    if(text == '...'){
      text = '';
    }
    planner_dialog_edit($(this), text, false);
  });
  $(".datetime").live('click',function(){
    var time = $(this).prev().val();
    var text = $(this).text();
    if(text == '...'){
      text = '';
    }
    if($(this).parent().attr('class') != 'birthday'){
      planner_dialog_edit($(this), text, time);
    }
  });
  
  // autocomplete
  $('#planner_autocomplete').click(function(){
    if($('#planner_autocomplete').is(":visible")){
      $('#planner_raw').val($('#planner_autocomplete_content').html());
      $('#planner_autocomplete_content').html('');
      $('#planner_autocomplete').hide();
      $('#planner_text').val('');
      $('#planner_datetime').val('');
      var ret = planner_dialog_parse($('#planner_raw').val());
      if(ret[1] && ret[0])
        var time = ret[1] + ' ' + ret[0];
      else
        var time = false;
      var text = $('#planner_raw').val();
      if(ret[2])
        text = ret[2];
      planner_dialog_preview(text, time);
    }
  });
  
  // filters
  var fleft = $('#filter').offset().left + parseInt($('#filter_bar_fudge_left').html());
  var ftop  = $('#filter').offset().top + parseInt($('#filter_bar_fudge_top').html());
  var style = "top: " + ftop + "px; left: " + fleft + "px;";
  $('#filter_selector').attr('style', style);
  $('#filter').click(function(){
    $('#filter_selector').toggle();
  });
  $('#filter_selector').click(function(){
    $('#filter_selector').hide();
  });
  $('#fall').click(function(){
    if(rcmail.env.planner_search_mode){
      rcmail.env.planner_search_mode = false;
      $('#planner_input').dialog('close');
      $('#c' + rcmail.env.planner_items).trigger('click');
    }
    else{
      rcmail.env.planner_filter = 'all';
      planner_save_prefs();
      planner_show(rcmail.env.planner_items);
    }
    $('#dfilter').text(rcmail.gettext('planner.nofilter'));
    $('.flink').css('font-weight', 'normal');
    $(this).css('font-weight', 'bold');
  });
  $('#fplans').click(function(){
    rcmail.env.planner_filter = 'plans';
    planner_save_prefs()
    planner_show(rcmail.env.planner_items);
    $('#dfilter').text(rcmail.gettext('planner.plans'));
    $('.flink').css('font-weight', 'normal');
    $(this).css('font-weight', 'bold');
  });
  $('#ftodos').click(function(){
    rcmail.env.planner_filter = 'todos';
    planner_save_prefs();
    planner_show(rcmail.env.planner_items);
    $('#dfilter').text(rcmail.gettext('planner.todos'));
    $('.flink').css('font-weight', 'normal');
    $(this).css('font-weight', 'bold');
  });
  $('#fbirthdays').click(function(){
    rcmail.env.planner_filter = 'birthdays';
    planner_save_prefs();
    planner_show(rcmail.env.planner_items);
    $('#dfilter').text(rcmail.gettext('planner.birthdays'));
    $('.flink').css('font-weight', 'normal');
    $(this).css('font-weight', 'bold');
  });
  $('#fnone').click(function(){
    $('#fall').trigger('click');
  });
  $('#cbirthdays').click(function(){
    if($(this).prop('checked')){
      $('#lbirthdays').show();
      $('#birthdays_count').show();
      $('#cbirthdays').attr('title', rcmail.gettext('planner.birthdays_no'));
      var val = 1;
    }
    else{
      $('#lbirthdays').hide();
      $('#birthdays_count').hide();
      $('#cbirthdays').attr('title', rcmail.gettext('planner.birthdays_yes'));
      var val = 0;
    }
    planner_overlay_toggle(true);
    rcmail.http_post('plugin.planner_prefsbirthdays', '_v=' + val);
  });
  
  // hints
  $('.longdatetime').html(planner_html_sanitize(planner_dialog_ampm_replace(new Date().format(js_date_formats[rcmail.env.rc_date_format].replace(/yy/i, 'yyyy') + ' ' + js_time_formats[rcmail.env.rc_time_format]))));
  $('.shortdatetime').html(planner_html_sanitize(planner_dialog_ampm_replace(new Date(new Date().getTime() + 86400/4.2 * 1000).format(js_date_formats[rcmail.env.rc_date_format].replace(/(\-yy)|(yy\-)/i, '') + ' ' + js_time_formats[rcmail.env.rc_time_format]))));
  $('.longtime').html(planner_html_sanitize(planner_dialog_ampm_replace(new Date(new Date().getTime() + 86400/3.2 * 1000).format(js_time_formats[rcmail.env.rc_time_format]))));
  $('.shorttime').html(planner_html_sanitize(planner_dialog_ampm_replace(new Date(new Date().getTime() + 86400/2.2 * 1000).format(js_time_formats[rcmail.env.rc_time_format].replace(/:mm/i, '')))));
  $('.ltoday').html(planner_html_sanitize($('.ltoday').html().toLowerCase()));
  $('.ltomorrow').html(planner_html_sanitize($('.ltomorrow').html().toLowerCase()));
  $('.hint').click(function(){
    $('#planner_raw').val($(this).text() + ' ' + $('#planner_text').val());
    planner_dialog_preview($('#planner_text').val(), $(this).text());
  });
  
  // help
  $("#help").click(function () {
    $("#planner_help").slideToggle("slow");
  });
});
