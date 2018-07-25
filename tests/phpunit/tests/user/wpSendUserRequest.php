<?php
/**
 * Test cases for the `wp_send_user_request()` function.
 *
 * @since 4.9.7
 */

/**
 * Tests_User_WpSendUserRequest class.
 *
 * @since 4.9.7
 *
 * @group privacy
 * @group user
 * @covers wp_send_user_request
 */
class Tests_User_WpSendUserRequest extends WP_UnitTestCase {
	/**
	 * Reset the mocked phpmailer instance before each test method.
	 *
	 * @since 4.9.7
	 */
	function setUp() {
		parent::setUp();
		reset_phpmailer_instance();
	}

	/**
	 * Reset the mocked phpmailer instance after each test method.
	 *
	 * @since 4.9.7
	 */
	function tearDown() {
		reset_phpmailer_instance();
		parent::tearDown();
	}

	/**
	 * The function should send an user request export email when the requester is a registered user.
	 *
	 * @ticket 43985
	 */
	public function test_function_should_send_user_request_export_email_when_requester_registered_user() {
		$email      = 'export.request.from.user@example.com';
		$user_id    = $this->factory->user->create(
			array(
				'user_email' => $email,
				'role'       => 'administrator',
			)
		);
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		$email_sent = wp_send_user_request( $request_id );
		$mailer     = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $email_sent );
		$this->assertSame( $email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Export Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Export Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should send an user request erase email when the requester is a registered user.
	 *
	 * @ticket 43985
	 */
	public function test_function_should_send_user_request_erease_email_when_requester_registered_user() {
		$email      = 'erase.request.from.user@example.com';
		$user_id    = $this->factory->user->create(
			array(
				'user_email' => $email,
				'role'       => 'author',
			)
		);
		$request_id = wp_create_user_request( $email, 'remove_personal_data' );

		$email_sent = wp_send_user_request( $request_id );
		$mailer     = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $email_sent );
		$this->assertSame( $email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Erase Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Erase Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should send an user request erase email when the requester is an un-registered user.
	 *
	 * @ticket 43985
	 */
	public function test_function_should_send_user_request_erase_email_when_user_not_registered() {
		$email      = 'erase.request.from.unregistered.user@example.com';
		$request_id = wp_create_user_request( $email, 'remove_personal_data' );

		$email_sent = wp_send_user_request( $request_id );
		$mailer     = tests_retrieve_phpmailer_instance();

		$this->assertTrue( $email_sent );
		$this->assertSame( $email, $mailer->get_recipient( 'to' )->address );
		$this->assertContains( 'Confirm Action: Erase Personal Data', $mailer->get_sent()->subject );
		$this->assertContains( 'action=confirmaction&request_id=', $mailer->get_sent()->body );
		$this->assertContains( 'Erase Personal Data', $mailer->get_sent()->body );
	}

	/**
	 * The function should respect the user locale settings when the requester is a registered user.
	 *
	 * @ticket 43985
	 */
	public function test_function_should_send_user_request_email_in_user_language() {
		$email      = 'export.request.from.user@example.com';
		$user_id    = $this->factory->user->create(
			array(
				'user_email' => $email,
				'locale'     => 'es_ES',
				'role'       => 'author',
			)
		);
		$request_id = wp_create_user_request( $email, 'export_personal_data' );

		$email_sent = wp_send_user_request( $request_id );
		$mailer     = tests_retrieve_phpmailer_instance();

		$this->assertContains( 'Confirma la', $mailer->get_sent()->subject );
	}

	/**
	 * The function should error when the request ID is invalid.
	 *
	 * @ticket 43985
	 */
	public function test_function_should_error_when_invalid_request_id() {
		$request_id = null; // Invalid request ID.

		$email_sent = wp_send_user_request( $request_id );

		$this->assertWPError( $email_sent );
		$this->assertSame( 'Invalid request.', $email_sent->get_error_message() );
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

		add_filter( 'user_request_action_email_subject', array( $this, 'modify_email_subject' ), 10, 3 );
		$email_sent = wp_send_user_request( $request_id );
		$mailer     = tests_retrieve_phpmailer_instance();
		remove_filter( 'user_request_action_email_subject', array( $this, 'modify_email_subject' ), 10 );

		$this->assertTrue( $email_sent );
		$this->assertSame( 'Custom Subject Containing Email:' . $email, $mailer->get_sent()->subject );
	}

	/**
	 * Filter callback to modify the subject of the email sent when an account action is attempted.
	 *
	 * @since 4.9.7
	 *
	 * @param string $subject    The email subject.
	 * @param string $blogname   The name of the site.
	 * @param array  $email_data {
	 *     Data relating to the account action email.
	 *
	 *     @type WP_User_Request $request     User request object.
	 *     @type string          $email       The email address this is being sent to.
	 *     @type string          $description Description of the action being performed so the user knows what the email is for.
	 *     @type string          $confirm_url The link to click on to confirm the account action.
	 *     @type string          $sitename    The site name sending the mail.
	 *     @type string          $siteurl     The site URL sending the mail.
	 * }
	 */
	public function modify_email_subject( $subject, $blogname, $emaildata ) {
		$subject = 'Custom Subject Containing Email:' . $emaildata['email'];
		return $subject;
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
		$email_sent = wp_send_user_request( $request_id );
		$mailer     = tests_retrieve_phpmailer_instance();
		remove_filter( 'user_request_action_email_content', array( $this, 'modify_email_content' ), 10 );

		$this->assertTrue( $email_sent );
		$this->assertContains( 'Custom Content Containing Email:' . $email, $mailer->get_sent()->body );
	}

	/**
	 * Filter callback to modify the content of the email sent when an account action is attempted.
	 *
	 * @since 4.9.7
	 *
	 * @param string $email_text Text in the email.
	 * @param array  $email_data {
	 *     Data relating to the account action email.
	 *
	 *     @type WP_User_Request $request     User request object.
	 *     @type string          $email       The email address this is being sent to.
	 *     @type string          $description Description of the action being performed so the user knows what the email is for.
	 *     @type string          $confirm_url The link to click on to confirm the account action.
	 *     @type string          $sitename    The site name sending the mail.
	 *     @type string          $siteurl     The site URL sending the mail.
	 * }
	 * @return string $email_text Text in the email.
	 */
	public function modify_email_content( $email_text, $emaildata ) {
		$content = 'Custom Content Containing Email:' . $emaildata['email'];
		return $content;
	}

}
