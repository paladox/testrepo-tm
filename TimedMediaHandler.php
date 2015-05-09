<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'TimedMediaHandler' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['TimedMediaHandler'] = __DIR__ . '/i18n';
	$wgMessagesDirs['MwEmbed.EmbedPlayer'] = __DIR__ . '/MwEmbedModules/EmbedPlayer/i18n';
	$wgMessagesDirs['MwEmbed.TimedText'] = __DIR__ . '/MwEmbedModules/TimedText/i18n';
	$wgExtensionMessagesFiles['TimedMediaHandlerMagic'] = __DIR__ . '/TimedMediaHandler.i18n.magic.php';
	$wgExtensionMessagesFiles['TimedMediaHandlerAliases'] = __DIR__ . '/TimedMediaHandler.i18n.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for TimedMediaHandler extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the TimedMediaHandler extension requires MediaWiki 1.25+' );
}
