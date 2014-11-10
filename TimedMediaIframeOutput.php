<?php
/**
 * Adds iframe output ( bug 25862 )
 *
 * This enables iframe based embeds of the wikimeia player with the following syntax:
 *
 * <iframe src="http://commons.wikimedia.org/wiki/File:Folgers.ogv?embedplayer=yes"
 *     width="240" height="180" frameborder="0" ></iframe>
 *
 */

class TimedMediaIframeOutput {
	/**
	 * The iframe hook check file pages embedplayer=yes
	 * @param $title Title
	 * @param $article Article
	 * @param bool $doOutput
	 * @return bool
	 */
	static function iframeHook( &$title, &$article, $doOutput = true ) {
		global $wgRequest, $wgOut, $wgEnableIframeEmbed;
		if( !$wgEnableIframeEmbed )
			return true; //continue normal output iframes are "off" (maybe throw a warning in the future)

		// Make sure we are in the right namespace and iframe=true was called:
		if(	is_object( $title ) && $title->getNamespace() == NS_FILE  &&
			$wgRequest->getVal('embedplayer') == 'yes' &&
			$wgEnableIframeEmbed &&
			$doOutput ){

			if ( self::outputIframe( $title ) ) {
				// Turn off output of anything other than the iframe
				$wgOut->disable();
			}
		}

		return true;
	}

	/**
	 * Output an iframe
	 * @param $title Title
	 * @throws Exception
	 */
	static function outputIframe( $title ) {
		global $wgEnableIframeEmbed, $wgOut, $wgUser, $wgBreakFrames;

		if( !$wgEnableIframeEmbed ){
			return false;
		}

		// Setup the render parm
		$file = wfFindFile( $title );
		if ( !$file ) {
			// file was removed, show wiki page with warning
			return false;
		}
		$params = array(
			'fillwindow' => true
		);
		$videoTransform = $file->transform( $params );

		// Definitely do not want to break frames
		$wgBreakFrames = false;
		$wgOut->allowClickjacking();

		$wgOut->addModuleStyles( 'embedPlayerIframeStyle' );
		$wgOut->addModules( array( 'mw.TimedText.loader', 'mw.MediaWikiPlayer.loader', 'mw.EmbedPlayer.loader' ) );
		$wgOut->sendCacheControl();
	?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<title><?php echo $title->getText() ?></title>
	<?php
		echo implode( "\n", $wgOut->getHeadLinksArray() );
	?>
	<?php
		// In place of buildCssLinks, because we don't want to pull in all the skin CSS etc.
		$links = $wgOut->makeResourceLoaderLink( 'embedPlayerIframeStyle', ResourceLoaderModule::TYPE_STYLES );
		echo $links["html"];

		echo Html::element( 'meta', array( 'name' => 'ResourceLoaderDynamicStyles', 'content' => '' ) );
	?>
	<?php echo $wgOut->getHeadScripts(); ?>
	<script>
		mw.loader.using( 'mw.MwEmbedSupport', function() {
			mw.setConfig('EmbedPlayer.RewriteSelector', '');
		} );
		// Turn off rewrite selector. This prevents automatic conversion of
		// <video> tags, since we are going to do that ourselves later.
	</script>
	</head>
<body>
	<img src="<?php echo $videoTransform->getUrl() ?>" id="bgimage" ></img>
	<div id="videoContainer" style="visibility:hidden">
		<?php echo $videoTransform->toHtml(); ?>
	</div>
	<?php echo $wgOut->getBottomScripts(); ?>
	<script>
		mw.loader.using( 'mw.MwEmbedSupport', function() {
			// only enable fullscreen if enabled in iframe
			mw.setConfig('EmbedPlayer.EnableFullscreen', document.fullscreenEnabled || document.webkitFullscreenEnabled || document.mozFullScreenEnabled || false );

			mw.setConfig( 'EmbedPlayer.IsIframeServer', true );

			// rewrite player
			$( '#<?php echo TimedMediaTransformOutput::PLAYER_ID_PREFIX . '0' ?>' ).embedPlayer( function () {

				// Bind window resize to reize the player:
				var fitPlayer = function () {
					$( '#<?php echo TimedMediaTransformOutput::PLAYER_ID_PREFIX . '0' ?>' )
					[0].updateLayout();
				}

				$( window ).resize( fitPlayer );
				$( '#videoContainer' ).css( {
					'visibility':'visible'
				} );
				$( '#bgimage' ).remove();
				fitPlayer();
			} );
		} );
	</script>
</body>
</html>
	<?php
		return true;
	}

}
