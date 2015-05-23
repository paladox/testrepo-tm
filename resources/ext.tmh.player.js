( function ( $, mw, videojs ) {
	$( function () {
		var config,
			videoplayers = $( '.video-js' );

		config = { techOrder: ['html5', 'ogv.js'] };

		videoplayers.each( videoplayer, function() {
			videojs( videoplayer, config, function() {
				/* More custom stuff goes here */
			};
		} );
	} );

} )( jQuery, mediaWiki, videojs );
