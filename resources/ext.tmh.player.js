( function ( $, mw, videojs, OO ) {
	var globalConfig, audioConfig, playerConfig, $source, windowManager;

	globalConfig = {
		language: mw.config.get( 'wgUserLanguage' ),
		controlBar: {
			liveDisplay: false,
			volumeMenuButton: {
				vertical: true,
				inline: false
			}
		},
		techOrder: [ 'html5', 'ogvjs' ],
		plugins: {
			videoJsResolutionSwitcher: {
				sourceOrder: true
			},
			responsiveLayout: {
				layoutMap: [
					{ layoutClassName: 'vjs-layout-tiny', width: 3 },
					{ layoutClassName: 'vjs-layout-x-small', width: 4 },
					{ layoutClassName: 'vjs-layout-small', width: 5 },
					{ layoutClassName: 'defaults', width: 6 }
				]
			}
		},
		ogvjs: {
			base: mw.OgvJsSupport.basePath()
		}
	};

	audioConfig = {
		controlBar: {
			fullscreenToggle: false
		}
	};

	function loadSingleVideoPlayer( mediaElement, readyCallback ) {
		var $mediaElement = $( mediaElement );

		playerConfig = $.extend( {}, globalConfig );
		if ( mediaElement.tagName.toLowerCase() === 'audio' ) {
			// We hide the big play button, show the controlbar with CSS
			// We remove the fullscreen button
			playerConfig = $.extend( true, {}, playerConfig, audioConfig );
		}
		$mediaElement.find( 'source' ).each( function () {
			// FIXME would be better if we can configure the plugin to make use of our preferred attributes
			$source = $( this );
			$source.attr( 'res', $source.data( 'height' ) );
			$source.attr( 'label', $source.data( 'shorttitle' ) );
		} );

		// Launch the player
		videojs( mediaElement, playerConfig ).ready( function () {
			if ( readyCallback ) {
				readyCallback( this );
			}
		} );
	}

	/**
	 * Load video players for a jQuery collection
     */
	function loadVideoPlayer() {
		this.each( function ( index ) {
			$( this ).attr( {
				/* Don't preload on pages with many videos, like Category pages */
				preload: ( index < 10 ) ? 'auto' : 'metadata'
			} );
			loadSingleVideoPlayer( this );
		} );

		// Chainable
		return this;
	}

	/**
	 * Load video players for a jQuery collection
     */
	function loadPopupVideoPlayer() {
		this.each( function () {
			var $video = $( this );
			if ( this.tagName === 'audio'
				|| $video.data( 'player' ) !== 'popup'
			) {
				return;
			}
			var $newVideo = $video.clone();
			$newVideo.attr( 'id', null );

			$video.on( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();

				// Make the window, can't reuse the previous one, since content is dynamic
				// Have to figure something out for this.
				var videoJsDialog = new mw.VideoJsDialog( {
					size: 'larger',
					$mediaElement: $newVideo
				} );
				// We are not removing this...
				windowManager.addWindows( [ videoJsDialog ] );

				var loadedPlayer;
				loadSingleVideoPlayer( $( videoJsDialog.$body ).find( '.video-js' )[0], function ( player ) {
					loadedPlayer = player;
					videoJsDialog.setPlayer( player );
					//player.width( 600 );
					player.fluid( true );
					player.play();
					player.one( 'ended', function () {
						videoJsDialog.close();
					} );
				} );

				// Open the window!
				windowManager.openWindow( videoJsDialog ).then( function ( opened ) {

					return opened.then( function ( closing ) {
						loadedPlayer.pause();
						return closing.then( function () {
							loadedPlayer.dispose();
							setTimeout( function () {
								windowManager.removeWindows( [ 'videoJsDialog' ] );
							}, 0 );
						} );
					} );
				} );
			} );
		} );
	}

	$.fn.loadVideoPlayer = loadVideoPlayer;
	$.fn.loadPopupVideoPlayer = loadPopupVideoPlayer;

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		$content.find( '.video-js:not([data-player="popup"])' ).loadVideoPlayer();
	} ).add( function ( $content ) {
		$content.find( '.video-js[data-player="popup"]' ).loadPopupVideoPlayer();
	} );

	$( function () {
		// Create and append a window manager, which will open and close the window.
		windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );
	} );
} )( jQuery, mediaWiki, videojs, OO );
