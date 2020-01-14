<?php

/**
 * @covers FlaggableWikiPage
 */
class FlaggablePageTest extends PHPUnit\Framework\TestCase {
	/**
	 * Prepares the environment before running a test.
	 */
	protected function setUp() : void {
		parent::setUp();
		$this->user = new User();
	}

	/**
	 * Cleans up the environment after running a test.
	 */
	protected function tearDown() : void {
		parent::tearDown();
	}

	public function testPageDataFromTitle() {
		$title = Title::makeTitle( NS_MAIN, "somePage" );
		$article = new FlaggableWikiPage( $title );

		$user = $this->user;
		$article->doEditContent(
			ContentHandler::makeContent( "Some text to insert", $title ),
			"creating a page",
			EDIT_NEW,
			false,
			$user
		);

		$data = (array)$article->pageDataFromTitle( wfGetDB( DB_REPLICA ), $title );

		$this->assertTrue( array_key_exists( 'fpc_override', $data ),
			"data->fpc_override field exists" );
		$this->assertTrue( array_key_exists( 'fp_stable', $data ),
			"data->fp_stable field exists" );
		$this->assertTrue( array_key_exists( 'fp_pending_since', $data ),
			"data->fp_pending_since field exists" );
		$this->assertTrue( array_key_exists( 'fp_reviewed', $data ),
			"data->fp_reviewed field exists" );
	}
}
