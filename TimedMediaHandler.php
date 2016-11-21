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

// Controls weather to enable videojs beta feature
// Requires the BetaFeature extension be installed.
$wgTmhUseBetaFeatures = false;

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
$wgMediaVideoTypes = [ 'Theora', 'VP8' ];
$wgMediaAudioTypes = [ 'Vorbis', 'Speex', 'FLAC', 'Opus' ];

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
$wgTimedTextForeignNamespaces = [];

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
$wgEnabledTranscodeSet = [];

$wgEnabledAudioTranscodeSet = [];

// If mp4 source assets can be ingested:
$wgTmhEnableMp4Uploads = false;

// Two-pass encoding for .ogv Theora transcodes is flaky as of October 2015.
// Enable this only if testing with latest theora libraries!
// See tracking bug: https://phabricator.wikimedia.org/T115883
$wgTmhTheoraTwoPassEncoding = false;

/******************* CONFIGURATION ENDS HERE **********************/

// List of extensions handled by Timed Media Handler since its referenced in a few places.
// you should not modify this variable

$wgTmhFileExtensions = [ 'ogg', 'ogv', 'oga', 'flac', 'opus', 'wav', 'webm', 'mp4' ];

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
$wgParserTestFiles[] = "$timedMediaDir/tests/parserTests.txt";

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

$wgHooks['GetBetaFeaturePreferences'][] = 'TimedMediaHandlerHooks::onGetBetaFeaturePreferences';

$wgHooks['PageRenderingHash'][] = 'TimedMediaHandlerHooks::changePageRenderingHash';

// This way if you set a variable like $wgTimedTextNS in LocalSettings.php
// after you include TimedMediaHandler we can still read the variable values
// See also T123695 and T123823
$wgHooks['CanonicalNamespaces'][] = 'TimedMediaHandlerHooks::addCanonicalNamespaces';

// Register remaining Timed Media Handler hooks right after initial setup
$wgExtensionFunctions[] = 'TimedMediaHandlerHooks::register';

# add Special pages
$wgSpecialPages['OrphanedTimedText'] = 'SpecialOrphanedTimedText';
$wgSpecialPages['TimedMediaHandler'] = 'SpecialTimedMediaHandler';

// Extension Credits
$wgExtensionCredits['media'][] = [
	'path' => __FILE__,
	'name' => 'TimedMediaHandler',
	'namemsg' => 'timedmediahandler-extensionname',
	'author' => [
		'Michael Dale',
		'Tim Starling',
		'James Heinrich',
		'Jan Gerber',
		'Brion Vibber',
		'Derk-Jan Hartman'
	],
	'url' => 'https://www.mediawiki.org/wiki/Extension:TimedMediaHandler',
	'descriptionmsg' => 'timedmediahandler-desc',
	'version' => '0.5.0',
	'license-name' => 'GPL-2.0+',
];

// Setup a hook for iframe embed handling:
$wgHooks['ArticleFromTitle'][] = 'TimedMediaIframeOutput::iframeHook';

// When an upload completes ( check clear any existing transcodes )
$wgHooks['FileUpload'][] = 'TimedMediaHandlerHooks::onFileUpload';

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

// Bug T63923: Make sure modules are loaded for the image history of image pages.
// This is needed when ImageOpenShowImageInlineBefore is not triggered (diff previews).
$wgHooks['ImagePageFileHistoryLine'][] = 'TimedMediaHandlerHooks::onImagePageFileHistoryLine';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'TimedMediaHandlerHooks::loadExtensionSchemaUpdates';

// Add unit tests
$wgHooks['UnitTestsList'][] = 'TimedMediaHandlerHooks::registerUnitTests';
$wgHooks['ParserTestTables'][] = 'TimedMediaHandlerHooks::onParserTestTables';
$wgHooks['ParserTestGlobals'][] = 'TimedMediaHandlerHooks::onParserTestGlobals';

// Add transcode status to video asset pages:
$wgHooks['ImagePageAfterImageLinks'][] = 'TimedMediaHandlerHooks::checkForTranscodeStatus';
$wgHooks['NewRevisionFromEditComplete'][] =
	'TimedMediaHandlerHooks::onNewRevisionFromEditComplete';
$wgHooks['ArticlePurge'][] = 'TimedMediaHandlerHooks::onArticlePurge';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'TimedMediaHandlerHooks::checkSchemaUpdates';
$wgHooks['wgQueryPages'][] = 'TimedMediaHandlerHooks::onwgQueryPages';
$wgHooks['RejectParserCacheValue'][] = 'TimedMediaHandlerHooks::rejectParserCacheValue';

// Add transcode job class:
$wgJobClasses['webVideoTranscode'] = 'WebVideoTranscodeJob';

// Transcode jobs must be explicitly requested from the job queue:
$wgJobTypesExcludedFromDefaultQueue[] = 'webVideoTranscode';

$baseExtensionResource = [
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'TimedMediaHandler',
];

$wgResourceModules += [
	'ext.tmh.thumbnail.styles' => $baseExtensionResource + [
			'styles' => 'resources/ext.tmh.thumbnail.css',
			'position' => 'top',
		],
	'ext.tmh.transcodetable' => $baseExtensionResource + [
			'scripts' => 'resources/ext.tmh.transcodetable.js',
			'styles' => 'resources/transcodeTable.css',
			'dependencies' => [
				'mediawiki.api.edit',
				'oojs-ui',
			],
			'messages'=> [
				'timedmedia-reset-button-cancel',
				'timedmedia-reset-button-dismiss',
				'timedmedia-reset-button-reset',
				'timedmedia-reset-error',
				'timedmedia-reset',
				'timedmedia-reset-areyousure',
				'timedmedia-reset-explanation',
			]
		],
	'ext.tmh.TimedTextSelector' =>  $baseExtensionResource + [
			'scripts' => 'resources/ext.tmh.TimedTextSelector.js',
		],
	// Add OgvJs-related modules for Safari/IE/Edge Ogg playback
	'ext.tmh.OgvJsSupport' => $baseExtensionResource + [
			'scripts' => [
				'MwEmbedModules/EmbedPlayer/binPlayers/ogv.js/ogv-support.js',
				'resources/ext.tmh.OgvJsSupport.js',
			],
			'targets' => [ 'mobile', 'desktop' ],
		],
	'ext.tmh.OgvJs' => $baseExtensionResource + [
			'scripts' => [
				'MwEmbedModules/EmbedPlayer/binPlayers/ogv.js/ogv.js',
			],
			'dependencies' => 'ext.tmh.OgvJsSupport',
			'targets' => [ 'mobile', 'desktop' ],
		],
	'embedPlayerIframeStyle'=> $baseExtensionResource + [
			'styles' => 'resources/embedPlayerIframe.css',
			'targets' => [ 'mobile', 'desktop' ],
		],

	'mw.PopUpMediaTransform' => $baseExtensionResource + [
			'scripts' => 'resources/mw.PopUpThumbVideo.js',
			'dependencies' => [
				'mw.MwEmbedSupport',
				'mediawiki.Title',
				'mw.PopUpMediaTransform.styles'
			],
			'position' => 'top',
		],
	'mw.PopUpMediaTransform.styles' => $baseExtensionResource + [
			'position' => 'top',
			'styles' => 'resources/PopUpThumbVideo.css',
		],
	'mw.TMHGalleryHook.js' => $baseExtensionResource + [
			'scripts' => 'resources/mw.TMHGalleryHook.js',
			// position top needed as it needs to load before mediawiki.page.gallery
			'position' => 'top',
		],
	'ext.tmh.embedPlayerIframe' => $baseExtensionResource + [
			'scripts' => 'resources/ext.tmh.embedPlayerIframe.js',
			'dependencies' => [
				'jquery.embedPlayer',
				'mw.MwEmbedSupport',
			],
		],
	"mw.MediaWikiPlayerSupport" =>  $baseExtensionResource + [
			'scripts' => 'resources/mw.MediaWikiPlayerSupport.js',
			'dependencies'=> [
				'mw.Api',
				'mw.MwEmbedSupport',
			],
		],
	// adds support MediaWikiPlayerSupport player bindings
	"mw.MediaWikiPlayer.loader" => $baseExtensionResource + [
			'scripts' => 'resources/mw.MediaWikiPlayer.loader.js',
			'dependencies' => [
				"mw.EmbedPlayer.loader",
				"mw.TimedText.loader",
			],
			'position' => 'top',
		],
	'ext.tmh.video-js' => $baseExtensionResource + [
			'scripts' => 'resources/videojs/video.js',
			'styles' => 'resources/videojs/video-js.css',
			'noflip' => true,
			'targets' => [ 'mobile', 'desktop' ],
			'languageScripts' => [
				'ar' => 'resources/videojs/lang/ar.js',
				'ba' => 'resources/videojs/lang/ba.js',
				'bg' => 'resources/videojs/lang/bg.js',
				'ca' => 'resources/videojs/lang/ca.js',
				'cs' => 'resources/videojs/lang/cs.js',
				'da' => 'resources/videojs/lang/da.js',
				'de' => 'resources/videojs/lang/de.js',
				'el' => 'resources/videojs/lang/el.js',
				'en' => 'resources/videojs/lang/en.js',
				'es' => 'resources/videojs/lang/es.js',
				'fa' => 'resources/videojs/lang/fa.js',
				'fi' => 'resources/videojs/lang/fi.js',
				'fr' => 'resources/videojs/lang/fr.js',
				'hr' => 'resources/videojs/lang/hr.js',
				'hu' => 'resources/videojs/lang/hu.js',
				'it' => 'resources/videojs/lang/it.js',
				'ja' => 'resources/videojs/lang/ja.js',
				'ko' => 'resources/videojs/lang/ko.js',
				'nb' => 'resources/videojs/lang/nb.js',
				'nl' => 'resources/videojs/lang/nl.js',
				'nn' => 'resources/videojs/lang/nn.js',
				'pl' => 'resources/videojs/lang/pl.js',
				'pt-BR' => 'resources/videojs/lang/pt-BR.js',
				'ru' => 'resources/videojs/lang/ru.js',
				'sr' => 'resources/videojs/lang/sr.js',
				'sv' => 'resources/videojs/lang/sv.js',
				'tr' => 'resources/videojs/lang/tr.js',
				'uk' => 'resources/videojs/lang/uk.js',
				'vi' => 'resources/videojs/lang/vi.js',
				'zh-CN' => 'resources/videojs/lang/zh-CN.js',
				'zh-TW' => 'resources/videojs/lang/zh-TW.js',
			],
		],
	'ext.tmh.videojs-ogvjs' => $baseExtensionResource + [
			'scripts' => 'resources/videojs-ogvjs/videojs-ogvjs.js',
			'targets' => [ 'mobile', 'desktop' ],
			'dependencies' => [
				'ext.tmh.video-js',
				'ext.tmh.OgvJs',
			],
		],
	'ext.tmh.videojs-resolution-switcher' => $baseExtensionResource + [
			'scripts' => 'resources/videojs-resolution-switcher/videojs-resolution-switcher.js',
			'styles' => 'resources/videojs-resolution-switcher/videojs-resolution-switcher.css',
			'targets' => [ 'mobile', 'desktop' ],
			'dependencies' => [
				'ext.tmh.video-js',
			],
		],
	'ext.tmh.videojs-responsive-layout' => $baseExtensionResource + [
			'scripts' => 'resources/videojs-responsive-layout/videojs-responsive-layout.js',
			'targets' => [ 'mobile', 'desktop' ],
			'dependencies' => [
				'ext.tmh.video-js',
			],
		],
	'ext.tmh.videojs-replay' => $baseExtensionResource + [
			'scripts' => 'resources/videojs-replay/videojs-replay.js',
			'styles' => 'resources/videojs-replay/videojs-replay.css',
			'targets' => [ 'mobile', 'desktop' ],
			'dependencies' => [
				'ext.tmh.video-js',
			],
		],
	'ext.tmh.mw-info-button' => $baseExtensionResource + [
			'scripts' => 'resources/mw-info-button/mw-info-button.js',
			'styles' => 'resources/mw-info-button/mw-info-button.css',
			'targets' => [ 'mobile', 'desktop' ],
			'dependencies' => [
				'ext.tmh.video-js',
			],
		],
	'ext.tmh.player' => $baseExtensionResource + [
			'scripts' => 'resources/ext.tmh.player.js',
			'targets' => [ 'mobile', 'desktop' ],
			'dependencies' => [
				'ext.tmh.video-js',
				'ext.tmh.videojs-resolution-switcher',
				'ext.tmh.videojs-responsive-layout',
				'ext.tmh.videojs-replay',
				'ext.tmh.mw-info-button',
				'ext.tmh.OgvJsSupport',
			],
			'messages' => [
				'timedmedia-resolution-160',
				'timedmedia-resolution-240',
				'timedmedia-resolution-360',
				'timedmedia-resolution-480',
				'timedmedia-resolution-720',
				'timedmedia-resolution-1080',
				'timedmedia-resolution-1440',
				'timedmedia-resolution-2160',
			],
		],
	'ext.tmh.player.styles' => $baseExtensionResource + [
			'styles' => 'resources/ext.tmh.player.styles.less',
			'targets' => [ 'mobile', 'desktop' ],
		],
];

// Setup media Handlers:
$wgMediaHandlers['application/ogg'] = 'OggHandlerTMH';
$wgMediaHandlers['audio/webm'] = 'WebMHandler';
$wgMediaHandlers['video/webm'] = 'WebMHandler';
$wgMediaHandlers['video/mp4'] = 'Mp4Handler';
$wgMediaHandlers['audio/x-flac'] = 'FLACHandler';
$wgMediaHandlers['audio/flac'] = 'FLACHandler';
$wgMediaHandlers['audio/wav'] = 'WAVHandler';



if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}
