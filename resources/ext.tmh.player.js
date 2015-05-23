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
			config = {};
			start = $( videoplayer ).data( 'start' );
			end = $( videoplayer ).data( 'end' );
			if ( start || end ) {
				localConfig = {
					plugins: {
						offset: {
							start: start,
							end: end
						}
					}
				};
			}
			$.extend( config, localConfig, globalConfig);
			videojs( videoplayer, config, function () {
				/* More custom stuff goes here */
			} );
		} );
	} );

} )( jQuery, mediaWiki, videojs );
