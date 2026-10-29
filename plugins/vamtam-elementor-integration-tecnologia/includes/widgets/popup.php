<?php
namespace VamtamElementor\Documents\Popup;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'popup' ) ) {
	return;
}

if ( vamtam_theme_supports( [ 'popup--absolute-position', 'popup--open-on-selector-hover' ] ) ) {

	function add_advanced_section_controls( $controls_manager, $widget ) {
		$widget->start_injection( [
			'of' => 'avoid_multiple_popups',
		] );
		$widget->add_control(
			'vamtam_abs_pos',
			[
				'label' => __( 'Retain Position', 'vamtam-elementor-integration' ),
				'description' => __( 'The popup will retain it\'s initial position regardless of page scroll.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => '',
				'frontend_available' => true,
			]
		);
		$widget->end_injection();

		$widget->start_injection( [
			'of' => 'open_selector',
		] );

		if ( vamtam_theme_supports( 'popup--open-on-selector-hover' ) ) {
			// Open on Selector Hover.
			$widget->add_control(
				'vamtam_open_on_selector_hover',
				[
					'label' => __( 'Open By Selector on Hover', 'vamtam-elementor-integration' ),
					'description' => __( 'The popup will be triggered when the selector is hovered. When using this feature make sure the <strong>selector</strong> used in the "Open By Selector" field is <strong>unique</strong> (use ID when possible) and <strong>not</strong> used to open other Popups. <em>*Does <strong>not</strong> support multiple selectors.</em>', 'vamtam-elementor-integration' ),
					'type' => $controls_manager::SWITCHER,
					'default' => '',
					'frontend_available' => true,
					'condition' => [
						'open_selector!' => '',
					],
				]
			);
			// Close on Hover Away.
			$widget->add_control(
				'vamtam_close_on_hover_lost',
				[
					'label' => __( 'Close on Hover Away', 'vamtam-elementor-integration' ),
					'description' => __( 'The popup will be closed automatically when it loses hover.<br/>If you experience issues with popup flickering when using this option, it\'s probably related to the "Overlay" option. If disabling the "Overlay" option is not possible, make sure the overlay doesn\'t overlap the popup\'s "Open Selector" element.', 'vamtam-elementor-integration' ),
					'type' => $controls_manager::SWITCHER,
					'default' => '',
					'frontend_available' => true,
					'condition' => [
						'open_selector!' => '',
						'vamtam_open_on_selector_hover!' => '',
					],
				]
			);
		}

		// Align with Selector.
		$widget->add_control(
			'vamtam_align_with_selector',
			[
				'label' => __( 'Align with Selector', 'vamtam-elementor-integration' ),
				'description' => __( 'The popup will be positioned relative to the selector (trigger) element. When using this feature make sure the <strong>selector</strong> used in the "Open By Selector" field is <strong>unique</strong> (use ID when possible). <em>*Does <strong>not</strong> support multiple selectors.</em>', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => '',
				'frontend_available' => true,
				'condition' => [
					'open_selector!' => '',
				],
			]
		);
		$widget->end_injection();
	}

	// Advanced - Advanced section
	function section_advanced_content_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_advanced_section_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/popup/section_advanced/before_section_end', __NAMESPACE__ . '\section_advanced_content_before_section_end', 10, 2 );
}
