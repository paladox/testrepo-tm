( function( mw, $ ) { "use strict";

var support = mw.OgvJsSupport;

mw.EmbedPlayerOgvJs = $.extend( mw.EmbedPlayerOgvJsCommon, {

	// Instance name:
	instanceOf: 'OgvJs',

	// Supported feature set of the cortado applet:
	supports: {
		'playHead' : true,
		'pause' : true,
		'stop' : true,
		'fullscreen' : false,
		'sourceSwitch': true,
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
	},

	/**
	* Seek in the ogg stream
	* @param {Float} percentage Percentage to seek into the stream
	*/
	seek: function( percentage ) {
		this.getPlayerElement();

		if ( this.playerElement ) {
			this.playerElement.currentTime = ( percentage * parseFloat( this.getDuration() ) );
		}

		// Run the onSeeking interface update
		this.controlBuilder.onSeek();
		if( this.seeking ){
			this.seeking = false;
			$( this ).trigger( 'seeked' );
		}
	},

} );

} )( window.mediaWiki, window.jQuery );
