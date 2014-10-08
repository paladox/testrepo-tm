( function( $, mw, M, support ) {

	/**
	 * Transform a video or audio element into an ogvjs player
	 * if required and appropriate, otherwise leave it be.
	 *
	 * @param element video or audio element to transform
	 * @return promise fulfilled with the original or transformed media element
	 */
	function transformMediaElementsForOgvJs( element ) {
		var result = $.Deferred();

		// First check for media sources that we *can't* play natively
		var needOgvJs = false;

		nativeSources = 0;
		nonNativeSources = 0;
		$( element ).find( 'source' ).each( function( j, source ) {
			var canPlay = element.canPlayType( source.type ) ;
			if ( canPlay == 'probably' || canPlay == 'maybe' ) {
				nativeSources++;
			} else {
				nonNativeSources++;
			}
		} );
		if (nativeSources == 0 && nonNativeSources >= 0) {
			needOgvJs = true;
		}

		// Only load the magic loader if necessary
		if ( needOgvJs ) {
			var ext = mw.config.get( 'wgExtensionAssetsPath' );

			function transformMediaElement( element ) {

				var ogvjs = new OgvJsPlayer({
					base: support.basePath(),
					audioContext: M.tmh.initAudioContext()
				});
				ogvjs.id = element.id;

				if (element.getAttribute('poster')) {
					ogvjs.poster = element.getAttribute('poster');
				}

				var width, height;
				if (element.tagName == 'AUDIO') {
					// Size to the button
					width = 128;
					height = 128;
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
				var playIcon = 'url(' + ext + '/TimedMediaHandler/resources/player_big_play_button.png)';
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
						playButton.style.backgroundImage = 'none';
					} else {
						ogvjs.pause();
						playButton.style.backgroundImage = playIcon;
					}
				});

				// quick hack
				if ( M.tmh.autoplay ) {
					playButton.style.backgroundImage = 'none';
				}

				ogvjs.appendChild(playButton); // hacky?

				element.parentNode.replaceChild(ogvjs, element);

				return ogvjs;
			}

			support.loadOgvJs().done( function() {
				var transformed = transformMediaElement( element );
				result.resolve( transformed );
			});
		} else {
			result.resolve( element );
		}

		return result;
	}

	var Overlay = M.require( 'Overlay' ),
		Icon = M.require( 'Icon' ),
		ImageOverlay = M.require( 'modules/mediaViewer/ImageOverlay' );

	/**
	 * @class MediaOverlay
	 * @extends Overlay
	 */
	var MediaOverlay = ImageOverlay.extend( {
		className: 'overlay media-viewer timedmedia-viewer',
		defaults: {
			// For some reason this doesn't come through automatically from
			// the Overlay base class.
			cancelButton: new Icon( { tagName: 'button',
                                        name: 'cancel', additionalClassNames: 'cancel',
                                        label: mw.msg( 'mobile-frontend-overlay-close' )
                                } ).toHtmlString()
		},

		postRender: function( options ) {
			var self = this;
			Overlay.prototype.postRender.apply( this, arguments );

			var $target = $( '#' + this.options.title );
			var $content;

			if ( $target.is( 'audio, video' ) ) {
				$content = $target.clone();
			} else if ( $target.attr( 'videopayload' ) ) {
				$content = $( '<div></div>' )
					.html( $target.attr( 'videopayload' ) )
					.find( 'audio, video' );
			} else {
				console.log( $target[0] );
				throw new Error( 'Invalid media player loaded' );
			}

			// Save the aspect ratio...
			this.thumbWidth = parseInt( $content.css( 'width' ) );
			this.thumbHeight = parseInt( $content.css( 'height' ) );
			this.imgRatio = this.thumbWidth / this.thumbHeight;

			var title = $content.data( 'mwtitle' ),
				titleObj = new mw.Title( 'File:' + title ),
				link = titleObj.getUrl();
			this.$( '.details a' ).attr( 'href', link );

			transformMediaElementsForOgvJs( $content[0] ).done( function( element ) {
				self.$( '.spinner' ).hide();
				self.$( '.image' ).append( element );
				self._player = element;

				if ( M.tmh.autoplay ) {
					element.play();
					M.tmh.autoplay = false;
				}
			} );

			this._positionImage();

			$( window ).on( 'resize', $.proxy( this, '_positionImage' ) );
		},

		show: function() {
			Overlay.prototype.show.apply( this, arguments );
			this._positionImage();
		},

		_positionImage: function() {
			var detailsHeight = this.$( '.details' ).height(),
				windowWidth = $( window ).width(),
				windowHeight = $( window ).height() - detailsHeight,
				windowRatio = windowWidth / windowHeight,
				$media = this.$( 'audio,video,ogvjs' );

			// display: table (which we use for vertical centering) makes the overlay
			// expand so simply setting width/height to 100% doesn't work
			if ( this.imgRatio > windowRatio ) {
				if ( windowWidth < this.thumbWidth ) {
					$media.css( {
						width: windowWidth,
						height: Math.round( this.thumbHeight * windowWidth / this.thumbWidth )
					} );
				}
			} else {
				if ( windowHeight < this.thumbHeight ) {
					$media.css( {
						width: Math.round( this.thumbWidth * windowHeight / this.thumbHeight ),
						height: windowHeight
					} );
				}
			}
			this.$( '.image-wrapper' ).css( 'bottom', detailsHeight );
		}
	} );
	M.define( 'modules/tmh/MediaOverlay', MediaOverlay );

} )( jQuery, mw, mw.mobileFrontend, mw.OgvJsSupport );
