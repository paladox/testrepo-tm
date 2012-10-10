/**
* EmbedPlayer loader
*/
( function( mw, $ ) {
	/**
	* Add a DOM ready check for player tags
	*/
	$(function( event, callback ){
		// Check if we have tags to rewrite:
		if( $( mw.getConfig( 'EmbedPlayer.RewriteSelector' )  ).length ) {
			// Rewrite the embedPlayer EmbedPlayer.RewriteSelector and run callback once ready:
			$( mw.getConfig( 'EmbedPlayer.RewriteSelector' ) )
				.embedPlayer();
		}
	});

	/**
	* Add the mwEmbed jQuery loader wrapper
	*/
	$.fn.embedPlayer = function( readyCallback ){
		var playerSelect;
		if( this.selector ){
			playerSelect = this.selector;
		} else {
			playerSelect = this;
		}
		mw.log( 'jQuery.fn.embedPlayer :: ' + playerSelect );

		// Set up the embed video player class request: (include the skin js as well)
		var dependencySet = [
			'mw.EmbedPlayer'
		];

		var rewriteElementCount = 0;
		$( playerSelect).each( function(inx, playerElement){
			var skinName ='';
			// we have javascript ( disable controls ) 
			$( playerElement ).removeAttr( 'controls' );
			// Add an overlay loader ( firefox has its own native loading spinner )
			if( !$.browser.mozilla ){
				$( playerElement )
					.parent()
					.getAbsoluteOverlaySpinner()
					.attr('id', 'loadingSpinner_' + $( playerElement ).attr('id') )
			}
			// Allow other modules update the dependencies
			$( mw ).trigger( 'EmbedPlayerUpdateDependencies',
					[ playerElement, dependencySet ] );
		});

		// Remove any duplicates in the dependencySet:
		dependencySet = $.unique( dependencySet );

		// Do the request and process the playerElements with updated dependency set
		mediaWiki.loader.using( dependencySet, function(){
			// Setup the enhanced language: 
			window.gM = mw.jqueryMsg.getMessageFunction( {} );
			mw.processEmbedPlayers( playerSelect, readyCallback );
		}, function( e ){
			throw new Error( 'Error loading EmbedPlayer dependency set: ' + e.message  );
		});
	};


} )( window.mediaWiki, window.jQuery );
