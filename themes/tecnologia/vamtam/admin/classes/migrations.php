<?php

/**
 * Migrations
 *
 * @package vamtam/scuola
 */
class VamtamMigrations {

	public function __construct() {
		self::migrate_vamtam_token_flag();
		self::migrate_vamtam_key_source();
	}

	/**
	 * Customers on older theme versions had no dedicated VAMTAM field, so a VAMTAM-* update key was
	 * entered into the ThemeForest or Envato Elements field. Once on this version, normalize such a
	 * key to the 'vamtam' source and clear the Elements token flag, so check-theme sends it as a
	 * purchase_key and automatic updates work. Idempotent; only writes when something is off.
	 */
	private static function migrate_vamtam_key_source() {
		$code = trim( (string) VamtamFramework::get_purchase_code() );

		if ( '' === $code || ! preg_match( '/^VAMTAM-[A-Z0-9]{5}(?:-[A-Z0-9]{5}){5}$/i', $code ) ) {
			return;
		}

		if ( 'vamtam' !== get_option( '_vamtam_license_source' ) ) {
			update_option( '_vamtam_license_source', 'tecnologia' );
		}

		$token_key = VamtamFramework::get_token_option_key();
		if ( get_option( $token_key ) ) {
			delete_option( $token_key );
		}
	}

	private static function migrate_vamtam_token_flag() {
		global $wpdb;

		$migration_flag = 'vamtam_token_migration_completed';
		$last_attempt   = 'vamtam_token_migration_last_attempt';
		$old_token_key  = '_vamtam_license_token';

		if ( get_option( $migration_flag ) ) {
			return;
		}

		$current_time      = time();
		$last_attempt_time = get_option( $last_attempt, 0 );

		if ( $current_time - $last_attempt_time <  2 * HOUR_IN_SECONDS ) {
			return;
		}

		update_option( $last_attempt, $current_time );

		// Fetch all potential token keys (opts) from the database.
		$potential_tokens = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE 'envato_purchase_code_%'"
		);

		if ( empty( $potential_tokens ) ) {
			// Nothing to migrate - completed.
			delete_option( $last_attempt );
			delete_option( $old_token_key );
			update_option( $migration_flag, true );
			return;
		}

		// Extract the actual token values (unique & non-empty).
		$token_values = array_filter( array_unique( array_map( 'get_option', $potential_tokens ) ) );

		$response = wp_remote_post(
			'https://updates.vamtam.com/0/envato/check-tokens',
			array(
				'body' => array(
					'tokens' => $token_values,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Token validation request failed: ' . $response->get_error_message() );
			return;
		}

		$valid_tokens = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $valid_tokens ) ) {
			error_log( 'Invalid response from token validation endpoint' );
			return;
		}

		// Migrate valid tokens.
		$success = true;

		if ( ! empty( $valid_tokens ) ) {
			foreach ( $potential_tokens as $index => $option_name ) {
				$token = $token_values[ $index ];
				if ( in_array( $token, $valid_tokens, true ) ) {
					$theme_id        = str_replace( 'envato_purchase_code_', '', $option_name );
					$new_option_name = $old_token_key . '_' . $theme_id;
					if ( ! update_option( $new_option_name, '1' ) ) {
						$success = false;
						break;
					}
				}
			}
		}

		if ( $success ) {
			// Tokens migrated - completed.
			delete_option( $last_attempt );
			delete_option( $old_token_key );
			update_option( $migration_flag, true );
		}
	}
}
