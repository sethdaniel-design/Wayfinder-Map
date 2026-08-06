<?php
/**
 * Multiple maps: the `bandit_map` Custom Post Type, its per-map settings, the
 * map editor metaboxes, the data layer that assembles each map with its pins,
 * and a one-time migration that wraps the pre-2.0 single map into "Map 1".
 *
 * Data model:
 *  - Each map is a `bandit_map` post. Per-map settings live in post meta
 *    (background image, hotel pin, display toggles).
 *  - A pin (`bandit_map_point`) stores `bandit_map_positions`: an array keyed by
 *    map ID -> array( 'x' => float, 'y' => float ). Presence of a map ID means
 *    the pin appears on that map, at that map-specific position.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 *  Custom Post Type
 * ---------------------------------------------------------------------- */

add_action( 'init', 'bandit_lm_register_map_cpt' );
function bandit_lm_register_map_cpt() {
	register_post_type( 'bandit_map', array(
		'labels' => array(
			'name'               => __( 'Maps', 'bandit-locations-map' ),
			'singular_name'      => __( 'Map', 'bandit-locations-map' ),
			'add_new'            => __( 'Add Map', 'bandit-locations-map' ),
			'add_new_item'       => __( 'Add New Map', 'bandit-locations-map' ),
			'edit_item'          => __( 'Edit Map', 'bandit-locations-map' ),
			'new_item'           => __( 'New Map', 'bandit-locations-map' ),
			'view_item'          => __( 'View Map', 'bandit-locations-map' ),
			'search_items'       => __( 'Search Maps', 'bandit-locations-map' ),
			'not_found'          => __( 'No maps yet', 'bandit-locations-map' ),
			'not_found_in_trash' => __( 'No maps in trash', 'bandit-locations-map' ),
			'all_items'          => __( 'Maps', 'bandit-locations-map' ),
			'menu_name'          => __( 'Maps', 'bandit-locations-map' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=bandit_map_point', // submenu under Wayfinder Map
		'supports'        => array( 'title', 'page-attributes' ),
		'has_archive'     => false,
		'rewrite'         => false,
	) );
}

/* -------------------------------------------------------------------------
 *  Per-map settings + pin positions (data helpers)
 * ---------------------------------------------------------------------- */

/**
 * A single meta value with a fallback when it has never been saved.
 */
function bandit_lm_map_meta( $map_id, $key, $default ) {
	$v = get_post_meta( $map_id, $key, true );
	return ( $v === '' || $v === null ) ? $default : $v;
}

/**
 * Full settings for one map, in the same shape the frontend expects for a map.
 */
function bandit_lm_get_map_settings( $map_id ) {
	$img_id  = (int) bandit_lm_map_meta( $map_id, 'bandit_map_image_id', 0 );
	$img_url = '';
	if ( $img_id ) {
		$src = wp_get_attachment_image_src( $img_id, 'full' );
		if ( $src ) { $img_url = $src[0]; }
	}
	return array(
		'map_image_id'   => $img_id,
		'map_image_url'  => $img_url,
		'hotel_x'        => (float) bandit_lm_map_meta( $map_id, 'bandit_map_hotel_x', 50.0 ),
		'hotel_y'        => (float) bandit_lm_map_meta( $map_id, 'bandit_map_hotel_y', 50.0 ),
		'hotel_label'    => (string) bandit_lm_map_meta( $map_id, 'bandit_map_hotel_label', '' ),
		'hotel_sublabel' => (string) bandit_lm_map_meta( $map_id, 'bandit_map_hotel_sublabel', '' ),
		'hotel_color'    => (string) bandit_lm_map_meta( $map_id, 'bandit_map_hotel_color', '#CA5A35' ),
		'show_hotel_pin' => (int) bandit_lm_map_meta( $map_id, 'bandit_map_show_hotel', 1 ),
		'show_compass'   => (int) bandit_lm_map_meta( $map_id, 'bandit_map_show_compass', 0 ),
		'show_scale'     => (int) bandit_lm_map_meta( $map_id, 'bandit_map_show_scale', 0 ),
		'show_legend'    => (int) bandit_lm_map_meta( $map_id, 'bandit_map_show_legend', 1 ),
	);
}

/**
 * A pin's per-map positions as array( map_id => array('x'=>float,'y'=>float) ).
 */
function bandit_lm_get_pin_positions( $pin_id ) {
	$raw = get_post_meta( $pin_id, 'bandit_map_positions', true );
	if ( ! is_array( $raw ) ) { return array(); }
	$out = array();
	foreach ( $raw as $mid => $pos ) {
		if ( ! is_array( $pos ) ) { continue; }
		$out[ (int) $mid ] = array(
			'x' => isset( $pos['x'] ) ? (float) $pos['x'] : 50.0,
			'y' => isset( $pos['y'] ) ? (float) $pos['y'] : 50.0,
		);
	}
	return $out;
}

/**
 * All published maps, each hydrated with its settings and its pins (with the
 * per-map position resolved onto each pin's x/y). Ordered by menu_order.
 */
function bandit_lm_get_all_maps() {
	$maps = get_posts( array(
		'post_type'      => 'bandit_map',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'post_status'    => 'publish',
	) );
	if ( empty( $maps ) ) { return array(); }

	$all_pins = bandit_lm_get_all_points(); // shared pin content (name/blurb/category/...)

	$out = array();
	foreach ( $maps as $m ) {
		$map_pins = array();
		foreach ( $all_pins as $p ) {
			$positions = bandit_lm_get_pin_positions( $p['id'] );
			if ( isset( $positions[ $m->ID ] ) ) {
				$pin      = $p;
				$pin['x'] = $positions[ $m->ID ]['x'];
				$pin['y'] = $positions[ $m->ID ]['y'];
				$map_pins[] = $pin;
			}
		}
		$out[] = array(
			'id'       => $m->ID,
			'slug'     => $m->post_name,
			'name'     => get_the_title( $m ),
			'settings' => bandit_lm_get_map_settings( $m->ID ),
			'pins'     => $map_pins,
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 *  Map editor metaboxes
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes_bandit_map', 'bandit_lm_add_map_metaboxes' );
function bandit_lm_add_map_metaboxes() {
	add_meta_box( 'bandit_map_bg', __( 'Map Background', 'bandit-locations-map' ), 'bandit_lm_render_map_bg_box', 'bandit_map', 'normal', 'high' );
	add_meta_box( 'bandit_map_hotel', __( 'Hotel / “You Are Here” Pin', 'bandit-locations-map' ), 'bandit_lm_render_map_hotel_box', 'bandit_map', 'normal', 'default' );
	add_meta_box( 'bandit_map_display', __( 'Display Options', 'bandit-locations-map' ), 'bandit_lm_render_map_display_box', 'bandit_map', 'side', 'default' );
}

function bandit_lm_render_map_bg_box( $post ) {
	wp_nonce_field( 'bandit_lm_save_map', 'bandit_lm_map_nonce' );
	$s = bandit_lm_get_map_settings( $post->ID );
	?>
	<input type="hidden" id="bandit_map_image_id" name="bandit_map_image_id" value="<?php echo esc_attr( $s['map_image_id'] ); ?>" />
	<div id="bandit_map_image_preview" style="margin-bottom:8px;">
		<?php if ( $s['map_image_url'] ) : ?>
			<img src="<?php echo esc_url( $s['map_image_url'] ); ?>" style="max-width:520px;height:auto;border:1px solid #ddd;display:block;" />
		<?php else : ?>
			<em><?php esc_html_e( 'No image set for this map yet.', 'bandit-locations-map' ); ?></em>
		<?php endif; ?>
	</div>
	<button type="button" class="button" id="bandit_map_image_pick"><?php esc_html_e( 'Choose / Replace Image', 'bandit-locations-map' ); ?></button>
	<button type="button" class="button-link" id="bandit_map_image_clear" style="color:#a00;margin-left:8px;"><?php esc_html_e( 'Remove', 'bandit-locations-map' ); ?></button>
	<p class="description"><?php esc_html_e( 'Any aspect ratio works. Recommended: at least 2000px wide for sharpness on desktop.', 'bandit-locations-map' ); ?></p>
	<?php
}

function bandit_lm_render_map_hotel_box( $post ) {
	$s = bandit_lm_get_map_settings( $post->ID );
	?>
	<p>
		<label><input type="checkbox" name="bandit_map_show_hotel" value="1" <?php checked( $s['show_hotel_pin'], 1 ); ?> /> <?php esc_html_e( 'Show the “You Are Here” marker on this map', 'bandit-locations-map' ); ?></label>
	</p>
	<div id="bandit-lm-hotel-placer"
		data-map-url="<?php echo esc_attr( $s['map_image_url'] ); ?>"
		data-hotel-x="<?php echo esc_attr( $s['hotel_x'] ); ?>"
		data-hotel-y="<?php echo esc_attr( $s['hotel_y'] ); ?>"
		data-hotel-color="<?php echo esc_attr( $s['hotel_color'] ); ?>"
	></div>
	<p style="margin-top:8px;">
		<label>X: <input type="number" step="0.1" min="0" max="100" id="bandit_hotel_x" name="bandit_map_hotel_x" value="<?php echo esc_attr( $s['hotel_x'] ); ?>" class="small-text" /></label>
		&nbsp;&nbsp;
		<label>Y: <input type="number" step="0.1" min="0" max="100" id="bandit_hotel_y" name="bandit_map_hotel_y" value="<?php echo esc_attr( $s['hotel_y'] ); ?>" class="small-text" /></label>
	</p>
	<table class="form-table">
		<tr>
			<th><label for="bandit_hotel_label"><?php esc_html_e( 'Label', 'bandit-locations-map' ); ?></label></th>
			<td><input type="text" id="bandit_hotel_label" name="bandit_map_hotel_label" value="<?php echo esc_attr( $s['hotel_label'] ); ?>" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="bandit_hotel_sublabel"><?php esc_html_e( 'Sub-label', 'bandit-locations-map' ); ?></label></th>
			<td>
				<input type="text" id="bandit_hotel_sublabel" name="bandit_map_hotel_sublabel" value="<?php echo esc_attr( $s['hotel_sublabel'] ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Shown under the label. Leave blank to hide.', 'bandit-locations-map' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="bandit_hotel_color"><?php esc_html_e( 'Hotel Pin Color', 'bandit-locations-map' ); ?></label></th>
			<td>
				<input type="text" id="bandit_hotel_color" name="bandit_map_hotel_color" value="<?php echo esc_attr( $s['hotel_color'] ); ?>" />
				<input type="color" value="<?php echo esc_attr( $s['hotel_color'] ); ?>" onchange="document.getElementById('bandit_hotel_color').value=this.value" style="vertical-align:middle;margin-left:8px;" />
			</td>
		</tr>
	</table>
	<?php
}

function bandit_lm_render_map_display_box( $post ) {
	$s = bandit_lm_get_map_settings( $post->ID );
	?>
	<p><label><input type="checkbox" name="bandit_map_show_compass" value="1" <?php checked( $s['show_compass'], 1 ); ?> /> <?php esc_html_e( 'Compass rose', 'bandit-locations-map' ); ?></label></p>
	<p><label><input type="checkbox" name="bandit_map_show_legend" value="1" <?php checked( $s['show_legend'], 1 ); ?> /> <?php esc_html_e( 'Category legend', 'bandit-locations-map' ); ?></label></p>
	<p><label><input type="checkbox" name="bandit_map_show_scale" value="1" <?php checked( $s['show_scale'], 1 ); ?> /> <?php esc_html_e( 'Scale bar', 'bandit-locations-map' ); ?></label></p>
	<p class="description"><?php esc_html_e( 'Colors and fonts are shared across all maps — set them under Map Settings.', 'bandit-locations-map' ); ?></p>
	<?php
}

add_action( 'save_post_bandit_map', 'bandit_lm_save_map', 10, 2 );
function bandit_lm_save_map( $post_id, $post ) {
	try {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) { return; }
		if ( ! isset( $_POST['bandit_lm_map_nonce'] ) ) { return; }
		$nonce = sanitize_text_field( wp_unslash( $_POST['bandit_lm_map_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bandit_lm_save_map' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		update_post_meta( $post_id, 'bandit_map_image_id', isset( $_POST['bandit_map_image_id'] ) ? (int) $_POST['bandit_map_image_id'] : 0 );

		$hx = isset( $_POST['bandit_map_hotel_x'] ) ? max( 0.0, min( 100.0, (float) $_POST['bandit_map_hotel_x'] ) ) : 50.0;
		$hy = isset( $_POST['bandit_map_hotel_y'] ) ? max( 0.0, min( 100.0, (float) $_POST['bandit_map_hotel_y'] ) ) : 50.0;
		update_post_meta( $post_id, 'bandit_map_hotel_x', $hx );
		update_post_meta( $post_id, 'bandit_map_hotel_y', $hy );

		update_post_meta( $post_id, 'bandit_map_hotel_label', isset( $_POST['bandit_map_hotel_label'] ) ? sanitize_text_field( wp_unslash( $_POST['bandit_map_hotel_label'] ) ) : '' );
		update_post_meta( $post_id, 'bandit_map_hotel_sublabel', isset( $_POST['bandit_map_hotel_sublabel'] ) ? sanitize_text_field( wp_unslash( $_POST['bandit_map_hotel_sublabel'] ) ) : '' );

		$color = isset( $_POST['bandit_map_hotel_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['bandit_map_hotel_color'] ) ) : '';
		update_post_meta( $post_id, 'bandit_map_hotel_color', $color ? $color : '#CA5A35' );

		update_post_meta( $post_id, 'bandit_map_show_hotel', ! empty( $_POST['bandit_map_show_hotel'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'bandit_map_show_compass', ! empty( $_POST['bandit_map_show_compass'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'bandit_map_show_legend', ! empty( $_POST['bandit_map_show_legend'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'bandit_map_show_scale', ! empty( $_POST['bandit_map_show_scale'] ) ? 1 : 0 );
	} catch ( \Throwable $e ) {
		if ( function_exists( 'bandit_lm_log' ) ) {
			bandit_lm_log( 'save_map failed for post ' . (int) $post_id . ': ' . $e->getMessage() );
		}
	}
}

/* -------------------------------------------------------------------------
 *  One-time migration: wrap the pre-2.0 single map into "Map 1"
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', 'bandit_lm_maybe_migrate_maps' );
function bandit_lm_maybe_migrate_maps() {
	if ( get_option( 'bandit_lm_maps_migrated' ) ) { return; }

	// If maps already exist, nothing to migrate — just flag it done.
	$existing = get_posts( array( 'post_type' => 'bandit_map', 'posts_per_page' => 1, 'post_status' => 'any', 'fields' => 'ids' ) );
	if ( ! empty( $existing ) ) { update_option( 'bandit_lm_maps_migrated', 1 ); return; }

	$g = get_option( 'bandit_lm_settings', array() );
	if ( ! is_array( $g ) ) { $g = array(); }

	$map_id = wp_insert_post( array(
		'post_type'   => 'bandit_map',
		'post_status' => 'publish',
		'post_title'  => 'Map 1',
		'menu_order'  => 0,
	) );
	if ( is_wp_error( $map_id ) || ! $map_id ) { return; } // leave flag unset; retry next admin load

	update_post_meta( $map_id, 'bandit_map_image_id', isset( $g['map_image_id'] ) ? (int) $g['map_image_id'] : 0 );
	update_post_meta( $map_id, 'bandit_map_hotel_x', isset( $g['hotel_x'] ) ? (float) $g['hotel_x'] : 50.0 );
	update_post_meta( $map_id, 'bandit_map_hotel_y', isset( $g['hotel_y'] ) ? (float) $g['hotel_y'] : 50.0 );
	update_post_meta( $map_id, 'bandit_map_hotel_label', isset( $g['hotel_label'] ) ? $g['hotel_label'] : '' );
	update_post_meta( $map_id, 'bandit_map_hotel_sublabel', isset( $g['hotel_sublabel'] ) ? $g['hotel_sublabel'] : '' );
	update_post_meta( $map_id, 'bandit_map_hotel_color', isset( $g['hotel_color'] ) ? $g['hotel_color'] : '#CA5A35' );
	update_post_meta( $map_id, 'bandit_map_show_hotel', ! empty( $g['show_hotel_pin'] ) ? 1 : 0 );
	update_post_meta( $map_id, 'bandit_map_show_compass', ! empty( $g['show_compass'] ) ? 1 : 0 );
	update_post_meta( $map_id, 'bandit_map_show_scale', ! empty( $g['show_scale'] ) ? 1 : 0 );
	update_post_meta( $map_id, 'bandit_map_show_legend', ! empty( $g['show_legend'] ) ? 1 : 0 );

	// Assign every existing pin to Map 1 at its current position.
	$pins = get_posts( array( 'post_type' => 'bandit_map_point', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
	foreach ( $pins as $pid ) {
		$x = get_post_meta( $pid, 'bandit_x', true );
		$y = get_post_meta( $pid, 'bandit_y', true );
		$x = ( $x === '' ) ? 50.0 : (float) $x;
		$y = ( $y === '' ) ? 50.0 : (float) $y;
		$positions = get_post_meta( $pid, 'bandit_map_positions', true );
		if ( ! is_array( $positions ) ) { $positions = array(); }
		$positions[ $map_id ] = array( 'x' => $x, 'y' => $y );
		update_post_meta( $pid, 'bandit_map_positions', $positions );
	}

	update_option( 'bandit_lm_maps_migrated', 1 );
}
