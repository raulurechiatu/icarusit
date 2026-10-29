<?php

// vamtam_theme_supports
if ( ! function_exists( 'vamtam_theme_supports' ) ) {
    function vamtam_theme_supports( $feature, $relation = 'OR' ) {
        $supported = false;

        if ( is_array( $feature ) ) {
            // Multiple features.
            $not_supported_found = false;
            foreach ( $feature as $ftr ) {
                if ( current_theme_supports( $ftr ) || current_theme_supports( 'vamtam-elementor-widgets', $ftr ) ) {
                    if ( $relation === 'OR' ) {
                        $supported = true;
                        break;
                    }
                } else {
                    if ( $relation === 'AND' ) {
                        $not_supported_found = true;
                        break;
                    }
                }
            }

            if ( $relation === 'AND' && $not_supported_found === false ) {
                $supported = true;
            }
        } else {
            // Single feature.
            if ( current_theme_supports( $feature ) || current_theme_supports( 'vamtam-elementor-widgets', $feature ) ) {
                $supported = true;
            }
        }

        return $supported;
    }
}

add_filter( 'vamtam_force_disable_elementor_elements_cache', function( $force_disable ) {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( ! is_string( $host ) ) {
		return $force_disable;
	}

	if ( strpos( $host, 'demo.vamtam.com' ) !== false ) {
		return false;
	}

	return $force_disable;
} );
