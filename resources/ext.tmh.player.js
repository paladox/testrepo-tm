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


	/**
	 * Load video players for a jQuery collection
     */
	function loadVideoPlayer() {
		var videoplayer, $videoplayer;

		this.each( function ( index ) {
			videoplayer = this;
			$videoplayer = $( this );
			playerConfig = $.extend( {}, globalConfig );
			if ( videoplayer.tagName.toLowerCase() === 'audio' ) {
				// We hide the big play button, show the controlbar with CSS
				// We remove the fullscreen button
				playerConfig = $.extend( true, {}, playerConfig, audioConfig );
			}
			$videoplayer.attr( {
				/* Don't preload on pages with many videos, like Category pages */
				preload: ( index < 10 ) ? 'auto' : 'metadata'
			} ).find( 'source' ).each( function () {
				// FIXME would be better if we can configure the plugin to make use of our preferred attributes
				$source = $( this );
				$source.attr( 'res', $source.data( 'height' ) );
				$source.attr( 'label', $source.data( 'shorttitle' ) );
			} );

			// Launch the player
			videojs( videoplayer, playerConfig ).ready( function () {
				var $placeholder,
					player = this,
					$playerElement = $( player.el() );

				if ( $playerElement.hasClass( 'vjs-audio' )
					|| $playerElement.data( 'player' ) !== 'popup'
				) {
					return;
				}

				// Setup a popup window for the player
				player.el().addEventListener( 'click', function( event ) {
					if ( $playerElement.closest( '.oo-ui-window-body').length ) {
						/* Don't do anything if we are already inside a popup */
						return;
					}
					event.preventDefault();
					event.stopPropagation();
					if( !$placeholder ) {
						$placeholder = $playerElement.clone(false);
						$placeholder.attr('id', null)
							.insertBefore( $playerElement );
					}
					$placeholder.show();

					// Make the window, can't reuse the previous one, since content is dynamic
					// Have to figure something out for this.
					var videoDialog = new VideoDialog({
						size: 'larger',
						data: player
					});
					// We are not removing this...
					windowManager.addWindows( [ videoDialog ] );

					// Open the window!
					windowManager.openWindow( videoDialog ).then( function ( opened ) {
						player.fluid( true );
						player.play();
						player.one( 'ended', function() {
							videoDialog.close();
						});
						return opened.then( function ( closing ) {
							player.pause();
							return closing.then( function () {
								player.fluid( false )
								$playerElement.insertBefore( $placeholder );
								$placeholder.hide();
							});
						})
					});
				},
				true /* Capture the click so we do not start playback yet */);
			} );
		} );
	}

	// Subclass Dialog class. Note that the OOjs inheritClass() method extends the parent constructor's
	// prototype and static methods and properties to the child constructor.
	function VideoDialog( config ) {
		VideoDialog.super.call( this, config );
	}
	OO.inheritClass( VideoDialog, OO.ui.Dialog );

	// Specify a title statically (or, alternatively, with data passed to the opening() method).
	VideoDialog.static.title = 'Simple dialog';

	// Customize the initialize() function: This is where to add content to the dialog body and set up event handlers.
	VideoDialog.prototype.initialize = function () {
		// Call the parent method
		VideoDialog.super.prototype.initialize.call( this );
		this.$body.append( this.data.el() );
	};

	// Override the getBodyHeight() method to specify a custom height (or don't to use the automatically generated height)
	VideoDialog.prototype.getBodyHeight = function () {
		// Base our initial height on the Aspectratio of the original player
		// Account for the 1px border on the dialog.
		return ( ( this.data.dimension( 'height' ) / this.data.dimension( 'width' ) )
			* ( OO.ui.WindowManager.static.sizes["larger"].width) );
	};

	$.fn.loadVideoPlayer = loadVideoPlayer;

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		$content.find( '.video-js' ).loadVideoPlayer();
	} );

	$( function() {
		// Create and append a window manager, which will open and close the window.
		windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );
	} );
} )( jQuery, mediaWiki, videojs, OO );
