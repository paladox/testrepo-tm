( function( mw, $ ) { "use strict";

mw.EmbedPlayerOgvJsCommon = {

	/**
	 * Perform setup in response to a play start command.
	 * This means loading the code asynchronously if needed.
	 *
	 * @return jQuery.Deferred
	 */
	_ogvJsPreInit: function() {
		throw new Error(' must override _ogvJsPreInit' );
	},

	/**
	 * Actually initialize the player.
	 *
	 * @return HTMLElement
	 */
	_ogvJsInit: function() {
		throw new Error(' must override _ogvJsInit' );
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
		_this._ogvJsPreInit().done( function() {

			var player = _this._ogvJsInit();
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
			try {
				currentTime = this.playerElement.currentTime;
			} catch ( e ) {
				mw.log( 'EmbedPlayerOgvJs:: Could not get time from jPlayer: ' + e );
			}
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
		if( !$( '#' + this.pid ).length ) {
			return false;
		}
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
			try {
				this.playerElement.play();
			} catch ( e ){
				mw.log( "EmbedPlayerOgvJs::Could not issue play request" );
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
			try {
				this.playerElement.pause();
			} catch( e ) {
				mw.log( "EmbedPlayerOgvJs::Could not issue pause request" );
			}
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
	}

};

} )( window.mediaWiki, window.jQuery );
