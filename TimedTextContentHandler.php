<?php
/**
 * Created on Sep 5, 2015
 *
 * Copyright © 2015 Derk-Jan Hartman "hartman.wiki@gmail.com"
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * Timed Text Content Handler
 *
 * @file
 * @ingroup Extensions
 * @ingroup TimedText
 * @author Derk-Jan Hartman <hartman.wiki@gmail.com>
 * @since 1.26
 */

class TimedTextContentHandler extends TextContentHandler {
	static private $knownTimedTextExtensions = array( 'srt', 'vtt' );

	/**
	 * @param string $modelId
	 * @param string[] $formats
	 */
	public function __construct(
		$modelId = CONTENT_MODEL_TIMEDTEXT, $formats = array( CONTENT_FORMAT_TEXT )
	) {
		parent::__construct( $modelId, $formats );
	}

	/**
	 * Only allow this content handler to be used in the TimedText namespace
	 *
	 * @param Title $title
	 * @return bool
	 */
	public function canBeUsedOn( Title $title ) {
		if ( $title->getNamespace() !== NS_TIMEDTEXT ) {
			return false;
		}
		if ( self::getFormatForTitle( $title ) === null ) {
			return false;
		}

		return parent::canBeUsedOn( $title );
	}

	/**
	 * Creates an empty TimedTextContent object.
	 *
	 * @return Content
	 */
	public function makeEmptyContent() {
		return new TimedTextContent( '' );
	}

	/**
	 * Subtitle language is currently defined by the format of the title
	 *
	 * @param Title $title
	 * @param Content $content
	 * @return Language
	 */
	public function getPageLanguage( Title $title, Content $content = null ) {
		return self::getLanguageForTitle( $title );
	}

	/**
	 * Subtitle language is currently defined by the format of the title
	 *
	 * @param Title $title
	 * @param Content $content
	 * @return Language
	 */
	public function getPageViewLanguage( Title $title, Content $content = null ) {
		return self::getLanguageForTitle( $title );
	}

	public function getActionOverrides() {
		return array(
			// 'view' => '\TimedText\ViewAction',
			// 'edit' => '\TimedText\EditAction',
			// 'submit' => '\TimedText\SubmitAction',
		);
	}

	private function getLanguageForTitle( Title $title ) {
		$titleParts = explode( '.', $title->getDBkey() );
		$timedTextExtension = array_pop( $titleParts );
		$language = array_pop( $titleParts );
		return wfGetLangObj( $language );
	}

	private function getFormatForTitle( Title $title ) {
		$titleParts = explode( '.', $title->getDBkey() );
		$timedTextExtension = array_pop( $titleParts );
		if ( in_array( $timedTextExtension, self::$knownTimedTextExtensions ) ) {
			return $timedTextExtension;
		}
		return null;
	}

	private function getMimeType( Title $title, Content $content = null ) {
		$timedTextExtension = self::getFormatForTitle( $title );
		if ( $timedTextExtension === 'srt' ) {
			return 'text/x-srt';
		} elseif ( $timedTextExtension === 'vtt' ) {
			return 'text/vtt';
		}
		return null;
	}
}
