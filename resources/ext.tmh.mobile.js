( function( $, mw, M ) {

	// Globalish state for the lightweight mobile media player
	M.tmh = {
		autoplay: false,
		audioContext: null,
		initAudioContext: function() {
			if ( !M.tmh.audioContext ) {
				M.tmh.audioContext = mw.OgvJsSupport.initAudioContext();
			}
			return M.tmh.audioContext;
		}
	};

	function autoplayClick( ev ) {
		ev.preventDefault();

		// Use a single audio context for all players,
		// and pre-initialize it from event handler on iOS
		// to work around the restrictions on auto playback.
		M.tmh.initAudioContext();
		M.tmh.autoplay = true;

		M.router.navigate( this.getAttribute( 'href' ) );
	}

	function transformMediaElementsForPopup( container ) {
		var $container = $( container ),
			$popupTransforms = $container.find( '.PopUpMediaTransform' ),
			$mediaContainers = $container.find( '.mediaContainer' );
		
		// Transform the target URL for existing popup players
		$popupTransforms.each( function( i, el ) {
			var $popupTransform = $( el ),
				id = $popupTransform.attr( 'id' ),
				link = '#/media/' + id;
			$popupTransform
				.find( 'a' )
					.attr( 'href', link )
					.attr( 'target', '' )
					.off()
					.on( M.tapEvent( 'click' ), autoplayClick )
					.end();
		} );
		
		// Emulate the popup transform done in TMH for large players,
		// since we want to encapsulate all media in popups for mobile.
		$mediaContainers.each( function( i, el ) {
			var $mediaContainer = $( el ),
				$media = $mediaContainer.find( 'audio,video' ),
				id = $media.attr( 'id' ),
				link = '#/media/' + id,
				poster = $media.attr( 'poster' );
			
			$media.attr( 'id', null );
			$mediaContainer
				.attr( 'videopayload', $mediaContainer.html() )
				.html( '<img><a><span class="play-btn-large"><span class="mw-tmh-playtext"></span></span></a>' )
				.addClass( 'PopUpMediaTransform' )
				.attr( 'id', id )
				.find( 'img' )
					.attr( 'src', poster )
					.end()
				.find( 'a' )
					.attr( 'href', link )
					.off()
					.on( M.tapEvent( 'click' ), autoplayClick )
					.end();
			
		} );
	}

	function loadMediaOverlay( title ) {
		console.log('loading', title);
		var result = $.Deferred();
		
		mw.loader.using( 'ext.tmh.mobile.MediaOverlay', function() {

			var MediaOverlay = M.require( 'modules/tmh/MediaOverlay' );
			var overlay = new MediaOverlay( {
				title: decodeURIComponent( title )
			} );

			// ogv.js does not currently stop automatically
			// when you detach it from the DOM.
			overlay.on( 'hide', function() {
				console.log('hiding');
				console.log(overlay);
				var player = overlay.$el.find( 'audio,video,ogvjs' )[0];
				if (player && player.stop ) {
					player.stop();
				}
			} );

			result.resolve( overlay );
		} );

		return result;
	}
	M.overlayManager.add( /^\/media\/(.+)$/, loadMediaOverlay );
	
	// First, transform any live players immediately...
	transformMediaElementsForPopup( $( '#content_wrapper' ) );

	M.on( 'page-loaded', function( page ) {
		// And if we dynamically load a new page...
		transformMediaElementsForPopup( $( '#content_wrapper' ) );
	} );

} )( jQuery, mw, mw.mobileFrontend );
