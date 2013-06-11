<?php
/**
 * Special:TimedMediaHandler
 *
 * Show some information about unprocessed jobs
 *
 * @file
 * @ingroup SpecialPage
 */

class SpecialTimedMediaHandler extends SpecialPage {
	private $transcodeStates = array(
		'active' => 'transcode_time_startwork IS NOT NULL AND transcode_time_success IS NULL AND transcode_time_error IS NULL',
		'failed' => 'transcode_error != "" AND transcode_time_success IS NULL',
		'queued' => 'transcode_time_startwork IS NULL AND transcode_time_success IS NULL AND transcode_time_error IS NULL',

	);
	private $formats = array(
		'ogg' => 'img_major_mime="application" AND img_minor_mime = "ogg"',
		'webm' => 'img_major_mime="video" AND img_minor_mime = "webm"',
	);

	public function __construct( $request = null, $par = null ) {
		parent::__construct( 'TimedMediaHandler', 'transcode-status' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$out = $this->getOutput();

		$out->addModuleStyles( 'mediawiki.special' );

		$stats = $this->getStats();

		$out->addHTML(
			"<h2>"
			. $this->msg( 'timedmedia-videos',  $stats['videos']['total'] )->escaped()
			. "</h2>"
		);
		// Give grep a chance to find the usages: timedmedia-ogg-videos, timedmedia-webm-videos
		foreach ( $this->formats as $format => $condition ) {
			if ( $stats[ 'videos' ][ $format ] ) {
				$out->addHTML(
					$this->msg ( "timedmedia-$format-videos", $stats[ 'videos' ][ $format ] )->escaped()
					. "<br>"
				);
			}
		}
		$this->renderState( $out, 'transcodes', $stats, false );
		foreach ( $this->transcodeStates as $state => $condition ) {
			$this->renderState( $out, $state, $stats );
		}
	}

	/**
	 * @param OutputPage $out
	 * @param $state
	 * @param $stats
	 * @param bool $showTable
	 */
	private function renderState ( $out, $state, $stats, $showTable = true ) {
		global $wgEnabledTranscodeSet;
		if ( $stats[ $state ][ 'total' ] ) {
			// Give grep a chance to find the usages:
			// timedmedia-derivative-state-transcodes, timedmedia-derivative-state-active,
			// timedmedia-derivative-state-queued, timedmedia-derivative-state-failed
			$out->addHTML(
				"<h2>"
				. $this->msg( 'timedmedia-derivative-state-' . $state, $stats[ $state ]['total'] )->escaped()
				. "</h2>"
			);
			foreach( $wgEnabledTranscodeSet as $key ) {
				if ( $stats[ $state ][ $key ] ) {
					$out->addHTML(
						$stats[ $state ][ $key ]
						. ' '
						. $this->msg( 'timedmedia-derivative-desc-' . $key )->escaped()
						. "<br>" );
				}
			}
			if ( $showTable ) {
				$out->addHTML( $this->getTranscodesTable( $state ) );
			}
		}
	}
	private function getTranscodes ( $state, $limit = 50 ) {
		$dbr = wfGetDB( DB_SLAVE );
		$files = array();
		$res = $dbr->select(
			'transcode',
			'*',
			$this->transcodeStates[ $state ],
			__METHOD__,
			array( 'LIMIT' => $limit, 'ORDER BY' => 'transcode_time_error DESC' )
		);
		foreach( $res as $row ) {
			$transcode = array();
			foreach( $row as $k => $v ){
				$transcode[ str_replace( 'transcode_', '', $k ) ] = $v;
			}
			$files[] = $transcode;
		}
		return $files;
	}

	private function getTranscodesTable ( $state, $limit = 50 ) {
		$table = '<table class="wikitable">' . "\n"
			. '<tr>'
			. '<th>' . $this->msg( 'timedmedia-transcodeinfo' )->escaped() . '</th>'
			. '<th>' . $this->msg( 'timedmedia-file' )->escaped() . '</th>'
			. '</tr>'
			. "\n";

		foreach( $this->getTranscodes( $state, $limit ) as $transcode ) {
			$title = Title::newFromText( $transcode[ 'image_name' ], NS_FILE );
			$table .= '<tr>'
				. '<td>' . $this->msg('timedmedia-derivative-desc-' . $transcode[ 'key' ] )->escaped() . '</td>'
				. '<td>' . Linker::link( $title, $transcode[ 'image_name' ] ) . '</td>'
				. '</tr>'
				. "\n";
		}
		$table .=  '</table>';
		return $table;
	}

	private function getStats() {
		global $wgEnabledTranscodeSet, $wgMemc;

		$dbr = wfGetDB( DB_SLAVE );

		$key = wfMemcKey( 'TimedMediaHandler', 'stats' );
		$stats = $wgMemc->get( $key );
		if ( !$stats ) {
			$stats = array();
			$stats[ 'videos' ] = array( 'total' => 0 );
			foreach( $this->formats as $format => $condition ) {
				$stats[ 'videos' ][ $format ] = (int)$dbr->selectField(
					'image',
					'COUNT(*)',
					'img_media_type = "VIDEO" AND (' . $condition . ')',
					__METHOD__
				);
				$stats[ 'videos' ][ 'total' ] += $stats[ 'videos' ][ $format ];
			}
			$wgMemc->add( $key, $stats, 3600 );
		}

		$key = wfMemcKey( 'TimedMediaHandler', 'allStats' );
		$allStats = $wgMemc->get( $key );
		if ( !$allStats ) {
			$stats[ 'transcodes' ] = array( 'total' => 0 );
			foreach ( $this->transcodeStates as $state => $condition ) {
				$stats[ $state ] = array( 'total' => 0 );
				foreach( $wgEnabledTranscodeSet as $type ) {
					// Important to pre-initialize, as can give
					// warnings if you don't have a lot of things in transcode table.
					$stats[ $state ][ $type ] = 0;
				}
			}
			foreach ( $this->transcodeStates as $state => $condition ) {
				$cond = array( 'transcode_key' => $wgEnabledTranscodeSet );
				$cond[] = $condition;
				$res = $dbr->select( 'transcode',
					array('COUNT(*) as count', 'transcode_key'),
					$cond,
					__METHOD__,
					array( 'GROUP BY' => 'transcode_key' )
				);
				foreach( $res as $row ) {
					$key = $row->transcode_key;
					$stats[ $state ][ $key ] = $row->count;
					$stats[ $state ][ 'total' ] += $stats[ $state ][ $key ];
				}
			}
			$res = $dbr->select( 'transcode',
				array( 'COUNT(*) as count', 'transcode_key' ),
				array( 'transcode_key' => $wgEnabledTranscodeSet ),
				__METHOD__,
				array( 'GROUP BY' => 'transcode_key' )
			);
			foreach( $res as $row ) {
				$key = $row->transcode_key;
				$stats[ 'transcodes' ][ $key ] = $row->count;
				$stats[ 'transcodes' ][ $key ] -= $stats[ 'queued' ][ $key ];
				$stats[ 'transcodes' ][ 'total' ] += $stats[ 'transcodes' ][ $key ];
			}
			$wgMemc->add( $key, $stats, 60 );
		} else {
			$stats = $allStats;
		}
		return $stats;
	}
}
