<?php

/**
 * Interface for starting and monitoring a video encoding task.
 */
interface WebVideoEncoder {
	/**
	 * Construct the encoder object with a source file, destination file, and encoding options.
	 */
	public function __construct( File $srcFile, FSFile $dstFile, array $options );

	/**
	 * Start the encoding process.
	 */
	public function start();

	/**
	 * Pause the encoding process.
	 */
	public function pause();

	/**
	 * Continue the encoding process after it was paused.
	 */
	public function resume();

	/**
	 * Stop and kill the encoding process.
	 */
	public function stop();

	/**
	 * Get the current status of the encoding process.
	 *
	 * The current status is a Status object where the value is either 'running',
	 * 'stopped', or 'terminated' depending on the process status. If the process
	 * ran into some sort of problem, warnings/errors are set as appropriate.
	 *
	 * @return Status
	 */
	public function status();

	/**
	 * Perform any necessary cleanup operations after the encode has completed.
	 */
	public function cleanup();
}

class FfmpegEncoder implements WebVideoEncoder {
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

	private $srcFile, $srcPath, $srcFSFile, $dstFile, $options, $status, $process, $pipes, $startTime;

	function __construct( File $srcFile, FSFile $dstFile, array $options ) {
		$this->srcFile = $srcFile;
		$this->srcFSFile = $srcFile->repo->getLocalReference( $srcFile->getPath() );
		$this->srcPath = $this->srcFSFile->getPath();
		$this->dstFile = $dstFile;

		$this->options = $options;
		$this->options['pass'] = isset( $options['twopass'] ) ? 1 : 0;
		$this->process = null;
		$this->pipes = array();
	}

	function start() {
		global $wgTranscodeBackgroundTimeLimit, $wgTranscodeBackgroundMemoryLimit;
		global $wgTranscodeBackgroundSizeLimit, $wgEnableNiceBackgroundTranscodeJobs;
		global $wgShellCgroup, $wgMaxShellTime, $wgMaxShellWallClockTime, $wgMaxShellMemory;
		global $wgMaxShellFileSize;

		$this->status = Status::newGood();

		if( !is_file( $this->srcPath ) ) {
			$this->status->fatal( Status::newFatal( 'timedmediahandler-error-missingsource',  $this->srcPath ) );
			return;
		}

		// Check the codec see which encode method to call.
		$this->options['pass'] = $this->options['pass'] ?: 0;
		if ( isset( $this->options[ 'novideo' ] ) ) {
			$cmd = $this->getFfmpegCommandLine();
		} elseif( $this->options['videoCodec'] == 'theora' ){
			$cmd = $this->getFfmpeg2TheoraCommandLine();
		} elseif( $this->options['videoCodec'] == 'vp8' || $this->options['videoCodec'] == 'h264' ){
			// Check for twopass:
			if ( isset( $this->options['twopass'] ) && $this->options['pass'] == 1 ) {
				$this->options['pass'] = 2;
			}
			$cmd = $this->getFfmpegCommandLine();
		} else {
			wfDebug( 'Error unknown codec:' . $this->options['videoCodec'] );
			$this->status->fatal( 'timedmediahandler-error-badcodec', $this->options['codec'] );
			return;
		}

		// Check if background tasks are enabled
		if( $wgEnableNiceBackgroundTranscodeJobs === false ){
			// Directly execute the shell command:
			$limits = array(
				"filesize" => $wgTranscodeBackgroundSizeLimit,
				"memory" => $wgTranscodeBackgroundMemoryLimit,
				"time" => $wgTranscodeBackgroundTimeLimit
			);
			wfShellExec( $cmd . ' 2>&1', $retval , array(), $limits );
		} else {
			// Because there's no way to do the wfShellExec logic without actually calling wfShellExec
			wfInitShellLocale();
			if ( php_uname( 's' ) == 'Linux' ) {
				$time = (int)$wgTranscodeBackgroundTimeLimit ?: $wgMaxShellTime;
				$wallTime = $time ?: $wgMaxShellWallClockTime;
				$mem = $wgTranscodeBackgroundMemoryLimit ?: $wgMaxShellMemory;
				$filesize = $wgTranscodeBackgroundSizeLimit ?: $wgMaxShellFileSize;

				if ( $time > 0 || $mem > 0 || $filesize > 0 || $wallTime > 0 ) {
					$cmd = '/bin/bash ' . escapeshellarg( "$IP/includes/limit.sh" ) . ' ' .
						escapeshellarg( $cmd ) . ' ' .
						escapeshellarg(
							"MW_CPU_LIMIT=$time; " .
							'MW_CGROUP=' . escapeshellarg( $wgShellCgroup ) . '; ' .
							"MW_MEM_LIMIT=$mem; " .
							"MW_FILE_SIZE_LIMIT=$filesize; " .
							"MW_WALL_CLOCK_LIMIT=$wallTime"
						);
				}
			}

			$descriptorspec = array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' )
			);

			$this->startTime = time();
			$this->process = proc_open( $cmd, $descriptorspec, $this->pipes );

			if ( !$this->process ) {
				$this->status->fatal( 'timedmediahandler-error-cannotfork' );
			}
		}
	}

	function pause() {
		// SIGSTOP
		proc_terminate( $this->process, 17 );
	}

	function resume() {
		// SIGCONT
		proc_terminate( $this->process, 19 );
	}

	function stop() {
		// SIGTERM
		if ( !$this->process ) {
			return;
		}

		proc_terminate( $this->process );
		sleep( 1 );

		$status = proc_get_status( $this->process );
		if ( $status['running'] ) {
			// SIGKILL
			proc_terminate( $this->process, 9 );
		}
	}

	function cleanup() {
		$path =  $this->dstFile->getPath();
		$dir = dirname( $path );
		if ( is_dir( $dir ) ) {
			$dh = opendir( $dir );
			if ( $dh ) {
				while ( ( $file = readdir( $dh ) ) !== false ) {
					$log_path = "$dir/$file";
					$ext = strtolower( pathinfo( $log_path, PATHINFO_EXTENSION ) );
					if( $ext == 'log' && substr( $log_path, 0 , strlen( $path )  ) == $path ) {
						wfSuppressWarnings();
						unlink( $log_path );
						wfRestoreWarnings();
					}
				}
				closedir( $dh );
			}
		}

		array_map( 'fclose', $this->pipes );

		if ( $this->process ) {
			proc_close( $this->process );
			$this->process = null;
		}
	}

	function status() {
		global $wgTranscodeBackgroundTimeLimit, $wgLang;
		static $oldFileSize = 0;

		if ( !$this->process ) {
			$this->status->setResult( true, 'terminated' );
			return $this->status;
		}

		$status = proc_get_status( $this->process );

		// Check if we have global job run-time has been exceeded:
		if ( $wgTranscodeBackgroundTimeLimit && time() - $this->startTime  > $wgTranscodeBackgroundTimeLimit ) {
			// Past the time limit. Terminate.
			$this->status->fatal( 'timedmediahandler-error-timelimited' );
			$this->stop();
			$this->status();
		} elseif ( $status['stopped'] ) {
			// Process is stopped, probably because of self::stop()
			$this->status->setResult( true, 'stopped' );
		} elseif ( $status['signaled'] ) {
			// Process received a signal and exited abnormally.
			$this->status->fatal( 'timedmediahandler-error-signaled' );
			$this->status->setResult( false, 'terminated' );
		} elseif ( !$status['running'] ) {
			// Process exited of its own free will.
			if ( $status['exitcode'] ) {
				// Bad exit code.
				$this->status->fatal( 'timedmediahandler-error-exitcode', $status['exitcode'] );
				$this->status->setResult( false, 'terminated' );
			} elseif ( isset( $this->options['twopass'] ) && $this->options['pass'] == 1 ) {
				// Still have a 2nd pass to do, so start the next pass and keep going.
				$this->options['pass'] = 2;
				$this->start();
				$this->status();
			} elseif ( !is_file( $this->dstFile->getPath() ) || filesize( $this->dstFile->getPath() ) > 0 ) {
				// The encoded file disappeared
				$this->status->fatal( 'timedmediahandler-error-targetdisappeared' );
				$this->status->setResult( false, 'terminated' );
			} else {
				// Normal exit.
				$this->status->setResult( false, 'terminated' );
			}
		} else {
			// Still going.
			$this->status->setResult( true, 'running' );
			clearstatcache();
			$newFileSize = is_file( $this->dstFile->getPath() ) ? filesize( $this->dstFile->getPath() ) : 0;
			// Don't start checking for file growth until we have an initial positive file size:
			if ( $newFileSize > 0 && $newFileSize == $oldFileSize ) {
				// If a warning for frozen process has already been added, turn it into a fatal.
				if ( $this->status->hasMessage( 'timedmediahandler-error-frozen' ) ) {
					$this->fatal( 'timedmediahandler-error-frozen' );
					$this->stop();
					$this->status();
				} else {
					$this->warning( 'timedmediahandler-error-frozen' );
				}
			}
		}

		return $this->status;
	}

	/**
	 * Utility helper for ffmpeg and ffmpeg2theora mapping
	 * @param $this->options array
	 * @param $this->options['pass'] int
	 * @return bool|string
	 */
	private function getFfmpegCommandLine() {
		global $wgFFmpegLocation, $wgTranscodeBackgroundMemoryLimit;

		// Set up the base command
		$cmd = wfEscapeShellArg( $wgFFmpegLocation ) . ' -y -i ' . wfEscapeShellArg( $this->srcPath );


		if( isset( $this->options['vpre'] ) ){
			$cmd .= ' -vpre ' . wfEscapeShellArg( $this->options['vpre'] );
		}

		if ( isset( $this->options['novideo'] )  ) {
			$cmd .= " -vn ";
		} elseif( $this->options['videoCodec'] == 'vp8' ){
			$cmd .= ' -threads 1';

			// check for presets:
			if ( isset( $this->options['preset'] ) && in_array( $this->options['preset'], array( '360p', '720p', '1080p' ) ) ) {
				$cmd .= " -vpre libvpx-{$this->options['preset']}";
			}

			// Add the boiler plate vp8 ffmpeg command:
			$cmd .=" -skip_threshold 0 -bufsize 6000k -rc_init_occupancy 4000";

			// Check for video quality:
			if ( isset( $this->options['videoQuality'] ) && $this->options['videoQuality'] >= 0 ) {
				// Map 0-10 to 63-0, higher values worse quality
				$quality = 63 - (int)$this->options['videoQuality'] / 10 * 63;
				$cmd .= " -qmin " . wfEscapeShellArg( $quality );
				$cmd .= " -qmax " . wfEscapeShellArg( $quality );
			}

			// Check for video bitrate:
			if ( isset( $this->options['videoBitrate'] ) ) {
				$cmd .= " -qmin 1 -qmax 51";
				$cmd .= " -vb " . wfEscapeShellArg( $this->options['videoBitrate'] * 1000 );
			}
			// Set the codec:
			$cmd .= " -vcodec libvpx";

			// Check for keyframeInterval
			if ( isset( $this->options['keyframeInterval'] ) ) {
				$cmd .= ' -g ' . wfEscapeShellArg( $this->options['keyframeInterval'] );
				$cmd .= ' -keyint_min ' . wfEscapeShellArg( $this->options['keyframeInterval'] );
			}
			if ( isset( $this->options['deinterlace'] ) ) {
				$cmd .= ' -deinterlace';
			}

			// Output WebM
			$cmd .=" -f webm";
		} elseif( $this->options['videoCodec'] == 'h264'){
			// Set the codec:
			$cmd .= " -threads 1 -vcodec libx264";
			// Check for presets:
			if ( isset( $this->options['preset'] ) ) {
				// Add the two vpre types:
				switch ( $this->options['preset'] ) {
					case 'ipod320':
						$cmd .= " -profile:v baseline -preset slow -coder 0 -bf 0 -flags2 -wpred-dct8x8 -level 13 -maxrate 768k -bufsize 3M";
						break;
					case '720p':
					case 'ipod640':
						$cmd .= " -profile:v baseline -preset slow -coder 0 -bf 0 -refs 1 -flags2 -wpred-dct8x8 -level 30 -maxrate 10M -bufsize 10M";
						break;
					default:
						// in the default case just pass along the preset to ffmpeg
						$cmd .= " -vpre " . wfEscapeShellArg( $this->options['preset'] );
						break;
				}
			}
			if ( isset( $this->options['videoBitrate'] ) ) {
				$cmd .= " -b " . wfEscapeShellArg( $this->options['videoBitrate'] );
			}
			// Output mp4
			$cmd .=" -f mp4";
		}

		// Check for aspect ratio ( we don't do anything with this right now)
		if ( isset( $this->options['aspect'] ) ) {
			$aspectRatio = $this->options['aspect'];
		} else {
			$aspectRatio = $this->srcFile->getWidth() . ':' . $this->srcFile->getHeight();
		}
		if ( isset( $this->options['maxSize'] ) ) {
			// Get size transform ( if maxSize is > file, file size is used:

			list( $width, $height ) = WebVideoTranscode::getMaxSizeTransform( $this->srcFile, $this->options['maxSize'] );
			$cmd.= ' -s ' . (int)$width . 'x' . (int)$height;
		} elseif (
			isset( $this->options['width'] ) && $this->options['width'] > 0 &&
			isset( $this->options['height'] ) && $this->options['height'] > 0
		) {
			$cmd .= ' -s ' . (int)$this->options['width'] . 'x' . (int)$this->options['height'];
		}

		// Handle crop:
		$optionMap = array(
			'cropTop' => '-croptop',
			'cropBottom' => '-cropbottom',
			'cropLeft' => '-cropleft',
			'cropRight' => '-cropright'
		);

		foreach( $optionMap as $name => $cmdArg ) {
			if ( isset( $this->options[$name] ) ) {
				$cmd .= " $cmdArg " .  wfEscapeShellArg( $this->options[$name] );
			}
		}

		// Check for start time
		if ( isset( $this->options['starttime'] ) ){
			$cmd .= ' -ss ' . wfEscapeShellArg( $this->options['starttime'] );
		} else {
			$this->options['starttime'] = 0;
		}
		// Check for end time:
		if ( isset( $this->options['endtime'] ) ){
			$cmd .= ' -t ' . ( (int)$this->options['endtime']  - (int)$this->options['starttime'] );
		}

		if ( $this->options['pass'] == 1 || isset( $this->options['noaudio'] ) ) {
			$cmd .= ' -an';
		} else {
			if ( isset( $this->options['audioQuality'] ) ){
				$cmd .= " -aq " . wfEscapeShellArg( $this->options['audioQuality'] );
			}
			if ( isset( $this->options['audioBitrate'] )){
				$cmd .= ' -ab ' . (int)$this->options['audioBitrate'] * 1000;
			}
			if ( isset( $this->options['samplerate'] ) ){
				$cmd .= " -ar " .  wfEscapeShellArg( $this->options['samplerate'] );
			}
			if ( isset( $this->options['channels'] ) ){
				$cmd .= " -ac " . wfEscapeShellArg( $this->options['channels'] );
			}

			if ( isset( $this->options['audioCodec'] ) ){
				$encoders = array(
					'aac' => 'libvo_aacenc',
					'vorbis' => 'libvorbis',
					'opus' => 'libopus',
					'mp3' => 'libmp3lame',
				);
				if ( isset( $encoders[ $this->options['audioCodec'] ] ) ) {
					$codec = $encoders[ $this->options['audioCodec'] ];
				} else {
					$codec = $this->options['audioCodec'];
				}
				$cmd .= " -acodec " . wfEscapeShellArg( $codec );
			} else {
				// if no audio codec set use vorbis :
				$cmd .= " -acodec libvorbis ";
			}
		}

		if ( $this->options['pass'] != 0 ) {
			$cmd .=" -pass " . wfEscapeShellArg( $this->options['pass'] ) ;
			$cmd .=" -passlogfile " . wfEscapeShellArg( "{$this->dstFile->getPath()}.log" );
		}
		// And the output target:
		if ( $this->options['pass'] == 1 ) {
			$cmd .= ' /dev/null';
		} else {
			$cmd .= " {$this->dstFile->getPath()}";
		}

		return $cmd;
	}

	/**
	 * ffmpeg2Theora mapping is much simpler since it is the basis of the the firefogg API
	 * @param $this->options array
	 * @return bool|string
	 */
	function getFfmpeg2TheoraCommandLine(){
		global $wgFFmpeg2theoraLocation, $wgTranscodeBackgroundMemoryLimit;

		// Set up the base command
		$cmd = wfEscapeShellArg( $wgFFmpeg2theoraLocation ) . ' ' . wfEscapeShellArg( $this->srcPath );

		$file = $this->srcFile;

		if ( isset( $this->options['maxSize'] ) ){
			list( $width, $height ) = WebVideoTranscode::getMaxSizeTransform( $file, $this->options['maxSize'] );
			$this->options['width'] = $width;
			$this->options['height'] = $height;
			$this->options['aspect'] = $width . ':' . $height;
			unset( $this->options['maxSize'] );
		}

		// Add in the encode settings
		foreach ( $this->options as $key => $val ){
			if ( isset( self::$foggMap[$key] ) ){
				if ( is_array( self::$foggMap[$key] ) ) {
					$cmd .= ' '. implode(' ', self::$foggMap[$key] );
				} elseif ( $val == 'true' || $val === true ) {
					$cmd .= ' '. self::$foggMap[$key];
				} elseif ( $val != 'false' && $val !== false ) {
					//normal get/set value
					$cmd.= ' ' . self::$foggMap[$key] . ' ' . wfEscapeShellArg( $val );
				}
			}
		}

		// Add the output target:
		$cmd .= ' -o ' . wfEscapeShellArg ( $this->dstFile->getPath() );

		return $cmd;
	}
}
