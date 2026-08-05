<?php
/**
 * Helper to fetch fully-hydrated pin data + global settings + categories.
 * No register_post_meta — keeps the surface area small. Meta is private,
 * accessed only via update_post_meta / get_post_meta from this plugin.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fetch all published map points, fully hydrated, ordered by menu_order then title.
 */
function bandit_lm_get_all_points() {
	$posts = get_posts( array(
		'post_type'      => 'bandit_map_point',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'post_status'    => 'publish',
	) );

	$out = array();
	foreach ( $posts as $p ) {
		$cats = wp_get_post_terms( $p->ID, 'bandit_pin_category', array( 'fields' => 'all' ) );
		if ( is_wp_error( $cats ) ) { $cats = array(); }
		$cat  = ! empty( $cats ) ? $cats[0] : null;
		$cat_color = $cat ? get_term_meta( $cat->term_id, 'bandit_color', true ) : '#5A2816';
		if ( ! $cat_color ) { $cat_color = '#5A2816'; }

		$thumb_id = get_post_thumbnail_id( $p->ID );
		$thumb    = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'medium_large' ) : null;

		$tags_raw = (string) get_post_meta( $p->ID, 'bandit_tags', true );
		$tags     = $tags_raw !== '' ? array_values( array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) ) ) : array();

		$x_raw = get_post_meta( $p->ID, 'bandit_x', true );
		$y_raw = get_post_meta( $p->ID, 'bandit_y', true );

		$out[] = array(
			'id'              => $p->ID,
			'slug'            => $p->post_name,
			'name'            => get_the_title( $p ),
			'blurb'           => wpautop( $p->post_content ),
			'blurb_plain'     => wp_strip_all_tags( $p->post_content ),
			'category'        => $cat ? $cat->name : '',
			'category_slug'   => $cat ? $cat->slug : '',
			'category_color'  => $cat_color,
			'x'               => $x_raw === '' ? 50.0 : (float) $x_raw,
			'y'               => $y_raw === '' ? 50.0 : (float) $y_raw,
			'drive'           => (string) get_post_meta( $p->ID, 'bandit_drive', true ),
			'distance'        => (string) get_post_meta( $p->ID, 'bandit_distance', true ),
			'tags'            => $tags,
			'cta_url'         => (string) get_post_meta( $p->ID, 'bandit_cta_url', true ),
			'directions_url'  => (string) get_post_meta( $p->ID, 'bandit_directions_url', true ),
			'image'           => $thumb ? $thumb[0] : '',
		);
	}
	return $out;
}

/**
 * Global settings getter with sensible defaults.
 */
function bandit_lm_get_settings() {
	$defaults = array(
		'map_image_id'   => 0,
		'map_image_url'  => '',
		'hotel_x'        => 50.0,
		'hotel_y'        => 50.0,
		'hotel_label'    => 'Your Location',
		'hotel_sublabel' => 'YOU ARE HERE',
		'hotel_color'    => '#CA5A35',
		'show_compass'   => 1,
		'show_scale'     => 0,
		'show_legend'    => 1,
		'show_hotel_pin' => 1,
	);
	$saved = get_option( 'bandit_lm_settings', array() );
	if ( ! is_array( $saved ) ) { $saved = array(); }
	$out   = wp_parse_args( $saved, $defaults );

	if ( $out['map_image_id'] && empty( $out['map_image_url'] ) ) {
		$src = wp_get_attachment_image_src( $out['map_image_id'], 'full' );
		if ( $src ) { $out['map_image_url'] = $src[0]; }
	}
	return $out;
}

function bandit_lm_get_categories() {
	$terms = get_terms( array(
		'taxonomy'   => 'bandit_pin_category',
		'hide_empty' => false,
		'orderby'    => 'name',
	) );
	if ( is_wp_error( $terms ) ) { return array(); }
	$out = array();
	foreach ( $terms as $t ) {
		$color = get_term_meta( $t->term_id, 'bandit_color', true );
		$out[] = array(
			'id'    => $t->term_id,
			'name'  => $t->name,
			'slug'  => $t->slug,
			'color' => $color ? $color : '#5A2816',
		);
	}
	return $out;
}

/**
 * Internal error logger. Writes to wp-content/debug.log when WP_DEBUG_LOG is on.
 */
function bandit_lm_log( $msg ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( '[Wayfinder Map] ' . $msg );
	}
}
