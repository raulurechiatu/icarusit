<?php

// Bumped from _4 when the changelog support landed. The first plugin to load wins the class
// definition for every other plugin, so a version bump here is what stops an older, still
// un-updated plugin from defining the class that a freshly updated one then has to use.
if ( class_exists( 'Vamtam_Updates_5' ) ) {
	return;
}

class Vamtam_Updates_5 {
	private $slug;
	private $main_file;
	private $full_path;

	private $api_url;

	public function __construct( $file ) {
		$this->slug      = basename( dirname( $file ) );
		$this->main_file = trailingslashit( $this->slug ) . basename( $file );
		$this->full_path = $file;

		$this->api_url = 'https://updates.vamtam.com/0/envato/check';

		// delete_site_transient( 'update_plugins' );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ), 999 );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 999, 3 );
		add_filter( 'plugin_action_links_' . $this->main_file, array( $this, 'remove_update_action' ), 999);
		add_action( 'after_plugin_row', array( $this, 'update_handler' ), 999, 3);
		add_filter( 'plugin_row_meta', array( $this, 'vamtam_plugin_row_meta' ), 10, 4);
		add_filter( 'admin_url', array( $this, 'plugin_admin_url_on_updates_page' ), 10, 4 );
		add_filter( 'upgrader_pre_download', array( $this, 'plugin_upgrade_no_pkg' ), 10, 3 );
	}

	public function plugin_upgrade_no_pkg( $reply, $package, $upgrader ) {
		if ( isset( $upgrader->skin->plugin_info ) && false !== $upgrader->skin->plugin_info ) {
			$plugin_name = $upgrader->skin->plugin_info[ 'Name' ];
			if ( $plugin_name === get_plugin_data( $this->full_path )[ 'Name' ] && empty( $package ) ) {
				return new WP_Error( 'no_package', sprintf(
					/* translators: %1$s-%2$s: registration page link, %3$s-%4$s: purchase link. */
					__( 'You need a valid Purchase Code to use the Automatic Updates feature. Register your Purchase Code %1$shere%2$s if you already have one, or %3$sget one here%4$s.', 'wpv' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ) . '">',
					'</a>',
					'<a href="' . esc_url( 'https://vamtam.com/promo-envato-elements/?utm_source=theme&utm_medium=update-notice&utm_campaign=update-placeholder' ) . '" target="_blank">',
					'</a>'
				) );
			}
		}

		return $reply;
	}

	// core always passes all four, but the filter is public and cheap to defend
	public function plugin_admin_url_on_updates_page( $url, $path = '', $blog_id = null, $scheme = 'admin' ) {
		global $pagenow;

		// the modal has a real changelog now, so leave its URL alone
		if ( $this->has_changelog() ) {
			return $url;
		}

		if ( $pagenow === 'update-core.php' ) {
			if  ( strpos( $url, 'plugin-install.php?tab=plugin-information' ) !== false && strpos( $url, $this->slug ) !== false ) {
				$url = 'https://vamtam.com/changelog';
			}
		}
		return $url;
	}

	public function vamtam_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( $plugin_file === $this->main_file ) {
			foreach ( $plugin_meta as $key => $meta ) {
				if ( strpos( $meta, 'View details' ) !== false ) {
					// keep it, pointed straight at the changelog section
					if ( $this->has_changelog() ) {
						$plugin_meta[ $key ] = sprintf(
							'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
							esc_url( $this->details_url() ),
							/* translators: %s: Plugin name. */
							esc_attr( sprintf( __( 'Changelog for %s', 'wpv' ), $plugin_data['Name'] ) ),
							esc_html__( 'View changelog', 'wpv' )
						);
					} else {
						unset( $plugin_meta[ $key ] );
					}
				} else if ( strpos( $meta, 'By <a' ) !== false ) {
					$plugin_meta[ $key ] = str_replace( 'By <a', 'By <a target="_blank"', $meta );
				}
			}
		}

		return $plugin_meta;
	}

	public function remove_update_action( $links ) {
		unset( $links['update'] ); // tgm filters this
		unset( $links['upgrade'] );
		return $links;
	}

	public function update_handler( $plugin_file, $plugin_data, $status ) {
		if ( $plugin_file === $this->main_file ) {
			remove_all_actions( "after_plugin_row_{$plugin_file}" );
			if ( defined( 'VAMTAM_ENVATO_THEME_ID' ) ) {
				add_action( "after_plugin_row_{$plugin_file}", array( $this, 'version_update_notice'), 10, 2);
			}
		}
	}

	public function version_update_notice( $plugin_file, $plugin_data ){
		$plugin_file   = $this->main_file;
		$valid_key     = Version_Checker::is_valid_purchase_code();
		$is_token      = get_option( VamtamFramework::get_token_option_key() );
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$new_version   = $plugin_data[ 'new_version' ] ?? null;
		$name          = $plugin_data[ 'Name' ];

		if ( ! $new_version ) {
			return;
		}

		$plugin_active = in_array( $plugin_file, get_option( 'active_plugins', [] ) );
		$active_class  = $plugin_active ? ' active' : '';

		?>
		<tr class="plugin-update-tr<?php echo esc_attr( $active_class ); ?>" id="<?php echo esc_attr( $this->slug . '-update' ); ?>" data-slug="<?php echo esc_attr( $this->slug ); ?>" data-plugin="<?php echo esc_attr( $this->main_file ); ?>">
            <td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="plugin-update colspanchange">
                <div class="update-message notice inline notice-warning notice-alt">
				<p>
					<?php echo __( 'There is a new version of ' . $name . ' available ' . '(<strong>' . $new_version . '</strong>).', 'wpv'); ?>
					<?php if ( $this->has_changelog() ) : ?>
						<a href="<?php echo esc_url( $this->details_url() ); ?>" class="thickbox open-plugin-details-modal">
							<?php echo esc_html__( 'View changelog', 'wpv' ); ?>
						</a>
					<?php else : ?>
						<a href="<?php echo esc_url( 'https://vamtam.com/changelog/' ); ?> " target="_blank">
							<?php echo esc_html__( ' View changelog', 'wpv' ); ?>
						</a>
					<?php endif; ?>
				</p>
				<?php
					if ( ! $valid_key || $is_token ) {
						?>
							<hr class="vamtam-update-warning__separator" />
							<div class="vamtam-update-warning">
								<div class="vamtam-update-warning__icon">
									<span class="dashicons dashicons-warning"></span>
								</div>
								<div>
									<div class="vamtam-update-warning__title">
										<?php
											if ( ! $valid_key ) {
												echo esc_html__( 'Heads up, no valid license found!', 'wpv' );
											} else {
												echo esc_html__( 'Heads up, you\'re missing out on automatic updates!', 'wpv' );
											}
										?>
									</div>
									<div class="vamtam-update-warning__message">
										<?php
											if ( ! $valid_key ) {
												echo '<div>';
												echo esc_html__('Please activate your license to get automatic plugin updates!', 'wpv');
												echo '</div>';
												?>
													<a class="button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ); ?>">
														<?php echo esc_html__( 'Register Now', 'wpv' ); ?>
													</a>
												<?php
											} else {
												echo wp_kses_post( sprintf(
													/* translators: %1$s-%2$s: registration page link, %3$s-%4$s: purchase link. */
													__( 'You need a valid Purchase Code to use the Automatic Updates feature. Register your Purchase Code %1$shere%2$s if you already have one, or %3$sget one here%4$s.', 'wpv' ),
													'<a href="' . esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ) . '">',
													'</a>',
													'<a href="' . esc_url( 'https://vamtam.com/promo-envato-elements/?utm_source=theme&utm_medium=update-notice&utm_campaign=update-placeholder' ) . '" target="_blank">',
													'</a>'
												) );
											}
											?>
									</div>
								</div>
							</div>
						<?php
					} else {
						?>
						<a class="button-primary vamtam-update-btn" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $plugin_file, 'upgrade-plugin_' . $plugin_file ) ); ?>">
							<?php echo esc_html__( 'Update Now', 'wpv' ); ?>
						</a>
						<?php
					}
				?>
                </div>
			</td>
        </tr>
		<?php
	}

	public function check( $updates ) {
		$response = $this->api_request();

		if ( false === $response ) {
			return $updates;
		}

		if ( ! isset( $updates->response ) ) {
			$updates->response = array();
		}

		$updates->response = array_merge( $updates->response, $response );

		// Small trick to ensure the updates get shown in the network admin
		if( is_multisite() && ! is_main_site() ) {
			global $current_site;

			switch_to_blog( $current_site->blog_id );
			set_site_transient( 'update_plugins', $updates );
			restore_current_blog();
		}

		return $updates;
	}

	/**
	 * Only our own plugins have a changelog on the update server. This class is bundled with
	 * third party plugins too (revslider, woocommerce-bookings), and those keep the old
	 * behaviour of hiding the "View details" link rather than opening an empty modal.
	 */
	private function has_changelog() {
		return 0 === strpos( $this->slug, 'vamtam-' );
	}

	/**
	 * The plugin details modal, restricted to the changelog section.
	 */
	private function details_url() {
		return self_admin_url(
			'plugin-install.php?tab=plugin-information&section=changelog&TB_iframe=true&width=772&height=575&plugin=' . $this->slug
		);
	}

	/**
	 * Changelogs are published per slug and major version -- see make-plugin-changelog. The
	 * installed major is the right one to ask for: updates never cross a major, a new major
	 * ships with a new theme.
	 */
	private function changelog() {
		$version   = get_plugin_data( $this->full_path, false, false )['Version'];
		$transient = 'vamtam_changelog_' . md5( $this->slug . $version );

		$cached = get_transient( $transient );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://updates.vamtam.com/changelog/plugin/' . $this->slug . '.' . intval( $version ),
			array( 'timeout' => 10 )
		);

		$html = '';

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$html = $this->render_changelog( wp_remote_retrieve_body( $response ) );
		}

		// cache misses too, so a changelog the server does not have costs one request a day
		set_transient( $transient, $html, DAY_IN_SECONDS );

		return $html;
	}

	/**
	 * "= 1.2.3 (2026-07-31) =" starts a release, every other non-empty line is a changed file.
	 */
	private function render_changelog( $body ) {
		$html  = '';
		$files = array();

		foreach ( explode( "\n", $body ) as $line ) {
			$line = trim( $line );

			if ( preg_match( '/^=+\s*(.+?)\s*=+$/', $line, $matches ) ) {
				$html .= $this->render_files( $files );
				$files = array();

				$html .= '<h4>' . esc_html( $matches[1] ) . '</h4>';
			} elseif ( '' !== $line ) {
				$files[] = $line;
			}
		}

		return $html . $this->render_files( $files );
	}

	private function render_files( $files ) {
		if ( ! $files ) {
			return '';
		}

		return '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $files ) ) . '</li></ul>';
	}

	public function plugins_api( $data, $action = '', $args = null ) {
		if ( 'plugin_information' !== $action ) {
			return $data;
		}

		if ( ! isset( $args->slug ) || ( $args->slug !== $this->slug ) ) {
			return $data;
		}

		$data = new stdClass;

		if ( ! $this->has_changelog() ) {
			return $data;
		}

		$plugin_data = get_plugin_data( $this->full_path, false, false );

		$update = get_site_transient( 'update_plugins' );

		$data->name         = $plugin_data['Name'];
		$data->slug         = $this->slug;
		$data->version      = $update->response[ $this->main_file ]->new_version ?? $plugin_data['Version'];
		$data->author       = $plugin_data['Author'];
		$data->requires_php = $plugin_data['RequiresPHP'];
		$data->requires     = $plugin_data['RequiresWP'];
		$data->homepage     = 'https://vamtam.com';
		$data->sections     = array( 'changelog' => $this->changelog() );

		// not on wordpress.org, and the zip only comes from the licensed update endpoint
		$data->external      = true;
		$data->download_link = '';

		return $data;
	}

	private function api_request() {
		global $wp_version;

		$update_cache = get_site_transient( 'update_plugins' );

		$plugin_data = get_plugin_data( $this->full_path );

		$raw_response = wp_remote_post( $this->api_url, array(
			'body' => array(
				'slug' => $this->slug,
				'main_file' => $this->main_file,
				'version' => $plugin_data[ 'Version' ],
				'purchase_key' => apply_filters( 'wpv_purchase_code', '' ),
				'theme_name' => defined( 'VAMTAM_THEME_NAME' ) ? VAMTAM_THEME_NAME : '',
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
		) );

		if ( is_wp_error( $raw_response ) || 200 !== wp_remote_retrieve_response_code( $raw_response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

		if ( ! is_array( $response ) || ! isset( $response['plugins'] ) || ! is_array( $response['plugins'] ) ) {
			return false;
		}

		foreach ( $response['plugins'] as &$plugin ) {
			$plugin = (object) $plugin;
		}
		unset( $plugin );

		return $response['plugins'];
	}
}
