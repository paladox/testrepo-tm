<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	// @codingStandardsIgnoreStart
	echo "This is the TimedMediaHandler extension. Please see the README file for installation instructions.\n";
	// @codingStandardsIgnoreEnd
	exit( 1 );
}

// Set up the timed media handler dir:
$timedMediaDir = __DIR__;
// Include WebVideoTranscode
// (prior to config so that its defined transcode keys can be used in configuration)
$wgAutoloadClasses['WebVideoTranscode'] = "$timedMediaDir/WebVideoTranscode/WebVideoTranscode.php";

// Add the rest transcode right:
$wgAvailableRights[] = 'transcode-reset';
$wgAvailableRights[] = 'transcode-status';

// Configure the webplayer. Allowed values: mwembed, videojs
$wgTmhWebPlayer = 'mwembed';

/*** MwEmbed module configuration: *********************************/

// Show a warning to the user if they are not using an html5 browser with high quality ogg support
$wgMwEmbedModuleConfig['EmbedPlayer.DirectFileLinkWarning'] = true;

// Show the options menu:
$wgMwEmbedModuleConfig['EmbedPlayer.EnableOptionsMenu'] = true;

$wgMwEmbedModuleConfig['EmbedPlayer.DisableHTML5FlashFallback' ] = true;

// The text interface should always be shown
// ( even if there are no text tracks for that asset at render time )
$wgMwEmbedModuleConfig['TimedText.ShowInterface'] = 'always';

// Show the add text link:
$wgMwEmbedModuleConfig['TimedText.ShowAddTextLink'] = true;

/*** Timed Media Handler configuration ****************************/

// Which users can restart failed or expired transcode jobs:
$wgGroupPermissions['sysop']['transcode-reset'] = true;
$wgGroupPermissions['autoconfirmed']['transcode-reset'] = true;

// Which users can see Special:TimedMediaHandler
$wgGroupPermissions['sysop']['transcode-status'] = true;

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
// 2GB avconv, ffmpeg2theora mmap resources so virtual memory needs to be high enough
$wgTranscodeBackgroundMemoryLimit = 2 * 1024 * 1024;
// Maximum file size transcoding processes can create, in KB
$wgTranscodeBackgroundSizeLimit = 3 * 1024 * 1024; // 3GB

// Number of threads to use in avconv for transcoding
$wgFFmpegThreads = 1;

// The location of ffmpeg2theora (transcoding)
// Set to false to use avconv/ffmpeg to produce Ogg Theora transcodes instead;
// beware this will disable Ogg skeleton metadata generation.
$wgFFmpeg2theoraLocation = '/usr/bin/ffmpeg2theora';

// Location of oggThumb binary ( used instead of ffmpeg )
$wgOggThumbLocation = '/usr/bin/oggThumb';

// Location of the avconv/ffmpeg binary (used to encode WebM and for thumbnails)
$wgFFmpegLocation = '/usr/bin/avconv';

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
 * -Regardless of source size at least one WebM and Ogg source
 *  will be created from the $wgEnabledTranscodeSet
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

	// A 4K full high quality WebM stream
	// WebVideoTranscode::ENC_WEBM_2160P,


	// Ogg Theora/Vorbis
	// Fallback for Safari/IE/Edge with ogv.js

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

	// A 4K high quality stream; higher end phones, tablets, smart tvs
	WebVideoTranscode::ENC_H264_2160P,
*/
);

$wgEnabledAudioTranscodeSet = array(
	WebVideoTranscode::ENC_OGG_VORBIS,

	// opus support must be available in avconv
	// WebVideoTranscode::ENC_OGG_OPUS,

	// avconv needs libmp3lame support
	// WebVideoTranscode::ENC_MP3,

	// avconv needs libvo_aacenc support
	// WebVideoTranscode::ENC_AAC,
);

// If mp4 source assets can be ingested:
$wgTmhEnableMp4Uploads = false;

// Two-pass encoding for .ogv Theora transcodes is flaky as of October 2015.
// Enable this only if testing with latest theora libraries!
// See tracking bug: https://phabricator.wikimedia.org/T115883
$wgTmhTheoraTwoPassEncoding = false;

/******************* CONFIGURATION ENDS HERE **********************/


// List of extensions handled by Timed Media Handler since its referenced in a few places.
// you should not modify this variable

$wgTmhFileExtensions = array( 'ogg', 'ogv', 'oga', 'flac', 'opus', 'wav', 'webm', 'mp4' );

$wgFileExtensions = array_merge( $wgFileExtensions, $wgTmhFileExtensions );

// Timed Media Handler AutoLoad Classes:
$wgAutoloadClasses['TimedMediaHandler'] = "$timedMediaDir/TimedMediaHandler_body.php";
$wgAutoloadClasses['TimedMediaHandlerHooks'] = "$timedMediaDir/TimedMediaHandler.hooks.php";
$wgAutoloadClasses['TimedMediaTransformOutput'] = "$timedMediaDir/TimedMediaTransformOutput.php";
$wgAutoloadClasses['TimedMediaIframeOutput'] = "$timedMediaDir/TimedMediaIframeOutput.php";
$wgAutoloadClasses['TimedMediaThumbnail'] = "$timedMediaDir/TimedMediaThumbnail.php";
// Transcode Page
$wgAutoloadClasses['TranscodeStatusTable'] = "$timedMediaDir/TranscodeStatusTable.php";

// Testing:
$wgAutoloadClasses['ApiTestCaseVideoUpload'] =
	"$timedMediaDir/tests/phpunit/ApiTestCaseVideoUpload.php";

// Ogg Handler
$wgAutoloadClasses['OggHandlerTMH'] = "$timedMediaDir/handlers/OggHandler/OggHandler.php";
$wgAutoloadClasses['OggException'] = "$timedMediaDir/handlers/OggHandler/OggException.php";
$wgAutoloadClasses['File_Ogg'] = "$timedMediaDir/handlers/OggHandler/File_Ogg/File/Ogg.php";
$wgAutoloadClasses['File_Ogg_Bitstream'] =
	"$timedMediaDir/handlers/OggHandler/File_Ogg/File/Ogg/Bitstream.php";
$wgAutoloadClasses['File_Ogg_Flac'] =
	"$timedMediaDir/handlers/OggHandler/File_Ogg/File/Ogg/Flac.php";
$wgAutoloadClasses['File_Ogg_Media'] =
	"$timedMediaDir/handlers/OggHandler/File_Ogg/File/Ogg/Media.php";
$wgAutoloadClasses['File_Ogg_Opus'] =
	"$timedMediaDir/handlers/OggHandler/File_Ogg/File/Ogg/Opus.php";
$wgAutoloadClasses['File_Ogg_Speex'] =
	"$timedMediaDir/handlers/OggHandler/File_Ogg/File/Ogg/Speex.php";
$wgAutoloadClasses['File_Ogg_Theora'] =
	"$timedMediaDir/handlers/OggHandler/File_Ogg/File/Ogg/Theora.php";
$wgAutoloadClasses['File_Ogg_Vorbis'] =
	"$timedMediaDir/handlers/OggHandler/File_Ogg/File/Ogg/Vorbis.php";

// getID3 provides metadata for mp4 and webm files:
$wgAutoloadClasses['getID3'] = "$timedMediaDir/libs/getid3/getid3.php";
$wgAutoloadClasses['getid3_exception'] = "$timedMediaDir/libs/getid3/getid3.php";
$wgAutoloadClasses['getid3_handler'] = "$timedMediaDir/libs/getid3/getid3.php";

// ID3 Metadata Handler
$wgAutoloadClasses['ID3Handler'] = "$timedMediaDir/handlers/ID3Handler/ID3Handler.php";
// Mp4 / h264 Handler
$wgAutoloadClasses['Mp4Handler'] = "$timedMediaDir/handlers/Mp4Handler/Mp4Handler.php";
// WebM Handler
$wgAutoloadClasses['WebMHandler'] = "$timedMediaDir/handlers/WebMHandler/WebMHandler.php";
// FLAC Handler
$wgAutoloadClasses['FLACHandler'] = "$timedMediaDir/handlers/FLACHandler/FLACHandler.php";
// WAV Handler
$wgAutoloadClasses['WAVHandler'] = "$timedMediaDir/handlers/WAVHandler/WAVHandler.php";

// Text handler
$wgAutoloadClasses['ForeignApiQueryAllPages'] =
	"$timedMediaDir/handlers/TextHandler/TextHandler.php";
$wgAutoloadClasses['TextHandler'] = "$timedMediaDir/handlers/TextHandler/TextHandler.php";
$wgAutoloadClasses['TimedTextPage'] = "$timedMediaDir/TimedTextPage.php";

// Transcode support
$wgAutoloadClasses['WebVideoTranscodeJob'] =
	"$timedMediaDir/WebVideoTranscode/WebVideoTranscodeJob.php";

// API modules:
$wgAutoloadClasses['ApiQueryVideoInfo'] = "$timedMediaDir/ApiQueryVideoInfo.php";
$wgAPIPropModules['videoinfo'] = 'ApiQueryVideoInfo';

$wgAutoloadClasses['ApiTranscodeStatus'] = "$timedMediaDir/ApiTranscodeStatus.php";
$wgAPIPropModules['transcodestatus'] = 'ApiTranscodeStatus';

$wgAutoloadClasses['ApiTranscodeReset'] = "$timedMediaDir/ApiTranscodeReset.php";
$wgAPIModules['transcodereset'] = 'ApiTranscodeReset';

// Localization
$wgMessagesDirs['TimedMediaHandler'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['TimedMediaHandlerMagic'] =
	"$timedMediaDir/TimedMediaHandler.i18n.magic.php";
$wgExtensionMessagesFiles['TimedMediaHandlerAliases'] =
	"$timedMediaDir/TimedMediaHandler.i18n.alias.php";
// Inlcude module locationlizations
$wgMessagesDirs['MwEmbed.EmbedPlayer'] = __DIR__ . '/MwEmbedModules/EmbedPlayer/i18n';
$wgMessagesDirs['MwEmbed.TimedText'] = __DIR__ . '/MwEmbedModules/TimedText/i18n';

// Special Pages
$wgAutoloadClasses['SpecialTimedMediaHandler'] = "$timedMediaDir/SpecialTimedMediaHandler.php";
$wgAutoloadClasses['SpecialOrphanedTimedText'] = "$timedMediaDir/SpecialOrphanedTimedText.php";

// Register all Timed Media Handler hooks right after initial setup
// This way if you set a variable like $wgTimedTextNS in LocalSettings.php
// after you include TimedMediaHandler we can still read the variable values
$wgExtensionFunctions[] = 'TimedMediaHandlerHooks::register';

// Setup a hook for iframe embed handling:
$wgHooks['ArticleFromTitle'][] = 'TimedMediaIframeOutput::iframeHook';

// When an upload completes ( check clear any existing transcodes )
$wgHooks['UploadComplete'][] = 'TimedMediaHandlerHooks::checkUploadComplete';

// When an image page is moved:
$wgHooks['TitleMove'][] = 'TimedMediaHandlerHooks::checkTitleMove';

// When image page is deleted so that we remove transcode settings / files.
$wgHooks['FileDeleteComplete'][] = 'TimedMediaHandlerHooks::onFileDeleteComplete';

// Use a BeforePageDisplay hook to load the styles in pages that pull in media dynamically.
// (Special:Upload, for example, when there is an "existing file" warning.)
$wgHooks['BeforePageDisplay'][] = 'TimedMediaHandlerHooks::pageOutputHook';

// Make sure modules are loaded on image pages that don't have a media file in the wikitext.
$wgHooks['ImageOpenShowImageInlineBefore'][] =
	'TimedMediaHandlerHooks::onImageOpenShowImageInlineBefore';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'TimedMediaHandlerHooks::loadExtensionSchemaUpdates';

// Add unit tests
$wgHooks['UnitTestsList'][] = 'TimedMediaHandlerHooks::registerUnitTests';

// Add transcode status to video asset pages:
$wgHooks['ImagePageAfterImageLinks'][] = 'TimedMediaHandlerHooks::checkForTranscodeStatus';
$wgHooks['NewRevisionFromEditComplete'][] = 'TimedMediaHandlerHooks::onNewRevisionFromEditComplete';
$wgHooks['ArticlePurge'][] = 'TimedMediaHandlerHooks::onArticlePurge';
$wgHooks['wgQueryPages'][] = 'TimedMediaHandlerHooks::onwgQueryPages';

$wgHooks['RejectParserCacheValue'][] = 'TimedMediaHandlerHooks::rejectParserCacheValue';

# add Special pages
$wgSpecialPages['OrphanedTimedText'] = 'SpecialOrphanedTimedText';
$wgSpecialPages['TimedMediaHandler'] = 'SpecialTimedMediaHandler';

// Extension Credits
$wgExtensionCredits['media'][] = array(
	'path' => __FILE__,
	'name' => 'TimedMediaHandler',
	'namemsg' => 'timedmediahandler-extensionname',
	'author' => array(
		'Michael Dale',
		'Tim Starling',
		'James Heinrich',
		'Jan Gerber',
		'Brion Vibber',
		'Derk-Jan Hartman'
	),
	'url' => 'https://www.mediawiki.org/wiki/Extension:TimedMediaHandler',
	'descriptionmsg' => 'timedmediahandler-desc',
	'version' => '0.5.0',
	'license-name' => 'GPL-2.0+',
);
