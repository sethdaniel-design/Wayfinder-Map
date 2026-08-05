=== Wayfinder Map ===
Contributors:      sethdanieldesign
Tags:              map, locations, interactive map, points of interest, divi
Requires at least: 6.0
Tested up to:      6.5
Requires PHP:      7.4
Stable tag:        1.1.0
License:           GPL-2.0-or-later

Custom interactive locations map. Adds a Custom Post Type for map pins, a visual pin-placement tool, and three shortcodes for embedding the map anywhere — Divi 5 friendly.

== Description ==

A self-contained plugin for any Locations page (or anywhere you need an editable interactive map).

* **Custom Post Type** — every pin is a normal WordPress post you edit in the sidebar
* **Visual pin placer** — click or drag a pin on your map image to set position; no coordinate math required
* **Map-agnostic** — works with any 2D image: hand-illustrated, satellite, perspective, top-down
* **Categories with colors** — each category (Park / Trail / Town / Airport / custom) has its own pin color
* **Three shortcodes** — `[wayfinder_map]`, `[wayfinder_list]`, `[wayfinder_full]`
* **Divi 5 native styling** — inherits your `--gcid-*` color variables and `--et_global_*` fonts automatically
* **No build step, no React on the frontend** — pure vanilla JS for a fast page load

== Installation ==

1. Upload `wayfinder-map.zip` via Plugins → Add New → Upload Plugin
2. Activate
3. Go to **Wayfinder Map → Map Settings** to upload your map image and place the hotel pin
4. Add categories at **Wayfinder Map → Pin Categories** (a few defaults are seeded for you)
5. Add pins at **Wayfinder Map → Add Map Point**
6. Drop the shortcode into any page or Divi module:
   * `[wayfinder_map]` — the interactive map with side drawer
   * `[wayfinder_list]` — the full sortable list table
   * `[wayfinder_full]` — both, stacked

== Shortcode Options ==

`[wayfinder_map height="720" filter="All" show_filters="1"]`

* `height` — minimum height in px (default 720)
* `filter` — pre-select a category by name (default "All")
* `show_filters` — `1` or `0` to show/hide the filter chip row

`[wayfinder_list filter="All"]`

* `filter` — same as above

`[wayfinder_full height="720"]` — convenience wrapper that renders the map and list together

Note: the original `[bandit_locations_map]`, `[bandit_locations_list]`, and `[bandit_locations_full]` shortcodes still work as aliases, so existing embeds keep functioning.

== Frequently Asked Questions ==

= Does the map have to be top-down? =

No. Any 2D image works — perspective illustrations, axonometric maps, satellite tiles, or hand-drawn. Pins are placed in screen-space percentages, so the rendering is identical regardless of map style.

= What if I swap the map image? =

Pin positions are saved as X/Y percentages, so as long as the new map has the same proportions and same general geography, pins will still land roughly where you placed them. If proportions change, re-open each pin and click the new position on the map preview.

= Does this work with Divi 5? =

Yes. Drop the shortcode into any Text or Code module in the Divi Builder. The plugin also reads Divi's CSS variables (`--gcid-*`, `--et_global_*`) automatically, so colors and fonts match the rest of your site.

= Does it require ACF? =

No. The plugin uses native WordPress meta and custom metaboxes — no dependencies.

== Changelog ==

= 1.1.0 =
* New **Appearance** section in Map Settings: override the map's colors (9 tokens) and fonts (heading / body / accent) directly from the admin.
* Fonts can inherit from the theme, use a custom already-loaded family, or load a **Google Font** automatically.
* Every appearance control defaults to "inherit," so existing installs look identical until a value is set.

= 1.0.4 =
* Renamed the plugin to **Wayfinder Map** and removed site-specific branding so it can be reused across sites. All user-facing labels, menus, and headings are now generic.
* Added generic `[wayfinder_map]` / `[wayfinder_list]` / `[wayfinder_full]` shortcodes. The original `[bandit_locations_*]` shortcodes remain as backwards-compatible aliases.
* Aligned the plugin header version with the internal version constant.

= 1.0.3 =
* Pin clicks now use event delegation at the SVG layer (single listener, survives all repaints) — fixes click failures on WordPress sites where third-party scripts may interfere with per-pin listeners.
* Diagnostic `console.log` on pin click so you can verify in DevTools that clicks register.
* Hide the pin-count HUD overlay on mobile (≤720px) so it no longer overlaps the filter chips.
* CSS hardening with !important for pointer-events on pin layer — survives theme/plugin overrides.

= 1.0.2 =
* Hardened the Map Point save callback against unexpected errors (try/catch + debug.log diagnostic).
* Removed `register_post_meta` REST registration — meta is now plugin-internal only. Smaller surface, no REST schema conflicts.
* Stricter input casting and sanitization on save.

= 1.0.1 =
* Polyfill sanitize_hex_color() / sanitize_hex_color_no_hash() — fixes fatal "Call to undefined function" error on admin screens where the Customizer isn't loaded.

= 1.0.0 =
* Initial release
