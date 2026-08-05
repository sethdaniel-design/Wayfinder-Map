<?php
/**
 * Pin editor: metaboxes + visual pin placement tool.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'add_meta_boxes_bandit_map_point', 'bandit_lm_add_metaboxes' );
function bandit_lm_add_metaboxes() {
	add_meta_box(
		'bandit_lm_placement',
		__( 'Pin Placement', 'bandit-locations-map' ),
		'bandit_lm_render_placement_box',
		'bandit_map_point',
		'normal',
		'high'
	);
	add_meta_box(
		'bandit_lm_details',
		__( 'Pin Details', 'bandit-locations-map' ),
		'bandit_lm_render_details_box',
		'bandit_map_point',
		'normal',
		'default'
	);
}

function bandit_lm_render_placement_box( $post ) {
	try {
		wp_nonce_field( 'bandit_lm_save_meta', 'bandit_lm_meta_nonce' );
		$x = get_post_meta( $post->ID, 'bandit_x', true );
		$y = get_post_meta( $post->ID, 'bandit_y', true );
		if ( $x === '' || $x === null || $x === false ) { $x = 50; }
		if ( $y === '' || $y === null || $y === false ) { $y = 50; }
		$settings = bandit_lm_get_settings();
	?>
	<p class="description" style="margin:0 0 12px;">
		<?php esc_html_e( 'Click on the map to place this pin. You can also drag the pin to fine-tune. The X/Y values below update automatically.', 'bandit-locations-map' ); ?>
	</p>

	<div id="bandit-lm-placer"
		data-map-url="<?php echo esc_attr( $settings['map_image_url'] ); ?>"
		data-x="<?php echo esc_attr( $x ); ?>"
		data-y="<?php echo esc_attr( $y ); ?>"
		data-hotel-x="<?php echo esc_attr( $settings['hotel_x'] ); ?>"
		data-hotel-y="<?php echo esc_attr( $settings['hotel_y'] ); ?>"
		data-hotel-color="<?php echo esc_attr( $settings['hotel_color'] ); ?>"
		data-show-hotel="<?php echo (int) $settings['show_hotel_pin']; ?>"
	></div>

	<table class="form-table" style="margin-top:12px;">
		<tr>
			<th><label for="bandit_x"><?php esc_html_e( 'X (%)', 'bandit-locations-map' ); ?></label></th>
			<td><input type="number" step="0.1" min="0" max="100" id="bandit_x" name="bandit_x" value="<?php echo esc_attr( $x ); ?>" class="small-text" /></td>
			<th><label for="bandit_y"><?php esc_html_e( 'Y (%)', 'bandit-locations-map' ); ?></label></th>
			<td><input type="number" step="0.1" min="0" max="100" id="bandit_y" name="bandit_y" value="<?php echo esc_attr( $y ); ?>" class="small-text" /></td>
		</tr>
	</table>
	<?php
	} catch ( \Throwable $e ) {
		echo '<p style="color:#a00;"><strong>Pin placement tool failed to load.</strong> Error logged to debug.log: ' . esc_html( $e->getMessage() ) . '</p>';
		if ( function_exists( 'bandit_lm_log' ) ) {
			bandit_lm_log( 'render_placement_box failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
		}
	}
}

function bandit_lm_render_details_box( $post ) {
	$fields = array(
		'bandit_drive'          => array( 'Drive Time',     'e.g. "45 min" or "1 hr 20"' ),
		'bandit_distance'       => array( 'Distance',       'e.g. "38 mi"' ),
		'bandit_tags'           => array( 'Tags',           'Comma-separated, e.g. "Permit Required, Hike"' ),
		'bandit_cta_url'        => array( 'Primary CTA URL', 'Where the "Plan A Trip" button links. Leave blank to hide.' ),
		'bandit_directions_url' => array( 'Directions URL',  'Optional Google/Apple Maps link. Leave blank to hide.' ),
	);
	?>
	<table class="form-table">
		<?php foreach ( $fields as $key => $info ) :
			$value = get_post_meta( $post->ID, $key, true );
		?>
		<tr>
			<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $info[0] ); ?></label></th>
			<td>
				<input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
				<p class="description"><?php echo esc_html( $info[1] ); ?></p>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	<p class="description" style="margin-top:8px;">
		<strong><?php esc_html_e( 'Photo:', 'bandit-locations-map' ); ?></strong>
		<?php esc_html_e( 'Set the pin photo using the standard "Featured Image" box on the right.', 'bandit-locations-map' ); ?>
		<br>
		<strong><?php esc_html_e( 'Category:', 'bandit-locations-map' ); ?></strong>
		<?php esc_html_e( 'Assign one category in the "Pin Categories" box on the right (Park / Trail / Town / Airport, etc.).', 'bandit-locations-map' ); ?>
		<br>
		<strong><?php esc_html_e( 'Description:', 'bandit-locations-map' ); ?></strong>
		<?php esc_html_e( 'Use the main editor above for the blurb shown in the map drawer.', 'bandit-locations-map' ); ?>
	</p>
	<?php
}

add_action( 'save_post_bandit_map_point', 'bandit_lm_save_meta', 10, 2 );
function bandit_lm_save_meta( $post_id, $post ) {
	try {
		// Guard rails: bail in unsafe / irrelevant contexts.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) { return; }
		if ( ! isset( $_POST['bandit_lm_meta_nonce'] ) ) { return; }
		$nonce = sanitize_text_field( wp_unslash( $_POST['bandit_lm_meta_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bandit_lm_save_meta' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		// Numeric fields
		$numeric = array( 'bandit_x', 'bandit_y' );
		foreach ( $numeric as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$raw = is_scalar( $_POST[ $k ] ) ? (string) $_POST[ $k ] : '';
				$v = (float) $raw;
				$v = max( 0.0, min( 100.0, $v ) );
				update_post_meta( $post_id, $k, $v );
			}
		}

		// Text fields
		$text = array( 'bandit_drive', 'bandit_distance', 'bandit_tags', 'bandit_cta_url', 'bandit_directions_url' );
		$url_fields = array( 'bandit_cta_url', 'bandit_directions_url' );
		foreach ( $text as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$raw = is_scalar( $_POST[ $k ] ) ? wp_unslash( (string) $_POST[ $k ] ) : '';
				$v = in_array( $k, $url_fields, true ) ? esc_url_raw( $raw ) : sanitize_text_field( $raw );
				update_post_meta( $post_id, $k, $v );
			}
		}
	} catch ( \Throwable $e ) {
		// Catch any fatal/exception during save so the user sees the saved post,
		// not a critical-error screen. Logged for the developer.
		if ( function_exists( 'bandit_lm_log' ) ) {
			bandit_lm_log( 'save_meta failed for post ' . (int) $post_id . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
		}
	}
}

/**
 * Admin columns on the Map Points list table.
 */
add_filter( 'manage_bandit_map_point_posts_columns', 'bandit_lm_admin_columns' );
function bandit_lm_admin_columns( $cols ) {
	$out = array();
	$out['cb']        = $cols['cb'];
	$out['title']     = __( 'Name', 'bandit-locations-map' );
	$out['category']  = __( 'Category', 'bandit-locations-map' );
	$out['position']  = __( 'Position', 'bandit-locations-map' );
	$out['drive']     = __( 'Drive', 'bandit-locations-map' );
	$out['distance']  = __( 'Distance', 'bandit-locations-map' );
	$out['date']      = $cols['date'];
	return $out;
}

add_action( 'manage_bandit_map_point_posts_custom_column', 'bandit_lm_admin_column_value', 10, 2 );
function bandit_lm_admin_column_value( $col, $post_id ) {
	switch ( $col ) {
		case 'category':
			$cats = wp_get_post_terms( $post_id, 'bandit_pin_category' );
			if ( ! empty( $cats ) ) {
				$color = get_term_meta( $cats[0]->term_id, 'bandit_color', true );
				echo '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . esc_attr( $color ) . ';margin-right:6px;vertical-align:middle;"></span>';
				echo esc_html( $cats[0]->name );
			} else {
				echo '<em style="color:#a00;">' . esc_html__( '— none —', 'bandit-locations-map' ) . '</em>';
			}
			break;
		case 'position':
			$x = get_post_meta( $post_id, 'bandit_x', true );
			$y = get_post_meta( $post_id, 'bandit_y', true );
			echo '<code>' . esc_html( number_format( (float) $x, 1 ) . ', ' . number_format( (float) $y, 1 ) ) . '</code>';
			break;
		case 'drive':
			echo esc_html( get_post_meta( $post_id, 'bandit_drive', true ) );
			break;
		case 'distance':
			echo esc_html( get_post_meta( $post_id, 'bandit_distance', true ) );
			break;
	}
}
