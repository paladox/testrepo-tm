( function ( $, mw, videojs ) {
	$( function () {
		var globalConfig, localConfig, config,
			start, end,
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

		videoplayers.each( function ( index, videoplayer ) {
			start = $( videoplayer ).data( 'start' );
			end = $( videoplayer ).data( 'end' );
			videojs( videoplayer, globalConfig, function () {
				/* More custom stuff goes here */
			} );
		} );
	} );

} )( jQuery, mediaWiki, videojs );
