<?php
/**
 * Test cases for the `wp_send_user_request()` function.
 *
 * @package WordPress
 * @since 4.9.9
 */

/**
 * Tests_User_WpSendUserRequest class.
 *
 * @since 4.9.9
 *
 * @group privacy
 * @group user
 * @covers wp_send_user_request
 */
class Tests_User_WpSendUserRequest extends WP_UnitTestCase {
	/**
	 * Reset the mocked phpmailer instance before each test method.
	 *
	 * @since 4.9.9
	 */
	public function setUp() {
		parent::setUp();
		reset_phpmailer_instance();
	}

	/**
	 * Reset the mocked phpmailer instance after each test method.
	 *
	 * @since 4.9.9
	 */
	public function tearDown() {
		reset_phpmailer_instance();
		parent::tearDown();
	}

	/**
	 * The function should error when the request ID is invalid.
	 *
	 * @ticket 43985
	 */
	public function test_should_error_when_invalid_request_id() {
		$result = wp_send_user_request( null );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	/**
	 * The function should send a user request export email when the requester is a registered user.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_export_email_when_requester_registered_user() {
		$email      = 'export.request.from.user@example.com';
		$user_id    = $this->factory->user->create(
			array(
				'user_email' => $email,
				'role'       => 'administrator',
			)
		);
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( $email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Export Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Export Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should send a user request erase email when the requester is a registered user.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_erase_email_when_requester_registered_user() {
		$email      = 'erase.request.from.user@example.com';
		$user_id    = $this->factory->user->create(
			array(
				'user_email' => $email,
				'role'       => 'author',
			)
		);
		$request_id = wp_create_user_request( $email, 'remove_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( $email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Erase Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Erase Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should send a user request export email when the requester is an un-registered user.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_export_email_when_user_not_registered() {
		$email      = 'export.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( $email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Export Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Export Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should send a user request erase email when the requester is an un-registered user.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_erase_email_when_user_not_registered() {
		$email      = 'erase.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'remove_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( $email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Erase Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Erase Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The email subject should be filterable.
	 *
	 * @ticket 43985
	 */
	public function test_email_subject_should_be_filterable() {
		$email      = 'erase.request.from.user@example.com';
		$user_id    = $this->factory->user->create(
			array(
				'user_email' => $email,
			)
		);
		$request_id = wp_create_user_request( $email, 'remove_personal_data' );

		add_filter( 'user_request_action_email_subject', array( $this, 'modify_email_subject' ) );
		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertSame( 'Custom Email Subject', $mailer->get_sent()->subject );
	}

	/**
	 * Filter callback to modify the subject of the email sent when an account action is attempted.
	 *
	 * @since 4.9.9
	 *
	 * @param string $subject The email subject.
	 * @return string Filtered email subject.
	 */
	public function modify_email_subject( $subject ) {
		return 'Custom Email Subject';
	}

	/**
	 * The email content should be filterable.
	 *
	 * @ticket 43985
	 */
	public function test_email_content_should_be_filterable() {
		$email      = 'erase.request.from.user@example.com';
		$user_id    = $this->factory->user->create(
			array(
				'user_email' => $email,
			)
		);
		$request_id = wp_create_user_request( $email, 'remove_personal_data' );

		add_filter( 'user_request_action_email_content', array( $this, 'modify_email_content' ), 10, 2 );
		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $result );
		$this->assertContains( 'Custom Email Content.', $mailer->get_sent()->body );
	}

	/**
	 * Filter callback to modify the content of the email sent when an account action is attempted.
	 *
	 * @since 4.9.9
	 *
	 * @param string $email_text Confirmation email text.
	 * @return string $email_text Filtered email text.
	 */
	public function modify_email_content( $email_text ) {
		return 'Custom Email Content.';
	}

	/**
	 * The function should error when the email was not sent.
	 *
	 * @since 4.9.9
	 *
	 * @ticket 43985
	 */
	public function test_return_wp_error_when_sending_fails() {
		$request_id = wp_create_user_request( 'erase.request.from.unergistered.user@example.com', 'remove_personal_data' );

		add_filter( 'wp_mail_from', '__return_empty_string' ); // Cause `wp_mail()` to return false.
		$result = wp_send_user_request( $request_id );

		$this->assertWPError( $result );
		$this->assertSame( 'privacy_email_error', $result->get_error_code() );
	}

	/**
	 * The function should respect the user locale settings when the site uses the default locale.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_email_in_user_locale() {
		delete_option( 'WPLANG' );

		$email         = 'export.request.from.user@example.com';
		$admin_user_id = $this->factory->user->create(
			array(
				'user_email' => 'admin@local.test',
				'role'       => 'administrator',
			)
		);
		$user_id       = $this->factory->user->create(
			array(
				'user_email' => $email,
				'locale'     => 'es_ES',
				'role'       => 'author',
			)
		);

		wp_set_current_user( $admin_user_id );
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirma la', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the user locale settings when the site has a default locale and the administrator
	 * uses the site default.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_email_in_user_locale_when_site_has_non_default_locale() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		$email         = 'remove.request.from.user@example.com';
		$admin_user_id = $this->factory->user->create(
			array(
				'user_email' => 'admin@local.test',
				'role'       => 'administrator',
			)
		);
		$user_id       = $this->factory->user->create(
			array(
				'user_email' => $email,
				'locale'     => 'de_DE',
				'role'       => 'author',
			)
		);

		wp_set_current_user( $admin_user_id );
		$request_id = wp_create_user_request( $email, 'remove_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Aktion bestätigen', $mailer->get_sent()->subject );

		restore_current_locale();
		delete_option( 'WPLANG' );
	}

	/**
	 * The function should respect the user locale settings when the site has a default locale, the administrator
	 * has a different locale, and the user uses the site's default.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_email_in_user_locale_when_admin_has_different_locale_than_site() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		$email         = 'export.request.from.user@example.com';
		$admin_user_id = $this->factory->user->create(
			array(
				'user_email' => 'admin@local.test',
				'locale'     => 'de_DE',
				'role'       => 'administrator',
			)
		);
		$user_id       = $this->factory->user->create(
			array(
				'user_email' => $email,
				'role'       => 'author',
			)
		);

		wp_set_current_user( $admin_user_id );

		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirma la', $mailer->get_sent()->subject );

		restore_current_locale();
		delete_option( 'WPLANG' );
	}

	/**
	 * The function should respect the user locale settings when the site has a default locale and both the
	 * administrator and the user use different locales.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_email_in_user_locale_when_admin_and_site_have_different_locales() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		$email         = 'export.request.from.user@example.com';
		$admin_user_id = $this->factory->user->create(
			array(
				'user_email' => 'admin@local.test',
				'locale'     => 'de_DE',
				'role'       => 'administrator',
			)
		);
		$user_id       = $this->factory->user->create(
			array(
				'user_email' => $email,
				'role'       => 'author',
				'locale'     => 'en_US',
			)
		);

		wp_set_current_user( $admin_user_id );

		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirm Action', $mailer->get_sent()->subject );

		restore_current_locale();
		delete_option( 'WPLANG' );
	}

	/**
	 * The function should respect the site's locale when the request is for an unregistered user and the
	 * administrator does not use the site's locale.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_email_in_site_locale() {
		delete_option( 'WPLANG' );

		$email         = 'erase-user-not-registered@example.com';
		$admin_user_id = $this->factory->user->create(
			array(
				'user_email' => 'admin@local.test',
				'role'       => 'administrator',
				'locale'     => 'es_ES',
			)
		);

		wp_set_current_user( $admin_user_id );
		$request_id = wp_create_user_request( $email, 'erase_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirm Action', $mailer->get_sent()->subject );
	}

	/**
	 * The function should respect the site's locale when it is not the default (en_US), the request is for an
	 * unregistered user and the administrator does not use the site's locale.
	 *
	 * @ticket 43985
	 */
	public function test_should_send_user_request_email_in_site_locale_not_default() {
		update_option( 'WPLANG', 'es_ES' );
		switch_to_locale( 'es_ES' );

		$email         = 'export-user-not-registered@example.com';
		$admin_user_id = $this->factory->user->create(
			array(
				'user_email' => 'admin@local.test',
				'role'       => 'administrator',
				'locale'     => 'de_DE',
			)
		);

		wp_set_current_user( $admin_user_id );
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		$result = wp_send_user_request( $request_id );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirma la', $mailer->get_sent()->subject );

		restore_current_locale();
		delete_option( 'WPLANG' );
	}

}
