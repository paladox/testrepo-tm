/**
 * Play the video using the vlc app on iOS
 */

( function( mw, $ ) { "use strict";

mw.EmbedPlayerVLCApp = {
	// List of supported features (or lack thereof)
	 supports: {
		'playHead':false,
		'pause':false,
		'stop':true,
		'fullscreen':false,
		'timeDisplay':false,
		'volumeControl':false
	},

	// Instance name:
	instanceOf:'VLCApp',

	/*
	* Embed this "fake" player
	*
	* @return {String}
	* 	embed code to link to VLC app
	*/
	embedPlayerHTML: function() {
		var fileUrl = this.getSrc( this.seekTimeSec ),
			vlcUrl = 'vlc://' + fileUrl,
			appStoreUrl = 'https://itunes.apple.com/us/app/vlc-for-ios/id650377962?mt=8',
			appInstalled = false,
			promptInstallTimeout;

		// Replace video with download in vlc link.
		$( this ).html( $( '<div class="vlcapp-player"></div>' )
			.width( this.getWidth() )
			.height( this.getHeight() )
			.append(
				// mw.msg doesn't have rawParams() equivalent. Lame.
				mw.html.escape(
					mw.msg( 'mwe-embedplayer-vlcapp-intro' ).replace( /\$1/g,
						$( '<a></a>' ).attr( 'href', appStoreUrl )
							.text( mw.msg( 'mwe-embedplayer-vlcapp-vlcapplinktext' ) )
					)
				)
			).append( $( '<ul></ul>' )
				.append( $( '<li></li>' ).append( $( '<a></a>' ).attr( 'href', appStoreUrl )
					.text( mw.msg( 'mwe-embedplayer-vlcapp-downloadapp' ) ) )
				).append( $( '<li></li>' ).append( $( '<a></a>' ).attr( 'href', vlcUrl )
					.text( mw.msg( 'mwe-embedplayer-vlcapp-openvideo' ) ) )
				).append( $( '<li></li>' ).append( $( '<a></a>' ).attr( 'href', fileUrl )
					.text( mw.msg( 'mwe-embedplayer-vlcapp-downloadvideo' ) ) )
				)
			)
		);

		// Try to auto-open in vlc.
		// Based on http://stackoverflow.com/questions/627916/check-if-url-scheme-is-supported-in-javascript

		$( window ).one( 'pagehide', function() {
			appInstalled = true;
			if ( promptInstallTimeout ) {
				window.clearTimeout( promptInstallTimeout );
			}
		} );
		try {
			window.location = vlcUrl;
		} catch( e ) {
			// FIXME, temporary for testing. Unclear if iOS throws exception.
			alert( "Exception from setting url " + e );
		}
		promptInstallTimeout = window.setTimeout( function() {
			var install;
			if ( appInstalled ) {
				return;
			}
			install = confirm( 'To play videos on this site, you need the free VLC app. Install now?' );
			if ( install ) {
				window.location = appStoreUrl;

			}
		}, 600 );
	}
};

} )( window.mediaWiki, window.jQuery );
