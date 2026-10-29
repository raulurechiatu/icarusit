<?php
namespace VamtamElementor\ElementorHooks;

// Elementor actions.
add_action( 'elementor/editor/before_enqueue_scripts',   __NAMESPACE__ . '\enqueue_editor_scripts' );
add_action( 'elementor/frontend/before_enqueue_scripts', __NAMESPACE__ . '\frontend_before_enqueue_scripts' );
add_action( 'elementor/init', __NAMESPACE__ . '\elementor_init' );

// Elementor filters
add_filter( 'elementor/controls/animations/additional_animations', __NAMESPACE__ . '\vamtam_elementor_additional_animations' );
add_filter( 'elementor/image_size/get_attachment_image_html', __NAMESPACE__ . '\vamtam_add_no_lazy_class_to_img_element', 11, 4 );

// Prevent stale individual widget/wc mod toggles from persisting disabled behind an active master toggle
// (they'd be invisible in the editor - hidden by the same master's condition - yet still honored by
// is_widget_mod_active()/is_wc_mod_active(), silently disabling the feature with no way to see why).
add_filter( 'update_post_metadata', __NAMESPACE__ . '\vamtam_clean_stale_theme_mods_before_save', 10, 5 );

// One-time migration so sites that already have the stale-toggle bug baked into their DB get fixed
// automatically after updating, without needing to manually resave the Kit.
add_action( 'init', __NAMESPACE__ . '\vamtam_migrate_existing_kit_theme_mods', 20 );

function elementor_init() {
	// Theme-dependant.
	set_experiments_default_state();

	set_performance_options();
}

/*
	Resets every Elementor experiment to its own default state, except the few we force-disable.
	Happens only once, based on the guard option - bump the option name whenever the forced list
	below changes, so existing installs pick up the new state.
*/
function set_experiments_default_state() {
	if ( get_option( 'vamtam-set-experiments-default-state-2', false ) ) {
		return;
	}

	$exps = \Elementor\Plugin::$instance->experiments;

	// Features to force-disable.
	$fdisable = [
		'additional_custom_breakpoints',
		'e_font_icon_svg',
	];

	foreach ( $exps->get_features() as $fname => $fdata ) {
		update_option(
			'elementor_experiment-' . $fname,
			in_array( $fname, $fdisable, true ) ? $exps::STATE_INACTIVE : $exps::STATE_DEFAULT
		);
	}

	update_option( 'vamtam-set-experiments-default-state-2', true );
}

function __return_inactive_experiment( $state ) {
	return \Elementor\Plugin::$instance->experiments::STATE_INACTIVE;
}

function set_performance_options() {
	$force_disable_elements_cache = apply_filters( 'vamtam_force_disable_elementor_elements_cache', true );

	if ( $force_disable_elements_cache ) {
		add_filter( 'pre_option_elementor_element_cache_ttl', function( $ttl ) {
			return 'disable';
		}, 999 );
	}

	$fdisable = [];
	$selectors = [];

	if ( $force_disable_elements_cache ) {
		$fdisable[] = 'e_element_cache';
		$selectors[] = '.elementor_experiment-e_element_cache';
	}

	// temporarily allow this only on internal sites
	if ( ! function_exists( 'vamtam_internal_adminbar' ) ) {
		$fdisable[] = 'e_optimized_markup';
		$selectors[] = '.elementor_experiment-e_optimized_markup';
	}

	// Features to force-disable.
	foreach ( $fdisable as $fname ) {
		update_option( 'elementor_experiment-' . $fname, \Elementor\Plugin::$instance->experiments::STATE_INACTIVE );
		add_filter( 'pre_option_elementor_experiment-' . $fname, __NAMESPACE__ . '\__return_inactive_experiment' );
	}

	if ( ! empty( $selectors ) ) {
		add_action( 'admin_enqueue_scripts', function() use ( $selectors ) {
			$screen = get_current_screen();
			if ( $screen && $screen->id === 'elementor_page_elementor-settings' ) {
				$css = implode( ', ', $selectors ) . ' {
						display: none;
					}
				';
				wp_add_inline_style( 'elementor-admin', $css );
			}
		} );
	}
}

function vamtam_elementor_additional_animations( $additional_anims ) {
	if ( vamtam_theme_supports( 'image--grow-with-scale-anims' ) && \Vamtam_Elementor_Utils::is_widget_mod_active( 'image', true ) ) {
		if ( ! isset( $additional_anims[ 'Vamtam' ] ) ) {
			$additional_anims[ 'Vamtam' ] = [];
		}
		$additional_anims[ 'Vamtam' ] = $additional_anims[ 'Vamtam' ] + [
			'imageGrowWithScaleLeft' => __( 'Image - Grow With Scale (Left)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleRight' => __( 'Image - Grow With Scale (Right)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleTop' => __( 'Image - Grow With Scale (Top)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleBottom' => __( 'Image - Grow With Scale (Bottom)', 'vamtam-elementor-integration' ),
		];
	}
	return $additional_anims;
}

function frontend_before_enqueue_scripts() {
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	// Enqueue JS for Elementor (frontend).
	wp_enqueue_script(
		'vamtam-elementor-frontend',
		VAMTAM_ELEMENTOR_INT_URL . 'assets/js/vamtam-elementor-frontend' . $suffix . '.js',
		[
			'elementor-frontend', // dependency
			'elementor-dialog', // dependency
		],
		\VamtamElementorIntregration::PLUGIN_VERSION,
		true //in footer
	);
}

function enqueue_editor_scripts() {
	// Enqueue JS for Elementor editor.
	wp_enqueue_script( 'vamtam-elementor', VAMTAM_ELEMENTOR_INT_URL . 'assets/js/vamtam-elementor.js', [], \VamtamElementorIntregration::PLUGIN_VERSION, true );
}

/*
	Add the no-lazy class to the <img> element, if the root widget element has it.
	We need this cause Elementor adds the Custom CSS classes to the root widget element
	but caching plugins need it directly on the <img> element in order not to lazy-load it.
*/
function vamtam_add_no_lazy_class_to_img_element( $html, $settings, $image_size_key, $image_key ) {
	if ( ! isset( $settings[ '_css_classes' ] ) || empty( $settings[ '_css_classes' ] ) || strpos( $settings[ '_css_classes' ], 'no-lazy' ) === false ) {
		return $html;
	}

	$attrClass = strpos( $html, "class=" );
	if ( $attrClass ) {
		$html = preg_replace( '/class="(.*)"/', 'class="' . 'no-lazy' . ' \1"', $html );
	} else {
		$html = preg_replace( '/src="(.*)"/', 'class="' . 'no-lazy' . '" src="\1"', $html );
	}

	return $html;
}

/*
	When a master toggle ("enable all widget/wc mods") is truthy, the individual per-widget/per-wc
	toggles underneath it become irrelevant to is_widget_mod_active()/is_wc_mod_active() (they short-circuit
	on the master first) - but if one of them was previously saved as an explicit disabled value (e.g. from
	an earlier save while the master was off), it stays persisted and hidden, since the editor only shows
	these controls when the master is off, and displays their declared default ('yes') rather than the true
	stored value. That leaves a landmine: flip the master off later and the feature silently stays disabled
	with no visual indication why. Since these keys have no effect while their master is truthy, it's safe to
	strip them at that point, so there's never a stale disabled value left behind an enabled master.
*/
/*
	Shared cleanup: strips, in place, any individual widget/wc mod key whose master
	("enable all") toggle is truthy - those keys have no effect on
	is_widget_mod_active()/is_wc_mod_active() in that state, so removing them is safe.
	Returns whether anything was actually removed.
*/
function vamtam_strip_stale_theme_mods( array &$settings ) {
	$theme_prefix = 'vamtam_theme_';
	$changed = false;

	$mods_map = [
		'enable_all_widget_mods' => class_exists( '\VamtamElementorBridge' ) ? \VamtamElementorBridge::get_widget_mods_list() : [],
		'enable_all_wc_mods'     => ( class_exists( '\VamtamElementorBridge' ) && method_exists( '\VamtamElementorBridge', 'get_wc_mods_list' ) ) ? \VamtamElementorBridge::get_wc_mods_list() : [],
	];

	foreach ( $mods_map as $enable_all_key => $mods_list ) {
		if ( empty( $settings[ "{$theme_prefix}{$enable_all_key}" ] ) ) {
			continue;
		}
		foreach ( array_keys( $mods_list ) as $mod_name ) {
			$individual_key = "{$theme_prefix}{$mod_name}";
			if ( array_key_exists( $individual_key, $settings ) ) {
				unset( $settings[ $individual_key ] );
				$changed = true;
			}
		}
	}

	return $changed;
}

function vamtam_clean_stale_theme_mods_before_save( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
	if ( '_elementor_page_settings' !== $meta_key || ! is_array( $meta_value ) || 'elementor_library' !== get_post_type( $object_id ) ) {
		return $check;
	}

	if ( ! vamtam_strip_stale_theme_mods( $meta_value ) ) {
		return $check;
	}

	remove_filter( 'update_post_metadata', __NAMESPACE__ . '\vamtam_clean_stale_theme_mods_before_save', 10 );
	update_post_meta( $object_id, $meta_key, $meta_value );
	add_filter( 'update_post_metadata', __NAMESPACE__ . '\vamtam_clean_stale_theme_mods_before_save', 10, 5 );

	return true;
}

/*
	One-time migration for sites where the stale-toggle bug already happened before this fix
	existed - without this, those sites would stay broken until someone manually resaves the Kit.
	Guarded by an option so it only ever does work once per site.

	Written defensively because this runs unattended across many sites with no one watching:
	- Bypasses update_post_meta() and writes via raw SQL directly to every wp_postmeta row for
	  this post_id + meta_key. Sites have been observed in the wild with DUPLICATE rows for the
	  same post_id + meta_key, and update_post_meta()'s handling of that situation is what
	  corrupted a Kit's settings during development of this fix - direct, explicit per-row writes
	  avoid that path entirely.
	- Validates the existing raw value actually unserializes before touching it; if it's already
	  unreadable for unrelated reasons, this leaves it alone rather than guessing at a fix.
	- Serializes the cleaned array and round-trip-validates it BEFORE writing anywhere.
	- Backs up the original raw value to an option first, so a rollback value exists even without
	  direct DB access.
	- Throttles retries on failure (transient) instead of re-querying every single request.
*/
function vamtam_migrate_existing_kit_theme_mods() {
	$migration_option = 'vamtam_theme_mods_migration_1_done';
	if ( get_option( $migration_option ) ) {
		return;
	}

	if ( get_transient( 'vamtam_theme_mods_migration_1_retry_lock' ) ) {
		return;
	}
	set_transient( 'vamtam_theme_mods_migration_1_retry_lock', true, HOUR_IN_SECONDS );

	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return; // Elementor not ready/active yet - retry on a later request.
	}

	$kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
	if ( ! $kit_id ) {
		update_option( $migration_option, true, false );
		return;
	}

	global $wpdb;
	$meta_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$kit_id, '_elementor_page_settings'
	) );

	if ( empty( $meta_ids ) ) {
		update_option( $migration_option, true, false );
		return;
	}

	$raw = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $meta_ids[0]
	) );

	$settings = @unserialize( $raw );
	if ( ! is_array( $settings ) ) {
		return; // Already unreadable for unrelated reasons - don't touch it, don't mark done.
	}

	if ( ! vamtam_strip_stale_theme_mods( $settings ) ) {
		update_option( $migration_option, true, false );
		return;
	}

	$serialized = serialize( $settings );
	if ( @unserialize( $serialized ) === false ) {
		return; // Refuse to write anything that doesn't round-trip cleanly. Retry later.
	}

	update_option( 'vamtam_theme_mods_migration_1_backup', [
		'kit_id' => $kit_id,
		'raw'    => $raw,
	], false );

	foreach ( $meta_ids as $meta_id ) {
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d",
			$serialized, $meta_id
		) );
	}

	wp_cache_delete( $kit_id, 'post_meta' );
	update_option( $migration_option, true, false );
}

/*
	Elementor Editor 4 resolves dynamic tags when it generates a post's cached CSS file
	(uploads/elementor/css/local-<post_id>-frontend-<breakpoint>.css) and bakes the resolved value
	into it. For a Component that file is shared by every page the component is placed on, so
	whichever page happened to generate it wins - all the others get that page's Featured Image as
	their background. Same class of bug for a page's own file when the Featured Image changes
	without the document being resaved.
	https://github.com/veniko/insuracore/issues/25 - remove once Elementor resolves dynamic style
	values per request instead of per cached file.

	Workaround: re-render just the style variants that contain a dynamic value, in the context of
	the page actually being viewed, and print them after Elementor's <link>s. Same selectors, same
	specificity, later in the cascade - so the per-request value wins over the baked-in one.
*/
add_action( 'elementor/post/render', __NAMESPACE__ . '\vamtam_collect_dynamic_style_post' );
add_action( 'wp_head', __NAMESPACE__ . '\vamtam_print_dynamic_styles', 100 );

function vamtam_collect_dynamic_style_post( $post_id ) {
	$GLOBALS['vamtam_dynamic_style_posts'][] = $post_id;
}

function vamtam_print_dynamic_styles() {
	$post_ids = array_unique( $GLOBALS['vamtam_dynamic_style_posts'] ?? [] );

	if (
		empty( $post_ids ) ||
		! class_exists( '\Elementor\Modules\AtomicWidgets\Utils\Utils' ) ||
		// In the editor the styles are re-rendered live by JS on every change; a static block here
		// would override the user's edits until they reload.
		\Elementor\Plugin::$instance->preview->is_editor_or_preview()
	) {
		return;
	}

	$styles = [];

	foreach ( $post_ids as $post_id ) {
		\Elementor\Modules\AtomicWidgets\Utils\Utils::traverse_post_elements(
			(string) $post_id,
			function( $element_data ) use ( &$styles ) {
				foreach ( $element_data['styles'] ?? [] as $style ) {
					$variants = array_values( array_filter(
						$style['variants'] ?? [],
						__NAMESPACE__ . '\vamtam_has_dynamic_value'
					) );

					if ( $variants ) {
						$styles[] = array_merge( $style, [ 'variants' => $variants ] );
					}
				}
			}
		);
	}

	if ( empty( $styles ) ) {
		return;
	}

	$css = \Elementor\Modules\AtomicWidgets\Styles\Styles_Renderer::make(
		\Elementor\Plugin::$instance->breakpoints->get_breakpoints_config()
	)->render(
		// Same license gate Elementor applies before writing the cached file, so a variant's
		// custom CSS doesn't come back through this path when the license strips it.
		\Elementor\Modules\AtomicWidgets\Styles\Atomic_Widget_Styles::get_license_based_filtered_styles( $styles )
	);

	if ( $css ) {
		// CSS is never parsed for tags, so the closing tag is the only breakout to guard against.
		echo '<style id="vamtam-dynamic-styles">' . str_ireplace( '</style', '', $css ) . '</style>';
	}
}

/*
	True if $value contains an Elementor dynamic-tag prop ( { "$$type": "dynamic", ... } ) at any
	depth - style props nest it several levels down, e.g. background > background-overlay >
	background-image-overlay > image > src.
*/
function vamtam_has_dynamic_value( $value ) {
	if ( ! is_array( $value ) ) {
		return false;
	}

	if ( isset( $value['$$type'] ) && 'dynamic' === $value['$$type'] ) {
		return true;
	}

	foreach ( $value as $item ) {
		if ( vamtam_has_dynamic_value( $item ) ) {
			return true;
		}
	}

	return false;
}
