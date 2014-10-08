( function( mw, $ ) { "use strict";

var support = mw.OgvJsSupport;

mw.EmbedPlayerOgvJs = $.extend( mw.EmbedPlayerOgvJsCommon, {

	// Instance name:
	instanceOf: 'OgvJs',

	// Supported feature set of the cortado applet:
	supports: {
		'playHead' : false, // seeking not supported yet
		'pause' : true,
		'stop' : true,
		'fullscreen' : false,
		'sourceSwitch': true, // todo
		'timeDisplay' : true,
		'volumeControl' : false,
		'overlays': true
	},

	/**
	 * Perform setup in response to a play start command.
	 * This means loading the code asynchronously if needed,
	 * and enabling web audio for iOS Safari inside the event
	 * handler.
	 *
	 * @return jQuery.Deferred
	 */
	_ogvJsPreInit: function() {
		if( mw.isIOS() ) {
			this._initializeAudioForiOS();
		}
		return support.loadOgvJs();
	},

	/**
	 * Actually initialize the player.
	 *
	 * @return OgvJsPlayer
	 */
	_ogvJsInit: function() {
		var options = {
			webGL: true, // auto-detect accelerated YUV conversion
			base: support.basePath() // where to find the Flash shims for IE
		};
		if ( this._iOSAudioContext ) {
			// Reuse the audio context we opened earlier
			options.audioContext = this._iOSAudioContext;
		}
		return new OgvJsPlayer( options );
	},

	_iOSAudioContext: undefined,

	_initializeAudioForiOS: function() {
		// iOS Safari Web Audio API must be initialized from an input event handler
		// This is a temporary (?) hack while we load OgvJsPlayer support code async
		if ( this._iOSAudioContext ) {
			return;
		}
		this._iOSAudioContext = support.initAudioContext();
	}

} );

} )( window.mediaWiki, window.jQuery );
