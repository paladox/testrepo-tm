/**
 * mw.EmbedTypes object handles setting and getting of supported embed types:
 * closely mirrors OggHandler so that its easier to share efforts in this area:
 * http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/OggHandler/OggPlayer.js
 */
( function( mw, $ ) { "use strict";

/**
 * Setup local players and supported mime types In an ideal world we would query the plugin
 * to get what mime types it supports in practice not always reliable/available
 *
 * We can't cleanly store these values per library since player library is sometimes
 * loaded post player detection
 */
// Flash based players:
var kplayer = new mw.MediaPlayer('kplayer', [
	'video/x-flv',
	'video/h264',
	'video/mp4; codecs="avc1.42E01E"',
	'video/mp4; codecs="avc1.42E01E, mp4a.40.2"',
	'audio/mpeg'
], 'Kplayer');

// Java based player
var cortadoPlayer = new mw.MediaPlayer( 'cortado', [
	'video/ogg',
	'video/ogg; codecs="theora"',
	'video/ogg; codecs="theora, vorbis"',
	'audio/ogg',
	'audio/ogg; codecs="vorbis"',
	'application/ogg'
], 'Java' );

// Native html5 players
var oggNativePlayer = new mw.MediaPlayer( 'oggNative', [
	'video/ogg',
	'video/ogg; codecs="theora"',
	'video/ogg; codecs="theora, vorbis"',
	'audio/ogg',
	'audio/ogg; codecs="vorbis"',
	'application/ogg'
], 'Native' );
// Native html5 players
var opusNativePlayer = new mw.MediaPlayer( 'opusNative', [
	'audio/ogg; codecs="opus"',
], 'Native' );
var h264NativePlayer = new mw.MediaPlayer( 'h264Native', [
	'video/h264',
	'video/mp4; codecs="avc1.42E01E, mp4a.40.2"'
], 'Native' );
var appleVdnPlayer = new mw.MediaPlayer( 'appleVdn', [
	'application/vnd.apple.mpegurl',
	'application/vnd.apple.mpegurl; codecs="avc1.42E01E"'
], 'Native');
var mp3NativePlayer = new mw.MediaPlayer( 'mp3Native', [
	'audio/mpeg',
	'audio/mp3'
], 'Native' );
var aacNativePlayer = new mw.MediaPlayer( 'aacNative', [
	'audio/mp4',
	'audio/mp4; codecs="mp4a.40.5"'
], 'Native' );
var webmNativePlayer = new mw.MediaPlayer( 'webmNative', [
	'video/webm',
	'video/webm; codecs="vp8"',
	'video/webm; codecs="vp8, vorbis"',
	'audio/webm',
	'audio/webm; codecs="vorbis"'
], 'Native' );

// Image Overlay player ( extends native )
var imageOverlayPlayer = new mw.MediaPlayer( 'imageOverlay', [
	'image/jpeg',
	'image/png'
], 'ImageOverlay' );

// VLC player
//var vlcMimeList = ['video/ogg', 'audio/ogg', 'audio/mpeg', 'application/ogg', 'video/x-flv', 'video/mp4', 'video/h264', 'video/x-msvideo', 'video/mpeg', 'video/3gp'];
//var vlcPlayer = new mw.MediaPlayer( 'vlc-player', vlcMimeList, 'Vlc' );

var vlcAppPlayer = new mw.MediaPlayer( 'vlcAppPlayer', [
	'video/ogg',
	'video/ogg; codecs="theora"',
	'video/ogg; codecs="theora, vorbis"',
	'audio/ogg',
	'audio/ogg; codecs="vorbis"',
	'audio/ogg; codecs="opus"',
	'application/ogg',
	'video/webm',
	'video/webm; codecs="vp8"',
	'video/webm; codecs="vp8, vorbis"',
], 'VLCApp' );

var IEWebMPrompt = new mw.MediaPlayer( 'IEWebMPrompt', [
	'video/webm',
	'video/webm; codecs="vp8"',
	'video/webm; codecs="vp8, vorbis"'
], 'IEWebMPrompt' );

// Generic plugin
//var oggPluginPlayer = new mw.MediaPlayer( 'oggPlugin', ['video/ogg', 'application/ogg'], 'Generic' );


mw.EmbedTypes = {

	// MediaPlayers object ( supports methods for quering set of browser players )
	mediaPlayers: null,

	 // Detect flag for completion
	 detect_done:false,

	/**
	 * Runs the detect method and update the detect_done flag
	 *
	 * @constructor
	 */
	 init: function() {
		// detect supported types
		this.detect();
		this.detect_done = true;
	},

	getMediaPlayers: function(){
		if( this.mediaPlayers  ){
			return this.mediaPlayers;
		}
		this.mediaPlayers = new mw.MediaPlayers();
		// detect available players
		this.detectPlayers();
		return this.mediaPlayers;
	},

	/**
	 * If the browsers supports a given mimetype
	 *
	 * @param {String}
	 *      mimeType Mime type for browser plug-in check
	 */
	supportedMimeType: function( mimeType ) {
		for ( var i =0; i < navigator.plugins.length; i++ ) {
			var plugin = navigator.plugins[i];
			if ( typeof plugin[ mimeType ] != "undefined" ){
				return true;
			}
		}
		return false;
	},
	addFlashPlayer: function(){
		if( !mw.config.get( 'EmbedPlayer.DisableHTML5FlashFallback' ) ){
			this.mediaPlayers.addPlayer( kplayer );
		}
	},
	addJavaPlayer: function(){
		if( !mw.config.get( 'EmbedPlayer.DisableJava' ) ){
			mw.log("EmbedTypes::addJavaPlayer: adding cortadoPlayer");
			this.mediaPlayers.addPlayer( cortadoPlayer );
		}
	},
	/**
	 * Detects what plug-ins the client supports
	 */
	detectPlayers: function() {
		mw.log( "EmbedTypes::detectPlayers running detect" );

		// All players support for playing "images"
		this.mediaPlayers.addPlayer( imageOverlayPlayer );

		// In Mozilla, navigator.javaEnabled() only tells us about preferences, we need to
		// search navigator.mimeTypes to see if it's installed
		try{
			var javaEnabled = navigator.javaEnabled();
		} catch ( e ){

		}
		// Some browsers filter out duplicate mime types, hiding some plugins
		var uniqueMimesOnly = $.client.test( { opera: null, safari: null } );

		// Opera will switch off javaEnabled in preferences if java can't be
		// found. And it doesn't register an application/x-java-applet mime type like
		// Mozilla does.
		if ( javaEnabled && ( navigator.appName == 'Opera' ) ) {
			this.addJavaPlayer();
		}

		// Use core mw.supportsFlash check:
		if( mw.supportsFlash() ){
			this.addFlashPlayer();
		}

		// ActiveX plugins
		if ( $.client.profile().name === 'msie' ) {
			 // VLC
			 //if ( this.testActiveX( 'VideoLAN.VLCPlugin.2' ) ) {
			 //	 this.mediaPlayers.addPlayer( vlcPlayer );
			 //}

			 // Java ActiveX
			 if ( this.testActiveX( 'JavaWebStart.isInstalled' ) ) {
				 this.addJavaPlayer();
			 }

			 // quicktime (currently off)
			 // if ( this.testActiveX(
				// 'QuickTimeCheckObject.QuickTimeCheck.1' ) )
			 // this.mediaPlayers.addPlayer(quicktimeActiveXPlayer);
		 }
		// <video> element
		if ( ! mw.config.get('EmbedPlayer.DisableVideoTagSupport' ) // to support testing limited / old browsers
				&&
				(
				typeof HTMLVideoElement == 'object' // Firefox, Safari
					||
				typeof HTMLVideoElement == 'function' // Opera
				)
		){
			// Test what codecs the native player supports:
			try {
				var dummyvid = document.createElement( "video" );
				if( dummyvid.canPlayType ) {
					// Add the webm player
					if( dummyvid.canPlayType('video/webm; codecs="vp8, vorbis"') ){
						this.mediaPlayers.addPlayer( webmNativePlayer );
					}

					// Test for MP3:
					if ( this.supportedMimeType('audio/mpeg') ) {
							this.mediaPlayers.addPlayer( mp3NativePlayer );
					}

					// Test for AAC:
					if ( dummyvid.canPlayType( 'audio/mp4; codecs="mp4a.40.5"' ) ) {
							this.mediaPlayers.addPlayer( aacNativePlayer );
					}

					// Test for h264:
					if ( dummyvid.canPlayType('video/mp4; codecs="avc1.42E01E, mp4a.40.2"' ) ) {
						this.mediaPlayers.addPlayer( h264NativePlayer );
						// Check for iOS for vdn player support ( apple adaptive ) or vdn canPlayType != '' ( ie maybe/probably )
						if( mw.isIOS() || dummyvid.canPlayType('application/vnd.apple.mpegurl; codecs="avc1.42E01E"' ) ){
							// Android 3x lies about HLS support ( only add if not Android 3.x )
							if( navigator.userAgent.indexOf( 'Android 3.') == -1 ){
								this.mediaPlayers.addPlayer( appleVdnPlayer );
							}
						}

					}
					// For now if Android assume we support h264Native (FIXME
					// test on real devices )
					if ( mw.isAndroid2() ){
						this.mediaPlayers.addPlayer( h264NativePlayer );
					}

					// Test for ogg
					if ( dummyvid.canPlayType( 'video/ogg; codecs="theora, vorbis"' ) ) {
						this.mediaPlayers.addPlayer( oggNativePlayer );
					// older versions of safari do not support canPlayType,
				  	// but xiph qt registers mimetype via quicktime plugin
					} else if ( this.supportedMimeType( 'video/ogg' ) ) {
						this.mediaPlayers.addPlayer( oggNativePlayer );
					}

					// Test for opus
					if ( dummyvid.canPlayType( 'audio/ogg; codecs="opus"' ).replace(/maybe/, '') ) {
						this.mediaPlayers.addPlayer( opusNativePlayer );
					}
				}
			} catch ( e ) {
				mw.log( 'could not run canPlayType ' + e );
			}
		}

		 // "navigator" plugins
		if ( navigator.mimeTypes && navigator.mimeTypes.length > 0 ) {
			for ( var i = 0; i < navigator.mimeTypes.length; i++ ) {
				var type = navigator.mimeTypes[i].type;
				var semicolonPos = type.indexOf( ';' );
				if ( semicolonPos > -1 ) {
					type = type.substr( 0, semicolonPos );
				}
				// mw.log( 'on type: ' + type );
				var pluginName = navigator.mimeTypes[i].enabledPlugin ? navigator.mimeTypes[i].enabledPlugin.name : '';
				if ( !pluginName ) {
					// In case it is null or undefined
					pluginName = '';
				}
				//if ( pluginName.toLowerCase() == 'vlc multimedia plugin' || pluginName.toLowerCase() == 'vlc multimedia plug-in' ) {
				//	this.mediaPlayers.addPlayer( vlcPlayer );
				//	continue;
				//}

				if ( type == 'application/x-java-applet' ) {
					this.addJavaPlayer();
					continue;
				}

				if ( (type == 'video/mpeg' || type == 'video/x-msvideo') ){
					//pluginName.toLowerCase() == 'vlc multimedia plugin' ) {
					//this.mediaPlayers.addPlayer( vlcMozillaPlayer );
				}

				if ( type == 'application/ogg' ) {
					//if ( pluginName.toLowerCase() == 'vlc multimedia plugin' ) {
						//this.mediaPlayers.addPlayer( vlcMozillaPlayer );
					//else if ( pluginName.indexOf( 'QuickTime' ) > -1 )
						//this.mediaPlayers.addPlayer(quicktimeMozillaPlayer);
					//} else {
						//this.mediaPlayers.addPlayer( oggPluginPlayer );
					//}
					continue;
				} else if ( uniqueMimesOnly ) {
					if ( type == 'application/x-vlc-player' ) {
						// this.mediaPlayers.addPlayer( vlcMozillaPlayer );
						continue;
					} else if ( type == 'video/quicktime' ) {
						// this.mediaPlayers.addPlayer(quicktimeMozillaPlayer);
						continue;
					}
				}
			}
		}

		if ( mw.isIOS() ) {
			this.mediaPlayers.addPlayer( vlcAppPlayer );
		}

		// Note IE 11 doesn't identify itself as 'MSIE'.
		// For simplicity just check for the rendering engine codename 'Trident'.
		if ( navigator.userAgent.indexOf( 'Trident' ) != -1 ) {
			if ( this.mediaPlayers.isSupportedPlayer( 'webmNative' ) ) {
				// IE has the WebM components already, leave it be!
			} else if (navigator.userAgent.indexOf( 'ARM' ) != -1 ) {
				// Windows RT doesn't allow installation of the WebM components.
				// Don't tease the poor user.
			} else {
				// Prompt user to install the WebM media components for IE 9+
				this.mediaPlayers.addPlayer( IEWebMPrompt );
			}
		}
		// Allow extensions to detect and add their own "players"
		mw.log("EmbedPlayer::trigger:embedPlayerUpdateMediaPlayersEvent");
		$( mw ).trigger( 'embedPlayerUpdateMediaPlayersEvent' , this.mediaPlayers );

	},

	/**
	 * Test IE for activeX by name
	 *
	 * @param {String}
	 * 		name Name of ActiveXObject to look for
	 */
	testActiveX : function ( name ) {
		mw.log("EmbedPlayer::detect: test testActiveX: " + name);
		var hasObj = true;
		try {
			// No IE, not a class called "name", it's a variable
			var obj = new ActiveXObject( '' + name );
		} catch ( e ) {
			hasObj = false;
		}
		return hasObj;
	}
};


} )( mediaWiki, jQuery );
