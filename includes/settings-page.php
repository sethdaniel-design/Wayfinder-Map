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
	$out['hotel_label']    = isset( $input['hotel_label'] ) ? sanitize_text_field( $input['hotel_label'] ) : 'Your Location';
	$out['hotel_sublabel'] = isset( $input['hotel_sublabel'] ) ? sanitize_text_field( $input['hotel_sublabel'] ) : '';
	$out['hotel_color']    = isset( $input['hotel_color'] ) ? ( sanitize_hex_color( $input['hotel_color'] ) ?: '#CA5A35' ) : '#CA5A35';
	$out['show_compass']   = ! empty( $input['show_compass'] ) ? 1 : 0;
	$out['show_scale']     = ! empty( $input['show_scale'] ) ? 1 : 0;
	$out['show_legend']    = ! empty( $input['show_legend'] ) ? 1 : 0;
	$out['show_hotel_pin'] = ! empty( $input['show_hotel_pin'] ) ? 1 : 0;
	$out['pin_size']       = isset( $input['pin_size'] ) ? max( 40, min( 300, (int) $input['pin_size'] ) ) : 100;
	$out['show_pulse']     = ! empty( $input['show_pulse'] ) ? 1 : 0;

	// Appearance — colors (blank = inherit) and per-role font source/family.
	foreach ( bandit_lm_appearance_colors() as $ac_key => $ac_info ) {
		$raw = isset( $input[ $ac_key ] ) ? trim( (string) $input[ $ac_key ] ) : '';
		$out[ $ac_key ] = ( $raw === '' ) ? '' : ( sanitize_hex_color( $raw ) ?: '' );
	}
	$font_sources = array( 'inherit', 'custom', 'google' );
	foreach ( bandit_lm_appearance_fonts() as $af_key => $af_info ) {
		$src = isset( $input[ $af_key . '_source' ] ) ? $input[ $af_key . '_source' ] : 'inherit';
		$out[ $af_key . '_source' ] = in_array( $src, $font_sources, true ) ? $src : 'inherit';
		$out[ $af_key . '_family' ] = bandit_lm_clean_font_family( isset( $input[ $af_key . '_family' ] ) ? $input[ $af_key . '_family' ] : '' );
	}

	return $out;
}

function bandit_lm_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = bandit_lm_get_settings();
	wp_enqueue_media();
	?>
	<div class="wrap bandit-lm-settings">
		<h1><?php esc_html_e( 'Wayfinder Map — Global Settings', 'bandit-locations-map' ); ?></h1>

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

			<h2><?php esc_html_e( 'Map Markers', 'bandit-locations-map' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="bandit_pin_size"><?php esc_html_e( 'Pin size', 'bandit-locations-map' ); ?></label></th>
					<td>
						<input type="number" id="bandit_pin_size" name="bandit_lm_settings[pin_size]" value="<?php echo esc_attr( $s['pin_size'] ); ?>" min="40" max="300" step="10" class="small-text" /> %
						<p class="description"><?php esc_html_e( 'Size of the location pin markers on the map (100% = default). Try 60–160.', 'bandit-locations-map' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Marker pulse', 'bandit-locations-map' ); ?></th>
					<td><label><input type="checkbox" name="bandit_lm_settings[show_pulse]" value="1" <?php checked( $s['show_pulse'], 1 ); ?> /> <?php esc_html_e( 'Animate a pulse ring around the selected pin', 'bandit-locations-map' ); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Appearance', 'bandit-locations-map' ); ?></h2>
			<p class="description" style="max-width:820px;margin-bottom:4px;">
				<?php esc_html_e( 'Leave a color blank or a font set to “Inherit” to use your theme’s styling (the default). Fill in a value to override just that piece — everything else keeps inheriting.', 'bandit-locations-map' ); ?>
			</p>

			<h3 style="margin-bottom:0;"><?php esc_html_e( 'Colors', 'bandit-locations-map' ); ?></h3>
			<?php
			// Build the live-preview inline style from saved overrides, and group the controls.
			$ap_preview_style = '';
			$ap_groups        = array();
			foreach ( bandit_lm_appearance_colors() as $ac_key => $ac_info ) {
				$ap_groups[ $ac_info['group'] ][ $ac_key ] = $ac_info;
				$ac_val = isset( $s[ $ac_key ] ) ? trim( (string) $s[ $ac_key ] ) : '';
				if ( $ac_val !== '' ) { $ap_preview_style .= $ac_info['var'] . ':' . $ac_val . ';'; }
			}
			?>
			<div class="blm-ap-wrap">
				<div class="blm-ap-controls">
					<?php foreach ( $ap_groups as $ap_group_name => $ap_group_items ) : ?>
						<h4 class="blm-ap-group"><?php echo esc_html( $ap_group_name ); ?></h4>
						<table class="form-table blm-ap-table">
							<?php foreach ( $ap_group_items as $ac_key => $ac_info ) :
								$ac_val = isset( $s[ $ac_key ] ) ? $s[ $ac_key ] : '';
							?>
							<tr>
								<th>
									<label for="<?php echo esc_attr( $ac_key ); ?>"><?php echo esc_html( $ac_info['label'] ); ?></label>
									<?php if ( $ac_info['desc'] ) : ?><span class="blm-ap-desc"><?php echo esc_html( $ac_info['desc'] ); ?></span><?php endif; ?>
								</th>
								<td>
									<input type="text" id="<?php echo esc_attr( $ac_key ); ?>" class="blm-color-field" data-cssvar="<?php echo esc_attr( $ac_info['var'] ); ?>" name="bandit_lm_settings[<?php echo esc_attr( $ac_key ); ?>]" value="<?php echo esc_attr( $ac_val ); ?>" placeholder="<?php esc_attr_e( 'Inherit', 'bandit-locations-map' ); ?>" />
								</td>
							</tr>
							<?php endforeach; ?>
						</table>
					<?php endforeach; ?>
				</div>

				<div class="blm-ap-preview-col">
					<div class="blm-ap-preview-label"><?php esc_html_e( 'Live preview', 'bandit-locations-map' ); ?></div>
					<div class="blm-ap-preview" id="blm-ap-preview" style="<?php echo esc_attr( $ap_preview_style ); ?>">
						<div class="blm-ap-map">
							<span class="blm-ap-chip"><?php esc_html_e( 'All', 'bandit-locations-map' ); ?></span>
							<span class="blm-ap-pin" style="left:22%;top:52%;"></span>
							<span class="blm-ap-maplabel" style="left:44%;top:20%;">Castle Rock</span>
							<span class="blm-ap-pin blm-ap-pin--active" style="left:44%;top:34%;"></span>
							<span class="blm-ap-hotel" style="left:74%;top:58%;"></span>
							<span class="blm-ap-maplabel blm-ap-hotellabel" style="left:74%;top:72%;"><?php esc_html_e( 'THE BANDIT', 'bandit-locations-map' ); ?></span>
						</div>
						<div class="blm-ap-drawer">
							<div class="blm-ap-cat"><?php esc_html_e( 'TRAIL', 'bandit-locations-map' ); ?></div>
							<div class="blm-ap-title"><?php esc_html_e( 'Castle Rock', 'bandit-locations-map' ); ?></div>
							<div class="blm-ap-rule"></div>
							<div class="blm-ap-text"><?php esc_html_e( 'A short description of the location, shown in the drawer.', 'bandit-locations-map' ); ?></div>
							<div class="blm-ap-stats">
								<span><i><?php esc_html_e( 'DRIVE', 'bandit-locations-map' ); ?></i><b>45 min</b></span>
								<span><i><?php esc_html_e( 'DIST', 'bandit-locations-map' ); ?></i><b>38 mi</b></span>
							</div>
							<div class="blm-ap-btnrow">
								<span class="blm-ap-btn"><?php esc_html_e( 'Plan A Trip', 'bandit-locations-map' ); ?></span>
								<span class="blm-ap-btn2"><?php esc_html_e( 'Directions ↗', 'bandit-locations-map' ); ?></span>
							</div>
						</div>
						<div class="blm-ap-list">
							<div class="blm-ap-row blm-ap-row--head"><span>#</span><span><?php esc_html_e( 'PLACE', 'bandit-locations-map' ); ?></span><span><?php esc_html_e( 'TYPE', 'bandit-locations-map' ); ?></span></div>
							<div class="blm-ap-row"><span>01</span><span>Castle Rock</span><span>Trail</span></div>
							<div class="blm-ap-row"><span>02</span><span>Old Town</span><span>Town</span></div>
						</div>
					</div>
					<p class="description" style="margin-top:8px;"><?php esc_html_e( 'The preview uses the default palette as a baseline. Blank fields inherit your theme’s colors on the live site.', 'bandit-locations-map' ); ?></p>
				</div>
			</div>

			<h3 style="margin-bottom:0;"><?php esc_html_e( 'Fonts', 'bandit-locations-map' ); ?></h3>
			<table class="form-table">
				<?php foreach ( bandit_lm_appearance_fonts() as $af_key => $af_info ) :
					$af_src = isset( $s[ $af_key . '_source' ] ) ? $s[ $af_key . '_source' ] : 'inherit';
					$af_fam = isset( $s[ $af_key . '_family' ] ) ? $s[ $af_key . '_family' ] : '';
				?>
				<tr>
					<th><label><?php echo esc_html( $af_info['label'] ); ?></label></th>
					<td>
						<select name="bandit_lm_settings[<?php echo esc_attr( $af_key ); ?>_source]">
							<option value="inherit" <?php selected( $af_src, 'inherit' ); ?>><?php esc_html_e( 'Inherit from theme', 'bandit-locations-map' ); ?></option>
							<option value="custom"  <?php selected( $af_src, 'custom' ); ?>><?php esc_html_e( 'Custom (already loaded by theme)', 'bandit-locations-map' ); ?></option>
							<option value="google"  <?php selected( $af_src, 'google' ); ?>><?php esc_html_e( 'Google Font', 'bandit-locations-map' ); ?></option>
						</select>
						<input type="text" name="bandit_lm_settings[<?php echo esc_attr( $af_key ); ?>_family]" value="<?php echo esc_attr( $af_fam ); ?>" placeholder="<?php esc_attr_e( 'e.g. Poppins', 'bandit-locations-map' ); ?>" class="regular-text" style="max-width:220px;margin-left:8px;" />
						<p class="description"><?php esc_html_e( 'Font family name — used when the source is Custom or Google Font. For Google Fonts, type the exact family name (e.g. “Poppins”, “DM Serif Display”) and it loads automatically.', 'bandit-locations-map' ); ?></p>
					</td>
				</tr>
				<?php endforeach; ?>
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
			<li><strong><?php esc_html_e( 'Add pin categories.', 'bandit-locations-map' ); ?></strong> <?php esc_html_e( 'Wayfinder Map → Pin Categories. Each category has its own color.', 'bandit-locations-map' ); ?></li>
			<li><strong><?php esc_html_e( 'Add map points.', 'bandit-locations-map' ); ?></strong> <?php esc_html_e( 'Wayfinder Map → Add Map Point. Click on the live map preview to place each pin.', 'bandit-locations-map' ); ?></li>
			<li><strong><?php esc_html_e( 'Embed in any page or Divi module:', 'bandit-locations-map' ); ?></strong>
				<ul style="margin-left:24px;list-style:disc;">
					<li><code>[wayfinder_map]</code> — <?php esc_html_e( 'the interactive map with drawer', 'bandit-locations-map' ); ?></li>
					<li><code>[wayfinder_list]</code> — <?php esc_html_e( 'the full sortable list table', 'bandit-locations-map' ); ?></li>
					<li><code>[wayfinder_full]</code> — <?php esc_html_e( 'map + list, stacked', 'bandit-locations-map' ); ?></li>
				</ul>
			</li>
		</ol>
	</div>
	<?php
}
