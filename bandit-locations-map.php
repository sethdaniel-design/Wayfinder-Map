<?php
/**
 * Plugin Name:       Wayfinder Map
 * Plugin URI:        https://sethdanieldesign.com
 * Description:       Custom interactive locations map. Provides a Custom Post Type for map pins, a visual pin-placement tool, a global settings page, and shortcodes ([wayfinder_map], [wayfinder_list], [wayfinder_full]) for embedding the map anywhere — including Divi 5 Code/Text modules.
 * Version:           1.7.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Seth Daniel Design
 * License:           GPL-2.0-or-later
 * Text Domain:       bandit-locations-map
 * Update URI:        https://github.com/sethdaniel-design/Wayfinder-Map
 * GitHub Plugin URI: sethdaniel-design/Wayfinder-Map
 * Primary Branch:    main
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BANDIT_LM_VERSION', '1.7.1' );
define( 'BANDIT_LM_FILE',    __FILE__ );
define( 'BANDIT_LM_DIR',     plugin_dir_path( __FILE__ ) );
define( 'BANDIT_LM_URL',     plugin_dir_url( __FILE__ ) );

/**
 * Polyfill sanitize_hex_color() — WordPress core only declares this inside the
 * Customizer (wp-includes/class-wp-customize-manager.php). On any other admin
 * screen calling it triggers a fatal "Call to undefined function". This polyfill
 * matches WordPress's own implementation byte-for-byte.
 */
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		if ( '' === $color ) {
			return '';
		}
		if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
			return $color;
		}
		return null;
	}
}
if ( ! function_exists( 'sanitize_hex_color_no_hash' ) ) {
	function sanitize_hex_color_no_hash( $color ) {
		$color = ltrim( $color, '#' );
		if ( '' === $color ) {
			return '';
		}
		return sanitize_hex_color( '#' . $color ) ? $color : null;
	}
}

require_once BANDIT_LM_DIR . 'includes/cpt.php';
require_once BANDIT_LM_DIR . 'includes/meta.php';
require_once BANDIT_LM_DIR . 'includes/admin-metaboxes.php';
require_once BANDIT_LM_DIR . 'includes/settings-page.php';
require_once BANDIT_LM_DIR . 'includes/shortcodes.php';
require_once BANDIT_LM_DIR . 'includes/enqueue.php';

register_activation_hook( __FILE__, function() {
	bandit_lm_register_cpt();
	bandit_lm_register_category_taxonomy();
	bandit_lm_seed_default_categories();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );
