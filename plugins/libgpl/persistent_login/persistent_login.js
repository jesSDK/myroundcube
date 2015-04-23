/**
 * Plugin which provides a persistent login functionality.
 * Also known as "remembery me" or "stay logged in" function.
 *
 * @author insaneFactory, Manuel Freiholz
 * @website http://manuel.insanefactory.com/
*/
if (window.rcmail) {

	rcmail.addEventListener('init', function() {
	
		// create "stay logged in" checkbox.
		var	text = '<div id="ifplcontainer">';
			text+= '    <input type="checkbox" name="_ifpl" id="_ifpl" value="1">';
			text+= '    <label for="_ifpl">' + rcmail.gettext('ifpl_rememberme', 'persistent_login') + '</label>';
			text+= '  <p>' + rcmail.gettext('ifpl_rememberme_hint', 'persistent_login') + '</p>';
			text+= '</div>';
		
  	$('form').append(text);
		
		// show hint.
		$('#_ifpl').click(function() {
			var t = $(this);
			if (t.is(':checked')) {
				$('#ifplcontainer > p').show();
			}
			else {
				$('#ifplcontainer > p').hide();
			}
		});

	});

}
