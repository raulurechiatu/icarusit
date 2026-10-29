<?php

class VamTamTokenRestoration {

	private $update_api_prefix      = 'https://updates.vamtam.com/0/envato/';
	private $token_restoration_init = 'vamtam_token_restore_init';
	private $token_restoration_done = 'vamtam_token_restore_completed';
	private $last_attempt           = 'vamtam_token_restore_last_attempt';
	private $public_key_opt         = 'vamtam_updates_public_key';
	private $restore_url;
	private $public_key_url;
	private $host;
	private $ext_id;
	private $theme;

	public function __construct() {
		if ( get_option( $this->token_restoration_done ) ) {
			return;
		}

		if ( get_option( $this->token_restoration_init ) ) {
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		}

		$this->restore_url     = $this->update_api_prefix . 'restore-token';
		$this->public_key_url  = $this->update_api_prefix . 'public-key';
		$this->host            = get_home_url();
		$this->ext_id          = md5( get_site_url() );
		$this->theme           = wp_get_theme()->get( 'Name' );

		if ( ! is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$validated = VamtamFramework::license() === Version_Checker::$VALID_LICENSE;
		$is_token  = get_option( VamtamFramework::get_token_option_key() );
		$no_token  = empty( VamtamFramework::get_purchase_code() );

		if ( $validated && $is_token && $no_token ) {
			$this->request_restoration();
		}
	}

	/**
	 * Register REST endpoint: /wp-json/vamtam/v1/restore-elements-token
	 */
	public function register_rest_routes() {
		register_rest_route(
			'vamtam/v1',
			'/restore-elements-token',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'receive_signed_token' ),
				'permission_callback' => '__return_true', // Allow external access
			)
		);
	}

	public function request_restoration() {
		$current_time      = time();
		$last_attempt_time = get_option( $this->last_attempt, 0 );

		// Throttle attempts to once every 5 minutes
		if ( $current_time - $last_attempt_time < 300 ) {
			return;
		}

		update_option( $this->token_restoration_init, true );

		$response = wp_remote_post(
			$this->restore_url,
			array(
				'body' => array(
					'host' => $this->host,
					'ext_id' => $this->ext_id,
					'theme' => $this->theme,
				),
				'user-agent' => VamtamFramework::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Failed to contact server - try again later.
			update_option( $this->last_attempt, $current_time );
		}

		$body        = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 200 && isset( $body['success'] ) && $body['success'] ) {
			// Success - the server will call back the REST endpoint
			update_option( $this->token_restoration_init, true );
		} else {
			// Failed - try again later.
			update_option( $this->last_attempt, $current_time );
		}
	}

	public function receive_signed_token( $request ) {
		try {
			$params = $request->get_json_params();

			if ( ! isset( $params['token'] ) || ! isset( $params['signature'] ) ) {
				update_option( $this->last_attempt, time() );
				return new WP_Error( 'missing_data', 'Missing data', array( 'status' => 400 ) );
			}

			$token     = $params['token'];
			$signature = $params['signature'];
			$host      = $params['host'] ?? '';
			$ext_id    = $params['ext_id'] ?? '';
			$theme     = $params['theme'] ?? '';

			// Verify signature
			if ( ! $this->verify_signature( $token, $signature ) ) {
				$this->refresh_public_key(); // Refresh public key and try again
				update_option( $this->last_attempt, time() );
				return new WP_Error( 'invalid_signature', 'Signature verification failed', array( 'status' => 401 ) );
			}

			// Verify host, ext_id, theme
			if ( $host !== $this->host ) {
				update_option( $this->last_attempt, time() );
				return new WP_Error( 'domain_mismatch', 'Domain does not match', array( 'status' => 403 ) );
			}

			if ( $ext_id !== $this->ext_id ) {
				update_option( $this->last_attempt, time() );
				return new WP_Error( 'ext_id_mismatch', 'Site ID does not match', array( 'status' => 403 ) );
			}

			if ( $theme !== $this->theme ) {
				update_option( $this->last_attempt, time() );
				return new WP_Error( 'theme_mismatch', 'Theme does not match', array( 'status' => 403 ) );
			}

			// Valid token
			update_option( VamtamFramework::get_purchase_code_option_key(), $token );
			update_option( $this->token_restoration_done, true );
			delete_option( $this->token_restoration_init );
			delete_option( $this->last_attempt );

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Token restored successfully',
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'server_error', 'Failed to process restoration', array( 'status' => 500 ) );
		}
	}

	private function verify_signature( $token, $signature_base64 ) {
		$public_key_pem = $this->get_public_key();

		if ( ! $public_key_pem ) {
			return false;
		}

		$public_key = openssl_pkey_get_public( $public_key_pem );

		if ( ! $public_key ) {
			return false;
		}

		$signature_binary = base64_decode( $signature_base64 );

		if ( false === $signature_binary ) {
			openssl_free_key( $public_key );
			return false;
		}

		$result = openssl_verify(
			$token,
			$signature_binary,
			$public_key,
			OPENSSL_ALGO_SHA512
		);

		openssl_free_key( $public_key );

		return 1 === $result;
	}

	/**
	 * Get public key from cache or fetch from server.
	 */
	private function get_public_key() {
		// Try to get cached public key first.
		$cached_key = get_option( $this->public_key_opt );

		if ( $cached_key ) {
			return $cached_key;
		}

		// Fetch from server.
		$response = wp_remote_get(
			$this->public_key_url,
			array(
				'timeout'    => 10,
				'user-agent' => VamtamFramework::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$public_key_pem = wp_remote_retrieve_body( $response );
		$status_code    = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code || empty( $public_key_pem ) ) {
			return false;
		}

		// Validate it's a valid PEM key before caching.
		$test_key = openssl_pkey_get_public( $public_key_pem );
		if ( ! $test_key ) {
			return false;
		}
		openssl_free_key( $test_key );

		// Cache the public key (it doesn't change often).
		update_option( $this->public_key_opt, $public_key_pem, false );

		return $public_key_pem;
	}


	public function refresh_public_key() {
		delete_option( $this->public_key_opt );
		return $this->get_public_key();
	}
}

new VamTamTokenRestoration();
