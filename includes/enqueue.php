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

	// Appearance: load any Google Fonts and inject --blm-* color/font overrides.
	$google_font_url = bandit_lm_appearance_google_font_url();
	if ( $google_font_url ) {
		wp_enqueue_style( 'bandit-lm-google-fonts', $google_font_url, array(), null );
	}
	$inline_css = bandit_lm_appearance_inline_css();
	if ( $inline_css ) {
		wp_add_inline_style( 'bandit-lm-frontend', $inline_css );
	}
}

/**
 * Build inline CSS overriding --blm-* tokens for any appearance setting the user
 * has filled in. Blank settings are skipped so the map keeps inheriting the theme.
 */
function bandit_lm_appearance_inline_css() {
	$s     = bandit_lm_get_settings();
	$decls = array();

	foreach ( bandit_lm_appearance_colors() as $key => $info ) {
		$val = isset( $s[ $key ] ) ? trim( (string) $s[ $key ] ) : '';
		if ( $val !== '' && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $val ) ) {
			$decls[] = $info['var'] . ':' . $val;
		}
	}
	foreach ( bandit_lm_appearance_fonts() as $key => $info ) {
		$source = isset( $s[ $key . '_source' ] ) ? $s[ $key . '_source' ] : 'inherit';
		if ( $source !== 'custom' && $source !== 'google' ) { continue; }
		$family = bandit_lm_clean_font_family( isset( $s[ $key . '_family' ] ) ? $s[ $key . '_family' ] : '' );
		if ( $family === '' ) { continue; }
		$decls[] = $info['var'] . ":'" . $family . "'," . $info['fallback'];
	}

	return empty( $decls ) ? '' : '.bandit-lm-root{' . implode( ';', $decls ) . '}';
}

/**
 * Assemble a Google Fonts URL for any font role whose source is "google".
 * Uses the forgiving v1 CSS API (unavailable weights are ignored, not errored).
 */
function bandit_lm_appearance_google_font_url() {
	$s        = bandit_lm_get_settings();
	$families = array();

	foreach ( bandit_lm_appearance_fonts() as $key => $info ) {
		$source = isset( $s[ $key . '_source' ] ) ? $s[ $key . '_source' ] : 'inherit';
		if ( $source !== 'google' ) { continue; }
		$family = bandit_lm_clean_font_family( isset( $s[ $key . '_family' ] ) ? $s[ $key . '_family' ] : '' );
		if ( $family !== '' ) { $families[ $family ] = true; }
	}
	if ( empty( $families ) ) { return ''; }

	$parts = array();
	foreach ( array_keys( $families ) as $family ) {
		$parts[] = str_replace( ' ', '+', $family ) . ':400,500,600,700';
	}
	return 'https://fonts.googleapis.com/css?family=' . implode( '|', $parts ) . '&display=swap';
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
