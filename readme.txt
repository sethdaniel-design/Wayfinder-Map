=== Wayfinder Map ===
Contributors:      sethdanieldesign
Tags:              map, locations, interactive map, points of interest, divi
Requires at least: 6.0
Tested up to:      6.5
Requires PHP:      7.4
Stable tag:        2.2.1
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
3. Create a map at **Wayfinder Map → Add Map** — upload its background image and place its hotel pin
4. Add categories at **Wayfinder Map → Pin Categories** (a few defaults are seeded for you)
5. Add pins at **Wayfinder Map → Add Map Point**, ticking each map the location appears on and placing it
6. Set shared colors/fonts under **Wayfinder Map → Map Settings**
7. Drop the shortcode into any page or Divi module:
   * `[wayfinder_map]` — the interactive map with side drawer
   * `[wayfinder_list]` — the full sortable list table
   * `[wayfinder_full]` — both, stacked

== Shortcode Options ==

`[wayfinder_map height="720" filter="All" show_filters="1"]`

* `height` — minimum height in px (default 720)
* `filter` — pre-select a category by name (default "All")
* `show_filters` — `1` or `0` to show/hide the category Filter dropdown in the legend

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

= 2.2.1 =
* The cover-fit starting view now centers on the currently-selected location (clamped to the map edges), so the open pin is always in view instead of possibly being cropped off to the side. Deep links and list clicks also pan to the location.

= 2.2.0 =
* Pins, the hotel marker, and labels now keep a **constant on-screen size while you zoom** (they no longer balloon as you zoom in).
* Maps now **start zoomed to fill the panel** (cover-fit), so a wide/short image no longer sits letterboxed — including in fullscreen. You can still zoom back out.
* New per-pin **Label position** option (Above / Below / Left / Right) on the Map Point editor.

= 2.1.0 =
* Moved the map switcher to the top-left and removed the separate filter chip row (it was overlapping the switcher).
* Category filtering now lives in the **legend**: it shows up to 5 categories as a colour key, with a **Filter** dropdown listing every category.
* Added an **eye button** to hide/show the on-map overlays (switcher, legend, HUD, compass) for a clean view.

= 2.0.0 =
* **Multiple maps.** Create any number of maps (Wayfinder Map → Maps), each with its own background image, hotel pin, and display toggles. A switcher bar above the map lets visitors flip between them instantly.
* Locations can appear on **several maps at once**, each with its own position — set under "Maps & Positions" in the pin editor.
* Colors, fonts, and marker size are shared across all maps (Map Settings); image / hotel pin / toggles are per-map.
* New `map="slug"` attribute on `[wayfinder_map]` to embed one specific map (no switcher).
* Automatic, one-time migration wraps your existing map into "Map 1" and assigns all current pins to it — nothing to redo.

= 1.7.1 =
* Deep-link buttons now re-open and re-scroll to their location every time they're clicked — previously a second click did nothing if you were already on that link's hash.

= 1.7.0 =
* **Deep links:** each Map Point has a shareable link (e.g. your-page/#loc-pin-slug). Opening it (or clicking an in-page link to it) scrolls to the map and opens that location — resetting the category filter if needed so the pin is visible.
* Added a **Shareable link** field with a Copy button on the Map Point editor, and a **Map page URL** setting (Map Settings → Links) so those links are full copy-paste URLs.

= 1.6.2 =
* Fixed map pins not being clickable while in fullscreen (they now respond to hover and click as expected).

= 1.6.1 =
* Fixed the fullscreen toggle showing a black screen in some embeds. It no longer uses the native Fullscreen API; instead the map expands to a reliable full-viewport overlay (works even inside transformed page builders), with Esc to close.

= 1.6.0 =
* Added a **fullscreen toggle** to the map (uses the native Fullscreen API, with a fixed-overlay fallback where fullscreen isn't permitted).

= 1.5.0 =
* The admin pin-placement tool now shows a teardrop pin (matching the frontend) whose tip marks the exact spot.
* New **Map Markers** settings: adjust the **pin size** and toggle the **selected-pin pulse** animation on/off.

= 1.4.0 =
* Map markers are now **teardrop pins** (SVG) whose tip sits on the exact location, replacing the plain dots.
* Added a **per-pin color override** on each Map Point — falls back to the category color, then the default, when left blank.

= 1.3.0 =
* Finer color control: split "Text on dark areas" into separate **Map label text** and **Drawer text**, so map labels and drawer text can be colored independently.
* The drawer blurb, counter, and empty-state text now follow the Drawer text control (previously hardcoded and unchangeable).
* Added dedicated **Directions button** text and border color controls.
* Reorganized the color controls into Accents / Map area / Info panel / Location list, and the live preview now shows map labels, the hotel label, and the Directions button.

= 1.2.0 =
* Reworked the Appearance → Colors controls so each one changes exactly one thing (split the shared color tokens that previously did double-duty).
* Grouped color controls (Accents / Map & panel / Location list) with a **live preview** in Settings that recolors as you edit.
* Switched color fields to the native WordPress color picker (hex-first, with swatch and palette).

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
