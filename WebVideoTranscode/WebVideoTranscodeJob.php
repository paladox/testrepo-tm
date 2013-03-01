<?php
/**
 * Job for transcode jobs
 *
 * @file
 * @ingroup JobQueue
 */

/**
 * Job for web video transcode
 *
 * Support two modes
 * 1) non-free media transcode ( delays the media file being inserted, adds note to talk page once ready)
 * 2) derivatives for video ( makes new sources for the asset )
 *
 * @ingroup JobQueue
 */
class WebVideoTranscodeJob extends Job {
	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'webVideoTranscode', $title, $params, $id );
	}

	/**
	 * Run the transcode request
	 * @return boolean success
	 */
	public function run() {
		global $wgTranscodeEncoderClass;

		// get a local pointer to the file
		$file = wfLocalFile( $this->title );
		$source = $file->repo->getLocalReference( $file->getPath() );

		// Validate the file exists:
		if ( !$file || !$source ){
			wfDebugLog( 'TimedMediaHandler', $this->title . ': File not found ' );
			return false;
		}

		// Validate the transcode key param:
		$transcodeKey = $this->params['transcodeKey'];
		// Build the destination target
		if ( !isset(  WebVideoTranscode::$derivativeSettings[ $transcodeKey ] )){
			wfDebugLog( 'TimedMediaHandler', "Transcode key $transcodeKey not found, skipping" );
			return false;
		}

		$options = WebVideoTranscode::$derivativeSettings[ $transcodeKey ];

		if ( isset( $options[ 'novideo' ] ) ) {
			wfDebugLog( 'TimedMediaHandler', "Encoding to audio codec: " . $options['audioCodec'] );
		} else {
			wfDebugLog( 'TimedMediaHandler', "Encoding to codec: " . $options['videoCodec'] );
		}

		$dbw = wfGetDB( DB_MASTER );

		// Check if we have "already started" the transcode ( possible error )
		$dbw->begin();
		$dbStartTime = $dbw->selectField(
			'transcode',
			'transcode_time_startwork',
			array(
				'transcode_image_name' => $this->title->getDBKey(),
				'transcode_key' => $transcodeKey
			),
			__METHOD__,
			array(
				'ORDER BY' => 'transcode_id',
				'FOR UPDATE'
			)
		);

		if ( $dbStartTime !== null ) {
			$dbw->rollback();
			wfDebugLog( 'TimedMediaHandler', 'Error, running transcode job, for job that has already started' );
			// back out of this job. ( if there was a transcode error it should be restarted with api transcode-reset )
			// not some strange out-of-order error.
			return false;
		}

		// Update the transcode table letting it know we have "started work":
		$jobStartTimeCache = $dbw->timestamp();
		$dbw->update(
			'transcode',
			array( 'transcode_time_startwork' => $jobStartTimeCache ),
			array(
				'transcode_image_name' => $this->title->getDBkey(),
				'transcode_key' => $transcodeKey
			),
			__METHOD__,
			array( 'ORDER BY' => 'transcode_id', 'LIMIT' => 1 )
		);
		$dbw->commit();

		$target = WebVideoTranscode::getTargetEncodeFile( $file, $transcodeKey );
		$target->bind( $this );
		$encode = new $wgTranscodeEncoderClass( $file, $target, $options );
		$encode->start();

		do {
			sleep( 5 );
			$status = $encode->status();
		} while( $status->getValue() !== 'terminated' );

		// Do a quick check to confirm the job was not restarted or removed while we were transcoding
		// Confirm the in memory $jobStartTimeCache matches db start time
		$dbw->begin();
		$dbStartTime = $dbw->selectField( 'transcode', 'transcode_time_startwork',
			array(
				'transcode_image_name' => $this->title->getDBKey(),
				'transcode_key' => $transcodeKey
			)
		);

		// Check for ( hopefully rare ) issue of or job restarted while transcode in progress
		if ( $dbw->timestamp( $jobStartTimeCache ) != $dbw->timestamp( $dbStartTime ) ){
			$dbw->rollback();
			wfDebugLog( 'TimedMediaHandler', 'Possible Error, transcode task restarted, removed, or completed while transcode was in progress' );
		} elseif ( !$status->isOK() ) {
			// Update the transcode table with failure time and error
			$target->purge();
			$dbw->update(
				'transcode',
				array(
					'transcode_time_error' => $dbw->timestamp(),
					'transcode_error' => $status->getWikiText()
				),
				array(
						'transcode_image_name' => $this->title->getDBkey(),
						'transcode_key' => $transcodeKey
				),
				__METHOD__,
				array( 'LIMIT' => 1 )
			);
			$dbw->commit();
			// no need to invalidate all pages with video. Because all pages remain valid ( no $transcodeKey derivative )
			// just clear the file page ( so that the transcode table shows the error )
			$this->title->invalidateCache();
		} else {
			// Copy derivative from the FS into storage at $finalDerivativeFilePath
			$status->merge( $file->getRepo()->quickImport(
				$target->getPath(), // temp file
				WebVideoTranscode::getDerivativeFilePath( $file, $transcodeKey ) // storage
			) );
			$target->purge();

			if ( !$status->isOK() ) {
				$dbw->update(
					'transcode',
					array(
						'transcode_time_error' => $dbw->timestamp(),
						'transcode_error' => $status->getWikiText()
					),
					array(
							'transcode_image_name' => $this->title->getDBkey(),
							'transcode_key' => $transcodeKey
					),
					__METHOD__,
					array( 'LIMIT' => 1 )
				);
				$dbw->commit();
				// no need to invalidate all pages with video. Because all pages remain valid ( no $transcodeKey derivative )
				// just clear the file page ( so that the transcode table shows the error )
				$this->title->invalidateCache();
			} else {
				$bitrate = round( filesize( $target->getPath() ) / $file->getLength() * 8 );

				// Update the transcode table with success time:
				$dbw->update(
					'transcode',
					array(
						'transcode_time_success' => $dbw->timestamp(),
						'transcode_final_bitrate' => $bitrate
					),
					array(
						'transcode_image_name' => $this->title->getDBkey(),
						'transcode_key' => $transcodeKey,
					),
					__METHOD__,
					array( 'LIMIT' => 1 )
				);
				$dbw->commit();

				WebVideoTranscode::invalidatePagesWithFile( $this->title );
			}
		}

		$encode->cleanup();
		// Clear the webVideoTranscode cache ( so we don't keep out dated table cache around )
		WebVideoTranscode::clearTranscodeCache( $this->title->getDBkey() );

		return $status->isOK();
	}
}
