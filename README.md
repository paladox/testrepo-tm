TimedMediaHandler
=========

This extension provides a media handler for the Ogg, WebM, mp4 container format.
When enabled, a player will be automatically embedded in the file description
page, or any wiki page while still using the same syntax as for images.

* Broad support for input file formats
* Transcoder to make video at web resolutions when embedding clips in a page
* include support for timed Text per the w3c "track" recommendation
* use embedPlayer mwEmbed javascript module for playback

To install this extension, add the following to the end of your
LocalSettings.php:

For MediaWiki 1.24 or lower please add this

```php
// You need mwEmbedSupport ( if not already added )
require_once( "$IP/extensions/MwEmbedSupport/MwEmbedSupport.php" );

// TimedMediaHandler
require_once( "$IP/extensions/TimedMediaHandler/TimedMediaHandler.php" );
```

For MediaWiki 1.25 or higher please add this

```php
// TimedMediaHandler
wfLoadExtensions( array( 'TimedMediaHandler' ) );
```

### Running Transcodes

Transcoding a video to another resolution or format takes a good amount which
prevents that processing to be handled as a web service. Instead, the extension
implements an asynchronous job, named webVideoTranscode, which you must be
running regularly as your web server user.

The job can be run using the MediaWiki maintenance/runJobs.php utility (do not
forget to su as a webserver user):

```php
php runJobs.php --type webVideoTranscode --maxjobs 1
```

### Kaltura HTML5 player library

TimedMediaHandler uses the Kaltura HTML5 player library for video playback, it
relies on the <video> element as well as JavaScript.

For more information about the player library visit: http://www.html5video.org/kaltura-player/docs

### Libav

We use Libav for two purposes:
 - creating still images of videos (aka thumbnails)
 - transcoding WebM, H.264 videos

Wikimedia currently uses libav as shipped in Ubuntu 12.04 (libav 0.8.x).
For best experience use that or any later release from http://libav.org.

On Ubuntu/Debian:
  apt-get install libav-tools

For H.264 support:
  apt-get install libav-tools libavcodec-extra-53

If you operating system does not include the required libav software,
you can download static builds for multiple platforms at:
  http://firefogg.org/nightly/

You can also build libav/ffmpeg from source.
Guide for building ffmpeg with H.264 for Ubuntu:
https://ffmpeg.org/trac/ffmpeg/wiki/UbuntuCompilationGuide

Some old versions of FFmpeg had a bug which made it extremely slow to seek in
large theora videos in order to generate a thumbnail.  If you are using an old
version of FFmpeg and find that performance is extremely poor (tens of seconds)
to generate thumbnails of theora videos that are several minutes or more in
length. Please update to a more recent version.

In MediaWiki configuration, after the require line in LocalSettings.php, you
will have to specify the FFmpeg binary location with:
```php
$wgFFmpegLocation = '/path/to/ffmpeg';
```

Default being `/usr/bin/avconv`.

### ffmpeg2theora

We use ffmpeg2theora for extract metadata from videos, you will need a copy on
your server. For best experience, use the latest release of ffmpeg2theora. At a
minimum you need to use ffmpeg2thoera 0.27.

You can download static ffmpeg2theora builds for multiple platforms at:
http://firefogg.org/nightly/

Set the ffmpeg2theora binary location with:

```php
$wgFFmpeg2theoraLocation = '/path/to/ffmpeg2theora';
```

Default being `/usr/bin/ffmpeg2theora`.

oggThumb
-------

We use oggvideotools for creating still images of videos, you will need a copy on your
server.

Set the oggThumb binary location with:

```php
$wgOggThumbLocation = '/path/to/oggThumb';
```

Download oggThumb from: http://dev.streamnik.de/oggvideotools.html


### PEAR File_Ogg

Tim Starling, a Wikimedia developer, forked the PEAR File_Ogg package and
improved it significantly to support this extension.

The PEAR bundle is licensed under the LGPL, you can get informations about
this package on the pear webpage: http://pear.php.net/package/File_Ogg

### getID3

getID3 is used for metadata of WebM files.

getID3() by James Heinrich <info@getid3.org>
available at http://getid3.sourceforge.net
or http://www.getid3.org/

getID3 code is released under the GNU GPL:
http://www.gnu.org/copyleft/gpl.html

### Configuration settings

The following variables can be overridden in your LocalSettings.php file:

```php
// Path overdie for cortado ( by default its false and uses hard coded paths relative to TMH
// or the predefined path on upload server: http://upload.wikimedia.org/jars/cortado.jar
$wgCortadoJarFile = false;

// Show a warning to the user if they are not using an html5 browser with high quality ogg support
$wgMwEmbedModuleConfig['EmbedPlayer.DirectFileLinkWarning'] = true;

// Show the options menu:
$wgMwEmbedModuleConfig['EmbedPlayer.EnableOptionsMenu'] = true;

// TMH needs java ( no h.264 or mp3 derivatives )
$wgMwEmbedModuleConfig['EmbedPlayer.DisableJava' ] = true;
$wgMwEmbedModuleConfig['EmbedPlayer.DisableHTML5FlashFallback' ] = true;

// The text interface should always be shown
// ( even if there are no text tracks for that asset at render time )
$wgMwEmbedModuleConfig['TimedText.ShowInterface'] = 'always';

// Show the add text link:
$wgMwEmbedModuleConfig['TimedText.ShowAddTextLink'] = true;

// How long you have to wait between transcode resets for non-error transcodes
$wgWaitTimeForTranscodeReset = 3600;

// The minimum size for an embed video player ( smaller than this size uses a pop-up player )
$wgMinimumVideoPlayerSize = 200;

// Set the supported ogg codecs:
$wgMediaVideoTypes = array( 'Theora', 'VP8' );
$wgMediaAudioTypes = array( 'Vorbis', 'Speex', 'FLAC', 'Opus' );

// Default skin for mwEmbed player
$wgVideoPlayerSkinModule = 'mw.PlayerSkinKskin';

// Support iframe for remote embedding
$wgEnableIframeEmbed = true;

// If transcoding is enabled for this wiki (if disabled, no transcode jobs are added and no
// transcode status is displayed). Note if remote embedding an asset we will still check if
// the remote repo has transcoding enabled and associated flavors for that media embed.
$wgEnableTranscode = true;

// If the job runner should run transcode commands in a background thread and monitor the
// transcoding progress. This enables more fine grain control of the transcoding process, wraps
// encoding commands in a lower priority 'nice' call, and kills long running transcodes that are
// not making any progress. If set to false, the job runner will use the more compatible
// php blocking shell exec command.
$wgEnableNiceBackgroundTranscodeJobs = false;

// The priority to be used with the nice transcode commands.
$wgTranscodeBackgroundPriority = 19;

// The total amout of time a transcoding shell command can take:
$wgTranscodeBackgroundTimeLimit = 3600 * 8;
// Maximum amount of virtual memory available to transcoding processes in KB
$wgTranscodeBackgroundMemoryLimit = 2 * 1024 * 1024; // 2GB avconv, ffmpeg2theora mmap resources so virtual memory needs to be high enough
// Maximum file size transcoding processes can create, in KB
$wgTranscodeBackgroundSizeLimit = 3 * 1024 * 1024; // 3GB

// Number of threads to use in avconv for transcoding
$wgFFmpegThreads = 1;

// The NS for TimedText (registered on MediaWiki.org)
// http://www.mediawiki.org/wiki/Extension_namespace_registration
// Note commons pre-dates TimedMediaHandler and should set $wgTimedTextNS = 102 in LocalSettings.php
$wgTimedTextNS = 710;

// Set TimedText namespace for ForeignDBViaLBRepo on a per wikiID basis
// $wgTimedTextForeignNamespaces = array( 'commonswiki' => 102 );
$wgTimedTextForeignNamespaces = array();

// Set to false to disable local TimedText,
// you still get subtitles for videos from foreign repos
// to disable all TimedText, set
// $wgMwEmbedModuleConfig['TimedText.ShowInterface'] = 'off';
$wgEnableLocalTimedText = true;

/**
 * Default enabled transcodes
 *
 * -If set to empty array, no derivatives will be created
 * -Derivative keys encode settings are defined in WebVideoTranscode.php
 *
 * -These transcodes are *in addition to* the source file.
 * -Only derivatives with smaller width than the source asset size will be created
 * -Regardless of source size at least one WebM and Ogg source will be created from the $wgEnabledTranscodeSet
 * -Derivative jobs are added to the MediaWiki JobQueue the first time the asset is displayed
 * -Derivative should be listed min to max
 */
$wgEnabledTranscodeSet = array(

	// WebM VP8/Vorbis
	// primary free/open video format
	// supported by Chrome/Firefox/Opera but not Safari/IE/Edge

	// Medium-bitrate web streamable WebM video
	WebVideoTranscode::ENC_WEBM_360P,

	// Moderate-bitrate web streamable WebM video
	WebVideoTranscode::ENC_WEBM_480P,

	// A high quality WebM stream
	WebVideoTranscode::ENC_WEBM_720P,

	// A full-HD high quality WebM stream
	WebVideoTranscode::ENC_WEBM_1080P,


	// Ogg Theora/Vorbis
	// Fallback for Safari/IE/Edge with ogv.js
	//
	// Requires twice the bitrate for same quality as VP8,
	// and JS decoder can be slow, so shift to smaller sizes.

	// Low-bitrate Ogg stream
	WebVideoTranscode::ENC_OGV_160P,

	// Medium-bitrate Ogg stream
	WebVideoTranscode::ENC_OGV_240P,

	// Moderate-bitrate Ogg stream
	WebVideoTranscode::ENC_OGV_360P,

	// High-bitrate Ogg stream
	WebVideoTranscode::ENC_OGV_480P,

/*
	// MP4 H.264/AAC
	// Primary format for the Apple/Microsoft world
	//
	// Check patent licensing issues in your country before use!
	// Similar to WebM in quality/bitrate

	// A least common denominator h.264 stream; first gen iPhone, iPods, early android etc.
	WebVideoTranscode::ENC_H264_320P,

	// A mid range h.264 stream; mid range phones and low end tables
	WebVideoTranscode::ENC_H264_480P,

	// An high quality HD stream; higher end phones, tablets, smart tvs
	WebVideoTranscode::ENC_H264_720P,

	// A full-HD high quality stream; higher end phones, tablets, smart tvs
	WebVideoTranscode::ENC_H264_1080P,
*/
);

$wgEnabledAudioTranscodeSet = array(
	WebVideoTranscode::ENC_OGG_VORBIS,

	//opus support must be available in avconv
	//WebVideoTranscode::ENC_OGG_OPUS,

	//avconv needs libmp3lame support
	//WebVideoTranscode::ENC_MP3,

	//avconv needs libvo_aacenc support
	//WebVideoTranscode::ENC_AAC,
);

// If mp4 source assets can be ingested:
$wgTmhEnableMp4Uploads = false;


// List of extensions handled by Timed Media Handler since its referenced in a few places.
// you should not modify this variable

$wgTmhFileExtensions = array( 'ogg', 'ogv', 'oga', 'flac', 'wav', 'webm', 'mp4' );

$wgFileExtensions = array_merge( $wgFileExtensions, $wgTmhFileExtensions );
```
