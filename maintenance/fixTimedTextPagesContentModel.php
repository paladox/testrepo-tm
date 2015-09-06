<?php
/**
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
 * @ingroup TimedText
 */

if ( !class_exists( 'LoggedUpdateMaintenance' ) ) {
	$basePath = getenv( 'MW_INSTALL_PATH' ) !== false
		? getenv( 'MW_INSTALL_PATH' )
		: __DIR__ . '/../../..';
	require_once $basePath . '/maintenance/Maintenance.php';
}

/**
 * Set the content model type for TimedText: pages
 */
class FixTimedTextPagesContentModel extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Set the content model type for TimedText: pages' );
		$this->setBatchSize( 100 );
	}

	/**
	 * @see LoggedUpdateMaintenance::doDBUpdates
	 */
	public function doDBUpdates() {
		global $wgTimedTextNS;

		if ( !$this->getConfig()->get( 'ContentHandlerUseDB' ) ) {
			$this->output( '\$wgContentHandlerUseDB is not enabled, nothing to do.\n' );
		}

		$dbr = $this->getDB( DB_SLAVE );

		if ( !$dbr->fieldExists( 'page', 'page_content_model', __METHOD__ ) ) {
			$this->error( 'page_content_model field of page table does not exists.' );
			return false;
		}

		do {
			$rows = $dbr->select(
				'page',
				[ 'page_id', 'page_title', 'page_namespace', 'page_content_model' ],
				[
					'page_namespace' => $wgTimedTextNS,
					'page_content_model' => CONTENT_MODEL_WIKITEXT
				],
				__METHOD__,
				[ 'ORDER BY' => 'page_id', 'LIMIT' => $this-$this->mBatchSize ]
			);
			foreach ( $rows as $row ) {
				$this->handleRow( $row );
			}
		} while ( $rows->numRows() >= $this->mBatchSize );

		$this->output( 'Update of the content model for TimedText: pages is done.\n' );

		return true;
	}

	protected function handleRow( stdClass $row ) {
		$title = Title::makeTitle( $row->page_namespace, $row->page_title );
		$this->output( "Processing {$title} ({$row->page_id})...\n" );

		$dbw = $this->getDB( DB_MASTER );
		$dbw->update(
			'page',
			[ 'page_content_model' => CONTENT_MODEL_TIMEDTEXT ],
			[ 'page_id' => $row->page_id ],
			__METHOD__
		);
		wfWaitForSlaves();
	}

	/**
	 * @see LoggedUpdateMaintenance::getUpdateKey
	 */
	public function getUpdateKey() {
		return 'FixTimedTextPagesContentModel';
	}

}

$maintClass = 'FixTimedTextPagesContentModel';
require_once ( RUN_MAINTENANCE_IF_MAIN );
