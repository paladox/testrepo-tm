( function( $, mw ) {

	// First check for media elements that we *can't* play natively
	var needOgvJs = false;
	var $media = $( 'audio,video' );
	$media.each( function( i, element ) {
		nativeSources = 0;
		nonNativeSources = 0;
		$( element ).find( 'source' ).each( function( j, source ) {
			var canPlay = element.canPlayType( source.type ) ;
			console.log(source.type, canPlay);
			if ( canPlay == 'probably' || canPlay == 'maybe' ) {
				nativeSources++;
			} else {
				nonNativeSources++;
			}
		} );
		console.log(nativeSources, nonNativeSources);
		if (nativeSources == 0 && nonNativeSources >= 0) {
			needOgvJs = true;
		}
	} );
	
	// Only load the magic loader if necessary
	if ( needOgvJs ) {
		var ext = mw.config.get( 'wgExtensionAssetsPath' ),
			base = ext + '/TimedMediaHandler/MwEmbedModules/EmbedPlayer/binPlayers/ogv.js',
			script = base + '/ogvjs.js?version=0.1.5';

		function transformMediaElement( element ) {
			console.log(element);

			var ogvjs = new OgvJsPlayer({
				base: base
			});
			ogvjs.id = element.id;
			
			if (element.poster && element.poster != '') {
				ogvjs.poster = element.poster;
			}
			
			var width, height;
			if (element.tagName == 'AUDIO') {
				// Size to the button
				width = 80;
				height = 53;
			} else {
				width = parseInt(element.style.width);
				height = parseInt(element.style.height);
				var contentWidth = $('#content').width();
			
				if ( width > contentWidth ) {
					// fit on screen :P
					height = Math.round( height * contentWidth / width );
					width = contentWidth;
				}
			}
			ogvjs.width = width;
			ogvjs.height = height;
			
			var durationHint = element.getAttribute( 'data-durationhint' );
			if ( durationHint ) {
				ogvjs.durationHint = parseFloat( durationHint );
			}

			// Fixme pick size based on processor speed?
			var sources = [];
			$( element ).find( 'source' ).each( function( i, source ) {
				var canPlay = ogvjs.canPlayType( source.type ) ;
				if ( canPlay == 'probably' || canPlay == 'maybe' ) {
					sources.push(source.src);
				}
			} );
			ogvjs.src = sources[0];

			// Set up some basic controls
			var playIcon = 'url(' + ext + '/TimedMediaHandler/resources/player_big_play_button.png)',
				pauseIcon = 'url(' + ext + '/TimedMediaHandler/resources/player_big_pause_button.png)';
			var playButton = document.createElement( 'button' );
			playButton.style.appearance = 'none';
			playButton.style.webKitAppearance = 'none';
			playButton.style.display = 'block';
			playButton.style.position = 'absolute';
			playButton.style.left = '0';
			playButton.style.right = '0';
			playButton.style.width = width + 'px';
			playButton.style.height = height + 'px';
			playButton.style.backgroundImage = playIcon;
			playButton.style.backgroundRepeat = 'no-repeat';
			playButton.style.backgroundPosition = 'center center';
			playButton.addEventListener( 'click', function() {
				if ( ogvjs.paused ) {
					ogvjs.play();
					if (element.tagName == 'AUDIO') {
						playButton.style.backgroundImage = pauseIcon;
					} else {
						playButton.style.backgroundImage = 'none';
					}
				} else {
					ogvjs.pause();
					playButton.style.backgroundImage = playIcon;
				}
			});


			ogvjs.appendChild(playButton); // hacky?

			element.parentNode.replaceChild(ogvjs, element);
		}

		$.ajax({
			url: script,
			dataType: 'script',
			cacheable: true
		}).success( function() {
			$media.each( function( i, el ) {
				transformMediaElement( el )
			} );
		});
	}

} )( jQuery, mw );
