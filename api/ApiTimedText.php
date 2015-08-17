<?php
/**
 * Created on Aug 17, 2015
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
 * @file
 * @since 1.26
 */

use Captioning\Format\SubripFile;
use Captioning\Format\WebvttFile;

/**
 * Implements the timedtext module that outputs subtitle files
 * for consumption by <track> elements
 *
 * @ingroup API
 * @emits error.code timedtext-notfound, invalidlang, invalid-title
 */
class ApiTimedText extends ApiBase {

	/**
	 * This module uses a raw printer to directly output SRT, VTT or other subtitle formats
	 *
	 * @return ApiFormatRaw
	 */
	public function getCustomPrinter() {
		$printer = new ApiFormatRaw( $this->getMain(), null );
		$printer->setFailWithHTTPError( true );
		return $printer;
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$result = $this->getResult();

		if ( $params['lang'] === null ) {
			$langCode = false;
		} elseif ( !Language::isValidCode( $params['lang'] ) ) {
			$this->dieUsage( 'Invalid language code for parameter lang', 'invalidlang' );
		} else {
			$langCode = $params['lang'];
		}

		$page = $this->getTitleOrPageId( $params );
		if( !$page->exists() ) {
			$this->dieUsage( 'Title could not be found', 'timedtext-notfound' );
		}

		$ns = $page->getTitle()->getNamespace();
		if( $ns != NS_FILE && $ns != NS_TIMEDTEXT ) {
			// TODO register this i18n
			$this->dieUsage( 'Title is not in a valid namespace', 'invalidtitle' );
		}
		if ( $ns == NS_FILE ) {
			$this->getTextHandler( $page->getTitle() );
			// TODO find subtitle [content] that goes with file
			$page = null;
		}
		// TODO convert to contentmodel
		$rawTimedText = $page->getContent()->getNativeData();
		// TODO factor out. dupe with TimedTextPage.php
		$titleParts = explode( '.', $page->getTitle()->getDBkey() );
		$timedTextExtension = array_pop( $titleParts );

		if ( $timedTextExtension !== $params['trackformat'] ) {
			// TODO: Move this into seperate class ?
			// Cache the conversion in memcached ?
			switch ( $timedTextExtension ) {
				case 'srt':
					$originalFile = new SubripFile();
					break;
				case 'vtt':
					$originalFile = new WebvttFile();
					break;
				default:
					throw new MWException( 'Unsupported timedtext filetype' );
			}
			try {
				$originalFile->setEncoding( 'UTF-8' );
				$originalFile->loadFromString( $rawTimedText );

				if ( $params['trackformat'] === 'srt' ) {
					$newFile = $originalFile->convertTo( 'subrip' );
				} else if ( $params['trackformat'] === 'vtt' ) {
					$newFile = $originalFile->convertTo( 'webvtt' );
				} else {
					throw new MWException( 'Unsupported timedtext trackformat' );
				}
				if ( $newFile ) {
					$newFile->build();
					$rawTimedText = $newFile->getFileContent();
				}
			} catch(Exception $e) {
				throw new MWException( 'Timed text track conversion failed' );
			}
		}
		// We want to cache our output
		$this->getMain()->setCacheMode( 'public' );
		if ( !$this->getMain()->getParameter( 'smaxage' ) ) {
			// cache at least 15 seconds.
			$this->getMain()->setCacheMaxAge( 15 );
		}

		if ( $params['trackformat'] === 'srt' ) {
			$mimeType = 'text/srt';
		} else if ( $params['trackformat'] === 'vtt' ) {
			$mimeType = 'text/vtt';
		} else {
			throw new MWException( 'Unsupported timedtext trackformat' );
		}

		$result = $this->getResult();
		$result->addValue( null, 'text', $rawTimedText, ApiResult::NO_SIZE_CHECK );
		$result->addValue( null, 'mime', $mimeType, ApiResult::NO_SIZE_CHECK );
	}

	/**
	 * Finds the subtitle tracks that go with a $title file
	 * @param $title The Title of a filename that contains or references TimedText
	 * @return TextHandler
	 */
	function getTextHandler( $title ){
		$file = wfFindFile( $title );
		if( !$this->textHandler ){
			// Init an associated textHandler
			$this->textHandler = new TextHandler( $this->file );
		}
		return $this->textHandler;
	}

	public function getAllowedParams( $flags = 0 ) {
		global $wgContLang;
		$ret = array(
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'pageid' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
			'trackformat' => array(
				ApiBase::PARAM_DFLT => 'srt',
				ApiBase::PARAM_TYPE => array( 'srt', 'vtt' ),
			),
			'tracktype' => array(
				ApiBase::PARAM_DFLT => 'subtitles',
				ApiBase::PARAM_TYPE => array( 'subtitles', 'captions', 'descriptions' ), //, 'chapters', 'metadata' ),
			),
			// TODO possibly use uselang instead.
			'lang' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => $wgContLang->getCode(),
			),
		);
		return $ret;
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=timedtext&title=File:Example.ogv&lang=de&format=srt'
				=> 'apihelp-timedtext-example-1',
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:TimedText';
	}
}
