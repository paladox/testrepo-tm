<?php

/**
 * Hooks for TimedMediaHandler extension
 *
 * @file
 * @ingroup Extensions
 */

class TimedMediaHandlerHooks {

	/**
	 * Executed after processing extension.json
	 */
	public static function registerExtension() {
		global $wgFileExtensions, $wgTmhFileExtensions, $wgEnabledTranscodeSet,
		$wgEnabledAudioTranscodeSet;

		// List of file extensions handled by Timed Media Handler since its referenced in
		// a few places. You should not modify this variable.
		$wgTmhFileExtensions = array( 'ogg', 'ogv', 'oga', 'flac', 'wav', 'webm', 'mp4' );

		$wgFileExtensions = array_merge( $wgFileExtensions, $wgTmhFileExtensions );

		// @codingStandardsIgnoreStart
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
		// @codingStandardsIgnoreEnd
		$features = array(
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

		$audiotranscode = array(
			WebVideoTranscode::ENC_OGG_VORBIS,

			// opus support must be available in avconv
			// WebVideoTranscode::ENC_OGG_OPUS,

			// avconv needs libmp3lame support
			// WebVideoTranscode::ENC_MP3,

			// avconv needs libvo_aacenc support
			// WebVideoTranscode::ENC_AAC,
		);

		if ( $wgEnabledTranscodeSet ) {
			foreach ( $features as $settings ) {
				if ( isset( $wgEnabledTranscodeSet ) ) {
					$wgEnabledTranscodeSet += $settings;
				} else {
					$wgEnabledTranscodeSet = $settings;
				}
			}
		} else {
			$wgEnabledTranscodeSet = $features;
		}

		if ( $wgEnabledAudioTranscodeSet ) {
			foreach ( $audiotranscode as $setting ) {
				if ( isset( $wgEnabledAudioTranscodeSet ) ) {
					$wgEnabledAudioTranscodeSet += $setting;
				} else {
					$wgEnabledAudioTranscodeSet = $setting;
				}
			}
		} else {
			$wgEnabledAudioTranscodeSet = $audiotranscode;
		}
	}

	// Register TimedMediaHandler Hooks
	public static function register() {
		global $wgHooks, $wgJobClasses, $wgJobTypesExcludedFromDefaultQueue, $wgMediaHandlers,
		$wgResourceModules, $wgExcludeFromThumbnailPurge, $wgExtraNamespaces, $wgParserOutputHooks,
		$wgTimedTextNS, $wgFileExtensions, $wgTmhEnableMp4Uploads, $wgExtensionAssetsPath,
		$wgMwEmbedModuleConfig, $wgEnableLocalTimedText, $wgTmhFileExtensions,
		$wgTmhTheoraTwoPassEncoding;

		// Remove mp4 if not enabled:
		if ( $wgTmhEnableMp4Uploads === false ) {
			$index = array_search( 'mp4', $wgFileExtensions );
			if ( $index !== false ) {
				array_splice( $wgFileExtensions, $index, 1 );
			}
		}

		// Enable experimental 2-pass Theora encoding if enabled:
		if ( $wgTmhTheoraTwoPassEncoding ) {
			foreach ( WebVideoTranscode::$derivativeSettings as $key => &$settings ) {
				if ( isset( $settings['videoCodec'] ) && $settings['videoCodec'] === 'theora' ) {
					$settings['twopass'] = 'true';
				}
			}
		}

		if ( !class_exists( 'MwEmbedResourceManager' ) ) {
			echo "TimedMediaHandler requires the MwEmbedSupport extension.\n";
			exit( 1 );
		}

		// Register the Timed Media Handler javascript resources ( MwEmbed modules )
		MwEmbedResourceManager::register( 'extensions/TimedMediaHandler/MwEmbedModules/EmbedPlayer' );
		MwEmbedResourceManager::register( 'extensions/TimedMediaHandler/MwEmbedModules/TimedText' );

		// Set the default webPath for this embed player extension
		$wgMwEmbedModuleConfig['EmbedPlayer.WebPath'] = $wgExtensionAssetsPath .
			'/' . basename( __DIR__ ) . '/MwEmbedModules/EmbedPlayer';

		// Setup media Handlers:
		$wgMediaHandlers['application/ogg'] = 'OggHandlerTMH';
		$wgMediaHandlers['audio/webm'] = 'WebMHandler';
		$wgMediaHandlers['video/webm'] = 'WebMHandler';
		$wgMediaHandlers['video/mp4'] = 'Mp4Handler';
		$wgMediaHandlers['audio/x-flac'] = 'FLACHandler';
		$wgMediaHandlers['audio/flac'] = 'FLACHandler';
		$wgMediaHandlers['audio/wav'] = 'WAVHandler';

		// Add transcode job class:
		$wgJobClasses['webVideoTranscode'] = 'WebVideoTranscodeJob';

		// Transcode jobs must be explicitly requested from the job queue:
		$wgJobTypesExcludedFromDefaultQueue[] = 'webVideoTranscode';

		$baseExtensionResource = array(
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'TimedMediaHandler',
		);

		// Add the PopUpMediaTransform module ( specific to timedMedia handler )
		// ( no support in mwEmbed modules )
		$wgResourceModules += array(
			'ext.tmh.thumbnail.styles' => $baseExtensionResource + array(
				'styles' => 'resources/ext.tmh.thumbnail.css',
				'position' => 'top',
			),
			'mw.PopUpMediaTransform' => $baseExtensionResource + array(
				'scripts' => 'resources/mw.PopUpThumbVideo.js',
				'dependencies' => array(
					'mw.MwEmbedSupport',
					'mediawiki.Title',
					'mw.PopUpMediaTransform.styles'
				),
				'position' => 'top',
			),
			'mw.PopUpMediaTransform.styles' => $baseExtensionResource + array(
				'position' => 'top',
				'styles' => 'resources/PopUpThumbVideo.css',
			),
			'mw.TMHGalleryHook.js' => $baseExtensionResource + array(
				'scripts' => 'resources/mw.TMHGalleryHook.js',
				// position top needed as it needs to load before mediawiki.page.gallery
				'position' => 'top',
			),
			'embedPlayerIframeStyle'=> $baseExtensionResource + array(
				'styles' => 'resources/embedPlayerIframe.css',
				'position' => 'bottom',
			),
			'ext.tmh.embedPlayerIframe' => $baseExtensionResource + array(
				'scripts' => 'resources/ext.tmh.embedPlayerIframe.js',
				'dependencies' => array(
					'jquery.embedPlayer',
					'mw.MwEmbedSupport',
				),
			),
			'ext.tmh.transcodetable' => $baseExtensionResource + array(
				'scripts' => 'resources/ext.tmh.transcodetable.js',
				'styles' => 'resources/transcodeTable.css',
				'dependencies' => array(
					'mediawiki.api.edit',
					'oojs-ui',
				),
				'messages'=> array(
					'timedmedia-reset-button-cancel',
					'timedmedia-reset-button-dismiss',
					'timedmedia-reset-button-reset',
					'timedmedia-reset-error',
					'timedmedia-reset',
					'timedmedia-reset-areyousure',
					'timedmedia-reset-explanation',
				)
			),
			'ext.tmh.TimedTextSelector' =>  $baseExtensionResource + array(
				'scripts' => 'resources/ext.tmh.TimedTextSelector.js',
			),
			"mw.MediaWikiPlayerSupport" =>  $baseExtensionResource + array(
				'scripts' => 'resources/mw.MediaWikiPlayerSupport.js',
				'dependencies'=> array(
					'mw.Api',
					'mw.MwEmbedSupport',
				),
			),
			// adds support MediaWikiPlayerSupport player bindings
			"mw.MediaWikiPlayer.loader" => $baseExtensionResource + array(
				'scripts' => 'resources/mw.MediaWikiPlayer.loader.js',
				'dependencies' => array(
					"mw.EmbedPlayer.loader",
					"mw.TimedText.loader",
				),
				'position' => 'top',
			),
		);

		// Add OgvJs-related modules for Safari/IE/Edge Ogg playback
		$wgResourceModules += array(
			'ext.tmh.OgvJsSupport' => $baseExtensionResource + array(
				'scripts' => array(
					'MwEmbedModules/EmbedPlayer/binPlayers/ogv.js/ogv-support.js',
					'resources/ext.tmh.OgvJsSupport.js',
				),
				'targets' => array( 'mobile', 'desktop' ),
			),
			'ext.tmh.OgvJs' => $baseExtensionResource + array(
				'scripts' => array(
					'MwEmbedModules/EmbedPlayer/binPlayers/ogv.js/ogv.js',
				),
				'dependencies' => 'ext.tmh.OgvJsSupport',
				'targets' => array( 'mobile', 'desktop' ),
			),
		);

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

		// Bug T63923: Make sure modules are loaded for the image history of image pages.
		// This is needed when ImageOpenShowImageInlineBefore is not triggered (diff previews).
		$wgHooks['ImagePageFileHistoryLine'][] = 'TimedMediaHandlerHooks::onImagePageFileHistoryLine';

		// Exclude transcoded assets from normal thumbnail purging
		// ( a maintenance script could handle transcode asset purging)
		if ( isset( $wgExcludeFromThumbnailPurge ) ) {
			$wgExcludeFromThumbnailPurge = array_merge( $wgExcludeFromThumbnailPurge, $wgTmhFileExtensions );
			// Also add the .log file ( used in two pass encoding )
			// ( probably should move in-progress encodes out of web accessible directory )
			$wgExcludeFromThumbnailPurge[] = 'log';
		}

		$wgHooks['LoadExtensionSchemaUpdates'][] = 'TimedMediaHandlerHooks::loadExtensionSchemaUpdates';

		// Add unit tests
		$wgHooks['UnitTestsList'][] = 'TimedMediaHandlerHooks::registerUnitTests';

		/**
		 * Add support for the "TimedText" NameSpace
		 */
		if ( $wgEnableLocalTimedText ) {
			define( "NS_TIMEDTEXT", $wgTimedTextNS );
			define( "NS_TIMEDTEXT_TALK", $wgTimedTextNS +1 );

			$wgExtraNamespaces[NS_TIMEDTEXT] = "TimedText";
			$wgExtraNamespaces[NS_TIMEDTEXT_TALK] = "TimedText_talk";

			// Check for timed text page:
			$wgHooks[ 'ArticleFromTitle' ][] = 'TimedMediaHandlerHooks::checkForTimedTextPage';
			$wgHooks[ 'ArticleContentOnDiff' ][] = 'TimedMediaHandlerHooks::checkForTimedTextDiff';
		} else {
			$wgTimedTextNS = false;
			// overwrite TimedText.ShowInterface for video with mw-provider=local
			$wgMwEmbedModuleConfig['TimedText.ShowInterface.local'] = 'off';
		}

		// Add transcode status to video asset pages:
		$wgHooks['ImagePageAfterImageLinks'][] = 'TimedMediaHandlerHooks::checkForTranscodeStatus';
		$wgHooks['NewRevisionFromEditComplete'][] =
			'TimedMediaHandlerHooks::onNewRevisionFromEditComplete';
		$wgHooks['ArticlePurge'][] = 'TimedMediaHandlerHooks::onArticlePurge';

		$wgHooks['LoadExtensionSchemaUpdates'][] = 'TimedMediaHandlerHooks::checkSchemaUpdates';
		$wgHooks['wgQueryPages'][] = 'TimedMediaHandlerHooks::onwgQueryPages';
		$wgHooks['RejectParserCacheValue'][] = 'TimedMediaHandlerHooks::rejectParserCacheValue';
		return true;
	}

	/**
	 * @param ImagePage $imagePage the imagepage that is being rendered
	 * @param OutputPage $out the output for this imagepage
	 * @return bool
	 */
	public static function onImageOpenShowImageInlineBefore( &$imagePage, &$out ) {
		$file = $imagePage->getDisplayedFile();
		return TimedMediaHandlerHooks::onImagePageHooks( $file, $out );
	}

	/**
	 * @param ImagePage $imagePage that is being rendered
	 * @param File $file the (old) file added in this history entry
	 * @param string &$line the HTML of the history line
	 * @param string &$css the CSS class of the history line
	 * @return bool
	 */
	public static function onImagePageFileHistoryLine( $imagePage, $file, &$line, &$css ) {
		$out = $imagePage->getContext()->getOutput();
		return TimedMediaHandlerHooks::onImagePageHooks( $file, $out );
	}

	/**
	 * @param File $file the file that is being rendered
	 * @param OutputPage $wgOut the output to which this file is being rendered
	 * @return bool
	 */
	private static function onImagePageHooks( $file, $out ) {
		$handler = $file->getHandler();
		if ( $handler !== false && $handler instanceof TimedMediaHandler ) {
			$out->addModuleStyles( 'ext.tmh.thumbnail.styles' );
			$out->addModules( array(
				'mw.MediaWikiPlayer.loader',
				'mw.PopUpMediaTransform',
				'mw.TMHGalleryHook.js',
			) );
		}
		return true;
	}

	/**
	 * @param $title Title
	 * @param $article Article
	 * @return bool
	 */
	public static function checkForTimedTextPage( &$title, &$article ) {
		global $wgTimedTextNS;
		if ( $title->getNamespace() === $wgTimedTextNS ) {
			$article = new TimedTextPage( $title );
		}
		return true;
	}

	/**
	 * @param $diffEngine DifferenceEngine
	 * @param $output OutputPage
	 * @return bool
	 */
	public static function checkForTimedTextDiff( $diffEngine, $output ) {
		global $wgTimedTextNS;
		if ( $output->getTitle()->getNamespace() === $wgTimedTextNS ) {
			$article = new TimedTextPage( $output->getTitle() );
			$article->renderOutput( $output );
			return false;
		}
		return true;
	}

	/**
	 * Wraps the isTranscodableFile function
	 * @param $title Title
	 * @return bool
	 */
	public static function isTranscodableTitle( $title ) {
		if ( $title->getNamespace() != NS_FILE ) {
			return false;
		}
		$file = wfFindFile( $title );
		return self::isTranscodableFile( $file );
	}

	/**
	 * Utility function to check if a given file can be "transcoded"
	 * @param $file File object
	 * @return bool
	 */
	public static function isTranscodableFile( & $file ) {
		global $wgEnableTranscode, $wgEnabledAudioTranscodeSet;

		// don't show the transcode table if transcode is disabled
		if ( !$wgEnableTranscode && !$wgEnabledAudioTranscodeSet ) {
			return false;
		}
		// Can't find file
		if ( !$file ) {
			return false;
		}
		// We can only transcode local files
		if ( !$file->isLocal() ) {
			return false;
		}

		$handler = $file->getHandler();
		// Not able to transcode files without handler
		if ( !$handler ) {
			return false;
		}
		$mediaType = $handler->getMetadataType( $file );
		// If ogg or webm format and not audio we can "transcode" this file
		$isAudio = $handler instanceof TimedMediaHandler && $handler->isAudio( $file );
		if ( ( $mediaType == 'webm' || $mediaType == 'ogg' || $mediaType =='mp4' )
			&& !$isAudio
		) {
			return true;
		}
		if ( $isAudio && count( $wgEnabledAudioTranscodeSet ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param $article Article
	 * @param $html string
	 * @return bool
	 */
	public static function checkForTranscodeStatus( $article, &$html ) {
		// load the file:
		$file = wfFindFile( $article->getTitle() );
		if ( self::isTranscodableFile( $file ) ) {
			$html .= TranscodeStatusTable::getHTML( $file );
		}
		return true;
	}

	/**
	 * @param $image UploadBase
	 * @return bool
	 */
	public static function checkUploadComplete( $upload ) {
		$file = $upload->getLocalFile();
		// Check that the file is a transcodable asset:
		if ( $file && self::isTranscodableFile( $file ) ) {
			// Remove all the transcode files and db states for this asset
			WebVideoTranscode::removeTranscodes( $file );
			WebVideoTranscode::startJobQueue( $file );
		}
		return true;
	}
	/**
	 * Handle moved titles
	 *
	 * For now we just remove all the derivatives for the oldTitle. In the future we could
	 * look at moving the files, but right now thumbs are not moved, so I don't want to be
	 * inconsistent.
	 * @param $title Title
	 * @param $newTitle Title
	 * @param $user User
	 * @return bool
	 */
	public static function checkTitleMove( $title, $newTitle, $user ) {
		if ( self::isTranscodableTitle( $title ) ) {
			// Remove all the transcode files and db states for this asset
			// ( will be re-added the first time the asset is displayed with its new title )
			$file = wfFindFile( $title );
			WebVideoTranscode::removeTranscodes( $file );
		}
		return true;
	}

	/**
	 * Hook to FileDeleteComplete
	 * remove transcodes on delete
	 * @param $file File
	 * @param $oldimage
	 * @param $article Article
	 * @param $user User
	 * @param $reason string
	 * @return bool
	 */
	public static function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ) {
		if ( !$oldimage ) {
			if ( self::isTranscodableFile( $file ) ) {
				WebVideoTranscode::removeTranscodes( $file );
			}
		}
		return true;
	}

	/*
	 * If file gets reverted to a previous version, reset transcodes.
	 */
	public static function onNewRevisionFromEditComplete(
		$article, Revision $rev, $baseID, User $user
	) {
		if ( $baseID !== false ) {
			// Check if the article is a file and remove transcode files:
			if ( $article->getTitle()->getNamespace() == NS_FILE ) {
				$file = wfFindFile( $article->getTitle() );
				if ( self::isTranscodableFile( $file ) ) {
					WebVideoTranscode::removeTranscodes( $file );
					WebVideoTranscode::startJobQueue( $file );
				}
			}
		}
		return true;
	}

	/**
	 * When a user asks for a purge, perhaps through our handy "update transcode status"
	 * link, make sure we've got the updated set of transcodes. This'll allow a user or
	 * automated process to see their status and reset them.
	 */
	public static function onArticlePurge( $article ) {
		if ( $article->getTitle()->getNamespace() == NS_FILE ) {
			$file = wfFindFile( $article->getTitle() );
			if ( self::isTranscodableFile( $file ) ) {
				WebVideoTranscode::cleanupTranscodes( $file );
			}
		}
		return true;
	}

	/**
	 * Adds the transcode sql
	 * @param $updater DatabaseUpdater
	 * @return bool
	 */
	public static function loadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'transcode', __DIR__ . '/TimedMediaHandler.sql' );
		return true;
	}

	/**
	 * Hook to add list of PHPUnit test cases.
	 * @param $files Array of files
	 * @return bool
	 */
	public static function registerUnitTests( array &$files ) {
		$testDir = __DIR__ . '/tests/phpunit/';
		$testFiles = array(
			'TestTimeParsing.php',
			'TestApiUploadVideo.php',
			'TestVideoThumbnail.php',
			'TestVideoTranscode.php',
			'TestOggHandler.php',
			'TestWebMHandler.php',
			'TestTimedMediaTransformOutput.php',
			'TestTimedMediaHandler.php'
		);
		foreach ( $testFiles as $fileName ) {
			$files[] = $testDir . $fileName;
		}
		return true;
	}

	/**
	 * Add JavaScript and CSS for special pages that may include timed media
	 * but which will not fire the parser hook.
	 *
	 * FIXME: There ought to be a better interface for determining whether the
	 * page is liable to contain timed media.
	 *
	 * @param $out OutputPage
	 * @param $sk
	 * @return bool
	 */
	static function pageOutputHook( &$out, &$sk ) {
		global $wgTimedTextNS;

		$title = $out->getTitle();
		$namespace = $title->getNamespace();
		$addModules = false;

		if ( $namespace === NS_CATEGORY || $namespace === $wgTimedTextNS ) {
			$addModules = true;
		}

		if ( $title->isSpecialPage() ) {
			list( $name, /* subpage */ ) = SpecialPageFactory::resolveAlias( $title->getDBkey() );
			if ( stripos( $name, 'file' ) !== false || stripos( $name, 'image' ) !== false
				|| $name === 'Search' || $name === 'GlobalUsage' ) {
					$addModules = true;
			}
		}

		if ( $addModules ) {
			$out->addModuleStyles( 'ext.tmh.thumbnail.styles' );
			$out->addModules( array(
				'mw.MediaWikiPlayer.loader',
				'mw.PopUpMediaTransform',
			) );
		}

		return true;
	}

	public static function checkSchemaUpdates( DatabaseUpdater $updater ) {
		$base = __DIR__;

		switch ( $updater->getDB()->getType() ) {
		case 'mysql':
		case 'sqlite':
			 // Initial install tables
			$updater->addExtensionTable( 'transcode', "$base/TimedMediaHandler.sql" );
			$updater->addExtensionUpdate( array( 'addIndex', 'transcode', 'transcode_name_key',
				"$base/archives/transcode_name_key.sql", true ) );
			break;
		case 'postgres':
			// TODO
			break;
		}
		return true;
	}

	public static function onwgQueryPages( $qp ) {
		$qp[] = array( 'SpecialOrphanedTimedText', 'OrphanedTimedText' );
		return true;
	}

	/**
	 * Return false here to evict existing parseroutput cache
	 */
	public static function rejectParserCacheValue( $parserOutput, $wikiPage, $parserOptions ) {
		if (
			$parserOutput->getExtensionData( 'mw_ext_TMH_hasTimedMediaTransform' )
			|| isset( $parserOutput->hasTimedMediaTransform )
		) {
			/* page has old style TMH elements */
			if ( !in_array( 'mw.MediaWikiPlayer.loader', $parserOutput->getModules() ) ) {
				wfDebug( 'Bad TMH parsercache value, throw this out.' );
				$wikiPage->getTitle()->purgeSquid();
				return false;
			}
		}
		return true;
	}
}
