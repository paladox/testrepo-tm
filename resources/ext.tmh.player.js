( function ( $, mw, videojs ) {
	$( function () {
		var globalConfig, audioConfig,
			start, end, $source,
			videoplayers = $( '.video-js' );

		globalConfig = {
			language: mw.config.get( 'wgUserLanguage' ),
			controlBar: {
				liveDisplay: false,
			},
			techOrder: [ 'html5' ],
			plugins: {
				videoJsResolutionSwitcher: {
					'default': '360'
				}
			}
		};

		audioConfig = {
			controlBar: {
				fullscreenToggle: false
			}
		};

		function growPlayer() {
			// FIXME: fancy transform stuff should be added here :)
			// Possibly only do this when content renderer sets a certain class ?
			var targetWidth = 600,
				player = this,
				$playerDiv = $( player.el() );

			var width = player.width();
			var enlargeRatio = targetWidth / width;
			if ( width < targetWidth ) {
				$playerDiv
				.addClass( 'vjs-expanded' )
				.data( {
					'original-height': player.height(),
					'original-width': width,
					style: $playerDiv.attr( 'style' )
				} );
				player.height( Math.ceil( player.height() * enlargeRatio ) );
				player.width( targetWidth );
				var parentThumb = $playerDiv.closest( '.thumb' );
				if ( parentThumb.length ) {
					parentThumb.data( {
						style: parentThumb.attr( 'style' ),
						'class': parentThumb.attr( 'class' )
					} );
					parentThumb.removeClass( 'thumb tright tleft tnone' ).addClass( 'removedThumb' ).css( { clear: 'both' } );
					parentThumb.children( '.thumbinner' ).removeClass( 'thumbinner' ).addClass( 'removedInner' ).css( { width: 'auto', height: 'auto', 'margin-left': 'auto', 'margin-right': 'auto' } );
				}
				$playerDiv.css( { clear: 'both', 'margin-left': 'auto', 'margin-right': 'auto' } );
			}

		}

		function shrinkPlayer() {
			var player = this,
				$playerDiv = $( player.el() );
			if ( $playerDiv.hasClass( 'vjs-expanded' ) ) {
				player.width( $playerDiv.data( 'original-width' ) );
				player.height( $playerDiv.data( 'original-height' ) );
				$playerDiv.removeClass( 'vjs-expanded' ).attr( { style: $playerDiv.data( 'style' ) } );
				var parentThumb = $playerDiv.closest( '.removedThumb' );
				if ( parentThumb.length ) {
					parentThumb.attr( { style: parentThumb.data( 'style' ), 'class': parentThumb.data( 'class' ) } );
					parentThumb.children( '.removedInner' ).attr( 'class', 'thumbinner' );
				}
			}
		}

		videoplayers.each( function ( index, videoplayer ) {
			playerConfig = {};
			$.extend( playerConfig, globalConfig );
			if ( videoplayer.tagName === 'AUDIO' ) {
				// We hide the play button and add the controlbar
				$( videoplayer ).addClass( 'vjs-audio' );
				$.extend( playerConfig, audioConfig );
			}
			// FIXME would be better if we can configure the plugin to make use of our preferred attributes
			$( videoplayer ).find( 'source' ).each( function () {
				$source = $( this );
				$source.attr( 'res', $source.data( 'height' ) );
				$source.attr( 'label', $source.data( 'shorttitle' ) );
			} );
			// FIXME this is currently disabled, because the plugin breaks with multiple videos on a page
			start = $( videoplayer ).data( 'start' );
			end = $( videoplayer ).data( 'end' );

			// Launch the player
			videojs( videoplayer, playerConfig ).ready( function () {
				// this.on( 'play', growPlayer );
				// this.on( 'ended', shrinkPlayer );
				/* More custom stuff goes here */
			} );
		} );
	} );

} )( jQuery, mediaWiki, videojs );
