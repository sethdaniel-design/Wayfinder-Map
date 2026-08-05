<?php
/**
 * Asset registration. Frontend scripts only load when a shortcode is rendered.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function bandit_lm_enqueue_frontend() {
	static $done = false;
	if ( $done ) { return; }
	$done = true;

	wp_enqueue_style(
		'bandit-lm-frontend',
		BANDIT_LM_URL . 'assets/css/frontend.css',
		array(),
		BANDIT_LM_VERSION
	);
	wp_enqueue_script(
		'bandit-lm-frontend',
		BANDIT_LM_URL . 'assets/js/frontend.js',
		array(),
		BANDIT_LM_VERSION,
		true
	);

	wp_localize_script( 'bandit-lm-frontend', 'BanditLocationsData', array(
		'pins'       => bandit_lm_get_all_points(),
		'categories' => bandit_lm_get_categories(),
		'settings'   => bandit_lm_get_settings(),
	) );
}

add_action( 'admin_enqueue_scripts', 'bandit_lm_enqueue_admin' );
function bandit_lm_enqueue_admin( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$is_pin_editor = $screen && $screen->post_type === 'bandit_map_point';
	$is_settings   = isset( $_GET['page'] ) && $_GET['page'] === 'bandit-lm-settings';
	if ( ! $is_pin_editor && ! $is_settings ) { return; }

	wp_enqueue_style(
		'bandit-lm-admin',
		BANDIT_LM_URL . 'assets/css/admin.css',
		array(),
		BANDIT_LM_VERSION
	);
	wp_enqueue_script(
		'bandit-lm-admin-pin-placer',
		BANDIT_LM_URL . 'assets/js/admin-pin-placer.js',
		array( 'jquery' ),
		BANDIT_LM_VERSION,
		true
	);
}
