<?php
/**
 * WAV handler
 */
class WAVHandler extends TimedMediaHandler {
	// XXX match GETID3_VERSION ( too bad version is not a getter )
	const METADATA_VERSION = 2;

	/**
	 * @param $metadata
	 * @return bool|mixed
	 */
	function unpackMetadata( $metadata ) {
		wfSuppressWarnings();
		$unser = unserialize( $metadata );
		wfRestoreWarnings();
		if ( isset( $unser['version'] ) && $unser['version'] == self::METADATA_VERSION ) {
			return $unser;
		} else {
			return false;
		}
	}

	/**
	 * @param $image
	 * @return string
	 */
	function getMetadataType( $image ) {
		return 'wav';
	}
        
	/**
	 * @param $file File
	 * @return String
	 */
	function getWebType( $file ) {
		return 'audio/wav';
	}

	/**
	 * @param $file File
	 * @return array|bool
	 */
	function getStreamTypes( $file ) {
		$streamTypes = array();
		$metadata = $this->unpackMetadata( $file->getMetadata() );
		if ( !$metadata || isset( $metadata['error'] ) ) {
			return false;
		}

		if( isset( $metadata['audio'] ) && $metadata['audio']['dataformat'] == 'wav' ){
			$streamTypes[] =  'WAV';
		}

		return $streamTypes;
	}

	/**
	 * @param $file File
	 * @return mixed
	 */
	function getBitrate( $file ){
		$metadata = self::unpackMetadata( $file->getMetadata() );
		return $metadata['bitrate'];
	}	

	/**
	 * @param $file File
	 * @return int
	 */
	function getLength( $file ) {
		$metadata = $this->unpackMetadata( $file->getMetadata() );
		if ( !$metadata || isset( $metadata['error'] ) ) {
			return 0;
		} else {
			return $metadata['playtime_seconds'];
		}
	}

	/**
 	 * @param $file File
	 * @return bool|int
	 */
	function getFramerate( $file ){
		return false;
	}

	/**
 	 * @param $file File
 	 * @return String
	 */
	function getShortDesc( $file ) {
		global $wgLang;
		$streamTypes = $this->getStreamTypes( $file );
		if ( !$streamTypes ) {
			return parent::getShortDesc( $file );
		}
		return wfMessage( 'timedmedia-wav-short-audio', implode( '/' ),
			$wgLang->formatTimePeriod( $this->getLength( $file ) ) )->text();
	}

	/**
	 * @param $file File
	 * @return String
	 */
	function getLongDesc( $file ) {
		global $wgLang;
		$streamTypes = $this->getStreamTypes( $file );
		if ( !$streamTypes ) {
			return parent::getLongDesc( $file );
		}
		return wfMessage('timedmedia-wav-long-audio',
			$wgLang->formatTimePeriod( $this->getLength($file) ),
			$wgLang->formatBitrate( $this->getBitRate( $file ) )
		)->text();

	}

}
