( function( $, mw, OGVCompat ) {

	// Globalish state for the lightweight mobile media player
	var Mtmh = {
		autoplay: false,
		audioContext: null,
		initAudioContext: function() {
			if ( !Mtmh.audioContext ) {
				Mtmh.audioContext = mw.OgvJsSupport.initAudioContext();
			}
			return Mtmh.audioContext;
		}
	};

	/**
	 * Find the hidden audio or video element in the container!
	 * Beware it may be hiding in an attribute.
	 *
	 * @return jQuery containing 0 or 1 media elements
	 */
	function findMediaPlayer( $container ) {
		var payload = $container.attr( 'videopayload' );
		if ( payload ) {
			$container = $( payload );
		}
		if ( $container.is( 'audio, video' ) ) {
			return $container;
		} else {
			return $container.find( 'audio, video' );
		}
	}

	function autoplayClick( id ) {
		// Use a single audio context for all players,
		// and pre-initialize it from event handler on iOS
		// to work around the restrictions on auto playback.
		Mtmh.initAudioContext();
		Mtmh.autoplay = true;

		var $container = $( '#' + id ),
			$player = findMediaPlayer( $container );

		if ( $player.length ) {
			var player = $player[0];

			transformMediaElement( player ).then( function( el ) {
				var w = $container.width(),
					h = $container.height();
				if ( !w ) {
					w = 220;
				}
				if ( !h ) {
					h = 26;
				}
				$( el )
					.css( 'width', w + 'px' )
					.css( 'height', h + 'px' )
					.replaceAll( $container );
				el.play();
			} );
		}
	}

	function transformMediaPopups( container ) {
		var $container = $( container );

		// Transform the target URL to use our magic player
		$container.find( '.PopUpMediaTransform' ).each( function( i, el ) {
			var $popupTransform = $( el ),
				id = $popupTransform.attr( 'id' );
			$popupTransform
				.find( 'a' )
					.attr( 'target', '' )
					.off()
					.on( 'click', function( ev ) {
						autoplayClick( id )
						ev.preventDefault();
					} )
					.end();
		} );

	}

	/**
	 * Transform any live media elements in the given container
	 * into ogv.js elements.
	 *
	 * May happen asynchronously. Whee!
	 *
	 * @param HTMLElement container
	 */
	function transformMediaElements( container ) {
		var $media = $( container ).find( 'audio, video' );
		$media.each( function( i, el ) {
			transformMediaElement( el );
		} );
	}
	
	/**
	 * Transform a single media element into an ogv.js player if needed.
	 * @param el HTMLMediaElement target element
	 * @return promise
	 */
	function transformMediaElement( el ) {
		return $.Deferred( function( deferred ) {
			var oga = /^audio\/ogg;\s*codecs="(vorbis|opus)"$/,
				ogv = /^video\/ogg;\s*codecs="theora(,\s*(vorbis|opus))?"$/,
				nativeSources = 0,
				oggSources = 0,
				preferredSource = null,
				preferredSourceHeight = 0,
				supportsOgvJs = OGVCompat.supported( 'OGVPlayer' ),
				maxJsHeight = (OGVCompat.isSlow() ? 160 : 360);
			$( el ).find( 'source' ).each( function( j, source ) {
				var type = source.type;
				if ( el.canPlayType( type ) ) {
					nativeSources++;
				} else if ( supportsOgvJs ) {
					if ( type.match( oga ) || type.match( ogv ) ) {
						oggSources++;
						var height = $( source ).data( 'height' );
						if ( ( preferredSource === null || height > preferredSourceHeight ) && height <= maxJsHeight ) {
							preferredSource = source;
							preferredSourceHeight = height;
						}
					}
				}
			} );
			if ( oggSources > 0 && nativeSources === 0 ) {
				mw.OgvJsSupport.loadOgvJs().then( function() {
					if ( typeof OGVPlayer === 'undefined' ) {
						// failed to load ogv.js
						deferred.resolve( el );
					} else {
						var player = createPlayer();
						
						player.src = preferredSource.src;
						player.poster = el.poster;
						$( player ).on( 'click', function() {
							if ( player.paused ) {
								player.play();
							} else {
								player.pause();
							}
						} );
						
						$( el ).replaceWith( player );
						deferred.resolve( player );
					}
				} );
			} else {
				deferred.resolve( el );
			}
		} );
	}

	function createPlayer() {
		var options = {
			base: mw.OgvJsSupport.basePath()
		};
		if ( Mtmh.audioContext ) {
			// Reuse the audio context we opened earlier
			options.audioContext = Mtmh.audioContext;
		}
		return new OGVPlayer( options );
	}

	function prepTransforms( $container ) {
		transformMediaPopups( $container );
		transformMediaElements( $container );
	}

	$( function() {
		prepTransforms( $( '#bodyContent' ) );
	} );

} )( jQuery, mediaWiki, OGVCompat );
