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

		var _this = this;
		_this._loadOgvJs( function() {
			var player = new OgvJsPlayer();
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
	
	_findOgvJs: function() {
		var base = mw.getEmbedPlayerPath() + '/binPlayers/ogv.js';
		var url = base + '/ogvjs.js?version=0.1';
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
