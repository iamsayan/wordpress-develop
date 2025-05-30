<?php

/**
 * @group ms-required
 * @group multisite
 */
class Tests_Multisite_wpmuValidateUserSignup extends WP_UnitTestCase {

	/**
	 * @dataProvider data_user_name
	 */
	public function test_user_name( $user_name, $error_message ) {
		$v = wpmu_validate_user_signup( $user_name, 'foo@example.com' );
		$this->assertContains( 'user_name', $v['errors']->get_error_codes(), $error_message );
	}

	public function data_user_name() {
		return array(
			array( 'contains spaces', 'User names with spaces are not allowed.' ),
			array( 'ContainsCaps', 'User names with capital letters are not allowed.' ),
			array( 'contains_underscores', 'User names with underscores are not allowed.' ),
			array( 'contains%^*()junk', 'User names with non-alphanumeric characters are not allowed.' ),
			array( '', 'Empty user names are not allowed.' ),
			array( 'foo', 'User names of 3 characters are not allowed.' ),
			array( 'fo', 'User names of 2 characters are not allowed.' ),
			array( 'f', 'User names of 1 characters are not allowed.' ),
			array( 'f', 'User names of 1 characters are not allowed.' ),
			array( '12345', 'User names consisting only of numbers are not allowed.' ),
			array( 'thisusernamecontainsenoughcharacterstobelongerthan60characters', 'User names longer than 60 characters are not allowed.' ),
		);
	}

	public function test_should_fail_for_illegal_names() {
		$illegal = array( 'foo123', 'bar123' );
		update_site_option( 'illegal_names', $illegal );

		foreach ( $illegal as $i ) {
			$v = wpmu_validate_user_signup( $i, 'foo@example.com' );
			$this->assertContains( 'user_name', $v['errors']->get_error_codes() );
		}
	}

	public function test_should_fail_for_unsafe_email_address() {
		add_filter( 'is_email_address_unsafe', '__return_true' );
		$v = wpmu_validate_user_signup( 'foo123', 'foo@example.com' );
		$this->assertContains( 'user_email', $v['errors']->get_error_codes() );
		remove_filter( 'is_email_address_unsafe', '__return_true' );
	}

	public function test_should_fail_for_invalid_email_address() {
		add_filter( 'is_email', '__return_false' );
		$v = wpmu_validate_user_signup( 'foo123', 'foo@example.com' );
		$this->assertContains( 'user_email', $v['errors']->get_error_codes() );
		remove_filter( 'is_email', '__return_false' );
	}

	public function test_should_fail_for_emails_from_disallowed_domains() {
		$domains = array( 'foo.com', 'bar.org' );
		update_site_option( 'limited_email_domains', $domains );

		$v = wpmu_validate_user_signup( 'foo123', 'foo@example.com' );
		$this->assertContains( 'user_email', $v['errors']->get_error_codes() );
	}

	public function test_should_not_fail_for_emails_from_allowed_domains_with_mixed_case() {
		$domains = array( 'foo.com', 'bar.org' );
		update_site_option( 'limited_email_domains', $domains );

		$v = wpmu_validate_user_signup( 'foo123', 'foo@BAR.org' );
		$this->assertNotContains( 'user_email', $v['errors']->get_error_codes() );
	}

	public function test_should_fail_for_existing_user_name() {
		$u = self::factory()->user->create( array( 'user_login' => 'foo123' ) );
		$v = wpmu_validate_user_signup( 'foo123', 'foo@example.com' );
		$this->assertContains( 'user_name', $v['errors']->get_error_codes() );
	}

	public function test_should_fail_for_existing_user_email() {
		$u = self::factory()->user->create( array( 'user_email' => 'foo@example.com' ) );
		$v = wpmu_validate_user_signup( 'foo123', 'foo@example.com' );
		$this->assertContains( 'user_email', $v['errors']->get_error_codes() );
	}

	public function test_should_fail_for_existing_signup_with_same_username() {
		// Don't send notifications.
		add_filter( 'wpmu_signup_user_notification', '__return_false' );
		wpmu_signup_user( 'foo123', 'foo@example.com' );
		remove_filter( 'wpmu_signup_user_notification', '__return_false' );

		$v = wpmu_validate_user_signup( 'foo123', 'foo2@example.com' );
		$this->assertContains( 'user_name', $v['errors']->get_error_codes() );
	}

	public function test_should_not_fail_for_existing_signup_with_same_username_if_signup_is_old() {
		// Don't send notifications.
		add_filter( 'wpmu_signup_user_notification', '__return_false' );
		wpmu_signup_user( 'foo123', 'foo@example.com' );
		remove_filter( 'wpmu_signup_user_notification', '__return_false' );

		global $wpdb;
		$date = gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) - 60 );
		$wpdb->update( $wpdb->signups, array( 'registered' => $date ), array( 'user_login' => 'foo123' ) );

		$v = wpmu_validate_user_signup( 'foo123', 'foo2@example.com' );
		$this->assertNotContains( 'user_name', $v['errors']->get_error_codes() );
	}

	public function test_should_fail_for_existing_signup_with_same_email() {
		// Don't send notifications.
		add_filter( 'wpmu_signup_user_notification', '__return_false' );
		wpmu_signup_user( 'foo123', 'foo@example.com' );
		remove_filter( 'wpmu_signup_user_notification', '__return_false' );

		$v = wpmu_validate_user_signup( 'foo2', 'foo@example.com' );
		$this->assertContains( 'user_email', $v['errors']->get_error_codes() );
	}

	public function test_should_not_fail_for_existing_signup_with_same_email_if_signup_is_old() {
		// Don't send notifications.
		add_filter( 'wpmu_signup_user_notification', '__return_false' );
		wpmu_signup_user( 'foo123', 'foo@example.com' );
		remove_filter( 'wpmu_signup_user_notification', '__return_false' );

		global $wpdb;
		$date = gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) - 60 );
		$wpdb->update( $wpdb->signups, array( 'registered' => $date ), array( 'user_login' => 'foo123' ) );

		$v = wpmu_validate_user_signup( 'foo2', 'foo2@example.com' );
		$this->assertNotContains( 'user_email', $v['errors']->get_error_codes() );
	}

	/**
	 * @ticket 43232
	 */
	public function test_should_not_fail_for_data_used_by_a_deleted_user() {
		global $wpdb;

		// Don't send notifications.
		add_filter( 'wpmu_signup_user_notification', '__return_false' );
		add_filter( 'wpmu_welcome_user_notification', '__return_false' );

		// Signup, activate and delete new user.
		wpmu_signup_user( 'foo123', 'foo@example.com' );
		$key  = $wpdb->get_var( "SELECT activation_key FROM $wpdb->signups WHERE user_login = 'foo123'" );
		$user = wpmu_activate_signup( $key );
		wpmu_delete_user( $user['user_id'] );

		$valid = wpmu_validate_user_signup( 'foo123', 'foo2@example.com' );

		remove_filter( 'wpmu_signup_user_notification', '__return_false' );
		remove_filter( 'wpmu_signup_user_notification', '__return_false' );

		$this->assertNotContains( 'user_name', $valid['errors']->get_error_codes() );
		$this->assertNotContains( 'user_email', $valid['errors']->get_error_codes() );
	}

	public function test_invalid_email_address_with_no_banned_domains_results_in_error() {
		$valid = wpmu_validate_user_signup( 'validusername', 'invalid-email' );

		$this->assertContains( 'user_email', $valid['errors']->get_error_codes() );
	}

	public function test_invalid_email_address_with_banned_domains_results_in_error() {
		update_site_option( 'banned_email_domains', 'bar.com' );
		$valid = wpmu_validate_user_signup( 'validusername', 'invalid-email' );
		delete_site_option( 'banned_email_domains' );

		$this->assertContains( 'user_email', $valid['errors']->get_error_codes() );
	}

	public function test_incomplete_email_address_with_no_banned_domains_results_in_error() {
		$valid = wpmu_validate_user_signup( 'validusername', 'incomplete@email' );

		$this->assertContains( 'user_email', $valid['errors']->get_error_codes() );
	}

	public function test_valid_email_address_matching_banned_domain_results_in_error() {
		update_site_option( 'banned_email_domains', 'bar.com' );
		$valid = wpmu_validate_user_signup( 'validusername', 'email@bar.com' );
		delete_site_option( 'banned_email_domains' );

		$this->assertContains( 'user_email', $valid['errors']->get_error_codes() );
	}

	public function test_valid_email_address_not_matching_banned_domain_returns_in_success() {
		update_site_option( 'banned_email_domains', 'bar.com' );
		$valid = wpmu_validate_user_signup( 'validusername', 'email@example.com' );
		delete_site_option( 'banned_email_domains' );

		$this->assertNotContains( 'user_email', $valid['errors']->get_error_codes() );
	}

	/**
	 * @ticket 43667
	 */
	public function test_signup_nonce_check() {
		$original_php_self       = $_SERVER['PHP_SELF'];
		$_SERVER['PHP_SELF']     = '/wp-signup.php';
		$_POST['signup_form_id'] = 'user-signup-form';
		$_POST['_signup_form']   = wp_create_nonce( 'signup_form_' . $_POST['signup_form_id'] );

		$valid               = wpmu_validate_user_signup( 'validusername', 'email@example.com' );
		$_SERVER['PHP_SELF'] = $original_php_self;

		$this->assertNotContains( 'invalid_nonce', $valid['errors']->get_error_codes() );
	}

	/**
	 * @ticket 43667
	 */
	public function test_signup_nonce_check_invalid() {
		$original_php_self       = $_SERVER['PHP_SELF'];
		$_SERVER['PHP_SELF']     = '/wp-signup.php';
		$_POST['signup_form_id'] = 'user-signup-form';
		$_POST['_signup_form']   = wp_create_nonce( 'invalid' );

		$valid               = wpmu_validate_user_signup( 'validusername', 'email@example.com' );
		$_SERVER['PHP_SELF'] = $original_php_self;

		$this->assertContains( 'invalid_nonce', $valid['errors']->get_error_codes() );
	}

	/**
	 * Ensure that wp_ensure_editable_role does not throw an exception when the role is editable.
	 *
	 * @ticket 43251
	 *
	 * @covers ::wp_ensure_editable_role
	 */
	public function test_wp_ensure_editable_role_allows_editable_roles() {
		$role = get_role( 'editor' );
		$this->assertInstanceOf( 'WP_Role', $role, 'The editor role should exist.' );
		$this->assertNull( wp_ensure_editable_role( 'editor' ), 'The editor role should be editable.' );
	}

	/**
	 * Ensure that wp_ensure_editable_role throws an exception for non-existent roles.
	 *
	 * @ticket 43251
	 *
	 * @covers ::wp_ensure_editable_role
	 */
	public function test_wp_ensure_editable_role_does_not_allow_non_existent_role() {
		$this->expectException( 'WPDieException' );
		$role = get_role( 'non-existent-role' );
		$this->assertNotInstanceOf( 'WP_Role', $role, 'The non-existent-role role should not exist.' );
		wp_ensure_editable_role( 'non-existent-role' );
	}

	/**
	 * Ensure that wp_ensure_editable_role throws an exception for roles that are not editable.
	 *
	 * @ticket 43251
	 *
	 * @covers ::wp_ensure_editable_role
	 */
	public function test_wp_ensure_editable_role_does_not_allow_uneditable_roles() {
		add_filter(
			'editable_roles',
			function ( $roles ) {
				unset( $roles['editor'] );
				return $roles;
			}
		);
		$this->expectException( 'WPDieException' );
		$role = get_role( 'editor' );
		$this->assertInstanceOf( 'WP_Role', $role, 'The editor role should exist.' );
		wp_ensure_editable_role( 'editor' );
	}
}
