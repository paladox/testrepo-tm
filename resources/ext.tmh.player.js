( function ( $, mw, videojs ) {
	$( function () {
		var config,
			videoplayers = $( '.video-js' );

		config = {
			language: mw.config.get( 'wgUserLanguage' ),
			children: {
				controlBar: {
					children: {
						liveDisplay: false
					}
				}
			},
			techOrder: ['html5', 'ogv.js']
		};

		videoplayers.each( function( index, videoplayer ) {
			videojs( videoplayer, config, function() {
				/* More custom stuff goes here */
			} );
		} );
	} );

} )( jQuery, mediaWiki, videojs );
