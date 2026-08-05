<?php
/**
 * Shortcodes: render placeholder containers that the frontend JS will hydrate.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode( 'bandit_locations_map',  'bandit_lm_shortcode_map' );
add_shortcode( 'bandit_locations_list', 'bandit_lm_shortcode_list' );
add_shortcode( 'bandit_locations_full', 'bandit_lm_shortcode_full' );

function bandit_lm_shortcode_map( $atts ) {
	$atts = shortcode_atts( array(
		'height'   => '720',
		'filter'   => 'All',
		'active'   => '',
		'show_filters' => '1',
	), $atts, 'bandit_locations_map' );

	bandit_lm_enqueue_frontend();

	$id = 'bandit-lm-map-' . wp_unique_id();
	ob_start(); ?>
	<div class="bandit-lm-root bandit-lm-map-only" id="<?php echo esc_attr( $id ); ?>"
		data-component="map"
		data-height="<?php echo esc_attr( $atts['height'] ); ?>"
		data-filter="<?php echo esc_attr( $atts['filter'] ); ?>"
		data-active="<?php echo esc_attr( $atts['active'] ); ?>"
		data-show-filters="<?php echo esc_attr( $atts['show_filters'] ); ?>">
		<div class="bandit-lm-loading"><span></span><span></span><span></span></div>
	</div>
	<?php return ob_get_clean();
}

function bandit_lm_shortcode_list( $atts ) {
	$atts = shortcode_atts( array(
		'filter' => 'All',
		'columns' => 'number,name,category,drive,distance,action',
	), $atts, 'bandit_locations_list' );

	bandit_lm_enqueue_frontend();

	$id = 'bandit-lm-list-' . wp_unique_id();
	ob_start(); ?>
	<div class="bandit-lm-root bandit-lm-list-only" id="<?php echo esc_attr( $id ); ?>"
		data-component="list"
		data-filter="<?php echo esc_attr( $atts['filter'] ); ?>"
		data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">
		<div class="bandit-lm-loading"><span></span><span></span><span></span></div>
	</div>
	<?php return ob_get_clean();
}

function bandit_lm_shortcode_full( $atts ) {
	$atts = shortcode_atts( array(
		'height' => '720',
	), $atts, 'bandit_locations_full' );

	bandit_lm_enqueue_frontend();

	ob_start(); ?>
	<div class="bandit-lm-stack">
		<?php echo bandit_lm_shortcode_map( array( 'height' => $atts['height'] ) ); ?>
		<?php echo bandit_lm_shortcode_list( array() ); ?>
	</div>
	<?php return ob_get_clean();
}
