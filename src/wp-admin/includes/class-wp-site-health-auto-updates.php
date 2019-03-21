<?php
/**
 * Class for testing automatic updates in the WordPress code.
 *
 * @package WordPress
 * @subpackage Site_Health
 * @since 5.2.0
 */

/**
 * Class Site_Health_Auto_Updates
 */
class Site_Health_Auto_Updates {
	/**
	 * Health_Check_Auto_Updates constructor.
	 *
	 * @uses Health_Check::init()
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initiate the plugin class.
	 *
	 * @return void
	 */
	public function init() {
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Run tests to determine if auto-updates can run.
	 *
	 * @uses get_class_methods()
	 * @uses substr()
	 * @uses call_user_func()
	 *
	 * @return array
	 */
	public function run_tests() {
		$tests = array();

		foreach ( get_class_methods( $this ) as $method ) {
			if ( 'test_' !== substr( $method, 0, 5 ) ) {
				continue;
			}

			$result = call_user_func( array( $this, $method ) );

			if ( false === $result || null === $result ) {
				continue;
			}

			$result = (object) $result;

			if ( empty( $result->severity ) ) {
				$result->severity = 'warning';
			}

			$tests[ $method ] = $result;
		}

		return $tests;
	}

	/**
	 * Test if file modifications are possible.
	 *
	 * @uses defined()
	 * @uses sprintf()
	 * @uses __()
	 *
	 * @return array
	 */
	function test_constant_FILE_MODS() {
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the constant used. */
					__( 'The %s constant is defined and enabled.' ),
					'<code>DISALLOW_FILE_MODS</code>'
				),
				'severity' => 'fail',
			);
		}
	}

	/**
	 * Check if automatic updates are disabled with a constant.
	 *
	 * @uses defined()
	 * @uses sprintf()
	 * @uses __()
	 *
	 * @return array
	 */
	function test_constant_AUTOMATIC_UPDATER_DISABLED() {
		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the constant used. */
					__( 'The %s constant is defined and enabled.' ),
					'<code>AUTOMATIC_UPDATER_DISABLED</code>'
				),
				'severity' => 'fail',
			);
		}
	}

	/**
	 * Check if automatic core updates are disabled with a constant.
	 *
	 * @uses defined()
	 * @uses sprintf()
	 * @uses __()
	 *
	 * @return array
	 */
	function test_constant_WP_AUTO_UPDATE_CORE() {
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) && false === WP_AUTO_UPDATE_CORE ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the constant used. */
					__( 'The %s constant is defined and enabled.' ),
					'<code>WP_AUTO_UPDATE_CORE</code>'
				),
				'severity' => 'fail',
			);
		}
	}

	/**
	 * Check if updates are intercepted by a filter.
	 *
	 * @uses has_filter()
	 * @uses sprintf()
	 * @uses __()
	 *
	 * @return array
	 */
	function test_wp_version_check_attached() {
		$cookies = wp_unslash( $_COOKIE );
		$timeout = 10;
		$headers = array(
			'Cache-Control' => 'no-cache',
		);

		// Include Basic auth in loopback requests.
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}

		$url = add_query_arg(
			array(
				'health-check-test-wp_version_check' => true,
			),
			admin_url()
		);

		$test = wp_remote_get( $url, compact( 'cookies', 'headers', 'timeout' ) );

		$response = wp_remote_retrieve_body( $test );

		if ( 'yes' !== $response ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the filter used. */
					__( 'A plugin has prevented updates by disabling %s.' ),
					'<code>wp_version_check()</code>'
				),
				'severity' => 'fail',
			);
		}
	}

	/**
	 * Check if automatic updates are disabled by a filter.
	 *
	 * @uses apply_filters()
	 * @uses sprintf()
	 * @uses __()
	 *
	 * @return array
	 */
	function test_filters_automatic_updater_disabled() {
		if ( apply_filters( 'automatic_updater_disabled', false ) ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the filter used. */
					__( 'The %s filter is enabled.' ),
					'<code>automatic_updater_disabled</code>'
				),
				'severity' => 'fail',
			);
		}
	}

	/**
	 * Check if automatic updates have tried to run, but failed, previously.
	 *
	 * @uses get_site_option()
	 * @uses __()
	 * @uses sprintf()
	 *
	 * @return array|bool
	 */
	function test_if_failed_update() {
		$failed = get_site_option( 'auto_core_update_failed' );

		if ( ! $failed ) {
			return false;
		}

		if ( ! empty( $failed['critical'] ) ) {
			$desc  = __( 'A previous automatic background update ended with a critical failure, so updates are now disabled.' );
			$desc .= ' ' . __( 'You would have received an email because of this.' );
			$desc .= ' ' . __( "When you've been able to update using the \"Update Now\" button on Dashboard > Updates, we'll clear this error for future update attempts." );
			$desc .= ' ' . sprintf(
				/* translators: %s: Code of error shown. */
				__( 'The error code was %s.' ),
				'<code>' . $failed['error_code'] . '</code>'
			);
			return array(
				'desc'     => $desc,
				'severity' => 'warning',
			);
		}

		$desc = __( 'A previous automatic background update could not occur.' );
		if ( empty( $failed['retry'] ) ) {
			$desc .= ' ' . __( 'You would have received an email because of this.' );
		}

		$desc .= ' ' . __( "We'll try again with the next release." );
		$desc .= ' ' . sprintf(
			/* translators: %s: Code of error shown. */
			__( 'The error code was %s.' ),
			'<code>' . $failed['error_code'] . '</code>'
		);
		return array(
			'desc'     => $desc,
			'severity' => 'warning',
		);
	}

	/**
	 * Check if WordPress is controlled by a VCS (Git, Subversion etc).
	 *
	 * @uses dirname()
	 * @uses array_unique()
	 * @uses is_dir()
	 * @uses rtrim()
	 * @uses apply_filters()
	 * @uses sprintf()
	 * @uses __()
	 *
	 * @param string $context The path to check from.
	 *
	 * @return array
	 */
	function _test_is_vcs_checkout( $context ) {
		$context_dirs = array( ABSPATH );
		$vcs_dirs     = array( '.svn', '.git', '.hg', '.bzr' );
		$check_dirs   = array();

		foreach ( $context_dirs as $context_dir ) {
			// Walk up from $context_dir to the root.
			do {
				$check_dirs[] = $context_dir;

				// Once we've hit '/' or 'C:\', we need to stop. dirname will keep returning the input here.
				if ( dirname( $context_dir ) == $context_dir ) {
					break;
				}

				// Continue one level at a time.
			} while ( $context_dir = dirname( $context_dir ) );
		}

		$check_dirs = array_unique( $check_dirs );

		// Search all directories we've found for evidence of version control.
		foreach ( $vcs_dirs as $vcs_dir ) {
			foreach ( $check_dirs as $check_dir ) {
				// phpcs:ignore
				if ( $checkout = @is_dir( rtrim( $check_dir, '\\/' ) . "/$vcs_dir" ) ) {
					break 2;
				}
			}
		}

		if ( $checkout && ! apply_filters( 'automatic_updates_is_vcs_checkout', true, $context ) ) {
			return array(
				'desc'     => sprintf(
					// translators: %1$s: Folder name. %2$s: Version control directory. %3$s: Filter name.
					__( 'The folder %1$s was detected as being under version control (%2$s), but the %3$s filter is allowing updates.' ),
					'<code>' . $check_dir . '</code>',
					"<code>$vcs_dir</code>",
					'<code>automatic_updates_is_vcs_checkout</code>'
				),
				'severity' => 'info',
			);
		}

		if ( $checkout ) {
			return array(
				'desc'     => sprintf(
					// translators: %1$s: Folder name. %2$s: Version control directory.
					__( 'The folder %1$s was detected as being under version control (%2$s).' ),
					'<code>' . $check_dir . '</code>',
					"<code>$vcs_dir</code>"
				),
				'severity' => 'fail',
			);
		}

		return array(
			'desc'     => __( 'No version control systems were detected.' ),
			'severity' => 'pass',
		);
	}

	/**
	 * Check if the absolute path is under Version Control.
	 *
	 * @uses Health_Check_Auto_Updates::_test_is_vcs_checkout()
	 *
	 * @return array
	 */
	function test_vcs_ABSPATH() {
		$result = $this->_test_is_vcs_checkout( ABSPATH );
		return $result;
	}

	/**
	 * Check if we can access files without providing credentials.
	 *
	 * @uses Automatic_Upgrader_Skin
	 * @uses Automatic_Upgrader_Skin::request_filesystem_credentials()
	 * @uses __()
	 *
	 * @return array
	 */
	function test_check_wp_filesystem_method() {
		$skin    = new Automatic_Upgrader_Skin;
		$success = $skin->request_filesystem_credentials( false, ABSPATH );

		if ( ! $success ) {
			$desc  = __( 'Your installation of WordPress prompts for FTP credentials to perform updates.' );
			$desc .= ' ' . __( '(Your site is performing updates over FTP due to file ownership. Talk to your hosting company.)' );

			return array(
				'desc'     => $desc,
				'severity' => 'fail',
			);
		}

		return array(
			'desc'     => __( "Your installation of WordPress doesn't require FTP credentials to perform updates." ),
			'severity' => 'pass',
		);
	}

	/**
	 * Check if core files are writeable by the web user/group.
	 *
	 * @global $wp_filesystem
	 *
	 * @uses Automatic_Upgrader_Skin
	 * @uses Automatic_Upgrader_Skin::request_filesystem_credentials()
	 * @uses WP_Filesystem
	 * @uses WP_Filesystem::method
	 * @uses get_core_checksums()
	 * @uses strpos()
	 * @uses sprintf()
	 * @uses __()
	 * @uses array_keys()
	 * @uses substr()
	 * @uses file_exists()
	 * @uses is_writable()
	 * @uses count()
	 * @uses array_slice()
	 * @uses implode()
	 *
	 * @return array|bool
	 */
	function test_all_files_writable() {
		global $wp_filesystem;
		include ABSPATH . WPINC . '/version.php'; // $wp_version; // x.y.z

		$skin    = new Automatic_Upgrader_Skin;
		$success = $skin->request_filesystem_credentials( false, ABSPATH );

		if ( ! $success ) {
			return false;
		}

		WP_Filesystem();

		if ( 'direct' != $wp_filesystem->method ) {
			return false;
		}

		$checksums = get_core_checksums( $wp_version, 'en_US' );
		$dev       = ( false !== strpos( $wp_version, '-' ) );
		// Get the last stable version's files and test against that
		if ( ! $checksums && $dev ) {
			$checksums = get_core_checksums( (float) $wp_version - 0.1, 'en_US' );
		}

		// There aren't always checksums for development releases, so just skip the test if we still can't find any
		if ( ! $checksums && $dev ) {
			return false;
		}

		if ( ! $checksums ) {
			$desc = sprintf(
				// translators: %s: WordPress version
				__( "Couldn't retrieve a list of the checksums for WordPress %s." ),
				$wp_version
			);
			$desc .= ' ' . __( 'This could mean that connections are failing to WordPress.org.' );
			return array(
				'desc'     => $desc,
				'severity' => 'warning',
			);
		}

		$unwritable_files = array();
		foreach ( array_keys( $checksums ) as $file ) {
			if ( 'wp-content' == substr( $file, 0, 10 ) ) {
				continue;
			}
			if ( ! file_exists( ABSPATH . '/' . $file ) ) {
				continue;
			}
			if ( ! is_writable( ABSPATH . '/' . $file ) ) {
				$unwritable_files[] = $file;
			}
		}

		if ( $unwritable_files ) {
			if ( count( $unwritable_files ) > 20 ) {
				$unwritable_files   = array_slice( $unwritable_files, 0, 20 );
				$unwritable_files[] = '...';
			}
			return array(
				'desc'     => __( 'Some files are not writable by WordPress:' ) . ' <ul><li>' . implode( '</li><li>', $unwritable_files ) . '</li></ul>',
				'severity' => 'fail',
			);
		} else {
			return array(
				'desc'     => __( 'All of your WordPress files are writable.' ),
				'severity' => 'pass',
			);
		}
	}

	/**
	 * Check if the install is using a development branch and can use nightly packages.
	 *
	 * @uses strpos()
	 * @uses defined()
	 * @uses sprintf()
	 * @uses __()
	 * @uses apply_filters()
	 *
	 * @return array|bool
	 */
	function test_accepts_dev_updates() {
		include ABSPATH . WPINC . '/version.php'; // $wp_version; // x.y.z
		// Only for dev versions
		if ( false === strpos( $wp_version, '-' ) ) {
			return false;
		}

		if ( defined( 'WP_AUTO_UPDATE_CORE' ) && ( 'minor' === WP_AUTO_UPDATE_CORE || false === WP_AUTO_UPDATE_CORE ) ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the constant used. */
					__( 'WordPress development updates are blocked by the %s constant.' ),
					'<code>WP_AUTO_UPDATE_CORE</code>'
				),
				'severity' => 'fail',
			);
		}

		if ( ! apply_filters( 'allow_dev_auto_core_updates', $wp_version ) ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the filter used. */
					__( 'WordPress development updates are blocked by the %s filter.' ),
					'<code>allow_dev_auto_core_updates</code>'
				),
				'severity' => 'fail',
			);
		}
	}

	/**
	 * Check if the site supports automatic minor updates.
	 *
	 * @uses defined()
	 * @uses sprintf()
	 * @uses __()
	 * @uses apply_filters()
	 *
	 * @return array
	 */
	function test_accepts_minor_updates() {
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) && false === WP_AUTO_UPDATE_CORE ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the constant used. */
					__( 'WordPress security and maintenance releases are blocked by %s.' ),
					"<code>define( 'WP_AUTO_UPDATE_CORE', false );</code>"
				),
				'severity' => 'fail',
			);
		}

		if ( ! apply_filters( 'allow_minor_auto_core_updates', true ) ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: Name of the filter used. */
					__( 'WordPress security and maintenance releases are blocked by the %s filter.' ),
					'<code>allow_minor_auto_core_updates</code>'
				),
				'severity' => 'fail',
			);
		}
	}
}
