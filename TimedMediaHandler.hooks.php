<?php

/**
 * Hooks for TimedMediaHandler extension
 *
 * @file
 * @ingroup Extensions
 */

class TimedMediaHandlerHooks {

	/**
	 * Register TimedMediaHandler namespace IDs
	 * These are configurable due to Commons history: T123823
	 * These need to be before registerhooks due to: T123695
	 *
	 * @param array $list
	 * @return bool
	 */
	public static function addCanonicalNamespaces( array &$list ) {
		global $wgEnableLocalTimedText, $wgTimedTextNS;
		if ( $wgEnableLocalTimedText ) {
			if ( !defined( 'NS_TIMEDTEXT' ) ) {
				define( 'NS_TIMEDTEXT', $wgTimedTextNS );
				define( 'NS_TIMEDTEXT_TALK', $wgTimedTextNS +1 );
			}

			$list[NS_TIMEDTEXT] = 'TimedText';
			$list[NS_TIMEDTEXT_TALK] = 'TimedText_talk';
		} else {
			$wgTimedTextNS = false;
		}
		return true;
	}


	/**
	 * At some point these should be registered in extension.json
	 * But for now we register them dynamically, because they are config dependent,
	 * while we have two players
	 *
	 * @param ResourceLoader $resourceLoader
	 * @return bool
	 */
	public static function resourceLoaderRegisterModules( &$resourceLoader ) {
		$baseExtensionResource = [
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'TimedMediaHandler',
		];

		$resourceModules = [
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

		$resourceLoader->register( $resourceModules );
		return true;
	}

	/**
	 * Register TimedMediaHandler Hooks
	 *
	 * @return bool
	 */
	public static function register() {
		global $wgHooks, $wgJobClasses, $wgJobTypesExcludedFromDefaultQueue, $wgMediaHandlers,
		$wgResourceModules, $wgExcludeFromThumbnailPurge,
		$wgFileExtensions, $wgTmhEnableMp4Uploads, $wgExtensionAssetsPath,
		$wgMwEmbedModuleConfig, $wgEnableLocalTimedText, $wgTmhFileExtensions,
		$wgTmhTheoraTwoPassEncoding, $wgWikimediaJenkinsCI;

		// set config for parser tests
		if ( isset( $wgWikimediaJenkinsCI ) && $wgWikimediaJenkinsCI  === true ) {
			global $wgEnableTranscode, $wgFFmpegLocation;
			$wgEnableTranscode = false;
			$wgFFmpegLocation = '/usr/bin/ffmpeg';
		}

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

		if ( self::activePlayerMode() === 'mwembed' ) {
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
		}

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
		$wgHooks['ParserTestTables'][] = 'TimedMediaHandlerHooks::onParserTestTables';
		$wgHooks['ParserTestGlobals'][] = 'TimedMediaHandlerHooks::onParserTestGlobals';

		/**
		 * Add support for the "TimedText" NameSpace
		 */
		if ( $wgEnableLocalTimedText ) {
			// Check for timed text page:
			$wgHooks[ 'ArticleFromTitle' ][] = 'TimedMediaHandlerHooks::checkForTimedTextPage';
			$wgHooks[ 'ArticleContentOnDiff' ][] = 'TimedMediaHandlerHooks::checkForTimedTextDiff';

			$wgHooks[ 'SkinTemplateNavigation' ][] = 'TimedMediaHandlerHooks::onSkinTemplateNavigation';
		} else {
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
	 * @param OutputPage $out the output to which this file is being rendered
	 * @return bool
	 */
	private static function onImagePageHooks( $file, $out ) {
		$handler = $file->getHandler();
		if ( $handler !== false && $handler instanceof TimedMediaHandler ) {
			if ( self::activePlayerMode() === 'mwembed' ) {
				$out->addModuleStyles( 'ext.tmh.thumbnail.styles' );
				$out->addModules( [
					'mw.MediaWikiPlayer.loader',
					'mw.PopUpMediaTransform',
					'mw.TMHGalleryHook.js',
				] );
			}

			if ( self::activePlayerMode() === 'videojs' ) {
				$out->addModuleStyles( 'ext.tmh.player.styles' );
				$out->addModules( 'ext.tmh.player' );
			}
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
	 * @param SkinTemplate $sktemplate
	 * @param array $links
	 */
	public static function onSkinTemplateNavigation( SkinTemplate &$sktemplate, array &$links ) {
		if ( self::isTimedMediaHandlerTitle( $sktemplate->getTitle() ) ) {
			$ttTitle = Title::makeTitleSafe( NS_TIMEDTEXT, $sktemplate->getTitle()->getDBkey() );
			if ( !$ttTitle ) {
				return;
			}
			$links[ 'namespaces' ][ 'timedtext' ] =
				$sktemplate->tabAction( $ttTitle, 'timedtext', false, '', false );
		}
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
	 * @param $title Title
	 * @return bool
	 */
	public static function isTimedMediaHandlerTitle( $title ) {
		if ( !$title->inNamespace( NS_FILE ) ) {
			return false;
		}
		$file = wfFindFile( $title );
		// Can't find file
		if ( !$file ) {
			return false;
		}
		$handler = $file->getHandler();
		if ( !$handler ) {
			return false;
		}
		return $handler instanceof TimedMediaHandler;
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
	 * @param $file LocalFile object
	 * @return bool
	 */
	public static function onFileUpload( $file, $reupload, $hasNewPageContent ) {
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

	/**
	 * If file gets reverted to a previous version, reset transcodes.
	 *
	 * @param $article Article
	 * @return bool
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
	 *
	 * @param $article Article
	 * @return bool
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
	 * @param $files array of files
	 * @return bool
	 */
	public static function registerUnitTests( array &$files ) {
		$testDir = __DIR__ . '/tests/phpunit/';
		$testFiles = [
			'TestTimeParsing.php',
			'TestApiUploadVideo.php',
			'TestVideoThumbnail.php',
			'TestVideoTranscode.php',
			'TestOggHandler.php',
			'TestWebMHandler.php',
			'TestTimedMediaTransformOutput.php',
			'TestTimedMediaHandler.php'
		];
		foreach ( $testFiles as $fileName ) {
			$files[] = $testDir . $fileName;
		}
		return true;
	}

	/**
	 * Hook to add list of DB tables to copy when running parser tests
	 * @param array &$tables
	 * @return bool
	 */
	public static function onParserTestTables( &$tables ) {
		$tables[] = 'transcode';
		return true;
	}

	/**
	 * Hook to reset player serial so that parser tests are not order-dependent
	 */
	public static function onParserTestGlobals( &$globals ) {
		TimedMediaTransformOutput::resetSerialForTest();
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
				|| $name === 'Search' || $name === 'GlobalUsage' || $name === 'Upload' ) {
					$addModules = true;
			}
		}

		if ( $addModules ) {
			if ( self::activePlayerMode() === 'mwembed' ) {
				$out->addModuleStyles( 'ext.tmh.thumbnail.styles' );
				$out->addModules( [
					'mw.MediaWikiPlayer.loader',
					'mw.PopUpMediaTransform',
				] );
			}

			if ( self::activePlayerMode() === 'videojs' ) {
				$out->addModuleStyles( 'ext.tmh.player.styles' );
				$out->addModules( 'ext.tmh.player' );
			}
		}

		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function checkSchemaUpdates( DatabaseUpdater $updater ) {
		$base = __DIR__;

		switch ( $updater->getDB()->getType() ) {
		case 'mysql':
		case 'sqlite':
			 // Initial install tables
			$updater->addExtensionTable( 'transcode', "$base/TimedMediaHandler.sql" );
			$updater->addExtensionUpdate( [ 'addIndex', 'transcode', 'transcode_name_key',
				"$base/archives/transcode_name_key.sql", true ] );
			break;
		case 'postgres':
			// TODO
			break;
		}
		return true;
	}

	/**
	 * @param array $qp
	 * @return bool
	 */
	public static function onwgQueryPages( $qp ) {
		$qp[] = [ 'SpecialOrphanedTimedText', 'OrphanedTimedText' ];
		return true;
	}

	/**
	 * Return false here to evict existing parseroutput cache
	 * @param $parserOutput ParserOutput
	 * @param $wikiPage WikiPage
	 * @param $parserOptions
	 * @return bool
	 */
	public static function rejectParserCacheValue( $parserOutput, $wikiPage, $parserOptions ) {
		if (
			$parserOutput->getExtensionData( 'mw_ext_TMH_hasTimedMediaTransform' )
			|| isset( $parserOutput->hasTimedMediaTransform )
		) {
			/* page has old style TMH elements */
			if (
				self::activePlayerMode() === 'mwembed' &&
				!in_array( 'mw.MediaWikiPlayer.loader', $parserOutput->getModules() )
			) {
				wfDebug( 'Bad TMH parsercache value, throw this out.' );
				$wikiPage->getTitle()->purgeSquid();
				return false;
			}
		}
		return true;
	}

	/**
	 * @param $hash
	 * @param User $user
	 * @param $forOptions
	 * @return bool
	 */
	public static function changePageRenderingHash( &$hash, User $user, &$forOptions ) {
		if ( self::activePlayerMode() === 'videojs' ) {
			if ( $user->getOption( 'tmh-videojs' ) === '1' ) {
				$hash .= '!tmh-videojs';
				return true;
			}
		}
	}

	/**
	 * @param $user
	 * @param $prefs
	 * @return bool
	 */
	public static function onGetBetaFeaturePreferences( $user, &$prefs ) {
		global $wgTmhUseBetaFeatures, $wgExtensionAssetsPath;

		if ( $wgTmhUseBetaFeatures ) {
			$prefs['tmh-videojs'] = [
				// The first two are message keys
				'label-message' => 'beta-feature-timedmediahandler-message-videojs',
				'desc-message' => 'beta-feature-timedmediahandler-description-videojs',
				// Paths to images that represents the feature.
				// The image is usually different for ltr and rtl languages.
				// Images for specific languages can also specified using the language code.
				'screenshot' => [
					'ltr' => "$wgExtensionAssetsPath/TimedMediaHandler/resources/BetaFeature_TMH_VIDEOJS.svg",
					'rtl' => "$wgExtensionAssetsPath/TimedMediaHandler/resources/BetaFeature_TMH_VIDEOJS.svg",
				],
				// Link to information on the feature
				'info-link' => 'https://www.mediawiki.org/wiki/Extension:TimedMediaHandler',
				// Link to discussion about the feature
				'discussion-link' => 'https://www.mediawiki.org/wiki/Extension_talk:TimedMediaHandler',
			];
		}
		return true;
	}

	/**
	 * @return string
	 */
	public static function activePlayerMode() {
		global $wgTmhWebPlayer, $wgTmhUseBetaFeatures, $wgUser;
		$context = new RequestContext();
		if ( $wgTmhUseBetaFeatures && class_exists( 'BetaFeatures' ) &&
			$wgUser->isSafeToLoad() && BetaFeatures::isFeatureEnabled( $context->getUser(), 'tmh-videojs' )
		) {
			return 'videojs';
		} else {
			return $wgTmhWebPlayer;
		}
	}
}
