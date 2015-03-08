( function( mw, $ ) { "use strict";

var support = mw.OgvJsSupport;

mw.EmbedPlayerOgvJs = {

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
	 * Output the the embed html
	 */
	embedPlayerHTML: function (optionalCallback) {

		$( this )
			.empty()
			.append( $.createSpinner( {
				size: 'large',
				type: 'block'
			} ) );

		var _this = this;
		if( mw.isIOS() ) {
			_this._initializeAudioForiOS();
		}
		support.loadOgvJs().done( function() {

			var player = _this._ogvJsInit();
			player.id = _this.pid;
			player.width = parseInt( _this.getWidth(), 10 );
			player.height = parseInt( _this.getHeight(), 10 );
			player.src = _this.getSrc();
			if ( _this.getDuration() ) {
				player.durationHint = parseFloat( _this.getDuration() );
			}
			// @todo: clean up event hooks in upstream ogv.js
			player.onended = function() {
				_this.onClipDone();
			};
			$( _this ).empty().append( player );
			player.play();

			// Start the monitor:
			_this.monitor();

			if ( optionalCallback ) {
				optionalCallback();
			}
		});
	},

	/**
	 * Get the embed player time
	 */
	getPlayerElementTime: function() {
		this.getPlayerElement();
		var currentTime = 0;
		if ( this.playerElement ) {
			currentTime = this.playerElement.currentTime;
		} else {
			mw.log( "EmbedPlayerOgvJs:: Could not find playerElement" );
		}
		return currentTime;
	},

	/**
	 * Update the playerElement instance with a pointer to the embed object
	 */
	getPlayerElement: function() {
		// this.pid is in the form 'pid_mwe_player_<number>'; inherited from mw.EmbedPlayer.js
		var $el = $( '#' + this.pid );
		if( !$el.length ) {
			return false;
		}
		this.playerElement = $el.get( 0 );
		return this.playerElement;
	},

	/**
	 * Issue the doPlay request to the playerElement
	 *	calls parent_play to update interface
	 */
	play: function() {
		this.getPlayerElement();
		this.parent_play();
		if ( this.playerElement ) {
			this.playerElement.play();
		}
	},

	/**
	 * Pause playback
	 * 	calls parent_pause to update interface
	 */
	pause: function() {
		this.getPlayerElement();
		// Update the interface
		this.parent_pause();
		// Call the pause function if it exists:
		if ( this.playerElement ) {
			this.playerElement.pause();
		}
	},

	/**
	 * Switch the source!
	 * For simplicity we just replace the player here.
	 */
	playerSwitchSource: function( source, switchCallback, doneCallback ){
		var _this = this;
		var src = source.getSrc();
		var vid = this.getPlayerElement();
		if ( typeof vid.stop !== 'undefined' ) {
			vid.stop();
		}

		switchCallback();

		// Currently have to tear down the player and make a new one
		this.embedPlayerHTML( doneCallback );
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

};

} )( mediaWiki, jQuery );
