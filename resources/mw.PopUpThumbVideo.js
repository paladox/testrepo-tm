/**
* Simple script to add pop-up video dialog link support for video thumbnails
*/
( function ( mw, $ ) {
	$( document ).ready( function () {
		$('.PopUpMediaTransform a').each( function () {
			var link, title,
				parent = $( this ).parent();
			if ( parent.attr( 'videopayload' ) ) {
				$( this ).click( function ( /*event*/ ) {
					var thisref = this;

					mw.loader.using( 'mw.MwEmbedSupport', function () {
						var $videoContainer = $( $( thisref ).parent().attr( 'videopayload' ) );
						mw.addDialog({
							'width': 'auto',
							'height': 'auto',
							'title': mw.html.escape( $videoContainer.find( 'video, audio' ).attr( 'data-mwtitle' ) ),
							'content': $videoContainer,
							'close': function(){
								// On close destroy the dialog rather than just hiding it,
								// so it doesn't eat up resources or keep playing.
								$( this ).remove();
								return true;
							}
						})
						.css( 'overflow', 'hidden' )
						.find( 'video, audio' ).embedPlayer();
					} );
					// don't follow file link
					return false;
				} );
			} else if ( parent.attr( 'data-videopayload' ) ) {
				link = $( this ).attr( 'href' );
				title = mw.Title.newFromImg( { src: link } );
				if ( title && title.getPrefixedDb() !== mw.config.get( 'wgPageName' ) ) {
					$( this ).attr( 'href', title.getUrl() );
				}
			} /* else fall back to linking directly to media file */
		} );
	} );
} )( mediaWiki, jQuery );
