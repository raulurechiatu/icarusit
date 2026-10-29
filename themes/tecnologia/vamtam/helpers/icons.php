<?php

/**
 * Helper functions for dealing with the icon fonts used by the theme
 *
 * @package vamtam/tecnologia
 */

function vamtam_icon( $key ) {
	echo vamtam_get_icon( $key ); // xss ok
}

function vamtam_get_icon( $key ) {
	if ( ( $num = vamtam_get_icon_num( $key ) ) !== false ) {
		return "&#$num;";
	}

	return $key;
}

function vamtam_get_icon_num( $key ) {
	$icons       = vamtam_get_icon_list();
	$theme_icons = vamtam_get_theme_icon_list();

	if ( isset( $icons[ $key ] ) )
		return $icons[ $key ];

	$theme_key = preg_replace( '/^vamtam-theme-/', '', $key, 1 );
	if ( isset( $theme_icons[ $theme_key ] ) ) {
		return $theme_icons[ $theme_key ];
	}

	return false;
}

/**
 * Returns the list of Icomoon icons
 * @return array list of icons
 */
function vamtam_get_icon_list() {
	if ( ! isset( $GLOBALS['VAMTAM_ICONS_CACHE'] ) ) {
		$GLOBALS['VAMTAM_ICONS_CACHE'] = include VAMTAM_ASSETS_DIR . 'fonts/icons/list.php';
	}

	return $GLOBALS['VAMTAM_ICONS_CACHE'];
}

/**
 * Returns the list of theme icons
 * @return array list of icons
 */
function vamtam_get_theme_icon_list() {
	if ( ! isset( $GLOBALS['VAMTAM_THEME_ICONS_CACHE'] ) ) {
		$GLOBALS['VAMTAM_THEME_ICONS_CACHE'] = include VAMTAM_ASSETS_DIR . 'fonts/theme-icons/list.php';
	}

	return $GLOBALS['VAMTAM_THEME_ICONS_CACHE'];
}

function vamtam_get_icon_html( $atts ) {
	$raw_atts = $atts;
	$atts     = shortcode_atts( array(
		'name'       => '',
		'style'      => '',
		'color'      => '',
		'size'       => '',
		'lheight'    => 1,
		'link_hover' => true,
	), $atts );

	$icon_char = vamtam_get_icon( $atts['name'] );

	$collection = '';

	if ( strpos( $atts['name'], 'vamtam-theme-' ) === 0 ) {
		$collection = 'theme';
	}

	$color = vamtam_sanitize_accent( $atts['color'], 'css' );
	$style = '';

	if ( ! empty( $color ) ) {
		$style = "color:$color;";
	}

	$style .= ( 1 !== (int) $atts['lheight'] && (int) $atts['lheight'] !== (int) $atts['size'] ) ? "line-height:{$atts['lheight']};" : '';

	if ( ! empty( $atts['size'] ) ) {
		if ( substr( $atts['size'], -2 ) !== 'em' ) {
			$atts['size'] .= 'px';
		}

		$style .= "font-size:{$atts['size']} !important;";
	}

	$class = array( $collection, $atts['style'] );

	if ( $atts['link_hover'] ) {
		$class[] = 'use-hover';
	}

	$class = implode( ' ', $class );

	return "<span class='icon shortcode $class' style='{$style}'>$icon_char</span>";
}


