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
	var $targetEncodePath = null;
	var $sourceFilePath = null;

	/**
	 * @var File
	 */
	var $file;

	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'webVideoTranscode', $title, $params, $id );
	}

	/**
	 * Local function to debug output ( jobs don't have access to the maintenance output class )
	 * @param $msg string
	 */
	private function output( $msg ){
		print $msg . "\n";
	}

	/**
	 * @return File
	 */
	private function getFile() {
		if( !$this->file ){
			$this->file = wfLocalFile( $this->title );
		}
		return $this->file;
	}

	/**
	 * @return string
	 */
	private function getTargetEncodePath(){
		if( !$this->targetEncodePath ){
			$file = $this->getFile();
			$transcodeKey = $this->params[ 'transcodeKey' ];
			$this->targetEncodePath = WebVideoTranscode::getTargetEncodePath( $file, $transcodeKey );
		}
		return $this->targetEncodePath;
	}

	/**
	 * @return string
	 */
	private function getSourceFilePath(){
		if( !$this->sourceFilePath ){
			$file = $this->getFile();
			$this->source = $file->repo->getLocalReference( $file->getPath() );
			// If file is in a remote repository we get a temp file.
			// make sure its not delted before encoding is done.
			if ( $this->source instanceof TempFSFile ) {
				$this->source->preserve();
			}
			$this->sourceFilePath = $this->source->getPath();
		}
		return $this->sourceFilePath;
	}

	/**
	 * Run the transcode request
	 * @return bool|string
	 */
	public function run() {
		// get a local pointer to the file
		$file = $this->getFile();

		// Validate the file exists :
		if( !$file || !is_file( $this->getSourceFilePath() ) ){
			$this->output( 'File not found: ' . $this->title );
			return false;
		}

		// Validate the transcode key param:
		$transcodeKey = $this->params['transcodeKey'];
		// Build the destination target
		if( ! isset(  WebVideoTranscode::$derivativeSettings[ $transcodeKey ] )){
			$this->output( "Transcode key $transcodeKey not found, skipping" );
			return false;
		}

		$options = WebVideoTranscode::$derivativeSettings[ $transcodeKey ];

		$this->output( "Encoding to codec: " . $options['videoCodec'] );

		$dbw = wfGetDB( DB_MASTER );
		$db = wfGetDB( DB_SLAVE );

		// Check if we have "already started" the transcode ( possible error )
		$dbStartTime = $db->selectField( 'transcode', 'transcode_time_startwork',
			array(
				'transcode_image_name' => $this->title->getDBKey(),
				'transcode_key' => $transcodeKey
			)
		);
		if( ! is_null( $dbStartTime ) ){
			$this->output( 'Error, running transcode job, for job that has already started' );
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
			array( 'LIMIT' => 1 )
		);


		// Check the codec see which encode method to call;
		if( $options['videoCodec'] == 'theora' ){
			$status = $this->ffmpeg2TheoraEncode( $options );
		} else if( $options['videoCodec'] == 'vp8' || $options['videoCodec'] == 'libx264' ){
			// Check for twopass:
			if( isset( $options['twopass'] ) ){
				// ffmpeg requires manual two pass
				$status = $this->ffmpegEncode( $options, 1 );
				if( $status && !is_string($status) ){
					$status = $this->ffmpegEncode( $options, 2 );
				}
			} else {
				$status = $this->ffmpegEncode( $options );
			}
		} else {
			wfDebug( 'Error unknown codec:' . $options['videoCodec'] );
			$status =  'Error unknown target encode codec:' . $options['codec'];
		}

		// Remove any log files all useful info should be in status and or we are done with 2 passs encoding
		$this->removeFffmpgeLogFiles();

		// Purge temp copy that was locked with preserve in getSourceFilePath
		if ( isset($this->source) && $this->source instanceof TempFSFile ) {
			$this->source->purge();
		}

		// Do a quick check to confirm the job was not restarted or removed while we were transcoding
		// Confirm the in memory $jobStartTimeCache matches db start time
		$dbStartTime = $db->selectField( 'transcode', 'transcode_time_startwork',
			array(
				'transcode_image_name' => $this->title->getDBKey(),
				'transcode_key' => $transcodeKey
			)
		);

		// Check for ( hopefully rare ) issue of or job restarted while transcode in progress
		if(  $db->timestamp( $jobStartTimeCache ) != $db->timestamp( $dbStartTime ) ){
			$this->output('Possible Error, transcode task restarted, removed, or completed while transcode was in progress');
			// if an error; just error out, we can't remove temp files or update states, because the new job may be doing stuff.
			if( $status !== true ){
				return false;
			}
			// else just continue with db updates, and when the new job comes around it won't start because it will see
			// that the job has already been started.
		}

		// If status is oky and file exists and is larger than 0 bytes
		if( $status === true && is_file( $this->getTargetEncodePath() ) && filesize( $this->getTargetEncodePath() ) > 0 ){
			$file = $this->getFile();
			// Copy derivative from the FS into storage at $finalDerivativeFilePath
			$status = $file->getRepo()->quickImport(
				$this->getTargetEncodePath(), // temp file
				WebVideoTranscode::getDerivativeFilePath( $file, $transcodeKey ) // storage
			);
			if ( !$status->isOK() ) {
				// Update the transcode table with failure time and error
				$dbw->update(
					'transcode',
					array(
						'transcode_time_error' => $db->timestamp(),
						'transcode_error' => $status
					),
					array(
							'transcode_image_name' => $this->title->getDBkey(),
							'transcode_key' => $transcodeKey
					),
					__METHOD__,
					array( 'LIMIT' => 1 )
				);
				// no need to invalidate all pages with video. Because all pages remain valid ( no $transcodeKey derivative )
				// just clear the file page ( so that the transcode table shows the error )
				$this->title->invalidateCache();
			} else {
				$bitrate = round( intval( filesize( $this->getTargetEncodePath() ) /  $file->getLength() ) * 8 );
				//wfRestoreWarnings();
				// Update the transcode table with success time:
				$dbw->update(
					'transcode',
					array(
						'transcode_time_success' => $db->timestamp(),
						'transcode_final_bitrate' => $bitrate
					),
					array(
						'transcode_image_name' => $this->title->getDBkey(),
						'transcode_key' => $transcodeKey,
					),
					__METHOD__,
					array( 'LIMIT' => 1 )
				);
				WebVideoTranscode::invalidatePagesWithFile( $this->title );
			}
		} else {
			// Update the transcode table with failure time and error
			$dbw->update(
				'transcode',
				array(
					'transcode_time_error' => $db->timestamp(),
					'transcode_error' => $status
				),
				array(
						'transcode_image_name' => $this->title->getDBkey(),
						'transcode_key' => $transcodeKey
				),
				__METHOD__,
				array( 'LIMIT' => 1 )
			);
			// no need to invalidate all pages with video. Because all pages remain valid ( no $transcodeKey derivative )
			// just clear the file page ( so that the transcode table shows the error )
			$this->title->invalidateCache();
		}
		//remove temoprary file in any case
		if( is_file( $this->getTargetEncodePath() ) ) {
			unlink( $this->getTargetEncodePath() );
		}
		// Clear the webVideoTranscode cache ( so we don't keep out dated table cache around )
		webVideoTranscode::clearTranscodeCache( $this->title->getDBkey() );

		// pass along result status:
		return $status;
	}

	function removeFffmpgeLogFiles(){
		$path =  $this->getTargetEncodePath();
		$dir = dirname( $path );
		if ( is_dir( $dir ) ) {
			$dh = opendir( $dir );
			if ( $dh ) {
				while ( ($file = readdir($dh)) !== false ) {
					$log_path = "$dir/$file";
					$ext = strtolower( pathinfo( $log_path, PATHINFO_EXTENSION ) );
					if( $ext == 'log' && substr( $log_path, 0 , strlen($path)  ) == $path ){
						wfSuppressWarnings();
						unlink( $log_path );
						wfRestoreWarnings();
					}
				}
				closedir( $dh );
			}
		}
	}

	/**
	 * Utility helper for ffmpeg and ffmpeg2theora mapping
	 * @param $options array
	 * @param $pass int
	 * @return bool|string
	 */
	function ffmpegEncode( $options, $pass=0 ){
		global $wgFFmpegLocation;

		if( !is_file( $this->getSourceFilePath() ) ) {
			return "source file is missing, " . $this->getSourceFilePath() . ". Encoding failed.";
		}

		// Set up the base command
		$cmd = wfEscapeShellArg( $wgFFmpegLocation ) . ' -y -i ' . wfEscapeShellArg( $this->getSourceFilePath() );

		
		if( isset( $options['vpre'] ) ){
			$cmd.= ' -vpre ' . wfEscapeShellArg( $options['vpre'] );
		}
		
		if ( isset( $options['novideo'] )  ) {
			$cmd.= " -vn ";
		} else if( $options['videoCodec'] == 'vp8' ){
			$cmd.= $this->ffmpegAddWebmVideoOptions( $options, $pass );
		} else if( $options['videoCodec'] == 'libx264'){
			$cmd.= $this->ffmpegAddH264VideoOptions( $options, $pass );
		}
		// Add size options: 
		$cmd .= $this->ffmpegAddVideoSizeOptions( $options ) ;
		
		// Check for start time
		if( isset( $options['starttime'] ) ){
			$cmd.= ' -ss ' . wfEscapeShellArg( $options['starttime'] );
		} else {
			$options['starttime'] = 0;
		}
		// Check for end time:
		if( isset( $options['endtime'] ) ){
			$cmd.= ' -t ' . intval( $options['endtime'] )  - intval($options['starttime'] ) ;
		}

		if ( $pass == 1 || isset( $options['noaudio'] ) ) {
			$cmd.= ' -an';
		} else {
			$cmd.= $this->ffmpegAddAudioOptions( $options, $pass );
		}

		if ( $pass != 0 ) {
			$cmd.=" -pass " .wfEscapeShellArg( $pass ) ;
			$cmd.=" -passlogfile " . wfEscapeShellArg( $this->getTargetEncodePath() .'.log' );
		}
		// And the output target:
		if ($pass==1) {
			$cmd.= ' /dev/null';
		} else{
			$cmd.= " " . $this->getTargetEncodePath();
		}

		$this->output( "Running cmd: \n\n" .$cmd . "\n" );

		// Right before we output remove the old file
		wfProfileIn( 'ffmpeg_encode' );
		$retval = 0;
		$shellOutput = $this->runShellExec( $cmd, $retval );
		wfProfileOut( 'ffmpeg_encode' );

		if( $retval != 0 ){
			return $cmd . "\n\nExitcode:" . $retval . "\n\n" . $shellOutput;
		}
		return true;
	}
	
	/**
	 * Adds ffmpeg shell options for h264
	 * 
	 * @param $options
	 * @param $pass
	 * @return string
	 */
	function ffmpegAddH264VideoOptions( $options, $pass ){
		// Set the codec:
		$cmd= " -vcodec libx264";
		// check for presets:
		if( isset( $options['preset'] ) ){
			// add the two vpre types:
			if( $options['preset'] ){
				$cmd.= " -vpre " . wfEscapeShellArg( $options['preset'] );
			}
		}
		if( isset( $options['videoBitrate'] ) ){ 
			$cmd.= " -b " . wfEscapeShellArg (  $options['videoBitrate'] );
		}
		// Output mp4
		$cmd.=" -f mp4";
		return $cmd;
	}
	
	function ffmpegAddVideoSizeOptions( $options ){
		$cmd = '';
		// Get a local pointer to the file object
		$file = $this->getFile();
		
		// Check for aspect ratio ( we don't do anything with this right now)
		if ( isset( $options['aspect'] ) ) {
			$aspectRatio = $options['aspect'];
		} else {
			$aspectRatio = $file->getWidth() . ':' . $file->getHeight();
		}
		if (isset( $options['maxSize'] )) {
			// Get size transform ( if maxSize is > file, file size is used:

			list( $width, $height ) = WebVideoTranscode::getMaxSizeTransform( $file, $options['maxSize'] );
			$cmd.= ' -s ' . intval( $width ) . 'x' . intval( $height );
		} elseif (
			(isset( $options['width'] ) && $options['width'] > 0 )
			&&
			(isset( $options['height'] ) && $options['height'] > 0 )
		){
			$cmd.= ' -s ' . intval( $options['width'] ) . 'x' . intval( $options['height'] );
		}

		// Handle crop:
		$optionMap = array(
			'cropTop' => '-croptop',
			'cropBottom' => '-cropbottom',
			'cropLeft' => '-cropleft',
			'cropRight' => '-cropright'
		);
		foreach( $optionMap as $name => $cmdArg ){
			if( isset($options[$name]) ){
				$cmd.= " $cmdArg " .  wfEscapeShellArg( $options[$name] );
			}
		}
		return $cmd;
	}
	/**
	 * Adds ffmpeg shell options for webm
	 * 
	 * @param $options
	 * @param $pass
	 * @return string
	 */
	function ffmpegAddWebmVideoOptions( $options, $pass ){

		// Get a local pointer to the file object
		$file = $this->getFile();

		$cmd ='';
		
		// check for presets:
		if( isset($options['preset']) ){
			if ($options['preset'] == "360p") {
				$cmd.= " -vpre libvpx-360p";
			} elseif ( $options['preset'] == "720p" ) {
				$cmd.= " -vpre libvpx-720p";
			} elseif ( $options['preset'] == "1080p" ) {
				$cmd.= " -vpre libvpx-1080p";
			}
		}
		
		// Add the boiler plate vp8 ffmpeg command:
		$cmd.=" -y -skip_threshold 0 -bufsize 6000k -rc_init_occupancy 4000 -threads 4";

		// Check for video quality:
		if ( isset( $options['videoQuality'] ) && $options['videoQuality'] >= 0 ) {
			// Map 0-10 to 63-0, higher values worse quality
			$quality = 63 - intval( intval( $options['videoQuality'] )/10 * 63 );
			$cmd .= " -qmin " . wfEscapeShellArg( $quality );
			$cmd .= " -qmax " . wfEscapeShellArg( $quality );
		}

		// Check for video bitrate:
		if ( isset( $options['videoBitrate'] ) ) {
			$cmd.= " -qmin 1 -qmax 51";
			$cmd.= " -vb " . wfEscapeShellArg( $options['videoBitrate'] * 1000 );
		}
		// Set the codec:
		$cmd.= " -vcodec libvpx";

		// Check for keyframeInterval
		if( isset( $options['keyframeInterval'] ) ){
			$cmd.= ' -g ' . wfEscapeShellArg( $options['keyframeInterval'] );
			$cmd.= ' -keyint_min ' . wfEscapeShellArg( $options['keyframeInterval'] );
		}
		if( isset( $options['deinterlace'] ) ){
			$cmd.= ' -deinterlace';
		}

		// Output WebM
		$cmd.=" -f webm";
		
		return $cmd;
	}

	/**
	 * @param $options array
	 * @param $pass
	 * @return string
	 */
	function ffmpegAddAudioOptions( $options, $pass ){
		$cmd ='';
		if( isset( $options['audioQuality'] ) ){
			$cmd.= " -aq " . wfEscapeShellArg( $options['audioQuality'] );
		}
		if( isset( $options['audioBitrate'] )){
			$cmd.= ' -ab ' . intval( $options['audioBitrate'] ) * 1000;
		}
		if( isset( $options['samplerate'] ) ){
			$cmd.= " -ar " .  wfEscapeShellArg( $options['samplerate'] );
		}
		if( isset( $options['channels'] ) ){
			$cmd.= " -ac " . wfEscapeShellArg( $options['channels'] );
		}
	
		if( isset( $options['audioCodec'] ) ){
			$cmd.= " -acodec " . wfEscapeShellArg( $options['channels'] );
		} else {
			// if no audio codec set use vorbis :
			$cmd.= " -acodec libvorbis ";
		}
		return $cmd;
	}

	/**
	 * ffmpeg2Theora mapping is much simpler since it is the basis of the the firefogg API
	 * @param $options array
	 * @return bool|string
	 */
	function ffmpeg2TheoraEncode( $options ){
		global $wgFFmpeg2theoraLocation;

		if( !is_file( $this->getSourceFilePath() ) ) {
			return "source file is missing, " . $this->getSourceFilePath() . ". Encoding failed.";
		}

		// Set up the base command
		$cmd = wfEscapeShellArg( $wgFFmpeg2theoraLocation ) . ' ' . wfEscapeShellArg( $this->getSourceFilePath() );

		$file = $this->getFile();

		if( isset( $options['maxSize'] ) ){
			list( $width, $height ) = WebVideoTranscode::getMaxSizeTransform( $file, $options['maxSize'] );
			$options['width'] = $width;
			$options['height'] = $height;
			$options['aspect'] = $width . ':' . $height;
			unset( $options['maxSize'] );
		}

		// Add in the encode settings
		foreach( $options as $key => $val ){
			if( isset( self::$foggMap[$key] ) ){
				if( is_array(  self::$foggMap[$key] ) ){
					$cmd.= ' '. implode(' ', self::$foggMap[$key] );
				} elseif ($val == 'true' || $val === true){
					$cmd.= ' '. self::$foggMap[$key];
				} elseif ($val == 'false' || $val === false){
					//ignore "false" flags
				} else {
					//normal get/set value
					$cmd.= ' '. self::$foggMap[$key] . ' ' . wfEscapeShellArg( $val );
				}
			}
		}

		// Add the output target:
		$cmd.= ' -o ' . wfEscapeShellArg ( $this->getTargetEncodePath() );

		$this->output( "Running cmd: \n\n" .$cmd . "\n" );

		wfProfileIn( 'ffmpeg2theora_encode' );
		$retval = 0;
		$shellOutput = $this->runShellExec( $cmd, $retval );
		wfProfileOut( 'ffmpeg2theora_encode' );
		if( $retval != 0 ){
			return $cmd . "\n\n" . $shellOutput;
		}
		return true;
	}

	/**
	 * Runs the shell exec command.
	 * if $wgEnableBackgroundTranscodeJobs is enabled will mannage a background transcode task
	 * else it just directly passes off to wfShellExec
	 *
	 * @param $cmd String Command to be run
	 * @param $retval String, refrence variable to return the exit code
	 * @return string
	 */
	public function runShellExec( $cmd, &$retval){
		global $wgTranscodeBackgroundTimeLimit,
			$wgTranscodeBackgroundMemoryLimit,
			$wgTranscodeBackgroundSizeLimit,
			$wgEnableNiceBackgroundTranscodeJobs;
		// Check if background tasks are enabled
		if( $wgEnableNiceBackgroundTranscodeJobs === false ){
			// Dont display shell output
			$cmd .= ' 2>&1';
			// Directly execute the shell command:
			$limits = array(
				"filesize" => $wgTranscodeBackgroundSizeLimit,
				"memory" => $wgTranscodeBackgroundMemoryLimit,
				"time" => $wgTranscodeBackgroundTimeLimit
			);
			return wfShellExec( $cmd . ' 2>&1', $retval , array(), $limits );
		}

		$encodingLog = $this->getTargetEncodePath() . '.stdout.log';
		$retvalLog = $this->getTargetEncodePath() . '.retval.log';
		// Check that we can actually write to these files
		//( no point in running the encode if we can't write )
		wfSuppressWarnings();
		if( ! touch( $encodingLog) || ! touch( $retvalLog ) ){
			wfRestoreWarnings();
			$retval = 1;
			return "Error could not write to target location";
		}
		wfRestoreWarnings();

		// Fork out a process for running the transcode
		$pid = pcntl_fork();
		if ($pid == -1) {
			$errorMsg = '$wgEnableNiceBackgroundTranscodeJobs enabled but failed pcntl_fork';
			$retval = 1;
			$this->output( $errorMsg);
			return $errorMsg;
		} elseif ( $pid == 0) {
			// we are the child
			$this->runChildCmd( $cmd, $retval, $encodingLog, $retvalLog);
			// exit with the same code as the transcode:
			exit( $retval );
		} else {
			// we are the parent monitor and return status
			return $this->monitorTranscode($pid, $retval, $encodingLog, $retvalLog);
		}
	}

	/**
	 * @param $cmd
	 * @param $retval
	 * @param $encodingLog
	 * @param $retvalLog
	 */
	public function runChildCmd( $cmd, &$retval, $encodingLog, $retvalLog ){
		global $wgTranscodeBackgroundTimeLimit,
			$wgTranscodeBackgroundMemoryLimit,
			$wgTranscodeBackgroundSizeLimit;
		// In theory we should use pcntl_exec but not sure how to get the stdout, ensure
		// we don't max php memory with the same protections provided by wfShellExec.

		// pcntl_exec requires a direct path to the exe and arguments as an array:
		//$cmd = explode(' ', $cmd );
		//$baseCmd = array_shift( $cmd );
		//print "run:" . $baseCmd . " args: " . print_r( $cmd, true );
		//$status  = pcntl_exec($baseCmd , $cmd );

		// Directly execute the shell command:
		//global $wgTranscodeBackgroundPriority;
		//$status = wfShellExec( 'nice -n ' . $wgTranscodeBackgroundPriority . ' '. $cmd . ' 2>&1', $retval );
		$limits = array(
			"filesize" => $wgTranscodeBackgroundSizeLimit,
			"memory" => $wgTranscodeBackgroundMemoryLimit,
			"time" => $wgTranscodeBackgroundTimeLimit
		);
		$status = wfShellExec( $cmd . ' 2>&1', $retval , array(), $limits );

		// Output the status:
		wfSuppressWarnings();
		file_put_contents( $encodingLog, $status );
		// Output the retVal to the $retvalLog
		file_put_contents( $retvalLog, $retval );
		wfRestoreWarnings();
	}

	/**
	 * @param $pid
	 * @param $retval
	 * @param $encodingLog
	 * @param $retvalLog
	 * @return string
	 */
	public function monitorTranscode( $pid, &$retval, $encodingLog, $retvalLog ){
		global $wgTranscodeBackgroundTimeLimit, $wgLang;
		$errorMsg = '';
		$loopCount = 0;
		$oldFileSize = 0;
		$startTime = time();
		$fileIsNotGrowing = false;

		$this->output( "Encoding with pid: $pid \npcntl_waitpid: " . pcntl_waitpid( $pid, $status, WNOHANG OR WUNTRACED) .
			"\nisProcessRunning: " . self::isProcessRunningKillZombie( $pid ) . "\n" );

		// Check that the child process is still running ( note this does not work well with  pcntl_waitpid
		// for some reason :(
		while( self::isProcessRunningKillZombie( $pid ) ) {
			//$this->output( "$pid is running" );

			// Check that the target file is growing ( every 5 seconds )
			if( $loopCount == 10 ){
				// only run check if we are outputing to target file
				// ( two pass encoding does not output to target on first pass )
				clearstatcache();
				$newFileSize = is_file( $this->getTargetEncodePath() ) ? filesize( $this->getTargetEncodePath() ) : 0;
				// Don't start checking for file growth until we have an initial positive file size:
				if( $newFileSize > 0 ){
					$this->output(  $wgLang->formatSize( $newFileSize ). ' Total size, encoding ' .
						$wgLang->formatSize( ( $newFileSize - $oldFileSize ) / 5 ) . ' per second' );
					if( $newFileSize == $oldFileSize ){
						if( $fileIsNotGrowing ){
							$errorMsg = "Target File is not increasing in size, kill process.";
							$this->output( $errorMsg );
							// file is not growing in size, kill proccess
							$retval = 1;

							//posix_kill( $pid, 9);
							self::killProcess( $pid );
							break;
						}
						// Wait an additional 5 seconds of the file not growing to confirm
						// the transcode is frozen.
						$fileIsNotGrowing = true;
					} else {
						$fileIsNotGrowing = false;
					}
					$oldFileSize = $newFileSize;
				}
				// reset the loop counter
				$loopCount = 0;
			}

			// Check if we have global job run-time has been exceeded:
			if ( $wgTranscodeBackgroundTimeLimit && time() - $startTime  > $wgTranscodeBackgroundTimeLimit ){
				$errorMsg = "Encoding exceeded max job run time ( "
					. TimedMediaHandler::seconds2npt( $wgTranscodeBackgroundTimeLimit ) . " ), kill process.";
				$this->output( $errorMsg );
				// File is not growing in size, kill proccess
				$retval = 1;
				//posix_kill( $pid, 9);
				self::killProcess( $pid );
				break;
			}

			// Sleep for one second before repeating loop
			$loopCount++;
			sleep( 1 );
		}

		$returnPcntl = pcntl_wexitstatus( $status );
		// check status
		wfSuppressWarnings();
		$returnCodeFile = file_get_contents( $retvalLog );
		wfRestoreWarnings();
		//$this->output( "TranscodeJob:: Child pcntl return:". $returnPcntl . ' Log file exit code:' . $returnCodeFile . "\n" );

		// File based exit code seems more reliable than pcntl_wexitstatus
		$retval = $returnCodeFile;

		// return the encoding log contents ( will be inserted into error table if an error )
		// ( will be ignored and removed if success )
		if( $errorMsg!= '' ){
			$errorMsg.="\n\n";
		}
		return $errorMsg . file_get_contents( $encodingLog );
	}

	/**
	 * check if proccess is running and not a zombie
	 * @param $pid int
	 * @return bool
	 */
	public static function isProcessRunningKillZombie( $pid ){
		exec( "ps $pid", $processState );
		if( !isset( $processState[1] ) ){
			return false;
		}
		if( strpos( $processState[1], '<defunct>' ) !== false ){
			// posix kill does not seem to work
			//posix_kill( $pid, 9);
			self::killProcess( $pid );
			return false;
		}
		return true;
	}

	/**
	* Kill Application PID
	*
	* @param $pid int
	* @return bool
	*/
	public static function killProcess( $pid ){
		exec( "kill -9 $pid" );
		exec( "ps $pid", $processState );
		if( isset( $processState[1] ) ){
			return false;
		}
		return true;
	}

	 /**
	 * Mapping between firefogg api and ffmpeg2theora command line
	 *
	 * This lets us share a common api between firefogg and WebVideoTranscode
	 * also see: http://firefogg.org/dev/index.html
	 */
	 public static $foggMap = array(
		// video
		'width'			=> "--width",
		'height'		=> "--height",
		'maxSize'		=> "--max_size",
		'noUpscaling'	=> "--no-upscaling",
		'videoQuality'=> "-v",
		'videoBitrate'	=> "-V",
		'twopass'		=> "--two-pass",
		'framerate'		=> "-F",
		'aspect'		=> "--aspect",
		'starttime'		=> "--starttime",
		'endtime'		=> "--endtime",
		'cropTop'		=> "--croptop",
		'cropBottom'	=> "--cropbottom",
		'cropLeft'		=> "--cropleft",
		'cropRight'		=> "--cropright",
		'keyframeInterval'=> "--keyint",
		'denoise'		=> array("--pp", "de"),
		'deinterlace'	=> "--deinterlace",
		'novideo'		=> array("--novideo", "--no-skeleton"),
		'bufDelay'		=> "--buf-delay",
		// audio
		'audioQuality'	=> "-a",
		'audioBitrate'	=> "-A",
		'samplerate'	=> "-H",
		'channels'		=> "-c",
		'noaudio'		=> "--noaudio",
		// metadata
		'artist'		=> "--artist",
		'title'			=> "--title",
		'date'			=> "--date",
		'location'		=> "--location",
		'organization'	=> "--organization",
		'copyright'		=> "--copyright",
		'license'		=> "--license",
		'contact'		=> "--contact"
	);

}
