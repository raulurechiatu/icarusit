<?php
namespace VamtamElementor\Widgets\PostsBase;

use ElementorPro\Modules\Posts\Skins\Skin_Classic as Elementor_Posts_Classic_Skin;
use ElementorPro\Modules\ThemeBuilder\Skins\Posts_Archive_Skin_Classic as Elementor_Archive_Posts_Classic_Skin;

// Extending the Posts/Posts_Archive widgets.

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

if ( vamtam_theme_supports( 'posts-base--extra-pagination-controls' ) ) {
    function add_extra_pagination_controls( $controls_manager, $widget ) {
        $widget->start_injection( [
            'of' => 'pagination_color_heading',
            'at' => 'before',
        ] );
        $widget->add_control(
            'show_pagination_border',
            [
                'label' => __( 'Border', 'vamtam-elementor-integration' ),
                'type' => $controls_manager::SWITCHER,
                'label_off' => __( 'Hide', 'vamtam-elementor-integration' ),
                'label_on' => __( 'Show', 'vamtam-elementor-integration' ),
                'default' => 'yes',
                'return_value' => 'yes',
                'prefix_class' => 'elementor-show-pagination-border-',
            ]
        );
        $widget->add_control(
            'pagination_border_color',
            [
                'label' => __( 'Border Color', 'vamtam-elementor-integration' ),
                'type' => $controls_manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-pagination .page-numbers' => 'border-color: {{VALUE}}',
                ],
                'condition' => [
                    'show_pagination_border' => 'yes',
                ],
            ]
        );
        $widget->add_control(
            'pagination_padding',
            [
                'label' => __( 'Padding', 'vamtam-elementor-integration' ),
                'type' => $controls_manager::SLIDER,
                'range' => [
                    'em' => [
                        'min' => 0,
                        'max' => 2,
                        'step' => 0.1,
                    ],
                ],
                'size_units' => [ 'em' ],
                'selectors' => [
                    '{{WRAPPER}} .elementor-pagination .page-numbers' => 'padding: {{SIZE}}{{UNIT}}',
                ],
            ]
        );
        $widget->end_injection();

		// Inject into the Pagination Colors Normal tab.
		$widget->start_injection( [
            'of' => 'pagination_color',
        ] );
        $widget->add_control(
            'pagination_bg_color',
            [
                'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
                'type' => $controls_manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-pagination .page-numbers:not(.dots)' => 'background-color: {{VALUE}}',
                ],
            ]
        );
        $widget->end_injection();

		// Inject into the Pagination Colors Hover tab.
		$widget->start_injection( [
            'of' => 'pagination_hover_color',
        ] );
        $widget->add_control(
            'pagination_hover_bg_color',
            [
                'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
                'type' => $controls_manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-pagination .page-numbers:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );
        $widget->end_injection();

		// Inject into the Pagination Colors Active tab.
		$widget->start_injection( [
			'of' => 'pagination_active_color',
		] );
        $widget->add_control(
            'pagination_active_bg_color',
            [
                'label' => __( 'Background Color', 'vamtam-elementor-integration' ),
                'type' => $controls_manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elementor-pagination .page-numbers.current' => 'background-color: {{VALUE}}',
                ],
            ]
        );
		$widget->end_injection();

    }
    // Style - Pagination Section.
    function section_pagination_style_before_section_end( $widget, $args ) {
        $controls_manager = \Elementor\Plugin::instance()->controls_manager;
        add_extra_pagination_controls( $controls_manager, $widget );
    }

    // Theme Settings.
    if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'posts' ) ) {
        add_action( 'elementor/element/posts/section_pagination_style/before_section_end', __NAMESPACE__ . '\section_pagination_style_before_section_end', 10, 2 );
    }
    // Theme Settings.
    if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'archive-posts' ) ) {
        add_action( 'elementor/element/archive-posts/section_pagination_style/before_section_end', __NAMESPACE__ . '\section_pagination_style_before_section_end', 10, 2 );
    }
}

if ( vamtam_theme_supports( 'posts-base--display-categories', 'posts-base--display-tags' ) ) {
	// We need new Skin for displaying post categories/tags.
	trait Vamtam_PostsBase_Classic_Skin_Overrides  {

		public function get_id() {
			return 'vamtam_classic';
		}

		public function get_title() {
			return esc_html__( 'Classic (Vamtam)', 'vamtam-elementor-integration' );
		}

		protected function render_meta_data() {
			/** @var array $settings e.g. [ 'author', 'date', ... ] */
			$settings = $this->get_instance_value( 'meta_data' );
			if ( empty( $settings ) ) {
				return;
			}
			?>
			<div class="elementor-post__meta-data">
				<?php
				if ( in_array( 'author', $settings ) ) {
					$this->render_author();
				}

				if ( in_array( 'date', $settings ) ) {
					$this->render_date_by_type();
				}

				if ( in_array( 'time', $settings ) ) {
					$this->render_time();
				}

				if ( in_array( 'comments', $settings ) ) {
					$this->render_comments();
				}

				if ( in_array( 'modified', $settings ) ) {
					$this->render_date_by_type( 'modified' );
				}

				if ( vamtam_theme_supports( 'posts-base--display-categories' ) ) {
					if ( in_array( 'vamtam-categories', $settings ) ) {
						$this->render_categories();
					}
				}

				if ( vamtam_theme_supports( 'posts-base--display-tags' ) ) {
					if ( in_array( 'vamtam-tags', $settings ) ) {
						$this->render_tags();
					}
				}
				?>
			</div>
			<?php
		}

		protected function render_categories() {
			?>
			<div class="vamtam-post__categories">
				<?php the_category( ', ' ); ?>
			</div>
			<?php
		}

		protected function render_tags() {
			?>
			<div class="vamtam-post__tags">
				<?php the_tags( '', ', ' ); ?>
			</div>
			<?php
		}

		protected function render_post() {
			$this->render_post_header();
			$this->render_thumbnail();
			$this->render_meta_data();
			$this->render_text_header();
			$this->render_title();
			$this->render_excerpt();
			$this->render_read_more();
			$this->render_text_footer();
			$this->render_post_footer();
		}
	}

	// Posts
	class Skin_Vamtam_Posts_Classic extends Elementor_Posts_Classic_Skin {
		use Vamtam_PostsBase_Classic_Skin_Overrides;
	}
	// Archive Posts
	class Skin_Vamtam_Archive_Posts_Classic extends Elementor_Archive_Posts_Classic_Skin {
		use Vamtam_PostsBase_Classic_Skin_Overrides;
	}

	function update_meta_data_control_for_vamtam_classic_skin( $controls_manager, $widget ) {
		if ( vamtam_theme_supports( 'posts-base--display-categories' ) ) {
			// Meta Data.
			\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'vamtam_classic_meta_data', [
				'options' => [
					'vamtam-categories' => esc_html__( 'Categories', 'vamtam-elementor-integration' ),
				],
			] );
		}
		if ( vamtam_theme_supports( 'posts-base--display-tags' ) ) {
			// Meta Data.
			\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'vamtam_classic_meta_data', [
				'options' => [
					'vamtam-tags' => esc_html__( 'Tags', 'vamtam-elementor-integration' ),
				],
			] );
		}
    }

	function update_content_padding_control_for_vamtam_classic_skin( $controls_manager, $widget ) {
		// Content Padding.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'vamtam_classic_content_padding', [
			'selectors' => [
				'{{WRAPPER}}' => '--vamtam-content-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
			],
		] );
    }

    // Content - Layout Section.
    function section_layout_before_section_end( $widget, $args ) {
        $controls_manager = \Elementor\Plugin::instance()->controls_manager;
        update_meta_data_control_for_vamtam_classic_skin( $controls_manager, $widget );
    }

	// Style - Box Section.
    function section_design_box_before_section_end( $widget, $args ) {
        $controls_manager = \Elementor\Plugin::instance()->controls_manager;
        update_content_padding_control_for_vamtam_classic_skin( $controls_manager, $widget );
    }

	// Theme Settings.
    if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'posts' ) ) {
		add_action( 'elementor/element/posts/section_layout/before_section_end', __NAMESPACE__ . '\section_layout_before_section_end', 11, 2 );
		add_action( 'elementor/element/posts/vamtam_classic_section_design_box/before_section_end', __NAMESPACE__ . '\section_design_box_before_section_end', 11, 2 );
	}
    // Theme Settings.
    if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'archive-posts' ) ) {
		add_action( 'elementor/element/archive-posts/section_layout/before_section_end', __NAMESPACE__ . '\section_layout_before_section_end', 11, 2 );
		add_action( 'elementor/element/archive-posts/vamtam_classic_section_design_box/before_section_end', __NAMESPACE__ . '\section_design_box_before_section_end', 11, 2 );
	}
}

function update_image_controls_for_skin( $controls_manager, $widget, $skin_prefix ) {
	// Image Border Radius.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, "{$skin_prefix}_img_border_radius", [
		'selectors' => [
			'{{WRAPPER}}' => '--vamtam-img-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
		]
	] );
}

// Style - Image Section (Classic Layout) - Before Section End.
function section_design_image_before_section_end_classic_skin( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_image_controls_for_skin( $controls_manager, $widget, 'classic' );
}
// Style - Image Section (Vamtam Classic Layout) - Before Section End.
function section_design_image_before_section_end_vamtam_classic_skin( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_image_controls_for_skin( $controls_manager, $widget, 'vamtam_classic' );
}
// Theme Settings.
if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'posts' ) ) {
	add_action( 'elementor/element/posts/classic_section_design_image/before_section_end', __NAMESPACE__ . '\section_design_image_before_section_end_classic_skin', 10, 2 );
	add_action( 'elementor/element/posts/vamtam_classic_section_design_image/before_section_end', __NAMESPACE__ . '\section_design_image_before_section_end_vamtam_classic_skin', 10, 2 );
}
// Theme Settings.
if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'archive-posts' ) ) {
	add_action( 'elementor/element/archive-posts/classic_section_design_image/before_section_end', __NAMESPACE__ . '\section_design_image_before_section_end_classic_skin', 10, 2 );
	add_action( 'elementor/element/archive-posts/vamtam_classic_section_design_image/before_section_end', __NAMESPACE__ . '\section_design_image_before_section_end_vamtam_classic_skin', 10, 2 );
}

if ( vamtam_theme_supports( 'posts-base--horizontal-layout' ) ) {
	function register_posts_base_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'vamtam-hr-scrolling',
			VAMTAM_ELEMENTOR_INT_URL . 'assets/js/widgets/vamtam-hr-scrolling/vamtam-hr-scrolling' . $suffix . '.js',
			[
				'elementor-frontend',
			],
			\VamtamElementorIntregration::PLUGIN_VERSION,
			true
		);
	}
	add_action( 'elementor/frontend/before_register_scripts', __NAMESPACE__ . '\register_posts_base_scripts' );

	// Posts_Base, before render_content.
	function posts_base_before_render_content( $widget ) {
		$widget_name = $widget->get_name();
		if ( $widget->get_name() === 'global' ) {
			$widget_name = $widget->get_original_element_instance()->get_name();
		}

		$posts_widgets = [
			'posts',
			'archive-posts',
		];

		if ( in_array( $widget_name, $posts_widgets ) ) {
			// Theme Settings.
			if ( \Vamtam_Elementor_Utils::is_widget_mod_active( $widget_name ) ) {
				do_action( 'vamtam_posts_base_before_render_content', $widget_name, $widget );
			}
		}
	}
	add_action( 'elementor/widget/before_render_content', __NAMESPACE__ . '\posts_base_before_render_content', 10, 1 );

	function posts_base_add_script_depends( $widget_name, $widget ) {
		// Theme settings are checked on vamtam_before_posts_widget_before_render_content (do_action)
		$settings = $widget->get_settings();
		$skin_id  = $settings['_skin'];

		if ( ! empty( $settings[ "{$skin_id}_vamtam_use_hr_layout" ] ) ) {
			$widget->add_script_depends( 'vamtam-hr-scrolling' );
		}
	}
	add_action( 'vamtam_posts_base_before_render_content', __NAMESPACE__ . '\posts_base_add_script_depends', 10, 2 );

	function posts_base_add_item_ratio_class( $widget_name, $widget ) {
		// Theme settings are checked on vamtam_before_posts_widget_before_render_content (do_action)
		$settings = $widget->get_settings();
		$skin_id  = $settings['_skin'];

		$thumb_ratio = $widget->get_settings( "{$skin_id}_item_ratio" );

		if ( ! empty( $thumb_ratio ) && ! empty( $thumb_ratio[ 'size' ] ) ) {
			$widget->add_render_attribute( 'container', [
				'class' => 'elementor-has-item-ratio',
			] );
		}
	}
	add_action( 'vamtam_posts_base_before_render_content', __NAMESPACE__ . '\posts_base_add_item_ratio_class', 10, 2 );


	function update_columns_control_for_skin( $controls_manager, $widget, $skin_prefix ) {
		// Columns.
		\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, "{$skin_prefix}_columns", [
			'selectors' => [
				'{{WRAPPER}}' => '--vamtam-cols: {{VALUE}}',
			],
		] );
		// Columns.
		\Vamtam_Elementor_Utils::replace_control_options( $controls_manager, $widget, "{$skin_prefix}_columns", [
			// This is required cause of https://github.com/elementor/elementor/issues/12947
			'prefix_class' => 'elementor-grid%s-',
		] );
	}
	function add_hr_layout_controls_for_skin( $controls_manager, $widget, $skin_prefix ) {
		$widget->add_control(
			"{$skin_prefix}_vamtam_use_hr_layout",
			[
				'label' => __( 'Use Horizontal Layout', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'hr-layout',
				'condition' => [
					'_skin' => $skin_prefix,
				]
			]
		);

		// Additional Columns Hint.
		$widget->add_responsive_control(
			"{$skin_prefix}_vamtam_additional_cols_hint",
			[
				'label' => esc_html__( 'Additional Columns Hint', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', 'em', '%' ],
				'default' => [
					'size' => 20,
					'unit' => 'px',
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--vamtam-col-hint: {{SIZE}}{{UNIT}}',
				],
				'required' => true,
				'condition' => [
					'_skin' => $skin_prefix,
					"{$skin_prefix}_vamtam_use_hr_layout!" => '',
				],
			]
		);

		$widget->add_control(
			"{$skin_prefix}_vamtam_has_nav",
			[
				'label' => __( 'Show Navigation', 'vamtam-elementor-integration' ),
				'description' => __( 'Disabled by default on mobile devices.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'nav',
				'condition' => [
					'_skin' => $skin_prefix,
					"{$skin_prefix}_vamtam_use_hr_layout!" => '',
				],
				'default' => 'nav',
				'render_type' => 'template',
			]
		);

		// Nav Position
		$widget->add_responsive_control(
			"{$skin_prefix}_vamtam_nav_pos",
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
					'_skin' => $skin_prefix,
					"{$skin_prefix}_vamtam_use_hr_layout!" => '',
					"{$skin_prefix}_vamtam_has_nav!" => '',
				],
			]
		);

		$conditions = [
			'_skin' => $skin_prefix,
			"{$skin_prefix}_vamtam_use_hr_layout!" => '',
			"{$skin_prefix}_vamtam_has_nav!" => '',
			"{$skin_prefix}_vamtam_nav_pos" => 'custom',
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
			"{$skin_prefix}_vamtam_nav_prev_x",
			[
				'label' => __( 'Previous Button X', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'default' => [ 'unit' => '%' ],
				'selectors' => [
					'{{WRAPPER}} .vamtam-nav' => '--vamtam-nav-prev-x: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Prev Btn Y
		$widget->add_responsive_control(
			"{$skin_prefix}_vamtam_nav_prev_y",
			[
				'label' => __( 'Previous Button Y', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'default' => [ 'unit' => '%' ],
				'selectors' => [
					'{{WRAPPER}} .vamtam-nav' => '--vamtam-nav-prev-y: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Next Btn X
		$widget->add_responsive_control(
			"{$skin_prefix}_vamtam_nav_next_x",
			[
				'label' => __( 'Next Button X', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'default' => [ 'unit' => '%' ],
				'selectors' => [
					'{{WRAPPER}} .vamtam-nav' => '--vamtam-nav-next-x: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Next Btn Y
		$widget->add_responsive_control(
			"{$skin_prefix}_vamtam_nav_next_y",
			[
				'label' => __( 'Next Button Y', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'default' => [ 'unit' => '%' ],
				'selectors' => [
					'{{WRAPPER}} .vamtam-nav' => '--vamtam-nav-next-y: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		$conditions = [
			'_skin' => $skin_prefix,
			"{$skin_prefix}_vamtam_use_hr_layout!" => '',
			"{$skin_prefix}_vamtam_has_nav!" => '',
			"{$skin_prefix}_vamtam_nav_pos!" => [ 'default', 'custom' ],
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
			"{$skin_prefix}_vamtam_nav_btns_gap",
			[
				'label' => __( 'Buttons Gap', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .vamtam-nav' => '--vamtam-nav-btns-gap: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Nav Btns Spacing
		$widget->add_responsive_control(
			"{$skin_prefix}_vamtam_nav_btns_spacing",
			[
				'label' => __( 'Buttons Spacing', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .vamtam-nav' => '--vamtam-nav-btns-spacing: {{SIZE}}{{UNIT}};',
				],
				'device_args' => $dev_args,
			]
		);

		// Always visible
		$widget->add_control(
			"{$skin_prefix}_vamtam_no_hide_anim",
			[
				'label' => __( 'Always Visible', 'vamtam-elementor-integration' ),
				'description' => __( 'Disables the default "Show on Hover" animation.', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-nav-',
				'return_value' => 'no-hide-anim',
				'condition' => [
					'_skin' => $skin_prefix,
					"{$skin_prefix}_vamtam_use_hr_layout!" => '',
					"{$skin_prefix}_vamtam_has_nav!" => '',
				],
				'default' => '',
				'render_type' => 'template',
			]
		);

		// Show on mobile
		$widget->add_control(
			"{$skin_prefix}_vamtam_show_on_mobile",
			[
				'label' => __( 'Enable on Mobile Devices', 'vamtam-elementor-integration' ),
				'type' => $controls_manager::SWITCHER,
				'prefix_class' => 'vamtam-has-',
				'return_value' => 'nav-on-mobile',
				'condition' => [
					'_skin' => $skin_prefix,
					"{$skin_prefix}_vamtam_use_hr_layout!" => '',
					"{$skin_prefix}_vamtam_has_nav!" => '',
				],
				'default' => '',
				'render_type' => 'template',
			]
		);
	}

	function section_layout_before_section_end_classic_skin( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		$skin_prefix      = $widget->get_name() === 'posts' ? 'classic' : 'archive_classic';
		add_hr_layout_controls_for_skin( $controls_manager, $widget, $skin_prefix );
		update_columns_control_for_skin( $controls_manager, $widget, $skin_prefix );
	}
	function section_layout_before_section_end_vamtam_classic_skin( $widget, $args ) {
		$controls_manager = \Elementor\Plugin::instance()->controls_manager;
		add_hr_layout_controls_for_skin( $controls_manager, $widget, 'vamtam_classic' );
		update_columns_control_for_skin( $controls_manager, $widget, 'vamtam_classic' );
	}

	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'posts' ) ) {
		// the posts widget has a --grid-column-gap var already
		add_action( 'elementor/element/posts/classic_section_design_layout/before_section_end', __NAMESPACE__ . '\section_layout_before_section_end_classic_skin', 10, 2 );
		add_action( 'elementor/element/posts/vamtam_classic_section_design_layout/before_section_end', __NAMESPACE__ . '\section_layout_before_section_end_vamtam_classic_skin', 10, 2 );
	}
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'archive-posts' ) ) {
		// the posts widget has a --grid-column-gap var already
		add_action( 'elementor/element/archive-posts/archive_classic_section_design_layout/before_section_end', __NAMESPACE__ . '\section_layout_before_section_end_classic_skin', 10, 2 );
		add_action( 'elementor/element/archive-posts/vamtam_classic_section_design_layout/before_section_end', __NAMESPACE__ . '\section_layout_before_section_end_vamtam_classic_skin', 10, 2 );
	}
}

// TODO: Temporary fix, remove when fixed: https://github.com/elementor/elementor/issues/12126
if ( vamtam_theme_supports( 'posts-base--404-handling-fix' ) ) {
	function vamtam_bypass_404_handling_on_posts_base_fetch( $bypass, $wp_query ) {
		$is_posts_base_fetch_req = isset( $_GET[ 'vamtam_posts_fetch' ] ) && $_GET[ 'vamtam_posts_fetch' ];
		$is_paged_req            = ( isset( $wp_query->query[ 'page' ] ) && $wp_query->query[ 'page' ] ); // on pages where 'paged' query_arg is used, this shouldn't be an issue. If it is we can just add a check fpr 'paged' as well.

		if (  $is_paged_req && $is_posts_base_fetch_req ) {
			// Bypass 404 handling for posts_base fetch requests (paged urls + vamtam_posts_fetch query_var), so there's no redirection that leads to invalid query results (always getting 1st page results).
			$bypass = true;
		}

		return $bypass;
	}
	// Theme Settings.
	if ( \Vamtam_Elementor_Utils::is_widget_mod_active( 'posts' ) || \Vamtam_Elementor_Utils::is_widget_mod_active( 'archive-posts' ) ) {
		add_filter( 'pre_handle_404', __NAMESPACE__ . '\vamtam_bypass_404_handling_on_posts_base_fetch', 10, 2 );
	}
}

// Fixes an issue where for queries with "Source" set to "Manual Selection", sticky posts are always included in the results.
function vamtam_ignore_sticky_posts_for_manual_selection_fix( $query_args, $widget ) {
	if ( ! $query_args[ 'ignore_sticky_posts' ] ) {
		$posts_source         = $widget->get_settings('posts_post_type');
		$exclude_sticky_posts = $posts_source === 'by_id' && isset( $query_args['post__in'] );

		if ( $exclude_sticky_posts ) {
			$query_args[ 'ignore_sticky_posts' ] = true;
		}
	}

	return $query_args;
}
add_action( 'elementor/query/query_args', __NAMESPACE__ . '\vamtam_ignore_sticky_posts_for_manual_selection_fix', 11, 2 );
