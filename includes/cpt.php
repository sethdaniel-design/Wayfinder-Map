<?php
/**
 * Custom Post Type: bandit_map_point
 * Taxonomy:         bandit_pin_category
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', 'bandit_lm_register_cpt' );
function bandit_lm_register_cpt() {
	$labels = array(
		'name'               => __( 'Map Points', 'bandit-locations-map' ),
		'singular_name'      => __( 'Map Point', 'bandit-locations-map' ),
		'add_new'            => __( 'Add Map Point', 'bandit-locations-map' ),
		'add_new_item'       => __( 'Add New Map Point', 'bandit-locations-map' ),
		'edit_item'          => __( 'Edit Map Point', 'bandit-locations-map' ),
		'new_item'           => __( 'New Map Point', 'bandit-locations-map' ),
		'view_item'          => __( 'View Map Point', 'bandit-locations-map' ),
		'search_items'       => __( 'Search Map Points', 'bandit-locations-map' ),
		'not_found'          => __( 'No map points found', 'bandit-locations-map' ),
		'not_found_in_trash' => __( 'No map points in trash', 'bandit-locations-map' ),
		'menu_name'          => __( 'Wayfinder Map', 'bandit-locations-map' ),
		'all_items'          => __( 'All Map Points', 'bandit-locations-map' ),
	);

	register_post_type( 'bandit_map_point', array(
		'labels'             => $labels,
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true, // exposes to block editor + REST
		'menu_position'      => 26,
		'menu_icon'          => 'dashicons-location-alt',
		'capability_type'    => 'post',
		'supports'           => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
		'has_archive'        => false,
		'rewrite'            => false,
		'taxonomies'         => array( 'bandit_pin_category' ),
	) );
}

add_action( 'init', 'bandit_lm_register_category_taxonomy' );
function bandit_lm_register_category_taxonomy() {
	register_taxonomy( 'bandit_pin_category', array( 'bandit_map_point' ), array(
		'label'             => __( 'Pin Categories', 'bandit-locations-map' ),
		'hierarchical'      => false,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'show_in_menu'      => true,
		'rewrite'           => false,
	) );
}

/**
 * Seed a sensible default set of categories on activation.
 */
function bandit_lm_seed_default_categories() {
	$defaults = array(
		array( 'name' => 'Park',    'color' => '#3D5C38' ),
		array( 'name' => 'Trail',   'color' => '#CA5A35' ),
		array( 'name' => 'Town',    'color' => '#AB792B' ),
		array( 'name' => 'Airport', 'color' => '#8AABAD' ),
	);
	foreach ( $defaults as $cat ) {
		if ( ! term_exists( $cat['name'], 'bandit_pin_category' ) ) {
			$term = wp_insert_term( $cat['name'], 'bandit_pin_category' );
			if ( ! is_wp_error( $term ) ) {
				update_term_meta( $term['term_id'], 'bandit_color', $cat['color'] );
			}
		}
	}
}

/**
 * Color picker on the taxonomy term edit screen.
 */
add_action( 'bandit_pin_category_add_form_fields', 'bandit_lm_category_color_add_field' );
function bandit_lm_category_color_add_field() { ?>
	<div class="form-field">
		<label for="bandit_color"><?php esc_html_e( 'Pin Color', 'bandit-locations-map' ); ?></label>
		<input type="text" name="bandit_color" id="bandit_color" value="#5A2816" />
		<p class="description"><?php esc_html_e( 'Hex color used for pins in this category (e.g. #CA5A35).', 'bandit-locations-map' ); ?></p>
	</div>
<?php }

add_action( 'bandit_pin_category_edit_form_fields', 'bandit_lm_category_color_edit_field' );
function bandit_lm_category_color_edit_field( $term ) {
	$color = get_term_meta( $term->term_id, 'bandit_color', true );
	if ( ! $color ) { $color = '#5A2816'; }
	?>
	<tr class="form-field">
		<th scope="row"><label for="bandit_color"><?php esc_html_e( 'Pin Color', 'bandit-locations-map' ); ?></label></th>
		<td>
			<input type="text" name="bandit_color" id="bandit_color" value="<?php echo esc_attr( $color ); ?>" />
			<input type="color" value="<?php echo esc_attr( $color ); ?>" onchange="document.getElementById('bandit_color').value=this.value" style="vertical-align:middle;margin-left:8px;" />
			<p class="description"><?php esc_html_e( 'Hex color used for pins in this category.', 'bandit-locations-map' ); ?></p>
		</td>
	</tr>
<?php }

add_action( 'created_bandit_pin_category', 'bandit_lm_save_category_color' );
add_action( 'edited_bandit_pin_category',  'bandit_lm_save_category_color' );
function bandit_lm_save_category_color( $term_id ) {
	if ( isset( $_POST['bandit_color'] ) ) {
		$color = sanitize_hex_color( $_POST['bandit_color'] );
		if ( $color ) {
			update_term_meta( $term_id, 'bandit_color', $color );
		}
	}
}
