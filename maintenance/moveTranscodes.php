<?php
/**
 * move transcodes from thumb to transcodes container
 *
 */
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class MoveTranscodes extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "move transcodes from thumb to transcodes container.";
	}
	public function execute() {
		global $wgEnabledTranscodeSet;

		$this->output( "Move transcodes:\n" );
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'transcode', '*', array(), __METHOD__ );
		foreach ( $res as $row ) {
			$title = Title::newFromText( $row->transcode_image_name, NS_FILE );
			$file = wfFindFile( $title );

			$oldPath = dirname(
				$file->getThumbPath( $file->thumbName( array() ) )
			) . '/' . $file->getName() . '.' . $row->transcode_key;

			$newPath = WebVideoTranscode::getDerivativeFilePath( $file, $row->transcode_key );
			if ($oldPath != $newPath) {
				if( $file->repo->fileExists( $oldPath ) ){
					if( $file->repo->fileExists( $newPath ) ){
						wfSuppressWarnings();
						$res = $file->repo->quickPurge( $oldPath );
						wfRestoreWarnings();
						if( !$res ){
							wfDebug( "Could not delete file $oldPath\n" );
						} else {
							$this->output( "deleted $oldPath, exists in transcodes container\n" );
						}
					} else {
						$this->output( " $oldPath => $newPath\n" );
						$dstZone = 'transcodes';
						$dstPath = substr($newPath, strlen($file->getRepo()->getZonePath( $dstZone ) ) );
						$file->getRepo()->store( $oldPath, $dstZone, $dstPath, File::DELETE_SOURCE );
					}
				}
			}

		}
		$this->output( "Finished!\n" );
	}
}

$maintClass = 'MoveTranscodes'; // Tells it to run the class
require_once( RUN_MAINTENANCE_IF_MAIN );
