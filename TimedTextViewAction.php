<?php
/**
 * Created on April 21, 2016
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
 * Timed Text Page view action for the TimedText contentmodel
 * Displays the current video with subtitles to the right.
 *
 * @file
 * @ingroup Extensions
 * @ingroup TimedText
 * @author Derk-Jan Hartman <hartman.wiki@gmail.com>
 * @since 1.27
 */
namespace TimedMediaHandler\TimedText;

use ViewAction;
use Title;
use Revision;
use Language;
use Xml;

class PageViewAction extends ViewAction {
	// The width of the video plane:
	static private $videoWidth = 400;
	// TODO need to move to to a less specific class
	static private $knownTimedTextExtensions = array( 'srt', 'vtt' );

	public function show() {
		global $wgTimedTextNS;
		$out = $this->getOutput();
		$title = $this->page->getTitle();
		// TODO handle the preview of the content of the diff
		if ( !$title->inNamespace( $wgTimedTextNS ) || $out->isPrintable() || $this->getContext()->getRequest()->getCheck( 'diff' ) ) {
			$this->page->view();
			return;
		}
		$wikiPage = $this->page->getPage();
		$content = $wikiPage->getContent( Revision::FOR_THIS_USER, $this->getUser() );
		if ( $content === null || $content->getModel() !== CONTENT_MODEL_TIMEDTEXT || $content->isRedirect()
		) {
			$this->page->view();
			return;
		}
		// parse page title:
		$titleParts = explode( '.', $title->getDBkey() );
		$timedTextExtension = array_pop( $titleParts );
		$languageKey = array_pop( $titleParts );

		// Our custom view

		// Check for File name without text extension:
		// i.e TimedText:myfile.ogg
		$fileTitle = Title::newFromText( $title->getDBkey(), NS_FILE );
		$file = wfFindFile( $fileTitle );
		// Check for a valid srt page, present redirect form for the full title match:
		if ( !in_array( $timedTextExtension, self::$knownTimedTextExtensions ) &&
			$file && $file->exists()
		) {
			if ( $file->isLocal() ) {
				$this->doRedirectToPageForm( $fileTitle );
			} else {
				$this->doLinkToRemote( $file );
			}
			return;
		}

		// Check for File name with text extension ( from remaning parts of title )
		// i.e TimedText:myfile.ogg.en.srt

		$videoTitle = Title::newFromText( implode( '.', $titleParts ), NS_FILE );

		// Check for remote file
		$basefile = wfFindFile( $videoTitle );
		if ( !$basefile ) {
			$out->addHTML( wfMessage( 'timedmedia-subtitle-no-video' )->escaped() );
			return;
		}

		if ( !$basefile->isLocal() ) {
			$this->doLinkToRemote( $basefile );
			return;
		}

		// Look up the language name:
		$language = $out->getLanguage()->getCode();
		$languages = Language::fetchLanguageNames( $language, 'all' );
		if ( isset( $languages[ $languageKey ] ) ) {
			$languageName = $languages[ $languageKey ];
		} else {
			$languageName = $languageKey;
		}

		// Set title
		$message = $this->page->exists() ?
			'mwe-timedtext-language-subtitles-for-clip' :
			'mwe-timedtext-language-no-subtitles-for-clip';
		$out->setPageTitle( wfMessage( $message, $languageName, $videoTitle ) );

		// Get the video with with a max of 600 pixel page
		// TODO get rid of table layout here
		$out->addHTML(
			xml::tags( 'table', array( 'style'=> 'border:none' ),
				xml::tags( 'tr', null,
					xml::tags( 'td', array( 'valign' => 'top',  'width' => self::$videoWidth ),
						$this->getVideoHTML( $videoTitle )
					) .
					xml::tags( 'td', array( 'valign' => 'top' ), $this->getTimedTextHTML( $languageName ) )
				)
			)
		);
	}

	/**
	 * Timed text or file is hosted on remote repo, Add a short description and link to foring repo
	 * @param $file File the base file
	 */
	function doLinkToRemote( $file ) {
		$output = $this->getContext()->getOutput();
		$output->setPageTitle( wfMessage( 'timedmedia-subtitle-remote',
			$file->getRepo()->getDisplayName() ) );
		$output->addHTML( wfMessage( 'timedmedia-subtitle-remote-link',
			$file->getDescriptionUrl(), $file->getRepo()->getDisplayName() ) );
	}

	function doRedirectToPageForm() {
		$lang = $this->getContext()->getLanguage();
		$out = $this->getContext()->getOutput();

		// Set the page title:
		$out->setPageTitle( wfMessage( 'timedmedia-subtitle-new' ) );

		// Look up the language name:
		$language = $out->getLanguage()->getCode();
		$attrs = array( 'id' => 'timedmedia-tt-input' );
		$langSelect = Xml::languageSelector( $language, false, null, $attrs, null );

		$out->addHTML(
			Xml::tags( 'div', array( 'style' => 'text-align:center' ),
				Xml::tags( 'div', null,
					wfMessage( 'timedmedia-subtitle-new-desc', $lang->getCode() )->parse()
				) .
				$langSelect[1] .
				Xml::tags( 'button',
					array( 'id' => 'timedmedia-tt-go' ),
					wfMessage( 'timedmedia-subtitle-new-go' )->escaped()
				)
			)
		);
		$out->addModules( 'ext.tmh.TimedTextSelector' );
	}

	/**
	 * Gets the video HTML ( with the current language set as default )
	 * @param $videoTitle string
	 * @return String
	 */
	private function getVideoHTML( $videoTitle ) {
		// Get the video embed:
		$file = wfFindFile( $videoTitle );
		if ( !$file ) {
			return wfMessage( 'timedmedia-subtitle-no-video' )->escaped();
		} else {
			$videoTransform = $file->transform(
				array(
					'width' => self::$videoWidth
				)
			);
			return $videoTransform->toHTML();
		}
	}

	/**
	 * Gets an HTML representation of the Timed Text
	 *
	 * @param $languageName string
	 * @return Message|string
	 */
	private function getTimedTextHTML( $languageName ) {
		if ( !$this->page->exists() ) {
			return wfMessage( 'timedmedia-subtitle-no-subtitles',  $languageName );
		}
		return Xml::element(
			'pre',
			array( 'style' => 'margin-top: 0px;' ),
			$this->page->getContent(),
			false
		);
	}

}

?>