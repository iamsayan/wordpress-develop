<?php

/**
 * @group admin
 * @group ms-required
 * @group network-admin
 */
class Tests_Multisite_wpMsSitesListTable extends WP_UnitTestCase {

	protected static $site_ids;

	/**
	 * @var WP_MS_Sites_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_MS_Sites_List_Table', array( 'screen' => 'ms-sites' ) );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$site_ids = array(
			'wordpress.org/'          => array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			),
			'wordpress.org/foo/'      => array(
				'domain' => 'wordpress.org',
				'path'   => '/foo/',
			),
			'wordpress.org/foo/bar/'  => array(
				'domain' => 'wordpress.org',
				'path'   => '/foo/bar/',
			),
			'wordpress.org/afoo/'     => array(
				'domain' => 'wordpress.org',
				'path'   => '/afoo/',
			),
			'make.wordpress.org/'     => array(
				'domain' => 'make.wordpress.org',
				'path'   => '/',
			),
			'make.wordpress.org/foo/' => array(
				'domain' => 'make.wordpress.org',
				'path'   => '/foo/',
			),
			'www.w.org/'              => array(
				'domain' => 'www.w.org',
				'path'   => '/',
			),
			'www.w.org/foo/'          => array(
				'domain' => 'www.w.org',
				'path'   => '/foo/',
			),
			'www.w.org/foo/bar/'      => array(
				'domain' => 'www.w.org',
				'path'   => '/foo/bar/',
			),
			'test.example.org/'       => array(
				'domain' => 'test.example.org',
				'path'   => '/',
			),
			'test2.example.org/'      => array(
				'domain' => 'test2.example.org',
				'path'   => '/',
			),
			'test3.example.org/zig/'  => array(
				'domain' => 'test3.example.org',
				'path'   => '/zig/',
			),
			'atest.example.org/'      => array(
				'domain' => 'atest.example.org',
				'path'   => '/',
			),
		);

		foreach ( self::$site_ids as &$id ) {
			$id = $factory->blog->create( $id );
		}
		unset( $id );
	}

	public static function wpTearDownAfterClass() {
		foreach ( self::$site_ids as $site_id ) {
			wp_delete_site( $site_id );
		}
	}

	public function test_ms_sites_list_table_default_items() {
		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		$this->assertSameSets( array( 1 ) + self::$site_ids, $items );
	}

	public function test_ms_sites_list_table_subdirectory_path_search_items() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'foo';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/'],
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['wordpress.org/afoo/'],
			self::$site_ids['make.wordpress.org/foo/'],
			self::$site_ids['www.w.org/foo/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdirectory_multiple_path_search_items() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'foo/bar';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_invalid_path_search_items() {
		$_REQUEST['s'] = 'foobar';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$this->assertEmpty( $items );
	}

	public function test_ms_sites_list_table_subdomain_domain_search_items() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'Domain search is not available for subdirectory configurations.' );
		}

		$_REQUEST['s'] = 'test';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['test.example.org/'],
			self::$site_ids['test2.example.org/'],
			self::$site_ids['test3.example.org/zig/'],
			self::$site_ids['atest.example.org/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdomain_domain_search_items_with_trailing_wildcard() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'Domain search is not available for subdirectory configurations.' );
		}

		$_REQUEST['s'] = 'test*';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['test.example.org/'],
			self::$site_ids['test2.example.org/'],
			self::$site_ids['test3.example.org/zig/'],
			self::$site_ids['atest.example.org/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	public function test_ms_sites_list_table_subdirectory_path_search_items_with_trailing_wildcard() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'Path search is not available for subdomain configurations.' );
		}

		$_REQUEST['s'] = 'fo*';

		$this->table->prepare_items();

		$items = wp_list_pluck( $this->table->items, 'blog_id' );
		$items = array_map( 'intval', $items );

		unset( $_REQUEST['s'] );

		$expected = array(
			self::$site_ids['wordpress.org/foo/'],
			self::$site_ids['wordpress.org/foo/bar/'],
			self::$site_ids['wordpress.org/afoo/'],
			self::$site_ids['make.wordpress.org/foo/'],
			self::$site_ids['www.w.org/foo/'],
			self::$site_ids['www.w.org/foo/bar/'],
		);

		$this->assertSameSets( $expected, $items );
	}

	/**
	 * @ticket 42066
	 */
	public function test_get_views_should_return_views_by_default() {
		$expected = array(
			'all'    => '<a href="sites.php" class="current" aria-current="page">All <span class="count">(14)</span></a>',
			'public' => '<a href="sites.php?status=public">Public <span class="count">(14)</span></a>',
		);

		$this->assertSame( $expected, $this->table->get_views() );
	}
}
