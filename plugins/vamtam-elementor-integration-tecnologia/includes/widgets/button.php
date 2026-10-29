<?php
namespace VamtamElementor\Widgets\Button;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'button' ) ) {
	return;
}

if ( vamtam_theme_supports( 'button--underline-anim' ) ) {
	function add_button_style_section_controls( $controls_manager, $widget ) {
		$global_default = \Vamtam_Elementor_Utils::get_theme_global_widget_option( 'underline_anim_default' );
		// Use Underline Anim.
		$widget->add_control(
			'vamtam_underline_anim',
			[
				'label' => __( 'Use Underline Animation', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'underline-anim',
				'default' => empty( $global_default ) ? '' : 'underline-anim',
				'render_type' => 'template',
			]
		);
		// Width
		$widget->add_control(
			'vamtam_underline_width',
			[
				'label' => __( 'Width', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'range' => [
					'px' => [
						'max' => 10,
						'min' => 1,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button-text' => '--vamtam-underline-width: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'vamtam_underline_anim!' => '',
				]
			]
		);
		// Spacing
		$widget->add_control(
			'vamtam_underline_spacing',
			[
				'label' => __( 'Spacing', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'range' => [
					'px' => [
						'max' => 50,
						'min' => 0,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button-text' => '--vamtam-underline-spacing: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'vamtam_underline_anim!' => '',
				]
			]
		);
		// Underline Color.
		$widget->add_control(
			'vamtam_underline_bg_color',
			[
				'label' => __( 'Underline Color', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .elementor-button-text' => '--vamtam-underline-bg-color: {{VALUE}};',
				],
				'condition' => [
					'vamtam_underline_anim!' => '',
				]
			]
		);
	}
	// Style - Button section
	function section_style_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_button_style_section_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/button/section_style/before_section_end', __NAMESPACE__ . '\section_style_before_section_end', 10, 2 );

	// Vamtam_Widget_Button.
	function widgets_registered() {
		class Vamtam_Widget_Button extends \Elementor\Widget_Button {
			public $extra_depended_scripts = [
				'vamtam-button',
			];

			// Extend constructor.
			public function __construct($data = [], $args = null) {
				parent::__construct($data, $args);

				$this->register_assets();

				$this->add_extra_script_depends();
			}

			// Register the assets the widget depends on.
			public function register_assets() {
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				wp_register_script(
					'vamtam-button',
					VAMTAM_ELEMENTOR_INT_URL . 'assets/js/widgets/button/vamtam-button' . $suffix . '.js',
					[
						'elementor-frontend'
					],
					\VamtamElementorIntregration::PLUGIN_VERSION,
					true
				);
			}

			// Assets the widget depends upon.
			public function add_extra_script_depends() {
				// Scripts
				foreach ( $this->extra_depended_scripts as $script ) {
					$this->add_script_depends( $script );
				}
			}
		}

		// Replace current divider widget with our extended version.
		$widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
		$widgets_manager->unregister( 'button' );
		$widgets_manager->register( new Vamtam_Widget_Button );
	}
	add_action( \Vamtam_Elementor_Utils::get_widgets_registration_hook(), __NAMESPACE__ . '\widgets_registered', 100 );
}

if ( vamtam_theme_supports( 'button--icon-size-control' ) ) {
	function add_icon_size_control( $controls_manager, $widget ) {
		$widget->start_injection( [
			'of' => 'selected_icon',
		] );
		$widget->add_control(
			'vamtam_icon_size',
			[
				'label' => __( 'Size', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 300,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button-icon' => 'font-size: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'selected_icon[value]!' => '',
				],
			]
		);
		$widget->end_injection();
	}
	// Content - Button section
	function section_button_content_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_icon_size_control( $controls_manager, $widget );
	}
	add_action( 'elementor/element/button/section_button/before_section_end', __NAMESPACE__ . '\section_button_content_before_section_end', 10, 2 );
}

