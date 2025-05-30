<?php
/**
 * Unit tests covering WP_REST_Posts_Types_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_Test_REST_Post_Types_Controller extends WP_Test_REST_Controller_Testcase {

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/types', $routes );
		$this->assertArrayHasKey( '/wp/v2/types/(?P<type>[\w-]+)', $routes );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'edit', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/types/post' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'edit', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_get_items() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$response = rest_get_server()->dispatch( $request );

		$data       = $response->get_data();
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'objects' );
		$this->assertCount( count( $post_types ), $data );
		$this->assertSame( $post_types['post']->name, $data['post']['slug'] );
		$this->check_post_type_obj( 'view', $post_types['post'], $data['post'], $data['post']['_links'] );
		$this->assertSame( $post_types['page']->name, $data['page']['slug'] );
		$this->check_post_type_obj( 'view', $post_types['page'], $data['page'], $data['page']['_links'] );
		$this->assertArrayNotHasKey( 'revision', $data );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_invalid_permission_for_context( $method ) {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( $method, '/wp/v2/types' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * Data provider intended to provide HTTP method names for testing GET and HEAD requests.
	 *
	 * @return array
	 */
	public static function data_readable_http_methods() {
		return array(
			'GET request'  => array( 'GET' ),
			'HEAD request' => array( 'HEAD' ),
		);
	}

	public function test_get_item() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types/post' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_post_type_object_response( 'view', $response );
		$data = $response->get_data();
		$this->assertSame( array( 'category', 'post_tag' ), $data['taxonomies'] );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_item_should_allow_adding_headers_via_filter( $method ) {
		$request = new WP_REST_Request( $method, '/wp/v2/types/post' );

		$hook_name = 'rest_prepare_post_type';
		$filter    = new MockAction();
		$callback  = array( $filter, 'filter' );
		add_filter( $hook_name, $callback );
		$header_filter = new class() {
			public static function add_custom_header( $response ) {
				$response->header( 'X-Test-Header', 'Test' );

				return $response;
			}
		};
		add_filter( $hook_name, array( $header_filter, 'add_custom_header' ) );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );
		remove_filter( $hook_name, array( $header_filter, 'add_custom_header' ) );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( 1, $filter->get_call_count(), 'The "' . $hook_name . '" filter was called when it should not be for HEAD requests.' );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-Test-Header', $headers, 'The "X-Test-Header" header should be present in the response.' );
		$this->assertSame( 'Test', $headers['X-Test-Header'], 'The "X-Test-Header" header value should be equal to "Test".' );
		if ( 'HEAD' !== $method ) {
			return null;
		}
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @dataProvider data_head_request_with_specified_fields_returns_success_response
	 * @ticket 56481
	 *
	 * @param string $path The path to test.
	 */
	public function test_head_request_with_specified_fields_returns_success_response( $path ) {
		$request = new WP_REST_Request( 'HEAD', $path );
		$request->set_param( '_fields', 'slug' );
		$server   = rest_get_server();
		$response = $server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
	}

	/**
	 * Data provider intended to provide paths for testing HEAD requests.
	 *
	 * @return array
	 */
	public static function data_head_request_with_specified_fields_returns_success_response() {
		return array(
			'get_item request'  => array( '/wp/v2/types/post' ),
			'get_items request' => array( '/wp/v2/types' ),
		);
	}

	/**
	 * @ticket 53656
	 */
	public function test_get_item_cpt() {
		register_post_type(
			'cpt',
			array(
				'show_in_rest'   => true,
				'rest_base'      => 'cpt',
				'rest_namespace' => 'wordpress/v1',
			)
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types/cpt' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_post_type_object_response( 'view', $response, 'cpt' );
	}

	/**
	 * @ticket 61477
	 */
	public function test_get_item_template_cpt() {
		register_post_type(
			'cpt_template',
			array(
				'show_in_rest'   => true,
				'rest_base'      => 'cpt_template',
				'rest_namespace' => 'wordpress/v1',
				'template'       => array(
					array( 'core/paragraph', array( 'placeholder' => 'Content' ) ),
				),
				'template_lock'  => 'all',
			)
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types/cpt_template' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_post_type_object_response( 'view', $response, 'cpt_template' );
	}

	public function test_get_item_page() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types/page' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_post_type_object_response( 'view', $response, 'page' );
		$data = $response->get_data();
		$this->assertSame( array(), $data['taxonomies'] );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_item_invalid_type( $method ) {
		$request  = new WP_REST_Request( $method, '/wp/v2/types/invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_type_invalid', $response, 404 );
	}

	public function test_get_item_edit_context() {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/types/post' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_post_type_object_response( 'edit', $response );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_item_invalid_permission_for_context( $method ) {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( $method, '/wp/v2/types/post' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_create_item() {
		/** Post types can't be created */
		$request  = new WP_REST_Request( 'POST', '/wp/v2/types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_update_item() {
		/** Post types can't be updated */
		$request  = new WP_REST_Request( 'POST', '/wp/v2/types/post' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_delete_item() {
		/** Post types can't be deleted */
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/types/post' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_prepare_item() {
		$obj      = get_post_type_object( 'post' );
		$endpoint = new WP_REST_Post_Types_Controller();
		$request  = new WP_REST_Request();
		$request->set_param( 'context', 'edit' );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->check_post_type_obj( 'edit', $obj, $response->get_data(), $response->get_links() );
	}

	public function test_prepare_item_limit_fields() {
		$obj      = get_post_type_object( 'post' );
		$request  = new WP_REST_Request();
		$endpoint = new WP_REST_Post_Types_Controller();
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,name' );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->assertSame(
			array(
				// 'id' doesn't exist in this context.
				'name',
			),
			array_keys( $response->get_data() )
		);
	}

	/**
	 * @ticket 56467
	 *
	 * @covers WP_REST_Post_Types_Controller::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/types' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertCount( 16, $properties, 'Schema should have 16 properties' );
		$this->assertArrayHasKey( 'capabilities', $properties, '`capabilities` should be included in the schema' );
		$this->assertArrayHasKey( 'description', $properties, '`description` should be included in the schema' );
		$this->assertArrayHasKey( 'hierarchical', $properties, '`hierarchical` should be included in the schema' );
		$this->assertArrayHasKey( 'viewable', $properties, '`viewable` should be included in the schema' );
		$this->assertArrayHasKey( 'labels', $properties, '`labels` should be included in the schema' );
		$this->assertArrayHasKey( 'name', $properties, '`name` should be included in the schema' );
		$this->assertArrayHasKey( 'slug', $properties, '`slug` should be included in the schema' );
		$this->assertArrayHasKey( 'supports', $properties, '`supports` should be included in the schema' );
		$this->assertArrayHasKey( 'has_archive', $properties, '`has_archive` should be included in the schema' );
		$this->assertArrayHasKey( 'taxonomies', $properties, '`taxonomies` should be included in the schema' );
		$this->assertArrayHasKey( 'rest_base', $properties, '`rest_base` should be included in the schema' );
		$this->assertArrayHasKey( 'rest_namespace', $properties, '`rest_namespace` should be included in the schema' );
		$this->assertArrayHasKey( 'visibility', $properties, '`visibility` should be included in the schema' );
		$this->assertArrayHasKey( 'icon', $properties, '`icon` should be included in the schema' );
		$this->assertArrayHasKey( 'template', $properties, '`template` should be included in the schema' );
		$this->assertArrayHasKey( 'template_lock', $properties, '`template_lock` should be included in the schema' );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'type',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/types/schema' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/types/post' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $response_data ) {
		return 123;
	}

	/**
	 * @ticket 56481
	 */
	public function test_get_items_with_head_request_should_not_prepare_post_types_data() {
		$request   = new WP_REST_Request( 'HEAD', '/wp/v2/types' );
		$hook_name = 'rest_prepare_post_type';
		$filter    = new MockAction();
		$callback  = array( $filter, 'filter' );
		add_filter( $hook_name, $callback );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );
		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( 0, $filter->get_call_count(), 'The "' . $hook_name . '" filter was called when it should not be for HEAD requests.' );
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	protected function check_post_type_obj( $context, $post_type_obj, $data, $links ) {
		$this->assertSame( $post_type_obj->label, $data['name'] );
		$this->assertSame( $post_type_obj->name, $data['slug'] );
		$this->assertSame( $post_type_obj->description, $data['description'] );
		$this->assertSame( $post_type_obj->hierarchical, $data['hierarchical'] );
		$this->assertSame( $post_type_obj->rest_base, $data['rest_base'] );
		$this->assertSame( $post_type_obj->rest_namespace, $data['rest_namespace'] );
		$this->assertSame( $post_type_obj->has_archive, $data['has_archive'] );
		$this->assertSame( $post_type_obj->template ?? array(), $data['template'] );
		$this->assertSame( ! empty( $post_type_obj->template_lock ) ? $post_type_obj->template_lock : false, $data['template_lock'] );

		$links = test_rest_expand_compact_links( $links );
		$this->assertSame( rest_url( 'wp/v2/types' ), $links['collection'][0]['href'] );
		$this->assertArrayHasKey( 'https://api.w.org/items', $links );
		if ( 'edit' === $context ) {
			$this->assertSame( $post_type_obj->cap, $data['capabilities'] );
			$this->assertSame( $post_type_obj->labels, $data['labels'] );
			if ( in_array( $post_type_obj->name, array( 'post', 'page' ), true ) ) {
				$viewable = true;
			} else {
				$viewable = is_post_type_viewable( $post_type_obj );
			}
			$this->assertSame( $viewable, $data['viewable'] );
			$visibility = array(
				'show_in_nav_menus' => (bool) $post_type_obj->show_in_nav_menus,
				'show_ui'           => (bool) $post_type_obj->show_ui,
			);
			$this->assertSame( $visibility, $data['visibility'] );
			$this->assertSame( get_all_post_type_supports( $post_type_obj->name ), $data['supports'] );
		} else {
			$this->assertArrayNotHasKey( 'capabilities', $data );
			$this->assertArrayNotHasKey( 'viewable', $data );
			$this->assertArrayNotHasKey( 'labels', $data );
			$this->assertArrayNotHasKey( 'supports', $data );
		}
	}

	protected function check_post_type_object_response( $context, $response, $post_type = 'post' ) {
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$obj  = get_post_type_object( $post_type );
		$this->check_post_type_obj( $context, $obj, $data, $response->get_links() );
	}
}
