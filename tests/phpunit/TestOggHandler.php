<?php
class TestOggHandler extends MediaWikiMediaTestCase {

	/** @var OggHandlerTMH */
	private $handler;

	/** @var File */
	private $testFile;

	function getFilePath() {
		return __DIR__ . '/media';
	}

	function setUp() {
		parent::setUp();
		$this->handler = new OggHandlerTMH;
		$this->testFile = $this->dataFile( 'test5seconds.electricsheep.300x400.ogv', 'application/ogg' );
	}

	/**
	 * @dataProvider providerGetCommonMetaArray
	 * @param $filename String name of file
	 * @param $expected Array
	 */
	function testGetCommonMetaArray( $filename, $expected ) {
		$testFile = $this->dataFile( $filename, 'application/ogg' );
		$this->assertEquals( $expected, $this->handler->getCommonMetaArray( $testFile ) );
	}

	function providerGetCommonMetaArray() {
		return array(
			array( 'test5seconds.electricsheep.300x400.ogv',
				array(
					'Software' => array( 'Lavf53.21.1' ),
					'ObjectName' => array( 'Electric Sheep' ),
					'UserComment' => array( '🐑' )
				)
			),
			array( 'doubleTag.oga',
				array(
					'Artist' => array( 'Brian', 'Bawolff' ),
					'Software' => array( 'Lavf55.10.2' )
				)
			)
		);
	}
}
