<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------------+
// | File_Ogg PEAR Package for Accessing Ogg Bitstreams                         |
// | Copyright (c) 2005-2007                                                    |
// | David Grant <david@grant.org.uk>                                           |
// | Tim Starling <tstarling@wikimedia.org>                                     |
// +----------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or              |
// | modify it under the terms of the GNU Lesser General Public                 |
// | License as published by the Free Software Foundation; either               |
// | version 2.1 of the License, or (at your option) any later version.         |
// |                                                                            |
// | This library is distributed in the hope that it will be useful,            |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of             |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU          |
// | Lesser General Public License for more details.                            |
// |                                                                            |
// | You should have received a copy of the GNU Lesser General Public           |
// | License along with this library; if not, write to the Free Software        |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA |
// +----------------------------------------------------------------------------+

require_once('File/Ogg/Media.php');

define( 'OGG_OPUS_COMMENTS_PAGE_OFFSET', 2 );

/**
 * @author      David Grant <david@grant.org.uk>, Tim Starling <tstarling@wikimedia.org>
 * @category    File
 * @copyright   David Grant <david@grant.org.uk>, Tim Starling <tstarling@wikimedia.org>
 * @license     http://www.gnu.org/copyleft/lesser.html GNU LGPL
 * @link        http://pear.php.net/package/File_Ogg
 * @link        http://www.opus-codec.org/
 * @package     File_Ogg
 * @version     CVS: $Id: Opus.php,v 1.10 2005/11/16 20:43:27 djg Exp $
 */
class File_Ogg_Opus extends File_Ogg_Media
{
    /**
     * @access  private
     */
    function __construct($streamSerial, $streamData, $filePointer)
    {
        parent::__construct($streamSerial, $streamData, $filePointer);
        $this->_decodeHeader();
        //$this->_decodeCommentsHeader();

        $endSec =  $this->getSecondsFromGranulePos( $this->_lastGranulePos );
	    $startSec = $this->getSecondsFromGranulePos( $this->_firstGranulePos );

		//make sure the offset is worth taking into account oggz_chop related hack
	    if( $startSec > 1){
            $this->_streamLength = $endSec - $startSec;
            $this->_startOffset = $startSec;
	    }else{
            $this->_streamLength = $endSec;
	    }
        $this->_avgBitrate = $this->_streamLength ? ($this->_streamSize * 8) / $this->_streamLength : 0;
    }

	function getSecondsFromGranulePos( $granulePos ){
		return (( '0x' . substr( $granulePos, 0, 8 ) ) * pow(2, 32)
            + ( '0x' . substr( $granulePos, 8, 8 ) )
            - $this->_header['pre_skip'])
            / 48000;
    }

    /**
     * Get a short string describing the type of the stream
     * @return string
     */
    function getType()
    {
        return 'Opus';
    }

    /**
     * Decode the stream header
     * @access  private
     */
    function _decodeHeader()
    {
        fseek($this->_filePointer, $this->_streamData['pages'][0]['body_offset'], SEEK_SET);
        // The first 8 characters should be "OpusHead".
        if (fread($this->_filePointer, 8) != 'OpusHead')
            throw new PEAR_Exception("Stream is undecodable due to a malformed header.", OGG_ERROR_UNDECODABLE);

        $this->_header = File_Ogg::_readLittleEndian($this->_filePointer, array(
            'opus_version'          => 8,
            'nb_channels'           => 8,
            'pre_skip'              => 16,
            'audio_sample_rate'     => 32,
            'output_gain'           => 16,
            'channel_mapping'       => 8,
        ));
    }

    /**
     * Get an associative array containing header information about the stream
     * @access  public
     * @return  array
     */
    function getHeader() {
        return $this->_header;
    }

    /**
     * Number of channels used in this stream
     *
     * This function returns the number of channels used in this stream.  This
     * can range from 1 to 255, but will likely be 2 (stereo) or 1 (mono).
     *
     * @access  public
     * @return  int
     * @see     File_Ogg_Vorbis::isMono()
     * @see     File_Ogg_Vorbis::isStereo()
     * @see     File_Ogg_Vorbis::isQuadrophonic()
     */
    function getChannels()
    {
        return ($this->header['nb_channels']);
    }

    function getSampleRate()
    {
        return 48000;
    }

    /**
     * Decode the comments header
     * @access private
     */
    function _decodeCommentsHeader()
    {
        $this->_decodeCommonHeader('OpusTags', OGG_OPUS_COMMENTS_PAGE_OFFSET);
        $this->_decodeBareCommentsHeader();
    }
}
?>
