( function( mw, $ ) { "use strict";

mw.EmbedPlayerOgvJs = {

	// Instance name:
	instanceOf: 'OgvJs',

	// Supported feature set of the cortado applet:
	supports: {
		'playHead' : false, // seeking not supported yet
		'pause' : true,
		'stop' : true,
		'fullscreen' : false,
		'timeDisplay' : true,
		'volumeControl' : false
	},

	/**
	* Output the the embed html
	*/
	embedPlayerHTML: function () {
	
		$( this ).text( '... loading ...' );

		if (mw.isIOS()) {
			this._initializeAudioForiOS();
		}
		var _this = this;
		_this._loadOgvJs( function() {
			var options = {
				webGL: true, // auto-detect accelerated YUV conversion
				base: _this._ogvJsBase() // where to find the Flash shims for IE
			};
			if (_this._iOSAudioContext) {
				options.audioContext = _this._iOSAudioContext;
			}
			var player = new OgvJsPlayer(options);
			player.id = _this.pid;
			player.width = parseInt( _this.getWidth() );
			player.height = parseInt( _this.getHeight() - _this.controlBuilder.getHeight() );
			player.src = _this.getSrc();
			if ( _this.getDuration() ) {
				player.durationHint = parseFloat( _this.getDuration() );
			}
			$( _this ).empty().append( player );
			player.play();
		
			// Start the monitor:
			_this.monitor();
		});
	},
	
	_iOSAudioContext: undefined,
	
	_initializeAudioForiOS: function() {
		// iOS Safari Web Audio API must be initialized from an input event handler
		// This is a temporary (?) hack while we load OgvJsPlayer support code async
		if ( this._iOSAudioContext ) {
			return;
		}
		var AudioContext = window.AudioContext || window.webkitAudioContext;
		if (AudioContext) {
			var context = new AudioContext(),
				node;
			if (context.createScriptProcessor) {
				node = context.createScriptProcessor(1024, 0, 2);
			} else if (context.createJavaScriptNode) {
				node = context.createJavaScriptNode(1024, 0, 2);
			} else {
				throw new Error("Bad version of web audio API?");
			}

			// Don't actually run any audio, just start & stop the node
			node.connect(context.destination);
			node.disconnect();

			this._iOSAudioContext = context;
		}
	},

	_loadOgvJs: function( callback ) {
		if ( typeof OgvJsPlayer === 'undefined' ) {
			$.ajax({
				dataType: 'script',
				cache: true,
				url: this._findOgvJs()
			}).done(function() {
				callback();
			});
		} else {
			callback();
		}
	},
	
	_ogvJsBase: function() {
		return mw.getEmbedPlayerPath() + '/binPlayers/ogv.js';
	},
	
	_findOgvJs: function() {
		var url = this._ogvJsBase() + '/ogvjs.js?version=0.1.5';
		return url;
	},

	/**
	* Get the embed player time
	*/
	getPlayerElementTime: function() {
		this.getPlayerElement();
		var currentTime = 0;
		if ( this.playerElement ) {
			currentTime = this.playerElement.currentTime;
				try {
					currentTime = this.playerElement.currentTime;
				} catch ( e ) {
					mw.log( 'EmbedPlayerOgvJs:: Could not get time from jPlayer: ' + e );
				}
		}else{
			mw.log("EmbedPlayerOgvJs:: Could not find playerElement " );
		}
		return currentTime;
	},

	/**
	* Update the playerElement instance with a pointer to the embed object
	*/
	getPlayerElement: function() {
		if( !$( '#' + this.pid ).length ) {
			return false;
		};
		this.playerElement = $( '#' + this.pid ).get( 0 );
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
			try{
				this.playerElement.play();
			}catch( e ){
				mw.log("EmbedPlayerOgvJs::Could not issue play request");
			}
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
			try{
				this.playerElement.pause();
			}catch( e ){
				mw.log("EmbedPlayerOgvJs::Could not issue pause request");
			}
		}
	}
};

} )( window.mediaWiki, window.jQuery );
