( function ( $, mw, videojs ) {
	$( function () {
		var globalConfig,
			start, end,
			videoplayers = $( '.video-js' );

		globalConfig = {
			language: mw.config.get( 'wgUserLanguage' ),
			children: {
				controlBar: {
					children: {
						liveDisplay: false
					}
				}
			},
			plugins: {
				resolutionSelector: {
					'default_res': '480'
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
