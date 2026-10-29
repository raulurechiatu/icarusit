<?php
namespace VamtamElementor\DynamicTags;

use \Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Prop_Type;
use \Elementor\Modules\AtomicWidgets\PropsResolver\Props_Resolver_Context;
use \Elementor\Modules\AtomicWidgets\PropsResolver\Transformer_Base;
use \Elementor\Modules\AtomicWidgets\PropsResolver\Transformers\Svg_Src_Transformer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
	Atomic (v4) SVG widgets accept dynamic tags in the 'svg' category - Post Custom Field and ACF URL
	both declare it - but Elementor's dynamic transformer hands back the tag's rendered output, which
	is a plain URL string. The svg-src prop needs [ 'html' => ..., 'url' => ... ], so atomic-svg.html.twig
	reads settings.svg.html, finds nothing, and the icon renders empty.

	Wrap the core transformer and push the string back through Svg_Src_Transformer, which is what would
	have happened had the value been picked in the media library instead of a custom field.

	Remove once Elementor does this itself (still broken as of 4.2.1).
*/

if (
	! class_exists( 'Elementor\Modules\AtomicWidgets\PropsResolver\Transformer_Base' ) ||
	! class_exists( 'Elementor\Modules\AtomicWidgets\PropsResolver\Transformers\Svg_Src_Transformer' ) ||
	! class_exists( 'Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Prop_Type' )
) {
	return; // Elementor without atomic widgets.
}

class Vamtam_Svg_Aware_Dynamic_Transformer extends Transformer_Base {
	private $inner;

	public function __construct( Transformer_Base $inner ) {
		$this->inner = $inner;
	}

	public function transform( $value, Props_Resolver_Context $context ) {
		$result = $this->inner->transform( $value, $context );

		if ( ! is_string( $result ) || '' === trim( $result ) || ! $this->is_svg_prop( $context ) ) {
			return $result;
		}

		$result = trim( $result );

		// The field can hold either a URL or a bare attachment ID.
		$svg = is_numeric( $result )
			? [ 'id' => (int) $result, 'url' => null ]
			: [ 'id' => null, 'url' => $result ];

		return ( new Svg_Src_Transformer() )->transform( $svg, $context );
	}

	private function is_svg_prop( Props_Resolver_Context $context ) {
		$prop_type = $context->get_prop_type();

		return $prop_type instanceof Dynamic_Prop_Type &&
			in_array( 'svg', $prop_type->get_categories(), true );
	}
}

add_action( 'elementor/atomic-widgets/settings/transformers/register', function( $transformers ) {
	$inner = $transformers->get( Dynamic_Prop_Type::get_key() );

	if ( $inner instanceof Transformer_Base && ! $inner instanceof Vamtam_Svg_Aware_Dynamic_Transformer ) {
		$transformers->register( Dynamic_Prop_Type::get_key(), new Vamtam_Svg_Aware_Dynamic_Transformer( $inner ) );
	}
}, 20 );
