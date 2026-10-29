<?php
/**
 * Search form template
 *
 * @package vamtam/tecnologia
 */
?>
<form role="search" method="get" class="searchform clearfix" action="<?php echo esc_url( home_url( '/' ) ) ?>">
	<label for="search-text-widget" class="visuallyhidden"><?php esc_html_e( 'Search for:', 'tecnologia' ) ?></label>
	<input id="search-text-widget" type="text" value="<?php echo esc_attr( get_query_var( 's' ) ) ?>" name="s" placeholder="<?php esc_attr_e( 'Search', 'tecnologia' )?>" required="required" />
	<input type="submit" value="<?php esc_attr_e( 'Search', 'tecnologia' )?>" />
	<?php if ( defined( 'ICL_LANGUAGE_CODE' ) ) : ?>
		<input type="hidden" name="lang" value="<?php echo esc_attr( ICL_LANGUAGE_CODE ) ?>"/>
	<?php endif ?>
</form>


