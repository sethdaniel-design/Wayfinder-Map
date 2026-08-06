<?php
/**
 * Pin editor: metaboxes + visual pin placement tool.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'add_meta_boxes_bandit_map_point', 'bandit_lm_add_metaboxes' );
function bandit_lm_add_metaboxes() {
	add_meta_box(
		'bandit_lm_placement',
		__( 'Maps & Positions', 'bandit-locations-map' ),
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
		$maps = get_posts( array(
			'post_type'      => 'bandit_map',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'post_status'    => 'publish',
		) );
		$positions = bandit_lm_get_pin_positions( $post->ID );

		if ( empty( $maps ) ) {
			echo '<p class="description">' . esc_html__( 'No maps yet. Create one under Wayfinder Map → Add Map, then come back here to place this pin.', 'bandit-locations-map' ) . '</p>';
			return;
		}
		?>
		<p class="description" style="margin:0 0 12px;">
			<?php esc_html_e( 'Tick each map this location should appear on, then click the map to place the pin (or drag it). A location can appear on several maps, each with its own position.', 'bandit-locations-map' ); ?>
		</p>
		<?php foreach ( $maps as $m ) :
			$ms  = bandit_lm_get_map_settings( $m->ID );
			$on  = isset( $positions[ $m->ID ] );
			$x   = $on ? $positions[ $m->ID ]['x'] : 50;
			$y   = $on ? $positions[ $m->ID ]['y'] : 50;
			$blk = 'blm-mappos-' . (int) $m->ID;
		?>
		<div class="blm-mappos" style="margin:0 0 14px;border:1px solid #e0d6c2;border-radius:4px;">
			<label style="display:block;padding:10px 12px;font-weight:600;background:#f6f1e7;cursor:pointer;">
				<input type="checkbox" class="blm-map-toggle" name="bandit_map_pos[<?php echo (int) $m->ID; ?>][on]" value="1" <?php checked( $on ); ?> data-target="<?php echo esc_attr( $blk ); ?>" />
				<?php echo esc_html( get_the_title( $m ) ); ?>
			</label>
			<div id="<?php echo esc_attr( $blk ); ?>" class="blm-mappos-body" style="padding:12px;<?php echo $on ? '' : 'display:none;'; ?>">
				<?php if ( $ms['map_image_url'] ) : ?>
					<div class="blm-multi-placer"
						data-map-url="<?php echo esc_attr( $ms['map_image_url'] ); ?>"
						data-x="<?php echo esc_attr( $x ); ?>"
						data-y="<?php echo esc_attr( $y ); ?>"
						data-x-input="bandit_pos_x_<?php echo (int) $m->ID; ?>"
						data-y-input="bandit_pos_y_<?php echo (int) $m->ID; ?>"
					></div>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'This map has no background image yet. Add one to the map first.', 'bandit-locations-map' ); ?></p>
				<?php endif; ?>
				<p style="margin-top:8px;">
					<label>X: <input type="number" step="0.1" min="0" max="100" id="bandit_pos_x_<?php echo (int) $m->ID; ?>" name="bandit_map_pos[<?php echo (int) $m->ID; ?>][x]" value="<?php echo esc_attr( $x ); ?>" class="small-text" /></label>
					&nbsp;&nbsp;
					<label>Y: <input type="number" step="0.1" min="0" max="100" id="bandit_pos_y_<?php echo (int) $m->ID; ?>" name="bandit_map_pos[<?php echo (int) $m->ID; ?>][y]" value="<?php echo esc_attr( $y ); ?>" class="small-text" /></label>
				</p>
			</div>
		</div>
		<?php endforeach; ?>
		<?php
	} catch ( \Throwable $e ) {
		echo '<p style="color:#a00;"><strong>Pin placement failed to load.</strong> ' . esc_html( $e->getMessage() ) . '</p>';
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
	<table class="form-table">
			<tr>
				<th><label for="bandit_pin_color"><?php esc_html_e( 'Pin Color (override)', 'bandit-locations-map' ); ?></label></th>
				<td>
					<?php $pin_color = get_post_meta( $post->ID, 'bandit_pin_color', true ); ?>
					<input type="text" id="bandit_pin_color" name="bandit_pin_color" class="blm-color-field" value="<?php echo esc_attr( $pin_color ); ?>" placeholder="<?php esc_attr_e( 'Category color', 'bandit-locations-map' ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. Overrides this pin’s marker color. Leave blank to use the category color (falling back to the default).', 'bandit-locations-map' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		$blm_slug     = $post->post_name;
		$blm_settings = bandit_lm_get_settings();
		$blm_base     = isset( $blm_settings['map_page_url'] ) ? preg_replace( '/#.*$/', '', (string) $blm_settings['map_page_url'] ) : '';
		$blm_frag     = $blm_slug ? '#loc-' . $blm_slug : '';
		$blm_full     = ( $blm_base && $blm_slug ) ? ( $blm_base . $blm_frag ) : $blm_frag;
		?>
		<table class="form-table">
			<tr>
				<th><label for="bandit_share_link"><?php esc_html_e( 'Shareable link', 'bandit-locations-map' ); ?></label></th>
				<td>
					<?php if ( $blm_slug ) : ?>
						<input type="text" id="bandit_share_link" class="regular-text" readonly value="<?php echo esc_attr( $blm_full ); ?>" onclick="this.select();" style="max-width:520px;" />
						<button type="button" class="button" id="bandit_share_copy"><?php esc_html_e( 'Copy', 'bandit-locations-map' ); ?></button>
						<?php if ( $blm_base ) : ?>
							<p class="description"><?php esc_html_e( 'Links to this location. Paste it into any button or text link — on this page or elsewhere — to open the map on this pin.', 'bandit-locations-map' ); ?></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Append this to your map page URL, or set the Map page URL under Wayfinder Map → Map Settings → Links for a full copy-paste link.', 'bandit-locations-map' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Publish or save this pin first to get its shareable link.', 'bandit-locations-map' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
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

		// Per-map positions: which maps this pin appears on, and where on each.
		if ( isset( $_POST['bandit_map_pos'] ) && is_array( $_POST['bandit_map_pos'] ) ) {
			$positions = array();
			foreach ( $_POST['bandit_map_pos'] as $mid => $data ) {
				$mid = (int) $mid;
				if ( ! $mid || empty( $data['on'] ) ) { continue; }
				$px = isset( $data['x'] ) ? max( 0.0, min( 100.0, (float) $data['x'] ) ) : 50.0;
				$py = isset( $data['y'] ) ? max( 0.0, min( 100.0, (float) $data['y'] ) ) : 50.0;
				$positions[ $mid ] = array( 'x' => $px, 'y' => $py );
			}
			update_post_meta( $post_id, 'bandit_map_positions', $positions );
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

		// Per-pin color override (hex, blank = use category color)
		if ( isset( $_POST['bandit_pin_color'] ) ) {
			$raw   = is_scalar( $_POST['bandit_pin_color'] ) ? trim( (string) $_POST['bandit_pin_color'] ) : '';
			$color = ( $raw === '' ) ? '' : ( sanitize_hex_color( $raw ) ?: '' );
			update_post_meta( $post_id, 'bandit_pin_color', $color );
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
