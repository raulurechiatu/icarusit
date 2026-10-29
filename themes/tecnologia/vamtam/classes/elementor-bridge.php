<?php

use ElementorPro\Modules\ThemeBuilder\Module as Theme_Builder_Module;

use ElementorPro\Modules\AssetsManager\AssetTypes\Icons\Custom_Icons as Elementor_Custom_Icons;
use ElementorPro\Modules\AssetsManager\AssetTypes\Icons_Manager as Elementor_Icons_Manager;
use Elementor\Core\Page_Assets\Data_Managers\Base as Page_Assets_Data_Manager;


include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

class VamtamElementorBridge {

	/** Refers to a single instance of this class. */
	private static $instance = null;

	/**
	 * Returns an instance of this class.
	 *
	 * @return  VamtamElementorBridge A single instance of this class.
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Constructor function
	 */
	private function __construct() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'hide_elementors_content_width_settings_option' ] );
		add_action( 'elementor/editor/after_enqueue_scripts' , [ __CLASS__, 'hide_elementors_content_width_settings_frontend' ], 99 );
		add_action( 'elementor/init', [ __CLASS__, 'migrate_theme_options_to_elementor_global_settings' ], 100 );
		if ( ! method_exists( '\VamtamElementorBridge', 'add_global_styles_to_elementor_global_settings' )	) {
			// Will be removed when all clients have a "elementor-global-defaults.php" file
			add_action( 'elementor/init', [ __CLASS__, 'add_sticky_header_bg_color_to_elementor_global_settings' ], 100 );
		}

		add_action( 'elementor/init', [ __CLASS__, 'double_primary_font_global_option_check' ], 100 );

		if ( is_admin() && current_user_can( 'edit_posts' ) && ! wp_doing_ajax() ) {
			add_action( 'elementor/init', [ __CLASS__, 'theme_icons_check_fix' ], 100 );
			add_action( 'elementor/init', [ __CLASS__, 'media_control_url_value_check' ], 100 );
		}

		add_action( 'elementor/theme/register_locations', [ __CLASS__, 'register_locations' ] );
		add_action( 'elementor/frontend/after_enqueue_styles', [ __CLASS__, 'enqueue_theme_icons' ] );

		add_action( 'elementor/init', [ __CLASS__, 'optim_features_widget_fixes' ], 100 );

		add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), [ __CLASS__, 'sync_custom_logo_to_elementor_kit' ], 10, 2 );
	}

	/**
	 * Keep the kit's "site_logo" in step with the "custom_logo" theme mod.
	 *
	 * The Customizer writes only the theme mod, while Elementor keeps its own copy in the
	 * kit and stamps it back over the theme mod on every Site Settings save
	 * (Settings_Site_Identity::on_save). Without this, changing the logo in the Customizer
	 * and then saving anything in Site Settings silently reverts the logo to the kit's
	 * stale value.
	 */
	public static function sync_custom_logo_to_elementor_kit( $old_value, $value ) {
		if ( ! self::is_elementor_active() || ! self::elementor_is_v3_or_greater() ) {
			return;
		}

		$logo_id = isset( $value['custom_logo'] ) ? (int) $value['custom_logo'] : 0;

		if ( $logo_id === ( isset( $old_value['custom_logo'] ) ? (int) $old_value['custom_logo'] : 0 ) ) {
			return;
		}

		$kit_id = (int) get_option( 'elementor_active_kit' );

		if ( ! $kit_id ) {
			return;
		}

		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

		// No stored value means Elementor still falls back to the theme mod - nothing to correct.
		if ( ! is_array( $settings ) || ! isset( $settings['site_logo'] ) ) {
			return;
		}

		// Already in step. Also what stops Elementor's own set_theme_mod() from looping back here.
		if ( (int) ( $settings['site_logo']['id'] ?? 0 ) === $logo_id ) {
			return;
		}

		$src = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : false;

		$settings['site_logo'] = array_merge( (array) $settings['site_logo'], [
			'id'  => $logo_id,
			'url' => $src ? $src[0] : '',
		] );

		update_post_meta( $kit_id, '_elementor_page_settings', $settings );
	}

	public static function elementor_is_v3_or_greater() {
		return defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.0', '>=' );
	}

	public static function elementor_is_v3_24_or_greater() {
		return defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.24', '>=' );
	}

	public static function elementor_is_v3_5_or_greater() {
		return defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5', '>=' );
	}

	public static function migrate_theme_options_to_elementor_global_settings() {
		if ( get_option( 'vamtam-migrated-theme-options', false ) ) {
			return;
		}

		if ( ! self::elementor_is_v3_or_greater() ) {
			return false;
		}

        $use_new_migrate_func = method_exists( '\VamtamElementorBridge', 'add_global_styles_to_elementor_global_settings' );

		if ( $use_new_migrate_func ) {
			self::add_global_styles_to_elementor_global_settings();
		} else {
			self::add_theme_accents_to_elementor_global_colors();
			self::add_theme_fonts_to_elementor_global_fonts();
		}

		self::move_global_layout_options_to_elementor_global_settings();

		$migrated = false;
		if ( $use_new_migrate_func ) {
			$migrated = get_option( 'vamtam-global-styles-imported', false ) && get_option( 'vamtam-global-layout-imported', false );
		} else {
			$migrated = get_option( 'vamtam-accents-imported', false ) && get_option( 'vamtam-fonts-imported', false ) && get_option( 'vamtam-global-layout-imported', false );
		}

		if ( $migrated ) {
			update_option( 'vamtam-migrated-theme-options', true );

			// Equivalent of running the "Regenerate Styles" procedure.
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	// Global option for the transparent sticky header's bg color (sticky state).
	public static function add_sticky_header_bg_color_to_elementor_global_settings() {
		if ( get_option( 'vamtam-added-sticky-header-global-option', false ) ) {
			return;
		}

		if ( ! self::elementor_is_v3_or_greater() ) {
			return false;
		}

		$active_kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		if ( ! $active_kit_id ) {
			// Active kit not found. nothing to do.
			return false;
		}

		$kit          = \Elementor\Plugin::$instance->documents->get( $active_kit_id );
		$kit_settings = $kit->get_data()['settings'];

		global $vamtam_defaults;

		if ( isset( $kit_settings['system_colors'] ) && array_search( 'vamtam_sticky_header_bg_color', array_column( $kit_settings['system_colors'], '_id' ) ) !== false ) {
			return; // Exists. Nothing to do.
		}

		// We add it on system_colors so they can't be deleted by the user.
		// This is also reflected on the UI (Global Colors).
		$kit->add_repeater_row( 'system_colors', [
			'_id' => 'vamtam_sticky_header_bg_color',
			'title' => __( 'Sticky Header Bg Color', 'tecnologia' ),
			'color' => strtoupper( $vamtam_defaults[ 'sticky-header-bg-color' ] ),
		] );

		update_option( 'vamtam-added-sticky-header-global-option', true );
	}

	// Checks and fixes an issue with a double primary_font global option (affected theme versions<=3.1).
	public static function double_primary_font_global_option_check() {
		if ( get_option( 'vamtam-dpf-opt-check', false ) ) {
			return;
		}

		if ( ! self::elementor_is_v3_or_greater() ) {
			return false;
		}

		$active_kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		if ( ! $active_kit_id ) {
			// Active kit not found. nothing to do.
			return false;
		}

		$pf_id        = 'vamtam_primary_font';
		$kit          = \Elementor\Plugin::$instance->documents->get( $active_kit_id );
		$kit_settings = $kit->get_data()['settings'];
		$pf_found     = false;

		if ( ! isset( $kit_settings[ 'system_typography' ] ) ) {
			return;
		}

		foreach ( $kit_settings[ 'system_typography' ] as $i => $opt_value) {
			if ( $opt_value['_id'] === $pf_id ) {
				if ( $pf_found ) {
					// There's already a primary-font global option.
					// Remove the spare one(s).
					self::remove_global_option( 'system_typography', $pf_id, $i );
				} else {
					$pf_found = true;
				}
			}
		}

		update_option( 'vamtam-dpf-opt-check', true );
	}

	// Addresses a value discrepancy for the url field of the media control for section/image/media-carousel/cta.
	public static function media_control_url_value_check() {
		if ( get_option('vamtam-medial-control-url-value-checked', false ) ) {
			return;
		}

		$done = false;

		$posts = get_posts( [
			'post_type'      => get_post_types(),
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation'  => 'AND',
				'existance' => [
					'key'     => '_elementor_data',
					'compare' => 'EXISTS',
				],
				'notempty' => [
					'key'     => '_elementor_data',
					'compare' => '!=',
					'value'   => '',
				],
				'contains_url_false' => [
					'key'     => '_elementor_data',
					'value'   => '"url":false',
					'compare' => 'LIKE',
				],
			],
			'orderby' => 'ID',
			'order' => 'ASC',
		] );

		function updateUrlFalseToEmpty( &$array, $current_widget_type = null ) {
			$affected_widgets = [ 'section', 'image', 'media-carousel', 'call-to-action' ];

			foreach ( $array as &$value ) {
				if ( is_array( $value ) ) {
					$elType = null;
					if ( isset( $value['elType'] ) ) {
						if ( $value['elType'] === 'widget' ) {
							$elType = $value['widgetType'];
						} else {
							$elType = $value['elType'];
						}
						$current_widget_type = $elType;
					}

					updateUrlFalseToEmpty( $value, $current_widget_type ); // recursion.
				} elseif ( in_array( $current_widget_type, $affected_widgets ) && isset( $array['url'] ) && $array['url'] === false ) {
					$array['url'] = '';
				}
			}

			return $array;
		}

		foreach ( $posts as $post ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );

			if ( ! $data ) {
				$meta = get_post_meta( $post->ID );

				if ( isset( $meta[ '_elementor_data' ] ) ) {
					$data = $meta[ '_elementor_data' ][0];
				} else {
					echo "missing _elementor_data for {$post->ID} {$post->post_type}\n";
					var_dump( $data );
					unset( $data );
				}
			}

			if ( isset( $data ) ) {
				$data = json_decode( $data, true );

				if ( is_array( $data ) ) {
					$data = updateUrlFalseToEmpty( $data );

					// usage of wp_slash()/wp_json_encode copied from Elementor\Core\Base\Document::save_elements()
					// do not change without checking what Elementor does
					$data = wp_slash( wp_json_encode( $data ) );

					update_post_meta( $post->ID, '_elementor_data', $data );
				}

				$done = true;
			}
		}

		if ( empty( $posts ) || $done ) {
			update_option( 'vamtam-medial-control-url-value-checked', true );
		}
	}

	public static function remove_global_option( $control_id, $option_id = null, $index = null ) {
		if ( ! self::elementor_is_v3_or_greater() ) {
			return false;
		}

		$active_kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		if ( ! $active_kit_id ) {
			// Active kit not found. nothing to do.
			return false;
		}

		$kit               = \Elementor\Plugin::$instance->documents->get( $active_kit_id );
		$meta_key          = \Elementor\Core\Settings\Page\Manager::META_KEY;
		$document_settings = $kit->get_meta( $meta_key );

		if ( ! $document_settings ) {
			$document_settings = [];
		}

		if ( ! isset( $document_settings[ $control_id ] ) ) {
			return; // Nothing to do.
		}

		if ( isset( $option_id ) ) {
			foreach ( $document_settings[ $control_id ] as $i => $option ) {
				if ( isset( $index ) ) {
					// Specified index.
					if ( $index === $i && $option[ '_id' ] === $option_id ) {
						unset( $document_settings[ $control_id ][ $i ] );
					}
				} else {
					if ( $option[ '_id' ] === $option_id ) {
						unset( $document_settings[ $control_id ][ $i ] );
					}
				}
			}

			// This is for reseting the indices after the unsets.
			$document_settings[ $control_id ] = array_values( $document_settings[ $control_id ] );
		} else {
			if ( isset( $document_settings[ $control_id ] ) ) {
				unset( $document_settings[ $control_id ] );
			}
		}

		$page_settings_manager = \Elementor\Core\Settings\Manager::get_settings_managers( 'page' );
		$page_settings_manager->save_settings( $document_settings, $kit->get_id() );

		return true;
	}

	protected static function move_global_layout_options_to_elementor_global_settings() {
		if ( get_option( 'vamtam-global-layout-imported', false ) ) {
			return;
		}

		if ( ! self::elementor_is_v3_or_greater() ) {
			return false;
		}

		$kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		$kit    = \Elementor\Plugin::$instance->documents->get( $kit_id );

		if ( ! $kit ) {
			return;
		}

		global $vamtam_defaults;
		$site_max_width                        = isset( $vamtam_defaults['site-max-width'] ) ? $vamtam_defaults['site-max-width'] : '';
		$meta_key                              = \Elementor\Core\Settings\Page\Manager::META_KEY;
		$current_settings                      = get_option( '_elementor_general_settings', [] );
		$current_settings[ 'viewport_md' ]     = VamtamElementorBridge::get_site_breakpoints( 'md' );
		$current_settings[ 'viewport_lg' ]     = VamtamElementorBridge::get_site_breakpoints( 'lg' );
		$current_settings[ 'container_width' ] = ! empty( $site_max_width ) ? $site_max_width : ( isset( $current_settings[ 'container_width' ] ) ? $current_settings[ 'container_width' ] : '' );
		$kit_settings                          = $kit->get_meta( $meta_key );

		if ( empty( $current_settings ) ) {
			return;
		}

		if ( ! $kit_settings ) {
			$kit_settings = [];
		}

		// Convert setting to Elementor slider format.
		if ( ! empty( $current_settings[ 'container_width' ] ) ) {
			$current_settings[ 'container_width' ] = [
				'unit' => 'px',
				'size' => strval( $current_settings[ 'container_width' ] ),
			];
		} else {
			unset( $current_settings[ 'container_width' ] );
		}

		$kit_settings = array_merge( $kit_settings, $current_settings );

		$page_settings_manager = \Elementor\Core\Settings\Manager::get_settings_managers( 'page' );
		$page_settings_manager->save_settings( $kit_settings, $kit_id );

		update_option( 'vamtam-global-layout-imported', true );
	}

	/* Replaces:
		-add_theme_accents_to_elementor_global_colors()
		-add_theme_fonts_to_elementor_global_fonts()
		-add_sticky_header_bg_color_to_elementor_global_settings()
	*/
	protected static function add_global_styles_to_elementor_global_settings() {
		if ( get_option( 'vamtam-global-styles-imported', false ) ) {
			return;
		}

		if ( ! self::elementor_is_v3_or_greater() ) {
			return false;
		}

		$active_kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		if ( ! $active_kit_id ) {
			// Active kit not found. nothing to do.
			return false;
		}

		// Theme Colors. (Adding from elementor-global-defaults.php)
		$theme_colors = self::options_exist( 'theme-accents' );
		if ( ! is_array( $theme_colors ) || empty( $theme_colors ) ) {
			return false;
		}

		// Theme Fonts. (Adding from elementor-global-defaults.php)
		$theme_fonts = self::options_exist( 'theme-fonts' );
		if ( ! is_array( $theme_fonts ) || empty( $theme_fonts ) ) {
			return false;
		}

		$kit              = \Elementor\Plugin::$instance->documents->get( $active_kit_id );
		$kit_settings     = $kit->get_meta( \Elementor\Core\Settings\Page\Manager::META_KEY );
		$new_kit_settings = [];

		if ( ! $kit_settings ) {
			$kit_settings = [];
		}

		// We add them on system_* so they can't be deleted by the user.
		// This is also reflected on the UI (Global Colors/Fonts).
		$new_kit_settings['system_colors']     = $theme_colors;
		$new_kit_settings['system_typography'] = $theme_fonts;

		$kit_settings = array_merge( $kit_settings, $new_kit_settings );

		$page_settings_manager = \Elementor\Core\Settings\Manager::get_settings_managers( 'page' );
		$page_settings_manager->save_settings( $kit_settings, $active_kit_id );

		update_option( 'vamtam-global-styles-imported', true );
	}

	// TODO: To be removed (make sure all clients have a "elementor-global-defaults.php" file before removing).
	protected static function add_theme_accents_to_elementor_global_colors() {
		if ( get_option( 'vamtam-accents-imported', false ) ) {
			return;
		}

		if ( ! self::elementor_is_v3_or_greater() ) {
			return false;
		}

		$opts = self::options_exist( 'theme-accents' );
		if ( ! $opts ) {
			return false;
		}

		$use_el_global_defaults = is_array( $opts ) && ! empty( $opts );
		$active_kit_id          = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		if ( ! $active_kit_id ) {
			// Active kit not found. nothing to do.
			return false;
		}

		// Remove default Elementor global colors.
		foreach ( [ 'primary', 'secondary', 'text', 'accent' ] as $id ) {
			self::remove_global_option( 'system_colors', $id );
		}

		$kit          = \Elementor\Plugin::$instance->documents->get( $active_kit_id );
		$kit_settings = $kit->get_data()['settings'];

		if ( $use_el_global_defaults ) {
			// Adding from elementor-global-defaults.php
			foreach ( $opts as $opt_index => $opt_value) {
				if ( isset( $kit_settings['system_colors'] ) && array_search( $opt_value['_id'], array_column( $kit_settings['system_colors'], '_id' ) ) !== false ) {
					continue; // Nothing to do.
				}

				// We add them on system_colors so they can't be deleted by the user.
				// This is also reflected on the UI (Global Colors).
				$kit->add_repeater_row( 'system_colors', $opt_value );
			}
		} else {
			// Adding from vamtam_get_option().
			$our_colors = vamtam_get_option( 'accent-color' );
			if ( ! is_array( $our_colors ) ) {
				$our_colors = [];
			}

			for ( $i = 1; $i <= 8; $i++ ) {
				if ( isset( $kit_settings['system_colors'] ) && array_search( "vamtam_accent_{$i}", array_column( $kit_settings['system_colors'], '_id' ) ) !== false ) {
					continue; // Nothing to do.
				}

				// We add them on system_colors so they can't be deleted by the user.
				// This is also reflected on the UI (Global Colors).
				$kit->add_repeater_row( 'system_colors', [
					'_id' => "vamtam_accent_{$i}",
					'title' => sprintf( __( 'Accent %s', 'tecnologia' ), $i ),
					'color' => isset( $our_colors[ $i ] ) ? strtoupper( $our_colors[ $i ] ) : '',
				] );
			}
		}

		update_option( 'vamtam-accents-imported', true );
	}

	// TODO: To be removed (make sure all clients have a "elementor-global-defaults.php" file before removing).
	protected static function add_theme_fonts_to_elementor_global_fonts() {
		if ( get_option( 'vamtam-fonts-imported', false ) ) {
			return;
		}

		if ( ! self::elementor_is_v3_or_greater() ) {
			return false;
		}

		$opts = self::options_exist( 'theme-fonts' );
		if ( ! $opts ) {
			return false;
		}

		$use_el_global_defaults = is_array( $opts ) && ! empty( $opts );
		$active_kit_id          = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		if ( ! $active_kit_id ) {
			// Active kit not found. nothing to do.
			return false;
		}

		// Remove default Elementor global fonts.
		foreach ( [ 'primary', 'secondary', 'text', 'accent' ] as $id ) {
			self::remove_global_option( 'system_typography', $id );
		}

		$kit          = \Elementor\Plugin::$instance->documents->get( $active_kit_id );
		$kit_settings = $kit->get_data()['settings'];

		if ( $use_el_global_defaults ) {
			// Adding from elementor-global-defaults.php
			foreach ( $opts as $opt_index => $opt_value) {
				if ( isset( $kit_settings['system_typography'] ) && array_search( $opt_value['_id'], array_column( $kit_settings['system_typography'], '_id' ) ) !== false ) {
					continue; // Nothing to do.
				}

				// We add them on system_typography so they can't be deleted by the user.
				$kit->add_repeater_row( 'system_typography', $opt_value );
			}
		} else {
			// Adding from vamtam_get_option().
			$our_fonts = [
				'primary-font' => __( 'Primary Font', 'tecnologia' ),
				'h1'           => __( 'H1', 'tecnologia' ),
				'h2'           => __( 'H2', 'tecnologia' ),
				'h3'           => __( 'H3', 'tecnologia' ),
				'h4'           => __( 'H4', 'tecnologia' ),
				'h5'           => __( 'H5', 'tecnologia' ),
				'h6'           => __( 'H6', 'tecnologia' ),
			];

			foreach ( $our_fonts as $font_prefix => $font_title ) {
				$el_font_prefix = str_replace( '-', '_', $font_prefix );
				if ( isset( $kit_settings['system_typography'] ) && array_search( "vamtam_{$el_font_prefix}", array_column( $kit_settings['system_typography'], '_id' ) ) !== false ) {
					continue; // Nothing to do.
				}

				$font = vamtam_get_option( $font_prefix );
				if ( ! is_array( $font ) ) {
					$font = [];
				}

				$typography_options = [
					'_id'                        => "vamtam_{$el_font_prefix}",
					'title'                      => $font_title,
					'typography_typography'      => 'custom',
					'typography_font_family'     => isset( $font['font-family'] ) ? $font['font-family'] : '',
					'typography_font_weight'     => isset( $font['font-weight'] ) ? $font['font-weight'] : '',
					'typography_font_style'      => isset( $font['font-style'] ) ? $font['font-style'] : '',
					'typography_text_transform'  => isset( $font['transform'] ) ? $font['transform'] : '',
					'typography_text_decoration' => isset( $font['decoration'] ) ? $font['decoration'] : '',
				];

				$responsive = [
					'font-size'      => 'font_size',
					'line-height'    => 'line_height',
					'letter-spacing' => 'letter_spacing',
				];

				$device = [
					'desktop' => '' ,
					'tablet'  => '_tablet',
					'phone'   => '_mobile',
				];

				$unit_defs = [
					'font-size'      => 'px',
					'line-height'    => 'em',
					'letter-spacing' => 'px',
				];

				foreach ( $responsive as $our_prefix => $elementor_prefix ) {
					foreach ( $device as $our_device_prefix => $elementor_device_prefix ) {
						$value =  ( isset( $font[ $our_prefix ] ) && isset( $font[ $our_prefix ][ $our_device_prefix ]  ) ) ? $font[ $our_prefix ][ $our_device_prefix ] : '';
						$unit  =  ( isset( $font[ $our_prefix ] ) && isset( $font[ $our_prefix ]['unit'][ $our_device_prefix ] ) ) ? $font[ $our_prefix ]['unit'][ $our_device_prefix ] : '';
						$typography_options[ "typography_{$elementor_prefix}{$elementor_device_prefix}" ] = [
							'size'  => $value,
							'unit'  => ! empty( $unit ) ? $unit : $unit_defs[ $our_prefix ],
							'sizes' => [], // Without this, db values are not properly reflected in the UI.
						];
					}
				}
				// We add them on system_typography so they can't be deleted by the user.
				$kit->add_repeater_row( 'system_typography', $typography_options );
				// The typography options dont have a color field. We add the font color to system_colors.
				$kit->add_repeater_row( 'system_colors', [
					'_id' => "vamtam_{$el_font_prefix}_color",
					'title' => sprintf( __( '%s', 'tecnologia' ), $font_title ),
					'color' => isset( $font['color'] ) ? strtoupper( $font['color'] ) : '',
				] );
			}
		}
		update_option( 'vamtam-fonts-imported', true );
	}

	/**
	 * Checks if accents/fonts default options exist.
	 *
	 * If elementor-global-defaults.php exists, returns it's values right away.
	 *
	 * @return boolean|array
	 */
	protected static function options_exist( $option ) {
		$options_exist       = true;
		$el_global_defs_path = VAMTAM_SAMPLES_DIR . 'elementor-global-defaults.php';
		$el_global_defs      = null;

		if ( file_exists( $el_global_defs_path ) ) {
			$el_global_defs = include $el_global_defs_path;
		}

		if ( $option === 'theme-accents' ) {
			if ( is_array( $el_global_defs ) ) {
				// Use elementor-global-defaults.php values.
				return $el_global_defs['system_colors'];
			} else {
				// Use default-options.php/customizer values (fallback).
				$our_colors = vamtam_get_option( 'accent-color' );

				for ( $i = 1; $i <= 8; $i++ ) {
					if ( ! isset( $our_colors[ $i ] ) ) {
						$options_exist = false;
						break;
					}
				}
			}
		} else if ( $option === 'theme-fonts' ) {
			if ( is_array( $el_global_defs ) ) {
				return $el_global_defs['system_typography'];
			} else {
				$our_fonts = [
					'primary-font',
					'h1',
					'h2',
					'h3',
					'h4',
					'h5',
					'h6',
				];

				foreach ( $our_fonts as $font_prefix ) {
					if ( vamtam_get_option( $font_prefix ) === null ) {
						$options_exist = false;
						break;
					}
				}
			}
		} else {
			$options_exist = false;
		}
		return $options_exist;
	}

	/**
	 * Hide elementors content width settings option by adding some CSS
	 *
	 * @param [type] $hook
	 * @return void
	 */
	public static function hide_elementors_content_width_settings_option( $hook ) {
		if ( self::elementor_is_v3_or_greater() ) {
			return;
		}

		if( $hook != 'toplevel_page_elementor') {
			return;
		}

		$css = 'tr.elementor_container_width { display: none; }';
		$css = wp_strip_all_tags( $css );
		wp_add_inline_style( 'forms', $css );
	}

	/**
	 * Hide elementors content width settings option
	 * from elementor frontend global settings
	 * by adding CSS.
	 *
	 */
	public static function hide_elementors_content_width_settings_frontend() {
		if ( self::elementor_is_v3_or_greater() ) {
			return;
		}
		?>
		<style>
			.elementor-control-elementor_container_width { display: none !important; }
		</style>
		<?php
	}

	/**
	 * Register theme locations
	 */
	public static function register_locations( $elementor_theme_manager ) {
		$elementor_theme_manager->register_all_core_location();

		$elementor_theme_manager->register_location(
			'page-title-location',
			[
				'label' => esc_html__( 'Page Title', 'tecnologia' ),
				'multiple' => true,
				'edit_in_content' => true,
			]
		);
	}

	/**
	 * Resolve the Elementor Single template that would render the given post and
	 * return the boxed px content width of the section wrapping its
	 * `theme-post-content` widget. Used by the Gutenberg editor so its canvas
	 * matches the real post width without hardcoding the value or a template ID.
	 *
	 * Elementor decides which Single template applies by running its conditions
	 * against the current WP query (is_singular(), get_queried_object_id(), term /
	 * author checks, ...). That query only exists on the front end, so here we
	 * briefly simulate a singular request for this post, let Elementor's OWN
	 * conditions engine pick the template, then restore the globals. No condition
	 * logic is reimplemented, so it stays correct as templates/conditions change.
	 *
	 * Deliberately fail-safe: returns null (→ caller falls back) on anything odd
	 * (no template, non-px width, unexpected data, any error) rather than guessing.
	 *
	 * Resolves against the post's SAVED terms at editor-load time, so with
	 * category-conditional Single templates (e.g. category X → 1200px, others →
	 * 1000px) the correct width shows on load and after save+reload. Changing the
	 * category in the sidebar does not move the canvas until the next reload; a live
	 * reaction would need an editor script re-querying this on term change, which we
	 * intentionally skipped.
	 *
	 * @param int $post_id
	 * @return int|null px width, or null if it can't be resolved safely
	 */
	public static function resolve_single_post_content_width( $post_id ) {
		if ( ! function_exists( 'elementor_theme_do_location' ) || ! class_exists( 'ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return null;
		}

		$the_post = get_post( $post_id );

		if ( ! $the_post ) {
			return null;
		}

		global $wp_query, $wp_the_query, $post;

		$orig_wp_query     = $wp_query;
		$orig_wp_the_query = $wp_the_query;
		$orig_post         = $post;

		$result = null;

		try {
			// Simulate a front-end singular request for this post so Elementor's
			// condition checks resolve exactly as they would on the front end.
			$sim = new \WP_Query( [
				'p'         => $post_id,
				'post_type' => $the_post->post_type,
			] );

			if ( $sim->is_singular() ) {
				$wp_query = $wp_the_query = $sim; // phpcs:ignore
				$sim->the_post();

				$conditions_manager = Theme_Builder_Module::instance()->get_conditions_manager();

				// Isolate our lookup from any admin-context result cached earlier /
				// leaking into later callers in the same request.
				$conditions_manager->clear_location_cache();
				$documents = $conditions_manager->get_documents_for_location( 'single' );
				$conditions_manager->clear_location_cache();

				if ( ! empty( $documents ) ) {
					$document = reset( $documents ); // Elementor ordered these by condition priority.

					if ( $document && method_exists( $document, 'get_elements_data' ) ) {
						$width = self::find_post_content_width( $document->get_elements_data() );

						if ( is_int( $width ) && $width > 0 ) {
							$result = $width;
						}
					}
				}
			}
		} catch ( \Throwable $e ) {
			$result = null;
		} finally {
			wp_reset_postdata();

			$wp_query     = $orig_wp_query; // phpcs:ignore
			$wp_the_query = $orig_wp_the_query; // phpcs:ignore
			$post         = $orig_post; // phpcs:ignore
		}

		return $result;
	}

	/**
	 * Walk Elementor element data for the `theme-post-content` widget and return the
	 * nearest ancestor section/container's explicit px content width. Returns null if
	 * the widget isn't found or no explicit px content width wraps it (i.e. give up).
	 *
	 * @param array $elements
	 * @return int|string|null int width, the '__found__' marker, or null
	 */
	private static function find_post_content_width( $elements ) {
		foreach ( (array) $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}

			if ( 'theme-post-content' === ( $el['widgetType'] ?? '' ) ) {
				return '__found__';
			}

			$below = self::find_post_content_width( $el['elements'] ?? [] );

			if ( null === $below ) {
				continue;
			}

			// Widget is somewhere below. Already resolved a closer ancestor width?
			if ( '__found__' !== $below ) {
				return $below;
			}

			// This is the closest ancestor so far; use its width if it has an explicit px one.
			$cw = $el['settings']['content_width'] ?? null;

			if ( is_array( $cw ) && 'px' === ( $cw['unit'] ?? '' ) && is_numeric( $cw['size'] ?? null ) && $cw['size'] > 0 ) {
				return (int) $cw['size'];
			}

			return '__found__';
		}

		return null;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $location
	 * @return boolean
	 */
	public static function is_location_template_exits( $location ) {
		if(! function_exists( 'elementor_theme_do_location' ) ) {
			return false;
		}
		$templates_asigned = Theme_Builder_Module::instance()->get_conditions_manager()->get_documents_for_location( $location );
		if( !empty( $templates_asigned ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if elementor pro is active
	 *
	 * @return boolean
	 */
	public static function is_elementor_pro_active() {

		/**
		 * This extra check because some call before plugin loaded not get the function
		 * named elementor_theme_do_location. Speatially in admin area.
		 * Eg: customizer settings array.
		 */
		if ( is_admin() ) {
			// PRO Elements defines ElementorPro\Plugin but not elementor_pro_load_plugin(),
			// so checking for that function alone reports Pro as inactive in wp-admin.
			return class_exists( 'ElementorPro\Plugin' );
		}


		return function_exists( 'elementor_theme_do_location' );
	}

	/**
	 * Check whether the page use elementor to build its content block or not.
	 *
	 * $check_body_class : There are posts that are being handled 100% by Elementor templates
	 * but don't report as "built_with_elementor" cause the actual post id is different than that
	 * of the template. Examples are single_product, products_archive etc..
	 *
	 * @return boolean
	 */
	public static function is_build_with_elementor( $check_body_class = false ) {
		if( ! self::is_elementor_active() ) {
			return false;
		}

		global $post;

		if ( empty( $post->ID ) || ! isset( $post->ID ) ) {
			return false;
		}

		$result = \Elementor\Plugin::$instance->documents->get( $post->ID )->is_built_with_elementor();

		if ( $check_body_class ) {
			$found = false;
			foreach ( get_body_class() as $class ) {
				if ( preg_match( '/elementor-page-\d+$/s', $class ) ) {
					$found = true;
					break;
				}
			}
			$result = $found;
		}

		return $result;
	}

	/**
	 * Check if elementor plugin is active or not
	 *
	 * @return boolean
	 */
	public static function is_elementor_active() {
		return class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Checks if a title widget is present for a post.
	 *
	 * @return boolean
	 */
	public static function is_title_present_for_post() {
		$found = false;

		// Check doc locations first.
		$found = self::is_title_present_in_doc_locations();

		if ( ! $found && self::is_build_with_elementor() ) {
			// Check the post itself.
			$found = self::is_title_present_in_post_content();
		}

		return $found;
	}

	/**
	 * Checks if a title widget is present in the post through Elementor Theme Builder.
	 *
	 * @return boolean
	 */
	public static function is_title_present_in_doc_locations() {
		$doc_locations = array (
			'header',
			'page-title-location',
			'archive',
			'single',
		);

		$title_widget_possible_names = array(
			'theme-post-title',
			'theme-page-title',
			'theme-archive-title',
			'woocommerce-product-title',
		);

		foreach ( $doc_locations as $location ) {
			$documents_by_conditions_for_loc = Theme_Builder_Module::instance()->get_conditions_manager()->get_documents_for_location( $location );

			foreach ( $documents_by_conditions_for_loc as $doc ) {
				$el_data = $doc->get_elements_data();

				$found_title_widget = self::find_element_by_type_recursive( $el_data, $title_widget_possible_names );

				if ( ! empty( $found_title_widget ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if a title widget is present in the post content.
	 *
	 * @return boolean
	 */
	public static function is_title_present_in_post_content() {
		global $post;

		if ( ! isset( $post->ID ) ) {
			return false;
		}

		$doc_data = \Elementor\Plugin::$instance->documents->get( $post->ID )->get_elements_data();

		$title_widget_possible_names = array(
			'theme-post-title',
			'theme-page-title',
		);

		$found_title_widget = self::find_element_by_type_recursive( $doc_data, $title_widget_possible_names );

		if ( ! empty( $found_title_widget ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Searches elements_data recursively for a widgetType. Returns the particular element or false.
	 *
	 * @return boolean||array
	 */
	public static function find_element_by_type_recursive( $elements, $widget_types = array() ) {
		foreach ( $elements as $element ) {
			if ( ! empty( $element['widgetType'] ) && in_array( $element['widgetType'], $widget_types ) ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) ) {
				$element = self::find_element_by_type_recursive( $element['elements'], $widget_types );

				if ( $element ) {
					return $element;
				}
			}
		}

		return false;
	}

	/**
	 * Translates Elementor Kit values
	 */
	public static function get_translated_kit() {
		if ( ! self::is_elementor_active() ) {
			return [];
		}

		$kits_manager = Elementor\Plugin::instance()->kits_manager;

		$kit = $kits_manager->get_active_kit()->get_data()['settings'];

		$opts = [];

		// Kit has priority. We fallback to body_color.
		if ( isset( $kit[ 'link_normal_color' ] ) ) {
			$opts['body-link-regular'] = $kit['link_normal_color'];
			$opts['body-link-visited'] = $kit['link_normal_color'];
		} else {
			// Body link fallback.
			if ( isset( $kit[ 'body_color' ] ) ) {
				if ( ! isset( $opts['body-link-regular'] ) ) {
					$opts['body-link-regular'] = $kit[ 'body_color' ];
				}
				if ( ! isset( $opts['body-link-visited'] ) ) {
					$opts['body-link-visited'] = $kit[ 'body_color' ];
				}
			}
		}

		// Kit only.
		if ( isset( $kit[ 'link_hover_color' ] ) ) {
			$opts['body-link-hover'] = $kit['link_hover_color'];
			$opts['body-link-active'] = $kit['link_hover_color'];
		}

		if ( isset( $kit[ 'body_background_color' ] ) ) {
			$opts['body-background-color'] = $kit['body_background_color'];
		}

		// Input border radius.
		if ( isset( $kit[ 'form_field_border_radius' ] ) ) {
			$br = $kit[ 'form_field_border_radius' ];
			if ( '' !== $br['top'] && '' !== $br['right'] && '' !== $br['bottom'] && '' !== $br['left'] ) {
				$opts['input-border-radius'] = $br['top'] . $br['unit'] . ' ' . $br['right'] . $br['unit'] . ' ' . $br['bottom'] . $br['unit'] . ' ' . $br['left'] . $br['unit'];
			}
		}

		if ( isset( $kit[ '__globals__' ][ 'form_field_border_color' ] ) ) {
			$opts['input-border-color'] = self::get_global_variable( $kit[ '__globals__' ][ 'form_field_border_color' ] ) ?? 'transparent';

			if ( is_array( $opts['input-border-color'] ) ) {
				$opts['input-border-color'] = $opts['input-border-color']['value'];
			}
		}

		// Button Opts.
		foreach ( [ 'button_text_color', 'button_hover_text_color', 'button_background_color', 'button_hover_background_color', 'button_border_radius', 'button_hover_border_radius' ] as $opt_name ) {
			if ( isset( $kit[ $opt_name ] ) ) {
				if ( in_array( $opt_name, [ 'button_border_radius', 'button_hover_border_radius' ] ) ) {
					$br = $kit[ $opt_name ];
					if ( '' !== $br['top'] && '' !== $br['right'] && '' !== $br['bottom'] && '' !== $br['left'] ) {
						$opts[ str_replace([ '_', 'button' ], [ '-', 'btn', 'bg' ], $opt_name) ] = $br['top'] . $br['unit'] . ' ' . $br['right'] . $br['unit'] . ' ' . $br['bottom'] . $br['unit'] . ' ' . $br['left'] . $br['unit'];
					}
				} else {
					$opts[ str_replace( [ '_', 'button', 'background' ], [ '-', 'btn', 'bg' ], $opt_name ) ] = $kit[ $opt_name ];
				}
			}
		}

		if ( self::elementor_is_v3_or_greater() && isset( $kit[ 'container_width' ] ) ) {
			$opts['site-max-width'] = $kit[ 'container_width' ][ 'size' ];
		}

		$custom_icons = self::get_active_icon_sets();

		if ( isset( $custom_icons['theme-icons'] ) ) {
			$wpfs = Elementor_Custom_Icons::get_wp_filesystem();

			$upload_dir = wp_upload_dir();

			$style_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $custom_icons['theme-icons']['url'] );

			$selection_path = preg_replace( '/style([^\.]*)\.css/', 'selection$1.json', $style_path );

			$icons = $wpfs->get_contents( $selection_path );

			if ( $icons ) {
				// if we have selection.json
				$icons = json_decode( $icons )->icons;

				foreach ( $icons as $icon ) {
					$opts[ "icon-{$icon->properties->name}" ] = '\\' . dechex( $icon->properties->code );
				}
			} else {
				// otherwise, parse style.css
				$styles = $wpfs->get_contents( $style_path );

				$styles = trim( substr( $styles, strpos( $styles, '.vamtam-theme-' ) ) );

				$styles = explode( '}', $styles );

				foreach ( $styles as $s ) {
					$s = trim( $s );

					if ( ! empty( $s ) ) {
						$name = substr( $s, 14, strpos( $s, ':before' ) - 14 );
						$code = trim( substr( $s, strpos( $s, '"' ) + 1 ), '";' );

						$opts[ "icon-{$name}" ] = $code;
					}
				}
			}
		}

		return $opts;
	}

	private static function get_active_icon_sets() {
		if ( ! class_exists( 'ElementorPro\Modules\AssetsManager\AssetTypes\Icons_Manager' ) ) {
			return [];
		}

		$icons = new \WP_Query( [
			'post_type' => Elementor_Icons_Manager::CPT,
			'posts_per_page' => -1,
		] );

		$custom_icon_sets = [];

		foreach ( $icons->posts as $icon_set ) {
			$set_config = json_decode( Elementor_Custom_Icons::get_icon_set_config( $icon_set->ID ), true );
			$set_config['custom_icon_post_id'] = $icon_set->ID;
			$set_config['label'] = $icon_set->post_title;
			$set_config['path'] = get_post_meta( $icon_set->ID, '_elementor_icon_set_path', true );

			if ( isset( $set_config['name'] ) ) {
				$custom_icon_sets[ $set_config['name'] ] = $set_config;
			}
		}

		return $custom_icon_sets;
	}

	/**
	 * Retrieves an Elementor Global Value.
	 *
	 * $global_key example: "globals/typography?id=vamtam_h1"
	 *
	 * @return array||null
	 */
	public static function get_global_variable( $global_key ) {
		$data = [];

		if ( self::elementor_is_v3_5_or_greater() ) {
			// Use v2 API.
			$data = \Elementor\Plugin::$instance->data_manager_v2->run( $global_key );
		} else {
			// Use v1 API.
			$data = \Elementor\Plugin::$instance->data_manager->run( $global_key );
		}

		if ( empty( $data[ 'value' ] ) ) {
			return null;
		}

		return $data;
	}

	public static function get_site_breakpoints( $device = false ) {
		$data           = [];
		$translate_keys = false;
		$device_keys    = [
			'md' => 'mobile',
			'lg' => 'tablet',
		];

		if ( self::is_elementor_active() ) {
			if ( self::elementor_is_v3_5_or_greater() ) {
				$data = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();
				$translate_keys = true;
			} else if ( self::elementor_is_v3_or_greater() ) {
				$data = \Elementor\Core\Responsive\Responsive::get_editable_breakpoints();
			} else {
				$active_kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
				if ( $active_kit_id ) {
					$kit        = \Elementor\Plugin::$instance->documents->get( $active_kit_id );
					$data['lg'] = $kit->get_settings( 'viewport_lg' ) ?: 1025;
					$data['md'] = $kit->get_settings( 'viewport_md' ) ?: 768;
				} else {
					$data['lg'] = ! empty( get_option( 'elementor_viewport_lg' ) ) ? (int) get_option( 'elementor_viewport_lg' ) : 1025;
					$data['md'] = ! empty( get_option( 'elementor_viewport_md' ) ) ? (int) get_option( 'elementor_viewport_md' ) : 768;
				}
			}
		} else {
			$data = [
				'lg' => 1025,
				'md' => 768,
			];
		}

		if ( ! empty( $device ) ) {
			if ( $translate_keys ) {
				$value = $data[ $device_keys[ $device ] ]->get_value();

				/*
					Elementor changed their md/lg defaults when transitioned from
					\Elementor\Core\Responsive\Responsive to \Elementor\Plugin::$instance->breakpoints

					Done this way so we can keep current sites as they are and also provide backwards compatibility.
					If we decide to drop BC we should update the calculations for min/max-width breakpoints across the theme.

					We could also do this with elementor_is_v3_5_or_greater() checks across the theme if this turns out problematic.
				*/
				if ( $value === 767 ) {
					$value = 768;
				} else if ( $value === 1024 ) {
					$value = 1025;
				}

				return $value;
			} else {
				return $data[ $device ];
			}
		} else {
			if ( $translate_keys ) {
				$data = [
					'lg' => $data[ $device_keys[ 'lg' ] ]->get_value(),
					'md' => $data[ $device_keys[ 'md' ] ]->get_value(),
				];
			}

			return $data;
		}
	}

	/**
	 * Returns the list of widgets that have configurable functionality
	 *
	 * @return array
	 */
	public static function get_widget_mods_list() {

		/* !! -- Important -- !!
			To find out which master toggles should be enabled for each theme consider the following:
				-unique widget types for features defined in the theme's feature list (in framework.php).
				-unique widget types for each use of "vamtam-has-theme-widget-styles" class (in css/src/elementor/widgets/ styles).
			Combinig those two, one should be able determine for which widget_types, master toggles will be needed.
		*/

		$widget_mods_list = [
			// The order is mirrored in the UI.
			'button'                                     => [ 'label' => __( 'Button', 'tecnologia' ) ],
			'form'                                       => [ 'label' => __( 'Form', 'tecnologia' ) ],
			'tabs'                                       => [ 'label' => __( 'Tabs', 'tecnologia' ) ],
			'icon-box'                                   => [ 'label' => __( 'Icon Box', 'tecnologia' ) ],
			'image-box'                                  => [ 'label' => __( 'Image Box', 'tecnologia' ) ],
			'nav-menu'                                   => [ 'label' => __( 'Nav Menu', 'tecnologia' ) ],
			'section'                                    => [ 'label' => __( 'Section', 'tecnologia' ) ],
			'toggle'                                     => [ 'label' => __( 'Toggle', 'tecnologia' ) ],
			'text-editor'                          		 => [ 'label' => __( 'Text Editor', 'tecnologia' ) ],
			'testimonial-carousel'						 => [ 'label' => __( 'Testimonial Carousel', 'tecnologia' ) ],
			'search-form'                                => [ 'label' => __( 'Search Form', 'tecnologia' ) ],
			'archive-posts'                              => [ 'label' => __( 'Archive Posts', 'tecnologia' ) ],
			'posts'                                      => [ 'label' => __( 'Posts', 'tecnologia' ) ],
			'post-navigation'                            => [ 'label' => __( 'Post Navigation', 'tecnologia' ) ],
			'post-comments'                              => [ 'label' => __( 'Post Comments', 'tecnologia' ) ],
			'popup' /* Not a widget but same logic */    => [ 'label' => __( 'Popup', 'tecnologia' ) ],
		];

		return $widget_mods_list;
	}

	/**
	 * Returns the list of configurable WC functionality.
	 *
	 * @return array
	 */
	public static function get_wc_mods_list() {

		$wc_mods_list = [
			// The order is mirrored in the UI.
		];

		return $wc_mods_list;
	}

	/*
		Enqueues the theme-icons library (if exists) everywhere in the site.

		The icons should have already been registered by Elementor's Icons_Manager (check Icons_Manager::register_styles()).
	*/
	public static function enqueue_theme_icons() {
		$config = \Elementor\Icons_Manager::get_icon_manager_tabs_config();
		foreach ( $config as $type => $icon_type ) {
			if ( $icon_type[ 'name' ] === 'theme-icons' ) {
				wp_enqueue_style( 'elementor-icons-' . $icon_type['name'] );
			}
		}
	}

	/*
		Fixes an issue where only the theme-icons post & the json represantion were imported
		without the actual theme-icon files.
	*/
	public static function theme_icons_check_fix() {
		if ( get_option( 'vamtam-theme-icons-imported', false ) || get_option( 'vamtam-theme-icons-importing', false ) ) {
			return false;
		}

		$content_imported = ! ! get_option( 'vamtam_last_import_map', false );

		if ( $content_imported ) {
			// Existing site that has already run the import procedure.
			self::import_theme_icons();
		}
	}

	public static function remove_theme_icons() {
		if ( ! class_exists( 'ElementorPro\Modules\AssetsManager\AssetTypes\Icons_Manager' ) ) {
			return false;
		}

		$icons = new \WP_Query( [
			'post_type' => Elementor_Icons_Manager::CPT,
			'post_status' => array( 'publish', 'trash', 'draft', 'auto-draft' ),
			'posts_per_page' => -1,
		] );

		$custom_icon_sets = [];

		$wp_upload_dir = wp_upload_dir();
		$icons_path = $wp_upload_dir['basedir'] . '/elementor/custom-icons/';

		foreach ( $icons->posts as $icon_set ) {
			if ( str_contains( $icon_set->post_title, 'theme-icons' ) ) {
				$post_id = $icon_set->ID;

				// Delete post
				wp_delete_post( $post_id, true );

				// Make sure there's no leftover /theme-icons* dir.
				$icons_dir = $icons_path . $icon_set->post_title;
				if ( is_dir( $icons_dir ) ) {
					Elementor_Custom_Icons::get_wp_filesystem()->rmdir( $icons_dir, true );
				}
			}
		}

		// Make sure there's no leftover /theme-icons dir.
		// Can happen after reset plugins, where the icons post has been removed but the folder in /uploads is still there.
		$icons_dir = $icons_path . 'theme-icons';
		if ( is_dir( $icons_dir ) ) {
			Elementor_Custom_Icons::get_wp_filesystem()->rmdir( $icons_dir, true );
		}

		return true;
	}

	public static function create_theme_icons_post() {
		if ( ! class_exists( 'ElementorPro\Modules\AssetsManager\AssetTypes\Icons_Manager' ) ) {
			return false;
		}

		$args = array(
			'title' => 'theme-icons',
			'post_status' => array( 'publish', 'trash', 'draft', 'auto-draft' ),
			'post_type' => Elementor_Icons_Manager::CPT,
		);

		$post = get_posts( $args );

		if ( $post ) {
			// Post shouldn't exist.
			return false;
		}

		$args[ 'post_status' ] = 'publish';
		$args[ 'post_title' ] = 'theme-icons';
		unset( $args[ 'title' ] );

		$post_id = wp_insert_post( $args );

		return $post_id;
	}

	public static function extract_zip( $file, $to ) {
		$valid_field_types = [
			'css',
			'eot',
			'html',
			'json',
			'otf',
			'svg',
			'ttf',
			'txt',
			'woff',
			'woff2',
		];

		$zip = new \ZipArchive();

		$zip->open( $file );

		$valid_entries = [];

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$zipped_file_name = $zip->getNameIndex( $i );
			$dirname = pathinfo( $zipped_file_name, PATHINFO_DIRNAME );

			// Skip the OS X-created __MACOSX directory.
			if ( '__MACOSX/' === substr( $dirname, 0, 9 ) ) {
				continue;
			}

			$zipped_extension = pathinfo( $zipped_file_name, PATHINFO_EXTENSION );

			if ( in_array( $zipped_extension, $valid_field_types, true ) ) {
				$valid_entries[] = $zipped_file_name;
			}
		}

		$unzip_result = false;

		if ( ! empty( $valid_entries ) ) {
			$unzip_result = $zip->extractTo( $to, $valid_entries );
		}

		if ( ! $unzip_result ) {
			$unzip_result = new \WP_Error( 'error', esc_html__( 'Could not unzip or empty archive.', 'tecnologia' ) );
		}

		@unlink( $file );

		return $unzip_result; // TRUE | WP_Error instance.
	}

	public static function theme_icons_zip_exists() {
		$theme_icons_zip = VAMTAM_SAMPLES_DIR . 'theme-icons.zip';
		return file_exists( $theme_icons_zip );
	}

	private static function import_theme_icons_set( $theme_icons_post_id ) {
		if ( ! current_user_can( Elementor_Icons_Manager::CAPABILITY ) ) {
			return false; // Access denied.
		}

		if ( ! self::theme_icons_zip_exists() ) {
			return false; // theme-icons.zip not found.
		}

		// Make a copy of the zip cause the one imported will get removed during the procedure.
		$theme_icons_zip      = VAMTAM_SAMPLES_DIR . 'theme-icons.zip';
		$theme_icons_zip_copy = VAMTAM_SAMPLES_DIR . 'theme-icons-copy.zip';
		$wp_filesystem        = Elementor_Custom_Icons::get_wp_filesystem();

		if ( ! $wp_filesystem->copy( $theme_icons_zip, $theme_icons_zip_copy ) ) {
			return false; // Can't copy theme-icons.zip (probably a permissions issue).
		}

		$theme_icons_zip = $theme_icons_zip_copy;

		$extract_to = VAMTAM_SAMPLES_DIR . 'theme-icons/';
		$unzipped   = self::extract_zip( $theme_icons_zip, $extract_to );

		if ( is_wp_error( $unzipped ) ) {
			return false;
		}

		$supported_icon_sets = Elementor_Custom_Icons::get_supported_icon_sets();

		foreach ( $supported_icon_sets as $key => $handler ) {
			$icon_set_handler = new $handler( $extract_to );

			if ( ! $icon_set_handler ) {
				continue;
			}
			if ( ! $icon_set_handler->is_valid() ) {
				continue;
			}
			$icon_set_handler->handle_new_icon_set();
			$icon_set_handler->move_files( $theme_icons_post_id );
			$config = $icon_set_handler->build_config();

			// Notify about duplicate prefix
			if ( Elementor_Custom_Icons::icon_set_prefix_exists( $config['prefix'] ) ) {
				$config['duplicate_prefix'] = true;
			}

			// Update the db key that holds the config.
			update_post_meta( $theme_icons_post_id, Elementor_Custom_Icons::META_KEY, json_encode( $config ) );

			// Force refresh of list in Options Table
			Elementor_Custom_Icons::clear_icon_list_option();

			return true;
		}

		return false; // Unsupported_zip_format.
	}

	/*
		Runs only once.
			- on existing sites (i.e with imported content), gets called from elementor-bridge.php -> theme_icons_check_fix()
			- on new sites, gets called during the import process from vamtam-importers.php -> elementor_import()
	*/
	public static function import_theme_icons() {
		if ( ! self::theme_icons_zip_exists() ) {
			return false; // No .zip, nothing to do.
		}

		wp_cache_delete_multiple( [
			'vamtam-theme-icons-imported',
			'vamtam-theme-icons-importing'
		], 'options' );

		if ( get_option( 'vamtam-theme-icons-imported', false ) || get_option( 'vamtam-theme-icons-importing', false ) ) {
			return false;
		}

		// Check also directly from db as get_option can return cached vals that make the function run multiple times.
		global $wpdb;
		$importing = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = 'vamtam-theme-icons-importing' LIMIT 1" ) );
		$imported  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = 'vamtam-theme-icons-imported' LIMIT 1" ) );

		if ( $importing || $imported ) {
			return false;
		}

		update_option( 'vamtam-theme-icons-importing', true, false ); //don't autoload

		// 1. Clear existing icons.
		if ( ! self::remove_theme_icons() ) {
			delete_option( 'vamtam-theme-icons-importing' );
			return false;
		}

		// 2. Create a new "elementor-icons" post.
		$theme_icons_post_id = self::create_theme_icons_post();

		if ( ! $theme_icons_post_id ) {
			delete_option( 'vamtam-theme-icons-importing' );
			return false;
		}

		// 3. Import theme-icons set from local .zip
		if ( ! self::import_theme_icons_set( $theme_icons_post_id ) ) {
			delete_option( 'vamtam-theme-icons-importing' );
			return false;
		}

		update_option( 'vamtam-theme-icons-imported', true, false ); //don't autoload
		delete_option( 'vamtam-theme-icons-importing' );

		return true;
	}

	/*
		If "e_font_icon_svg" feature is active, force-enqueue eicons when widgets that need them are present on the page.
	*/
	public static function optim_features_widget_fixes() {
		$is_inline_font_icons_active = \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' );

		if ( $is_inline_font_icons_active ) {
			add_filter( 'elementor/frontend/widget/after_render', [ __CLASS__, 'get_registered_runtime_widgets' ] );
			add_action( 'wp_footer', [ __CLASS__, 'print_registered_widgets_assets_data' ] );
		}
	}

	public static $registered_runtime_widgets = [];

	public static function get_registered_runtime_widgets( $widget ) {
		$widget_name = $widget->get_name();

		if ( ! in_array( $widget_name, self::$registered_runtime_widgets ) ) {
			self::$registered_runtime_widgets = $widget::$registered_runtime_widgets;
		}
	}

	public static function get_registered_widgets_assets_data() {
		$widget_assets = get_option( Page_Assets_Data_Manager::ASSETS_DATA_KEY, [] );

		if ( empty( $widget_assets ) ) {
			return [];
		}

		$widget_assets = $widget_assets['css']['widgets'] ?? [];

		$widgets_data = [];
		foreach ($widget_assets as $widget_name => $widget_asset_data) {
			if ( in_array( $widget_name, self::$registered_runtime_widgets ) ) {
				$widgets_data[ $widget_name ] = $widget_asset_data;
			}
		}

		return $widgets_data;
	}

	public static function print_registered_widgets_assets_data() {
		$widgets_data             = self::get_registered_widgets_assets_data();
		$registered_widgets       = array_values( self::$registered_runtime_widgets );

		if ( in_array( 'forms', $registered_widgets ) || isset( $widgets_data['forms'] ) ) {
			$inline_font_icons_active = \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' );

			if ( $inline_font_icons_active ) {
				// Forms need eicons library cause they contain icons in CSS pseudo-element format.
				wp_enqueue_style( 'elementor-icons' );
			}
		}
	}

	/**
	 * Check if current page has an immediate scroll-blocking popup.
	 *
	 * Checks for Elementor popups that load immediately on page load (0 delay)
	 * with scroll prevention enabled.
	 *
	 * @return int|false Popup ID if found, false otherwise.
	 */
	public static function get_immediate_scroll_blocking_popup() {
		// Frontend, visitors only.
		if ( is_admin() || is_user_logged_in() ) {
			return false;
		}

		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
			return false;
		}

		// Get conditions manager instance.
		$conditions_manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();

		// Get popup documents for current location.
		$popup_documents = $conditions_manager->get_documents_for_location( 'popup' );

		foreach ( $popup_documents as $document ) {
			$popup_id = $document->get_id();
			$settings = $document->get_settings();

			// Get popup display settings from post meta.
			$popup_display_settings = get_post_meta( $popup_id, '_elementor_popup_display_settings', true );

			if ( empty( $popup_display_settings ) ) {
				continue;
			}

			// Check prevent_scroll setting.
			$prevent_scroll = isset( $settings['prevent_scroll'] ) ? $settings['prevent_scroll'] : '';

			// Get triggers array.
			$triggers = $popup_display_settings['triggers'];

			if ( isset( $triggers ) && is_array( $triggers ) ) {
				$has_page_load   = isset( $triggers['page_load'] ) && 'yes' === $triggers['page_load'];
				$page_load_delay = isset( $triggers['page_load_delay'] ) ? intval( $triggers['page_load_delay'] ) : 0;

				// Check if popup qualifies.
				if ( 'yes' === $prevent_scroll && $has_page_load && 0 === $page_load_delay ) {
					return $popup_id;
				}
			}
		}

		return false;
	}
}
