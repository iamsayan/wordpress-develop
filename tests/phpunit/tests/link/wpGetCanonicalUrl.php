<?php
/**
 * Tests for the wp_get_canonical_url() function.
 *
 * @group link
 * @group canonical
 * @covers ::wp_get_canonical_url
 */
class Tests_Link_WpGetCanonicalUrl extends WP_UnitTestCase {

	/**
	 * The ID of the post.
	 *
	 * @var int
	 */
	public static $post_id;

	/**
	 * The ID of the attachment.
	 *
	 * @var int
	 */
	public static $attachment_id;

	/**
	 * Sets up the test environment before any tests are run.
	 *
	 * @param WP_UnitTest_Factory $factory The factory object.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_content' => 'Page 1 <!--nextpage--> Page 2 <!--nextpage--> Page 3',
				'post_status'  => 'publish',
			)
		);

		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'        => DIR_TESTDATA . '/images/canola.jpg',
				'post_parent' => self::$post_id,
				'post_status' => 'inherit',
			)
		);
	}

	/**
	 * Tests that false is returned for a non-existing post.
	 */
	public function test_non_existing_post() {
		$this->assertFalse( wp_get_canonical_url( -1 ) );
	}

	/**
	 * Tests that false is returned for a post that is not published.
	 */
	public function test_post_status() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
			)
		);

		$this->assertFalse( wp_get_canonical_url( $post_id ) );
	}

	/**
	 * Tests canonical URL for a page that is not the queried object.
	 */
	public function test_non_current_page() {
		$this->assertSame( get_permalink( self::$post_id ), wp_get_canonical_url( self::$post_id ) );
	}

	/**
	 * Tests non-permalink structure page usage.
	 */
	public function test_paged_with_plain_permalink_structure() {
		$link = add_query_arg(
			array(
				'page' => 2,
				'foo'  => 'bar',
			),
			get_permalink( self::$post_id )
		);

		$this->go_to( $link );

		$expected = add_query_arg(
			array(
				'page' => 2,
			),
			get_permalink( self::$post_id )
		);

		$this->assertSame( $expected, wp_get_canonical_url( self::$post_id ) );
	}

	/**
	 * Tests permalink structure page usage.
	 */
	public function test_paged_with_custom_permalink_structure() {
		$this->set_permalink_structure( '/%postname%/' );
		$page = 2;

		$link = add_query_arg(
			array(
				'page' => $page,
				'foo'  => 'bar',
			),
			get_permalink( self::$post_id )
		);

		$this->go_to( $link );

		$expected = trailingslashit( get_permalink( self::$post_id ) ) . user_trailingslashit( $page, 'single_paged' );

		$this->assertSame( $expected, wp_get_canonical_url( self::$post_id ) );
	}

	/**
	 * Tests non-permalink structure comment page usage.
	 */
	public function test_comments_paged_with_plain_permalink_structure() {
		$cpage = 2;

		$link = add_query_arg(
			array(
				'cpage' => $cpage,
				'foo'   => 'bar',
			),
			get_permalink( self::$post_id )
		);

		$this->go_to( $link );

		$expected = add_query_arg(
			array(
				'cpage' => $cpage,
			),
			get_permalink( self::$post_id ) . '#comments'
		);

		$this->assertSame( $expected, wp_get_canonical_url( self::$post_id ) );
	}

	/**
	 * Tests permalink structure comment page usage.
	 */
	public function test_comments_paged_with_pretty_permalink_structure() {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );
		$cpage = 2;

		$link = add_query_arg(
			array(
				'cpage' => $cpage,
				'foo'   => 'bar',
			),
			get_permalink( self::$post_id )
		);

		$this->go_to( $link );

		$expected = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . $wp_rewrite->comments_pagination_base . '-' . $cpage, 'commentpaged' ) . '#comments';

		$this->assertSame( $expected, wp_get_canonical_url( self::$post_id ) );
	}

	/**
	 * Tests that attachments with 'inherit' status properly receive a canonical URL.
	 *
	 * @ticket 63041
	 */
	public function test_attachment_canonical_url() {
		$this->go_to( get_attachment_link( self::$attachment_id ) );
		$canonical_url = wp_get_canonical_url( self::$attachment_id );

		$this->assertNotFalse( $canonical_url, 'Attachment should have a canonical URL' );
		$this->assertSame( get_attachment_link( self::$attachment_id ), $canonical_url, 'Canonical URL should match the attachment permalink' );
	}

	/**
	 * Tests calling of filter.
	 */
	public function test_get_canonical_url_filter() {
		add_filter( 'get_canonical_url', array( $this, 'canonical_url_filter' ) );
		$canonical_url = wp_get_canonical_url( self::$post_id );
		remove_filter( 'get_canonical_url', array( $this, 'canonical_url_filter' ) );

		$this->assertSame( $this->canonical_url_filter(), $canonical_url );
	}

	/**
	 * Filter callback for testing of filter usage.
	 *
	 * @return string
	 */
	public function canonical_url_filter() {
		return 'http://canonical.example.org/';
	}
}
