<?php

namespace VamtamElementor\SiteSettings\ThemeSettings;

use \Elementor\Controls_Manager;
use \Elementor\Core\Kits\Documents\Tabs;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Settings_Theme_Settings extends Tabs\Tab_Base {

	public function get_id() {
		return 'settings-theme-settings';
	}

	public function get_title() {
		return __( 'Theme Settings', 'vamtam-elementor-integration' );
	}

	public function get_group() {
		return 'settings';
	}

	public function get_icon() {
		return 'eicon-settings';
	}

	protected function register_tab_controls() {
		// Theme Settings
		$this->add_theme_settings_section( $this );
		// Global Widget Options
		$this->add_global_widget_options_section( $this );
	}

    protected function add_theme_settings_section( $kit ) {
        $kit->start_controls_section(
            'section_' . $this->get_id(),
			[
				'label' => $this->get_title(),
				'tab' => $this->get_id(),
			]
        );

        $theme_prefix  = 'vamtam_theme_';
        $kit->add_control(
            "{$theme_prefix}save_notice",
            [
                'type' => Controls_manager::RAW_HTML,
                'raw' => __( '<h3><strong>After saving your changes, a page refresh will be needed for the changes to take effect.</strong/></h3>', 'vamtam-elementor-integration' ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
            ]
        );

        $this->add_widgets_controls( $kit );

        $this->add_general_controls( $kit );

        $kit->end_controls_section();
    }

    protected function add_global_widget_options_section( $kit ) {
		$kit->start_controls_section(
            'section_global_widget_options',
			[
				'label' => __( 'Global Widget Options', 'vamtam-elementor-integration' ),
				'tab' => $this->get_id(),
			]
        );

		$theme_prefix  = 'vamtam_theme_';
        $kit->add_control(
            "{$theme_prefix}global_widget_options_save_notice",
            [
                'type' => Controls_manager::RAW_HTML,
                'raw' => __( '<h3><strong>After saving your changes, a page refresh will be needed for the changes to take effect.</strong/></h3>', 'vamtam-elementor-integration' ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
            ]
        );

		// Underline Animation.
        $this->add_global_underline_anim_controls( $kit );

        $kit->end_controls_section();
    }

	protected function add_global_underline_anim_controls( $kit ) {
        $theme_prefix  = 'vamtam_theme_';

		$kit->add_control(
            "{$theme_prefix}underline_anim_heading",
            [
                'type' => Controls_manager::HEADING,
                'label' => __( 'Underline Animation', 'vamtam-elementor-integration' ),
                'separator' => 'before',
            ]
        );

		$kit->add_control(
            "{$theme_prefix}underline_anim_notice",
            [
                'type' => Controls_manager::RAW_HTML,
                'raw' => __( '<h3>Used in Button / Posts / Archive Posts / Nav Menu / Form</h3>', 'vamtam-elementor-integration' ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

		// Default Underline Animation State.
		$kit->add_control(
			"{$theme_prefix}underline_anim_default",
			[
				'label' => __( 'Underline Animation Default State', 'vamtam-elementor-integration' ),
				'description' => __( 'Controls the <strong>default</strong> on/off state of the underline animation across widgets. Will be overridden by local widget options.', 'vamtam-elementor-integration' ),
				'type' => Controls_manager::SWITCHER,
				'label_on' => __( 'On', 'elementor' ),
				'label_off' => __( 'Off', 'elementor' ),
				'default' => 'yes',
			]
		);

		// Width
		$kit->add_control(
			"{$theme_prefix}underline_anim_width",
			[
				'label' => __( 'Underline Width (px)', 'vamtam-elementor-integration' ),
				'type' => Controls_manager::SLIDER,
				'range' => [
					'px' => [
						'max' => 10,
						'min' => 1,
					],
				],
				'selectors' => [
					'body' => '--vamtam-global-underline-width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		// Spacing
		$kit->add_control(
			"{$theme_prefix}underline_anim_spacing",
			[
				'label' => __( 'Underline Spacing (px)', 'vamtam-elementor-integration' ),
				'type' => Controls_manager::SLIDER,
				'range' => [
					'px' => [
						'max' => 50,
						'min' => 0,
					],
				],
				'selectors' => [
					'body' => '--vamtam-global-underline-spacing: {{SIZE}}{{UNIT}};',
				],
			]
		);

		// Underline Color.
		$kit->add_control(
			"{$theme_prefix}underline_anim_bg_color",
			[
				'label' => __( 'Underline Color', 'vamtam-elementor-integration' ),
				'type' => Controls_manager::COLOR,
				'default' => '',
				'selectors' => [
					'body' => '--vamtam-global-underline-bg-color: {{VALUE}};',
				],
                'separator' => 'after',
			]
		);
	}

    protected function add_widgets_controls( $kit ) {
        $theme_prefix  = 'vamtam_theme_';

        $kit->add_control(
            'section_theme_settings_widgets_heading',
            [
                'type' => Controls_manager::HEADING,
                'label' => __( 'Widgets', 'vamtam-elementor-integration' ),
                'separator' => 'before',
            ]
        );

        $kit->add_control(
            "{$theme_prefix}widget_notice",
            [
                'type' => Controls_manager::RAW_HTML,
                'raw' => __( '<h3>Using the switches below, you can easily toggle the theme\'s custom implementation for a particular widget type on and off.</h3>', 'vamtam-elementor-integration' ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $kit->add_control(
            "{$theme_prefix}enable_all_widget_mods",
            [
                'label' => __( 'Enable All Widget Modifications', 'vamtam-elementor-integration' ),
                'type' => Controls_manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    "{$theme_prefix}disable_all_widget_mods" => '',
                ],
				'frontend_available' => true,
            ]
        );

        $kit->add_control(
            "{$theme_prefix}disable_all_widget_mods",
            [
                'label' => __( 'Disable All Widget Modifications', 'vamtam-elementor-integration' ),
                'type' => Controls_manager::SWITCHER,
                'default' => '',
                'condition' => [
                    "{$theme_prefix}enable_all_widget_mods" => '',
                ],
				'frontend_available' => true,
            ]
        );

        $this->add_widgets_toggles( $kit );
    }

    protected function add_widgets_toggles( $kit ) {
        /* !! -- Important -- !!
            1 - We dont add toggles here directly. Instead, we manipulate the configurable_widgets list
                (see get_widget_mods_list()) in elementor_bridge.php accordingly.

            2 - The option names of these toggles, should follow the naming convention below:
                {"vamtam_theme_"}{$widget_name} where $widget_name is whatever is returned from the get_name() protected function of the current widget.
                Example:
                    for accordion widget: "vamtam_theme_accordion"
                    for accordion menu-cart widget: "vamtam_theme_woocommerce-menu-cart"
        */

        $theme_prefix  = 'vamtam_theme_';
        $widget_mods_list = \VamtamElementorBridge::get_widget_mods_list();
        $default_opts = [
            'type' => Controls_manager::SWITCHER,
            'separator' => 'before',
            'default' => 'yes',
            'condition' => [
                "{$theme_prefix}disable_all_widget_mods" => '',
                "{$theme_prefix}enable_all_widget_mods" => '',
            ],
			'frontend_available' => true,
        ];

        foreach ( $widget_mods_list as $widget_name => $widget_opts ) {
            $kit->add_control(
                "{$theme_prefix}{$widget_name}",
                array_merge( $default_opts, $widget_opts )
            );
        }
    }

	protected function add_general_controls( $kit ) {
        $theme_prefix  = 'vamtam_theme_';

        $kit->add_control(
            'section_theme_settings_general_heading',
            [
                'type' => Controls_manager::HEADING,
                'label' => __( 'General', 'vamtam-elementor-integration' ),
                'separator' => 'before',
            ]
        );

		// Font Smoothing.
		$kit->add_control(
			"{$theme_prefix}font_smoothing",
			[
				'label' => __( 'Font Smoothing', 'vamtam-elementor-integration' ),
				'description' => __( 'Toggles font-smoothing (added by the theme) for the whole site.', 'vamtam-elementor-integration' ),
				'type' => Controls_manager::SWITCHER,
				'label_on' => __( 'On', 'elementor' ),
				'label_off' => __( 'Off', 'elementor' ),
				'default' => 'yes',
			]
        );

		$kit->add_control(
            "{$theme_prefix}search_form_popups_get_focus",
			[
				'label' => __( 'Search Form Popups Get Focus', 'vamtam-elementor-integration' ),
				'description' => __( 'If enabled, all popups containing a search-form will receive focus everytime the popup is opened.', 'vamtam-elementor-integration' ),
				'type' => Controls_manager::SWITCHER,
				'default' => '',
				'frontend_available' => true,
                'separator' => 'before',
			]
        );
    }
}

function add_theme_settings_tab( $kit ) {
	$kit->register_tab( 'settings-theme-settings', Settings_Theme_Settings::class );
}

add_action( 'elementor/kit/register_tabs', __NAMESPACE__ . '\add_theme_settings_tab', 10 );
