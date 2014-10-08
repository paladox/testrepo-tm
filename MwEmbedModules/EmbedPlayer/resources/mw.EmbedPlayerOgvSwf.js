( function( mw, $ ) { "use strict";

var support = mw.OgvJsSupport;

mw.EmbedPlayerOgvSwf = $.extend( mw.EmbedPlayerOgvJsCommon, {

	// Instance name:
	instanceOf: 'OgvSwf',

	// Supported feature set of the cortado applet:
	supports: {
		'playHead' : false, // seeking not supported yet
		'pause' : true,
		'stop' : true,
		'fullscreen' : false, // todo
		'sourceSwitch': true,
		'timeDisplay' : true,
		'volumeControl' : false,
		'overlays': (function() {
			var supportsOverlays = $.client.test({
				// IE 9 doesn't handle the case where we overlay HTML content over
				// the Flash plugin well; at least it doesn't pass mouse events
				// so once the control bar disappears we never get it back.
				//
				// IE 10 and 11 handle this fine.
				//
				'msie': [['>=', 10]]

				// Other browsers should fall through to true, but we currently
				// only support the SWF version on IE. :)
			});
			return supportsOverlays;
		})()
	},

	/**
	 * Perform setup in response to a play start command.
	 * This means loading the code asynchronously if needed.
	 *
	 * @return jQuery.Deferred
	 */
	_ogvJsPreInit: function() {
		return support.loadOgvSwf();
	},

	/**
	 * Actually initialize the player.
	 *
	 * @return OgvJsPlayer
	 */
	_ogvJsInit: function() {
		var options = {
			base: support.basePath() // where to find the Flash .swf file
		};
		return new OgvSwfPlayer( options );
	}

} );

} )( window.mediaWiki, window.jQuery );
