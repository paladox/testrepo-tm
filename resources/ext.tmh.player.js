
( function ( $, mw, videojs ) {
	var globalConfig, audioConfig, playerConfig, $source;

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
				sourceOrder: true,
				customSourcePicker: function( player, sources, label ) {
					// Resolution switcher gets confused by preload=none on ogv.js
					if ( player.preload() === 'none' ) {
						player.preload( 'metadata' );
					}
					player.src( sources );
					return player;
				}
			},
			responsiveLayout: {
				layoutMap: [
					{ layoutClassName: 'vjs-layout-tiny', width: 3 },
					{ layoutClassName: 'vjs-layout-x-small', width: 4 },
					{ layoutClassName: 'vjs-layout-small', width: 5 },
					{ layoutClassName: 'defaults', width: 6 }
				]
			},
			replayButton: {},
			infoButton: {}
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

	/**
	 * Load video players for a jQuery collection
     */
	function loadVideoPlayer() {
		var videoplayer, $videoplayer;

		this.each( function ( index ) {
			videoplayer = this;
			$videoplayer = $( this );
			if ( $videoplayer.closest( '.video-js' ).size() ) {
				// This player has already been transformed.
				return;
			}
			playerConfig = $.extend( {}, globalConfig );
			if ( videoplayer.tagName.toLowerCase() === 'audio' ) {
				// We hide the big play button, show the controlbar with CSS
				// We remove the fullscreen button
				playerConfig = $.extend( true, {}, playerConfig, audioConfig );
			}
			// Future interactions go faster if we've preloaded a little
			var preload = 'metadata';
			if ( videoplayer.canPlayType( 'video/webm; codecs=vp8,vorbis' ) === '' ) {
				// ogv.js currently is expensive to start up:
				// https://github.com/brion/ogv.js/issues/438
				preload = 'none';
			}
			if ( index >= 10 ) {
				// On pages with many videos, like Category pages, don't preload em all
				preload = 'none';
			}

			var resolutions = [];

			$( videoplayer ).attr( {
				preload: preload
			} ).find( 'source' ).each( function () {
				// FIXME would be better if we can configure the plugin to make use of our preferred attributes
				var $source = $( this ),
					transcodeKey = $source.data( 'transcodekey' ),
					res = parseInt( $source.data( 'height' ) ),
					label = $source.data( 'shorttitle' );

				if ( transcodeKey ) {
					var matches = transcodeKey.match(/^(\d+)p\./);
					if ( matches ) {
						// Video derivative of fixed size.
						res = parseInt( matches[1] );
						label = mw.message( 'timedmedia-resolution-' + res ).text();
					}
				} else {
					// Original source; sort to top and never auto-select.
					res = 99999;
					label = $source.data( 'shorttitle' );
				}
				$source.attr( 'res', res );
				$source.attr( 'label', label );
				resolutions.push( res );
			} );

			// Pick the first resolution at least the size of the player,
			// unless they're all too small.
			var playerHeight = $( videoplayer ).height();
			resolutions.sort( function( a, b ) {
				return a - b;
			} );
			var defaultRes;
			for ( var i = 0; i < resolutions.length; i++ ) {
				defaultRes = resolutions[ i ];
				if ( defaultRes >= playerHeight ) {
					break;
				}
			}
			if ( defaultRes ) {
				playerConfig.plugins.videoJsResolutionSwitcher.default = defaultRes;
			}

			$videoplayer.parent( '.thumbinner' ).addClass( 'mw-overflow' );

			// Launch the player
			$videoplayer.addClass( 'video-js' );
			videojs( videoplayer, playerConfig ).ready( function () {
				/* More custom stuff goes here */
			} );
		} );
	}

	$.fn.loadVideoPlayer = loadVideoPlayer;

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		$content.find( 'video,audio' ).loadVideoPlayer();
	} );
	$( function () {
		// The iframe mode
		$( '#bgimage' ).remove();
		if ( $( '.video-js[data-player="fillwindow"]' ).length > 0 ) {
			$( '.video-js[data-player="fillwindow"]' ).loadVideoPlayer();
		}
	} );

} )( jQuery, mediaWiki, videojs );
