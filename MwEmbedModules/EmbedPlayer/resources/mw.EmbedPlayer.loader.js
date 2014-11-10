/**
* EmbedPlayer loader
*/
( function( mw, $ ) {
	/**
	* Add a DOM ready check for player tags
	*/
	var embedPlayerInit = function( $content ) {
		var $selected = $( mw.config.get( 'EmbedPlayer.RewriteSelector' ), $content );
		if ( $selected.length ) {
			var inx = 0;
			var checkSetDone = function() {
				if ( inx < $selected.length ) {
					// put in timeout to avoid browser lockup, and function stack
					$selected.slice( inx, inx + 1 ).embedPlayer( function() {
						setTimeout( function() {
							checkSetDone();
						}, 5 );
					} );
				}
				inx++;
			};

			checkSetDone();
		}
	}
	mw.hook( 'wikipage.content' ).add( embedPlayerInit );

} )( mediaWiki, jQuery );
