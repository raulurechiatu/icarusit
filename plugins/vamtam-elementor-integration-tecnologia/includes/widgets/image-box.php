<?php
namespace VamtamElementor\Widgets\ImageBox;

// Extending the Image Box widget.

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'image-box' ) ) {
	return;
}

function update_image_space_control( $controls_manager, $widget ) {
	// Spacing.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'image_space', [
		'selectors' => [
			'{{WRAPPER}} .elementor-image-box-img' => '--vamtam-img-spacing: {{SIZE}}{{UNIT}};',
		]
	] );
}
// Style - Image Section (Before).
function section_style_image_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_image_space_control( $controls_manager, $widget );
}
add_action( 'elementor/element/image-box/section_style_image/before_section_end', __NAMESPACE__ . '\section_style_image_before_section_end', 10, 2 );

function update_text_align_control( $controls_manager, $widget ) {
	// Alignment.
	\Vamtam_Elementor_Utils::add_control_options( $controls_manager, $widget, 'text_align', [
		'prefix_class' => 'vamtam-text-align%s-',
	] );
}
// Style - Content Section (Before).
function section_style_content_before_section_end( $widget, $args ) {
	$controls_manager = \Elementor\Plugin::instance()->controls_manager;
	update_text_align_control( $controls_manager, $widget );
}
add_action( 'elementor/element/image-box/section_style_content/before_section_end', __NAMESPACE__ . '\section_style_content_before_section_end', 10, 2 );
