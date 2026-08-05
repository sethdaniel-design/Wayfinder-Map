<?php
/**
 * Global plugin settings: map image, hotel pin position, toggles.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', 'bandit_lm_register_settings_page' );
function bandit_lm_register_settings_page() {
	add_submenu_page(
		'edit.php?post_type=bandit_map_point',
		__( 'Map Settings', 'bandit-locations-map' ),
		__( 'Map Settings', 'bandit-locations-map' ),
		'manage_options',
		'bandit-lm-settings',
		'bandit_lm_render_settings_page'
	);
}

add_action( 'admin_init', 'bandit_lm_register_settings' );
function bandit_lm_register_settings() {
	register_setting( 'bandit_lm_settings_group', 'bandit_lm_settings', array(
		'sanitize_callback' => 'bandit_lm_sanitize_settings',
	) );
}

function bandit_lm_sanitize_settings( $input ) {
	$out                   = array();
	$out['map_image_id']   = isset( $input['map_image_id'] ) ? (int) $input['map_image_id'] : 0;
	$out['hotel_x']        = isset( $input['hotel_x'] ) ? max( 0, min( 100, floatval( $input['hotel_x'] ) ) ) : 50.0;
	$out['hotel_y']        = isset( $input['hotel_y'] ) ? max( 0, min( 100, floatval( $input['hotel_y'] ) ) ) : 50.0;
	$out['hotel_label']    = isset( $input['hotel_label'] ) ? sanitize_text_field( $input['hotel_label'] ) : 'The Bandit';
	$out['hotel_sublabel'] = isset( $input['hotel_sublabel'] ) ? sanitize_text_field( $input['hotel_sublabel'] ) : '';
	$out['hotel_color']    = isset( $input['hotel_color'] ) ? ( sanitize_hex_color( $input['hotel_color'] ) ?: '#CA5A35' ) : '#CA5A35';
	$out['show_compass']   = ! empty( $input['show_compass'] ) ? 1 : 0;
	$out['show_scale']     = ! empty( $input['show_scale'] ) ? 1 : 0;
	$out['show_legend']    = ! empty( $input['show_legend'] ) ? 1 : 0;
	$out['show_hotel_pin'] = ! empty( $input['show_hotel_pin'] ) ? 1 : 0;
	return $out;
}

function bandit_lm_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = bandit_lm_get_settings();
	wp_enqueue_media();
	?>
	<div class="wrap bandit-lm-settings">
		<h1><?php esc_html_e( 'Bandit Map — Global Settings', 'bandit-locations-map' ); ?></h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'bandit_lm_settings_group' ); ?>

			<h2><?php esc_html_e( 'Map Background', 'bandit-locations-map' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label><?php esc_html_e( 'Map Image', 'bandit-locations-map' ); ?></label></th>
					<td>
						<input type="hidden" id="bandit_map_image_id" name="bandit_lm_settings[map_image_id]" value="<?php echo esc_attr( $s['map_image_id'] ); ?>" />
						<div id="bandit_map_image_preview" style="margin-bottom:8px;">
							<?php if ( $s['map_image_url'] ) : ?>
								<img src="<?php echo esc_url( $s['map_image_url'] ); ?>" style="max-width:520px;height:auto;border:1px solid #ddd;display:block;" />
							<?php else : ?>
								<em><?php esc_html_e( 'No image set. Pin placement will fall back to a blank canvas.', 'bandit-locations-map' ); ?></em>
							<?php endif; ?>
						</div>
						<button type="button" class="button" id="bandit_map_image_pick"><?php esc_html_e( 'Choose / Replace Image', 'bandit-locations-map' ); ?></button>
						<button type="button" class="button-link" id="bandit_map_image_clear" style="color:#a00;margin-left:8px;"><?php esc_html_e( 'Remove', 'bandit-locations-map' ); ?></button>
						<p class="description">
							<?php esc_html_e( 'Any aspect ratio works — illustrated, top-down, or perspective. Recommended: at least 2000px wide for sharpness on desktop.', 'bandit-locations-map' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Hotel Pin (Your Location)', 'bandit-locations-map' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Show hotel pin on map', 'bandit-locations-map' ); ?></th>
					<td><label><input type="checkbox" name="bandit_lm_settings[show_hotel_pin]" value="1" <?php checked( $s['show_hotel_pin'], 1 ); ?> /> <?php esc_html_e( 'Display "You Are Here" marker', 'bandit-locations-map' ); ?></label></td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Position', 'bandit-locations-map' ); ?></label></th>
					<td>
						<div id="bandit-lm-hotel-placer"
							data-map-url="<?php echo esc_attr( $s['map_image_url'] ); ?>"
							data-hotel-x="<?php echo esc_attr( $s['hotel_x'] ); ?>"
							data-hotel-y="<?php echo esc_attr( $s['hotel_y'] ); ?>"
							data-hotel-color="<?php echo esc_attr( $s['hotel_color'] ); ?>"
						></div>
						<p style="margin-top:8px;">
							<label>X: <input type="number" step="0.1" min="0" max="100" id="bandit_hotel_x" name="bandit_lm_settings[hotel_x]" value="<?php echo esc_attr( $s['hotel_x'] ); ?>" class="small-text" /></label>
							&nbsp;&nbsp;
							<label>Y: <input type="number" step="0.1" min="0" max="100" id="bandit_hotel_y" name="bandit_lm_settings[hotel_y]" value="<?php echo esc_attr( $s['hotel_y'] ); ?>" class="small-text" /></label>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="bandit_hotel_label"><?php esc_html_e( 'Label', 'bandit-locations-map' ); ?></label></th>
					<td><input type="text" id="bandit_hotel_label" name="bandit_lm_settings[hotel_label]" value="<?php echo esc_attr( $s['hotel_label'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="bandit_hotel_sublabel"><?php esc_html_e( 'Sub-label', 'bandit-locations-map' ); ?></label></th>
					<td>
						<input type="text" id="bandit_hotel_sublabel" name="bandit_lm_settings[hotel_sublabel]" value="<?php echo esc_attr( $s['hotel_sublabel'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Shown under the label. Leave blank to hide.', 'bandit-locations-map' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="bandit_hotel_color"><?php esc_html_e( 'Hotel Pin Color', 'bandit-locations-map' ); ?></label></th>
					<td>
						<input type="text" id="bandit_hotel_color" name="bandit_lm_settings[hotel_color]" value="<?php echo esc_attr( $s['hotel_color'] ); ?>" />
						<input type="color" value="<?php echo esc_attr( $s['hotel_color'] ); ?>" onchange="document.getElementById('bandit_hotel_color').value=this.value" style="vertical-align:middle;margin-left:8px;" />
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Display Options', 'bandit-locations-map' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Compass', 'bandit-locations-map' ); ?></th>
					<td><label><input type="checkbox" name="bandit_lm_settings[show_compass]" value="1" <?php checked( $s['show_compass'], 1 ); ?> /> <?php esc_html_e( 'Show compass rose overlay', 'bandit-locations-map' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Scale Bar', 'bandit-locations-map' ); ?></th>
					<td><label><input type="checkbox" name="bandit_lm_settings[show_scale]" value="1" <?php checked( $s['show_scale'], 1 ); ?> /> <?php esc_html_e( 'Show scale bar (only meaningful if the map is to-scale)', 'bandit-locations-map' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Legend', 'bandit-locations-map' ); ?></th>
					<td><label><input type="checkbox" name="bandit_lm_settings[show_legend]" value="1" <?php checked( $s['show_legend'], 1 ); ?> /> <?php esc_html_e( 'Show category color legend', 'bandit-locations-map' ); ?></label></td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<hr style="margin:32px 0;" />
		<h2><?php esc_html_e( 'How To Use', 'bandit-locations-map' ); ?></h2>
		<ol style="max-width:780px;line-height:1.7;">
			<li><strong><?php esc_html_e( 'Upload the map image above.', 'bandit-locations-map' ); ?></strong> <?php esc_html_e( 'Hand-illustrated, satellite, topographic — any 2D image works.', 'bandit-locations-map' ); ?></li>
			<li><strong><?php esc_html_e( 'Position the hotel pin.', 'bandit-locations-map' ); ?></strong> <?php esc_html_e( 'Click the map preview above.', 'bandit-locations-map' ); ?></li>
			<li><strong><?php esc_html_e( 'Add pin categories.', 'bandit-locations-map' ); ?></strong> <?php esc_html_e( 'Bandit Map → Pin Categories. Each category has its own color.', 'bandit-locations-map' ); ?></li>
			<li><strong><?php esc_html_e( 'Add map points.', 'bandit-locations-map' ); ?></strong> <?php esc_html_e( 'Bandit Map → Add Map Point. Click on the live map preview to place each pin.', 'bandit-locations-map' ); ?></li>
			<li><strong><?php esc_html_e( 'Embed in any page or Divi module:', 'bandit-locations-map' ); ?></strong>
				<ul style="margin-left:24px;list-style:disc;">
					<li><code>[bandit_locations_map]</code> — <?php esc_html_e( 'the interactive map with drawer', 'bandit-locations-map' ); ?></li>
					<li><code>[bandit_locations_list]</code> — <?php esc_html_e( 'the full sortable list table', 'bandit-locations-map' ); ?></li>
					<li><code>[bandit_locations_full]</code> — <?php esc_html_e( 'map + list, stacked', 'bandit-locations-map' ); ?></li>
				</ul>
			</li>
		</ol>
	</div>
	<?php
}
