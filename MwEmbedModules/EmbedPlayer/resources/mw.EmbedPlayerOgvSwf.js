( function( mw, $ ) { "use strict";

mw.EmbedPlayerOgvSwf = {

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
			// This option determines whether to overlay the player controls
			// on top of the video player, or whether to squeeze the player
			// down and put the controls next to it -- this latter should only
			// be used for browser/plugin combinations known not to handle
			// such overlaying of content.
			if (navigator.userAgent.indexOf('Trident') != -1) {
				// IE of some version
				if (navigator.userAgent.indexOf('MSIE') == -1) {
					// IE 11 drops the 'MSIE' token, and is known
					// to handle the overlay case correctly.
					return true;
				} else if (navigator.userAgent.indexOf('MSIE 10.') !== -1) {
					// IE 10 still has MSIE token, but is known
					// to handle the overlay case correctly.
					return true;
				} else {
					// IE 9 doesn't handle overlay case well;
					// at least it doesn't pass mouse events
					// so once the control bar disappears we
					// never get it back.
					return false;
				}
			}

			// Who knows? Might work!
			return true;
		})()
	},

	/**
	* Output the the embed html
	*/
	embedPlayerHTML: function () {

		$( this ).text( '... loading ...' );

		var _this = this;
		_this._loadOgvSwf( function() {
			var player = new OgvSwfPlayer({
				base: _this._OgvSwfBase() // where to find the Flash .swf files
			});
			player.id = _this.pid;
			player.width = parseInt( _this.getWidth() );
			player.height = parseInt( _this.getHeight() );
			if (!_this.supports.overlays) {
				// Squish to leave space for fixed control bar on IE 9
				player.height -= _this.controlBuilder.getHeight();
			}
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

	_loadOgvSwf: function( callback ) {
		if ( typeof OgvSwfPlayer === 'undefined' ) {
			$.ajax({
				dataType: 'script',
				cache: true,
				url: this._findOgvSwf()
			}).done(function() {
				callback();
			});
		} else {
			callback();
		}
	},

	_OgvSwfBase: function() {
		return mw.getEmbedPlayerPath() + '/binPlayers/ogv.js';
	},

	_findOgvSwf: function() {
		var url = this._OgvSwfBase() + '/ogvswf.js?version=0.1.2';
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
					mw.log( 'EmbedPlayerOgvSwf:: Could not get time from jPlayer: ' + e );
				}
		}else{
			mw.log("EmbedPlayerOgvSwf:: Could not find playerElement " );
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
				mw.log("EmbedPlayerOgvSwf::Could not issue play request");
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
				mw.log("EmbedPlayerOgvSwf::Could not issue pause request");
			}
		}
	},

	playerSwitchSource: function( source, switchCallback, doneCallback ){
		var _this = this;
		var src = source.getSrc();
		var vid = this.getPlayerElement();
		if (!this.paused) {
			vid.pause();
		}

		switchCallback();

		// Currently have to tear down the player and make a new one
		this.embedPlayerHTML( doneCallback );
	}

};

} )( window.mediaWiki, window.jQuery );
