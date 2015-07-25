( function( mw, $ ) { "use strict";

var support = mw.OgvJsSupport;

mw.EmbedPlayerOgvJs = {

	// Instance name:
	instanceOf: 'OgvJs',

	// Supported feature set of the OGVPlayer widget:
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

	_loadedMetadata: false,
	_initialPlaybackTime: 0,

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
	 * @return OGVPlayer
	 */
	_ogvJsInit: function() {
		var options = {
			base: support.basePath() // where to find the Flash shims for IE
		};
		if ( this._iOSAudioContext ) {
			// Reuse the audio context we opened earlier
			options.audioContext = this._iOSAudioContext;
		}
		return new OGVPlayer( options );
	},

	_iOSAudioContext: undefined,

	_initializeAudioForiOS: function() {
		// iOS Safari Web Audio API must be initialized from an input event handler
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
			player.addEventListener('ended', function() {
				_this.onClipDone();
			});
			$( _this ).empty().append( player );

			_this._loadedMetadata = false;
			player.addEventListener('loadedmetadata', function() {
				_this._loadedMetadata = true;
				player.play();
				if ( _this._initialPlaybackTime ) {
					player.currentTime = _this._initialPlaybackTime;
					_this._initialPlaybackTime = 0;
				}
			});
			player.load();

			// Start the monitor:
			_this.monitor();

			// Make an attempt to monitor playback quality...
			_this.cpuTime = 0;
			_this.clockTime = 0;
			_this.framesPlayed = 0;
			_this.frameTime = 0;
			player.addEventListener('framecallback', function(event) {
				_this.framesPlayed++;
				_this.cpuTime += event.cpuTime;
				_this.clockTime += event.clockTime;
				_this.frameTime += (1000 / player.ogvjsVideoFrameRate);
				
				if (_this.framesPlayed >= 150 && (_this.clockTime / _this.frameTime) > 1.1) {
					_this.ogvjsDowngradeSource();
				}
			});

			if ( optionalCallback ) {
				optionalCallback();
			}
		});
	},

	ogvjsDowngradeSource: function() {
		var _this = this,
			source = this.ogvjsFindDowngradedSource();

		if ( source.src !== this.mediaElement.selectedSource.src ) {
			var oldPlayer = this.getPlayerElement(),
				now = oldPlayer.currentTime;

			oldPlayer.pause();
			this.mediaElement.setSource( source );
			this.switchPlaySource( source, function(){}, function( vid ) {
				var player = _this.getPlayerElement();
				if ( now > 0 ) {
					var listener = function() {
						player.removeEventListener( 'framecallback', listener );
						player.pause();
						setTimeout(function() {
							player.currentTime = now;
							player.play();
						}, 0);
					};
					player.addEventListener( 'framecallback', listener );
				}
			});
		}
	},
	
	ogvjsFindDowngradedSource: function() {
		var sources = this.ogvjsSources();
		for ( var i = 0; i < sources.length; i++ ) {
			if ( sources[i].src == this.mediaElement.selectedSource.src) {
				if ( i == 0 ) {
					// already at the smallest source
					return this.mediaElement.selectedSource;
				} else {
					return sources[i - 1];
				}
			}
		}
		return this.selectedSource;
	},

	ogvjsSources: function() {
		var _this = this,
			sources = [];
		$.each( _this.mediaElement.getPlayableSources(), function( sourceIndex, source ) {
			// Output the player select code:
			var supportingPlayers = mw.EmbedTypes.getMediaPlayers().getMIMETypePlayers( source.getMIMEType() );
			for ( var i = 0; i < supportingPlayers.length; i++ ) {
				if( supportingPlayers[i].library === 'OgvJs' ){
					sources.push( source );
				}
			}
		});
		return sources.sort(function(a, b) {
			if ( parseInt( a.height, 10 ) < parseInt( b.height, 10 ) ) {
				return -1;
			} else if ( parseInt( a.height, 10 ) > parseInt( b.height, 10 ) ) {
				return 1;
			} else {
				return 0;
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
			// Restart the monitor if on second playthrough
			this.monitor();
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
