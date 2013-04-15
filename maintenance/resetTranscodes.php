<?php
/**
 * reset stalled transcodes
 *
 */
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class ResetTranscodes extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "reset stalled transcodes, that are no longer in job queue.";
	}
	public function execute() {
		global $wgEnabledTranscodeSet;
		$where = array(
			"transcode_time_startwork" => NULL,
			"transcode_time_error" => NULL
		);
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'transcode', '*', $where, __METHOD__ );
		foreach ( $res as $row ) {
			$cond = array(
				"job_cmd" => "webVideoTranscode",
				"job_title" => $row->transcode_image_name,
			);
			$cond[] = "job_params " . $dbr->buildLike( $dbr->anyString(), '"' . $row->transcode_key . '"', $dbr->anyString() );
			$r = $dbr->select( 'job', '*', $cond, __METHOD__ );
			if ( !(int)$dbr->selectField ( 'job', 'COUNT(*)', $cond, __METHOD__ ) ) {
				$this->output('remote stalled transcode for ' .  $row->transcode_image_name . ' ' . $row->transcode_key . "\n");
				$title = Title::newFromText( $row->transcode_image_name, NS_FILE );
				$file = wfLocalFile( $title );
				WebVideoTranscode::removeTranscodes( $file, $row->transcode_key );
			}
		}
	}
}

$maintClass = 'ResetTranscodes'; // Tells it to run the class
require_once( RUN_MAINTENANCE_IF_MAIN );
