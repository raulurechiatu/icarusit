<?php
namespace VamtamElementor\Widgets\TestimonialCarousel;

// Extending the Testimonial Carousel widget.

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'testimonial-carousel' ) ) {
	return;
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

if ( vamtam_theme_supports( 'testimonial-carousel--custom-nav-arrows-controls' ) ) {

	function add_nav_section_controls( $controls_manager, $widget ) {
		// Arrows Color.
		$arrows_color_control_data = \Vamtam_Elementor_Utils::remove_control( $controls_manager, $widget, 'arrows_color' );

		$widget->start_controls_tabs( 'vamtam_arrows_colors' );

        $widget->start_controls_tab(
            'vamtam_arrows_color_normal',
            [
                'label' => __( 'Normal', 'vamtam-elementor-integration' ),
				'condition' => [
					'show_arrows!' => ''
				],
            ]
        );

        $widget->add_control(
            'arrows_color',
			[
				'label' => esc_html__( 'Color', 'elementor-pro' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper-button' => 'color: {{VALUE}}',
					'{{WRAPPER}} .elementor-swiper-button svg' => 'fill: {{VALUE}}',
				],
				'condition' => [
					'show_arrows!' => ''
				],
			]
        );

        $widget->end_controls_tab();

        $widget->start_controls_tab(
            'arrows_color_hover',
            [
                'label' => __( 'Hover', 'vamtam-elementor-integration' ),
				'condition' => [
					'show_arrows!' => ''
				],
            ]
        );

        $widget->add_control(
            'arrows_hover_color',
			[
				'label' => esc_html__( 'Color', 'elementor-pro' ),
				'type' => $controls_manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper-button:hover' => 'color: {{VALUE}}',
					'{{WRAPPER}} .elementor-swiper-button:hover svg' => 'fill: {{VALUE}}',
				],
				'condition' => [
					'show_arrows!' => ''
				],
			]
        );

        $widget->end_controls_tab();
        $widget->end_controls_tabs();

		// Arrows Heading
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'heading_arrows', [
			'condition' => [
				'show_arrows!' => ''
			],
		] );

		// Arrows Size
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'arrows_size', [
			'selectors' => [
				'{{WRAPPER}} .elementor-swiper' => '--vamtam-arrows-size: {{SIZE}}{{UNIT}}',
			],
			'condition' => [
				'show_arrows!' => ''
			],
		] );

		// Nav Position
		$widget->add_responsive_control(
			"vamtam_nav_pos",
			[
				'label' => esc_html__( 'Position', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SELECT,
				'options' => [
					'default' => esc_html__( 'Default', 'vamtam-elementor-integration' ),
					'top-left' => esc_html__( 'Top Left', 'vamtam-elementor-integration' ),
					'top-right' => esc_html__( 'Top Right', 'vamtam-elementor-integration' ),
					'top-center' => esc_html__( 'Top Center', 'vamtam-elementor-integration' ),
					'bottom-left' => esc_html__( 'Bottom Left', 'vamtam-elementor-integration' ),
					'bottom-right' => esc_html__( 'Bottom Right', 'vamtam-elementor-integration' ),
					'bottom-center' => esc_html__( 'Bottom Center', 'vamtam-elementor-integration' ),
					'custom' => esc_html__( 'Custom', 'vamtam-elementor-integration' ),
				],
				'default' => 'default',
				'prefix_class' => 'vamtam-nav-pos%s-',
				'condition' => [
					'show_arrows!' => ''
				],
			]
		);

		$conditions = [
			'show_arrows!' => '',
			"vamtam_nav_pos" => 'custom',
		];

		$dev_args = [
			'desktop' => [
				'condition' => $conditions,
			],
			'tablet' => [
				'condition' => $conditions,
			],
			'mobile' => [
				'condition' => $conditions,
			]
		];

		// Prev Btn X
		$widget->add_responsive_control(
			"vamtam_nav_prev_x",
			[
				'label' => __( 'Previous Button X', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'default' => [ 'unit' => '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper' => '--vamtam-nav-prev-x: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Prev Btn Y
		$widget->add_responsive_control(
			"vamtam_nav_prev_y",
			[
				'label' => __( 'Previous Button Y', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'default' => [ 'unit' => '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper' => '--vamtam-nav-prev-y: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Next Btn X
		$widget->add_responsive_control(
			"vamtam_nav_next_x",
			[
				'label' => __( 'Next Button X', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'default' => [ 'unit' => '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper' => '--vamtam-nav-next-x: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Next Btn Y
		$widget->add_responsive_control(
			"vamtam_nav_next_y",
			[
				'label' => __( 'Next Button Y', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'default' => [ 'unit' => '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper' => '--vamtam-nav-next-y: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		$conditions = [
			'show_arrows!' => '',
			"vamtam_nav_pos!" => [ 'default', 'custom' ],
		];

		$dev_args = [
			'desktop' => [
				'condition' => $conditions,
			],
			'tablet' => [
				'condition' => $conditions,
			],
			'mobile' => [
				'condition' => $conditions,
			]
		];

		// Nav Btns Gap
		$widget->add_responsive_control(
			"vamtam_nav_btns_gap",
			[
				'label' => __( 'Buttons Gap', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper' => '--vamtam-nav-btns-gap: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Nav Btns Spacing
		$widget->add_responsive_control(
			"vamtam_nav_btns_spacing",
			[
				'label' => __( 'Buttons Spacing', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper' => '--vamtam-nav-btns-spacing: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);
	}

	// Style - Navigation section
	function section_navigation_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_nav_section_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/testimonial-carousel/section_navigation/before_section_end', __NAMESPACE__ . '\section_navigation_before_section_end', 10, 2 );
}

if ( vamtam_theme_supports( 'testimonial-carousel--reserve-slide-height' ) ) {

	function add_reserve_slide_height_controls( $controls_manager, $widget ) {
		// 'Reserve Slide Height
		$widget->add_control(
			'vamtam_reserve_slide_height',
			[
				'label' => esc_html__( 'Reserve Slide Height', 'vamtam-elementor-integration' ),
				'description' => esc_html__( 'Reserve space for the slide height to avoid content jumps on page load.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'default' => '',
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'slide-height',
			]
		);

		// Slide Height
		$widget->add_responsive_control(
			'vamtam_slide_height',
			[
				'label' => esc_html__( 'Slide Height', 'vamtam-elementor-integration' ),
				'description' => esc_html__( 'To find the proper slide height to reserve, check the height of the "swiper-wrapper" element after the carousel has finished loading the content.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-swiper' => '--vamtam-slide-height: {{SIZE}}{{UNIT}};',
				],
				'condition' => [
					'vamtam_reserve_slide_height!' => '',
				],
			]
		);
	}

	// Content - Additional Options section
	function section_additional_options_before_section_end( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_reserve_slide_height_controls( $controls_manager, $widget );
	}
	add_action( 'elementor/element/testimonial-carousel/section_additional_options/before_section_end', __NAMESPACE__ . '\section_additional_options_before_section_end', 10, 2 );
}
