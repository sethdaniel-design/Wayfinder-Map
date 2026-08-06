/**
 * Wayfinder Map — frontend renderer (vanilla JS, no dependencies)
 * Hydrates any element with .bandit-lm-root, reading window.BanditLocationsData.
 */
(function () {
	'use strict';

	if (typeof window.BanditLocationsData === 'undefined') return;
	var DATA = window.BanditLocationsData;
	var PINS = DATA.pins || [];
	var CATS = DATA.categories || [];
	var SETTINGS = DATA.settings || {};

	function el(tag, attrs, children) {
		var n = document.createElement(tag);
		applyAttrs(n, attrs);
		appendKids(n, children);
		return n;
	}
	function svg(tag, attrs, children) {
		var n = document.createElementNS('http://www.w3.org/2000/svg', tag);
		applyAttrs(n, attrs);
		appendKids(n, children);
		return n;
	}
	function applyAttrs(n, attrs) {
		if (!attrs) return;
		Object.keys(attrs).forEach(function (k) {
			var v = attrs[k];
			if (v === null || v === undefined) return;
			if (k === 'style' && typeof v === 'object') {
				Object.keys(v).forEach(function (s) { n.style[s] = v[s]; });
			} else if (k === 'className') {
				n.setAttribute('class', v);
			} else if (k.indexOf('on') === 0 && typeof v === 'function') {
				n.addEventListener(k.slice(2).toLowerCase(), v);
			} else {
				n.setAttribute(k, v);
			}
		});
	}
	function appendKids(n, children) {
		(children || []).forEach(function (c) {
			if (c == null) return;
			if (typeof c === 'string' || typeof c === 'number') n.appendChild(document.createTextNode(String(c)));
			else n.appendChild(c);
		});
	}
	function findCat(name) {
		for (var i = 0; i < CATS.length; i++) if (CATS[i].name === name) return CATS[i];
		return null;
	}
	function catColor(name) {
		var c = findCat(name);
		return c ? c.color : '#5A2816';
	}
	// Effective marker colour: per-pin override -> category colour -> default.
	function effColor(p) {
		if (p && p.pin_color) return p.pin_color;
		return (p && p.category_color) ? p.category_color : catColor(p ? p.category : '');
	}
	// Teardrop map-pin path with its tip at (cx, tipY) and total height h.
	function pinPath(cx, tipY, h) {
		var r = h * 0.32;
		var cy = tipY - h + r; // head centre
		return 'M' + cx + ',' + tipY +
			'C' + (cx - r * 0.75) + ',' + (tipY - h * 0.5) + ' ' + (cx - r) + ',' + (cy + r * 0.55) + ' ' + (cx - r) + ',' + cy +
			'A' + r + ',' + r + ' 0 1,1 ' + (cx + r) + ',' + cy +
			'C' + (cx + r) + ',' + (cy + r * 0.55) + ' ' + (cx + r * 0.75) + ',' + (tipY - h * 0.5) + ' ' + cx + ',' + tipY + 'Z';
	}

	// ---------- MAP COMPONENT ----------
	function renderMap(root) {
		root.innerHTML = '';
		root.classList.add('bandit-lm-rendered');

		var height = parseInt(root.dataset.height || '720', 10);
		var showFilters = root.dataset.showFilters !== '0';
		var activeId = root.dataset.active ? parseInt(root.dataset.active, 10) : (PINS[0] ? PINS[0].id : null);
		var hoverId = null;
		var filter = root.dataset.filter || 'All';

		// Pan / zoom state (translation in container pixels, scale multiplier)
		var tx = 0, ty = 0, scale = 1;
		var MIN_SCALE = 1;
		var MAX_SCALE = 5;

		function visible() { return filter === 'All' ? PINS : PINS.filter(function (p) { return p.category === filter; }); }
		function getActive() {
			var v = visible();
			var found = v.filter(function (p) { return p.id === activeId; })[0];
			return found || v[0] || PINS[0] || null;
		}

		// ----- Layout shell -----
		var stage = el('div', { className: 'blm-stage', style: { minHeight: height + 'px' } });
		var mapPanel = el('div', { className: 'blm-map-panel', style: { minHeight: height + 'px' } });
		var canvas = el('div', { className: 'blm-map-canvas' });
		var drawer = el('aside', { className: 'blm-drawer' });

		mapPanel.appendChild(canvas);
		stage.appendChild(mapPanel);
		stage.appendChild(drawer);
		root.appendChild(stage);

		// Persistent HUD elements (built once, never re-rendered so animations don't restart)
		var chipRow = showFilters ? el('div', { className: 'blm-chiprow' }) : null;
		if (chipRow) mapPanel.appendChild(chipRow);

		var hudTR = el('div', { className: 'blm-hud-tr' });
		mapPanel.appendChild(hudTR);

		var legend = null;
		if (SETTINGS.show_legend && CATS.length) {
			legend = el('div', { className: 'blm-legend' });
			CATS.forEach(function (c) {
				legend.appendChild(el('div', { className: 'blm-legend-row' }, [
					el('span', { className: 'blm-legend-dot', style: { background: c.color } }),
					el('span', { className: 'blm-legend-label' }, [c.name])
				]));
			});
			mapPanel.appendChild(legend);
		}

		if (SETTINGS.show_compass) {
			mapPanel.appendChild(el('div', { className: 'blm-compass' }, [
				el('span', { className: 'blm-compass-n' }, ['N']),
				el('span', { className: 'blm-compass-s' }, ['S']),
				el('span', { className: 'blm-compass-w' }, ['W']),
				el('span', { className: 'blm-compass-e' }, ['E']),
				el('div', { className: 'blm-compass-needle' })
			]));
		}

		// Zoom controls overlay
		var zoomCtrls = el('div', { className: 'blm-zoom-ctrls' }, [
			el('button', { type: 'button', className: 'blm-zoom-btn', 'aria-label': 'Zoom in', onClick: function () { setScale(scale * 1.25); } }, ['+']),
			el('button', { type: 'button', className: 'blm-zoom-btn', 'aria-label': 'Zoom out', onClick: function () { setScale(scale / 1.25); } }, ['−']),
			el('button', { type: 'button', className: 'blm-zoom-btn blm-zoom-reset', 'aria-label': 'Reset view', onClick: function () { resetView(); } }, ['⤾'])
		]);
		mapPanel.appendChild(zoomCtrls);

		// Fullscreen toggle — a fixed full-viewport overlay. We deliberately avoid
		// the native Fullscreen API: inside some embeds it promotes the element to
		// the top layer and renders black. Instead we relocate the stage into
		// <body> (carrying its theme variables inline) so position:fixed reliably
		// fills the viewport even under transformed Divi wrappers.
		var fsIconExpand = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>';
		var fsIconCompress = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 9V4M9 9H4M15 9V4M15 9h5M9 15v5M9 15H4M15 15v5M15 15h5"/></svg>';
		var fsBtn = el('button', { type: 'button', className: 'blm-zoom-btn blm-fs-btn', 'aria-label': 'Enter fullscreen', title: 'Fullscreen', onClick: function () { toggleFs(); } });
		fsBtn.innerHTML = fsIconExpand;
		zoomCtrls.insertBefore(fsBtn, zoomCtrls.firstChild);

		var FS_VARS = ['--blm-primary', '--blm-secondary', '--blm-heading', '--blm-body', '--blm-link', '--blm-canvas', '--blm-white', '--blm-black', '--blm-rule', '--blm-map-bg', '--blm-list-bg', '--blm-list-header', '--blm-map-text', '--blm-dir-btn-text', '--blm-dir-btn-border', '--blm-font-heading', '--blm-font-body', '--blm-font-mono', '--blm-font-cta', '--blm-font-sub'];
		var fsPlaceholder = null;
		function fsActive() { return stage.classList.contains('blm-pseudo-fs'); }
		function updateFsBtn(on) {
			fsBtn.innerHTML = on ? fsIconCompress : fsIconExpand;
			fsBtn.setAttribute('aria-label', on ? 'Exit fullscreen' : 'Enter fullscreen');
		}
		function enterFs() {
			var cs = window.getComputedStyle(root);
			FS_VARS.forEach(function (v) { stage.style.setProperty(v, cs.getPropertyValue(v)); });
			fsPlaceholder = document.createComment('blm-fs');
			stage.parentNode.insertBefore(fsPlaceholder, stage);
			document.body.appendChild(stage);
			stage.classList.add('blm-pseudo-fs', 'is-fullscreen');
			document.body.classList.add('blm-fs-lock');
			document.addEventListener('keydown', fsEsc);
			updateFsBtn(true);
			setTimeout(fitCanvas, 60);
		}
		function exitFs() {
			stage.classList.remove('blm-pseudo-fs', 'is-fullscreen');
			if (fsPlaceholder && fsPlaceholder.parentNode) {
				fsPlaceholder.parentNode.insertBefore(stage, fsPlaceholder);
				fsPlaceholder.parentNode.removeChild(fsPlaceholder);
				fsPlaceholder = null;
			}
			FS_VARS.forEach(function (v) { stage.style.removeProperty(v); });
			document.body.classList.remove('blm-fs-lock');
			document.removeEventListener('keydown', fsEsc);
			updateFsBtn(false);
			setTimeout(fitCanvas, 60);
		}
		function fsEsc(e) { if ((e.key === 'Escape' || e.keyCode === 27) && fsActive()) exitFs(); }
		function toggleFs() { fsActive() ? exitFs() : enterFs(); }

		// Canvas children
		var bgImg = null;
		if (SETTINGS.map_image_url) {
			bgImg = el('img', { className: 'blm-map-bg', src: SETTINGS.map_image_url, alt: '', draggable: 'false' });
			canvas.appendChild(bgImg);
		} else {
			canvas.appendChild(el('div', { className: 'blm-map-fallback' }, [
				el('span', { className: 'blm-map-fallback-label' }, ['MAP — UPLOAD AN IMAGE IN SETTINGS'])
			]));
		}

		var pinSvg = svg('svg', { class: 'blm-pin-layer', viewBox: '0 0 100 100', preserveAspectRatio: 'none' });
		canvas.appendChild(pinSvg);

		// ----- Pin interactions: event delegation on the SVG (survives all paint() rebuilds).
		// More robust than per-pin listeners which can be defeated by third-party scripts
		// or by the painting tree being rebuilt under an in-flight click sequence.
		function pinIdFromTarget(target) {
			if (!target || !target.closest) return null;
			var g = target.closest('.blm-pin');
			if (!g) return null;
			var id = parseInt(g.getAttribute('data-pin-id'), 10);
			return isNaN(id) ? null : id;
		}
		pinSvg.addEventListener('mousedown', function (e) {
			if (pinIdFromTarget(e.target) !== null) e.stopPropagation();
		});
		pinSvg.addEventListener('touchstart', function (e) {
			if (pinIdFromTarget(e.target) !== null) e.stopPropagation();
		}, { passive: true });
		pinSvg.addEventListener('click', function (e) {
			var id = pinIdFromTarget(e.target);
			if (id === null) return;
			activeId = id;
			paint();
		});
		pinSvg.addEventListener('mouseover', function (e) {
			var id = pinIdFromTarget(e.target);
			if (id === null) return;
			if (hoverId !== id) { hoverId = id; paint(); }
		});
		pinSvg.addEventListener('mouseout', function (e) {
			if (!e.target || !e.target.closest) return;
			var g = e.target.closest('.blm-pin');
			if (!g) return;
			// Don't fire when moving between children of the same pin
			if (e.relatedTarget && g.contains && g.contains(e.relatedTarget)) return;
			if (hoverId !== null) { hoverId = null; paint(); }
		});

		// Aspect-corrected coordinate space. Stored x/y are 0..100 (percent of image).
		// SVG viewBox is updated to match image aspect so 1 unit in X = 1 unit in Y in pixels
		// — keeps pins perfectly round on any image shape.
		var vbHeight = 100; // updated once image loads
		function my(y) { return (parseFloat(y) || 0) * vbHeight / 100; }
		function updateViewBox() {
			if (imgNaturalW && imgNaturalH) {
				vbHeight = 100 * (imgNaturalH / imgNaturalW);
				pinSvg.setAttribute('viewBox', '0 0 100 ' + vbHeight);
			}
		}

		// ----- Fit-to-panel logic (preserve image aspect, no crop) -----
		var imgNaturalW = 0, imgNaturalH = 0;
		function fitCanvas() {
			var panelRect = mapPanel.getBoundingClientRect();
			if (!panelRect.width || !panelRect.height) return;
			var pw = panelRect.width, ph = panelRect.height;
			var ratio = (imgNaturalW && imgNaturalH) ? (imgNaturalW / imgNaturalH) : (16 / 9);
			var panelRatio = pw / ph;
			var w, h;
			if (ratio > panelRatio) { w = pw; h = pw / ratio; }
			else { h = ph; w = ph * ratio; }
			canvas.style.width = w + 'px';
			canvas.style.height = h + 'px';
		}
		if (bgImg) {
			if (bgImg.complete && bgImg.naturalWidth) {
				imgNaturalW = bgImg.naturalWidth; imgNaturalH = bgImg.naturalHeight;
				updateViewBox(); fitCanvas(); paint();
			} else {
				bgImg.addEventListener('load', function () {
					imgNaturalW = bgImg.naturalWidth; imgNaturalH = bgImg.naturalHeight;
					updateViewBox(); fitCanvas(); paint();
				});
			}
		}
		var ro = (typeof ResizeObserver !== 'undefined') ? new ResizeObserver(fitCanvas) : null;
		if (ro) ro.observe(mapPanel);
		window.addEventListener('resize', fitCanvas);
		// Initial fit
		setTimeout(fitCanvas, 0);

		// ----- Pan & zoom transform -----
		function applyTransform() {
			canvas.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
			zoomCtrls.classList.toggle('is-zoomed', scale > 1.01 || Math.abs(tx) > 1 || Math.abs(ty) > 1);
		}
		function constrain() {
			var panelRect = mapPanel.getBoundingClientRect();
			var canvasW = canvas.offsetWidth * scale;
			var canvasH = canvas.offsetHeight * scale;
			var maxX = Math.max(0, (canvasW - panelRect.width) / 2);
			var maxY = Math.max(0, (canvasH - panelRect.height) / 2);
			tx = Math.max(-maxX, Math.min(maxX, tx));
			ty = Math.max(-maxY, Math.min(maxY, ty));
		}
		function setScale(newScale, centerX, centerY) {
			var oldScale = scale;
			newScale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, newScale));
			if (newScale === oldScale) return;
			if (typeof centerX === 'number') {
				// zoom around the cursor point (in panel local coords, relative to panel center)
				var ratio = newScale / oldScale;
				tx = centerX - (centerX - tx) * ratio;
				ty = centerY - (centerY - ty) * ratio;
			}
			scale = newScale;
			if (scale <= MIN_SCALE + 0.001) { tx = 0; ty = 0; }
			constrain();
			applyTransform();
		}
		function resetView() { tx = 0; ty = 0; scale = 1; applyTransform(); }

		// Mouse drag (skip if click started on an interactive control OR on a pin)
		var dragging = false, lastX = 0, lastY = 0, dragMoved = false;
		mapPanel.addEventListener('mousedown', function (e) {
			if (e.target.closest && e.target.closest('.blm-chip, .blm-zoom-btn, .blm-pin, .blm-hotel-pin')) return;
			dragging = true; dragMoved = false;
			lastX = e.clientX; lastY = e.clientY;
			mapPanel.classList.add('is-grabbing');
		});
		window.addEventListener('mousemove', function (e) {
			if (!dragging) return;
			var dx = e.clientX - lastX, dy = e.clientY - lastY;
			if (Math.abs(dx) + Math.abs(dy) > 2) dragMoved = true;
			tx += dx; ty += dy;
			lastX = e.clientX; lastY = e.clientY;
			constrain();
			applyTransform();
		});
		window.addEventListener('mouseup', function () {
			if (dragging) { dragging = false; mapPanel.classList.remove('is-grabbing'); }
		});

		// Scroll-wheel zoom (only when hovering map)
		mapPanel.addEventListener('wheel', function (e) {
			e.preventDefault();
			var rect = mapPanel.getBoundingClientRect();
			var cx = e.clientX - rect.left - rect.width / 2;
			var cy = e.clientY - rect.top - rect.height / 2;
			var delta = e.deltaY > 0 ? 0.9 : 1.111;
			setScale(scale * delta, cx, cy);
		}, { passive: false });

		// Touch: 1-finger pan, 2-finger pinch zoom
		var touchState = null;
		function touchDist(a, b) { return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY); }
		function touchCenter(a, b) { return { x: (a.clientX + b.clientX) / 2, y: (a.clientY + b.clientY) / 2 }; }
		mapPanel.addEventListener('touchstart', function (e) {
			if (e.target.closest && e.target.closest('.blm-chip, .blm-zoom-btn, .blm-pin, .blm-hotel-pin')) return;
			if (e.touches.length === 1) {
				touchState = { type: 'pan', x: e.touches[0].clientX, y: e.touches[0].clientY, moved: false };
			} else if (e.touches.length === 2) {
				touchState = {
					type: 'pinch',
					startDist: touchDist(e.touches[0], e.touches[1]),
					startScale: scale,
					startTx: tx, startTy: ty
				};
			}
		}, { passive: true });
		mapPanel.addEventListener('touchmove', function (e) {
			if (!touchState) return;
			if (touchState.type === 'pan' && e.touches.length === 1) {
				var t = e.touches[0];
				var dx = t.clientX - touchState.x, dy = t.clientY - touchState.y;
				if (Math.abs(dx) + Math.abs(dy) > 2) touchState.moved = true;
				tx += dx; ty += dy;
				touchState.x = t.clientX; touchState.y = t.clientY;
				constrain();
				applyTransform();
				if (touchState.moved) e.preventDefault();
			} else if (touchState.type === 'pinch' && e.touches.length === 2) {
				e.preventDefault();
				var dist = touchDist(e.touches[0], e.touches[1]);
				var rect = mapPanel.getBoundingClientRect();
				var ctr = touchCenter(e.touches[0], e.touches[1]);
				var cx = ctr.x - rect.left - rect.width / 2;
				var cy = ctr.y - rect.top - rect.height / 2;
				setScale(touchState.startScale * (dist / touchState.startDist), cx, cy);
			}
		}, { passive: false });
		mapPanel.addEventListener('touchend', function () { touchState = null; });
		mapPanel.addEventListener('touchcancel', function () { touchState = null; });

		// ----- Build/refresh pins + drawer (called any time selection/filter changes) -----
		function paint() {
			// Chip row
			if (chipRow) {
				chipRow.innerHTML = '';
				var allCats = [{ name: 'All', color: 'var(--gcid-heading-color, #5A2816)' }].concat(CATS);
				allCats.forEach(function (c) {
					var active = filter === c.name;
					var chip = el('button', {
						type: 'button',
						className: 'blm-chip' + (active ? ' is-active' : ''),
						style: active ? { background: c.color, borderColor: c.color, color: '#FCF8F2' } : {},
						onClick: function () { filter = c.name; paint(); }
					}, [c.name]);
					chipRow.appendChild(chip);
				});
			}

			// HUD readout
			hudTR.innerHTML = '';
			hudTR.appendChild(el('div', null, [(SETTINGS.hotel_label || 'Hotel') + ' · ' + visible().length + ' pins']));

			// SVG pin layer
			while (pinSvg.firstChild) pinSvg.removeChild(pinSvg.firstChild);

			var actv = getActive();

			// Connector lines
			visible().forEach(function (p) {
				var isActive = actv && p.id === actv.id;
				pinSvg.appendChild(svg('line', {
					x1: SETTINGS.hotel_x, y1: my(SETTINGS.hotel_y), x2: p.x, y2: my(p.y),
					stroke: isActive ? (SETTINGS.hotel_color || '#CA5A35') : 'rgba(28,25,23,0.18)',
					'stroke-width': isActive ? 0.25 : 0.12,
					'stroke-dasharray': isActive ? '0' : '0.6 0.5',
					'vector-effect': 'non-scaling-stroke',
					style: 'pointer-events:none'
				}));
			});

			// Hotel pin
			if (SETTINGS.show_hotel_pin) {
				var hx = SETTINGS.hotel_x, hy = my(SETTINGS.hotel_y);
				var hg = svg('g', { class: 'blm-hotel-pin' });
				hg.appendChild(svg('circle', { cx: hx, cy: hy, r: 2.6, fill: 'none', stroke: SETTINGS.hotel_color, 'stroke-width': 0.2, opacity: 0.5 }));
				hg.appendChild(svg('circle', { cx: hx, cy: hy, r: 1.8, fill: SETTINGS.hotel_color }));
				hg.appendChild(svg('circle', { cx: hx, cy: hy, r: 0.6, fill: '#FCF8F2' }));
				if (SETTINGS.hotel_label) hg.appendChild(svg('text', { x: hx, y: hy + 4.6, 'text-anchor': 'middle', class: 'blm-hotel-label' }, [SETTINGS.hotel_label]));
				if (SETTINGS.hotel_sublabel) hg.appendChild(svg('text', { x: hx, y: hy + 6.6, 'text-anchor': 'middle', class: 'blm-hotel-sublabel' }, [SETTINGS.hotel_sublabel]));
				pinSvg.appendChild(hg);
			}

			// POI pins (teardrop markers — tip sits on the exact location)
			var pinScale = (parseFloat(SETTINGS.pin_size) || 100) / 100;
			visible().forEach(function (p) {
				var isActive = actv && p.id === actv.id;
				var isHover = hoverId === p.id;
				var color = effColor(p);
				var px = p.x, py = my(p.y);
				var h = (isActive ? 5.8 : (isHover ? 5.2 : 4.5)) * pinScale; // marker height (tip -> top)
				var headR = h * 0.32;
				var headCy = py - h + headR;                    // centre of the round head
				var g = svg('g', {
					class: 'blm-pin' + (isActive ? ' is-active' : ''),
					'data-pin-id': p.id,
					style: 'cursor:pointer'
				});
				// Transparent hit area over the whole marker
				g.appendChild(svg('circle', { cx: px, cy: headCy, r: h * 0.72, fill: 'transparent' }));

				if (isActive && SETTINGS.show_pulse) {
					var pulse1 = svg('circle', {
						cx: px, cy: headCy, r: 2, fill: 'none',
						stroke: color, 'stroke-width': 0.45,
						opacity: 0.9, 'pointer-events': 'none'
					}, [
						svg('animate', { attributeName: 'r',       values: '1.6;5.5',  dur: '1.6s', repeatCount: 'indefinite', calcMode: 'spline', keySplines: '0.16 1 0.3 1' }),
						svg('animate', { attributeName: 'opacity', values: '0.9;0',     dur: '1.6s', repeatCount: 'indefinite', calcMode: 'spline', keySplines: '0.16 1 0.3 1' }),
						svg('animate', { attributeName: 'stroke-width', values: '0.45;0.05', dur: '1.6s', repeatCount: 'indefinite' })
					]);
					var pulse2 = svg('circle', {
						cx: px, cy: headCy, r: 2, fill: 'none',
						stroke: color, 'stroke-width': 0.45,
						opacity: 0.9, 'pointer-events': 'none'
					}, [
						svg('animate', { attributeName: 'r',       values: '1.6;5.5',  dur: '1.6s', begin: '0.8s', repeatCount: 'indefinite', calcMode: 'spline', keySplines: '0.16 1 0.3 1' }),
						svg('animate', { attributeName: 'opacity', values: '0.9;0',     dur: '1.6s', begin: '0.8s', repeatCount: 'indefinite', calcMode: 'spline', keySplines: '0.16 1 0.3 1' }),
						svg('animate', { attributeName: 'stroke-width', values: '0.45;0.05', dur: '1.6s', begin: '0.8s', repeatCount: 'indefinite' })
					]);
					g.appendChild(pulse1);
					g.appendChild(pulse2);
				} else if (isActive) {
					g.appendChild(svg('circle', { cx: px, cy: headCy, r: headR + 1.6, fill: color, opacity: 0.18, 'pointer-events': 'none' }));
				} else if (isHover) {
					g.appendChild(svg('circle', { cx: px, cy: headCy, r: headR + 1.4, fill: color, opacity: 0.16, 'pointer-events': 'none' }));
				}

				// Teardrop body + light knockout hole
				g.appendChild(svg('path', { d: pinPath(px, py, h), fill: color, stroke: '#1C1917', 'stroke-width': 0.14, 'stroke-linejoin': 'round' }));
				g.appendChild(svg('circle', { cx: px, cy: headCy, r: headR * 0.4, fill: '#FCF8F2', 'pointer-events': 'none' }));

				if (isActive || isHover) {
					g.appendChild(svg('text', { x: px, y: (headCy - headR - 1.2), 'text-anchor': 'middle', class: 'blm-pin-label', 'pointer-events': 'none' }, [p.name.split('·')[0].trim()]));
				}
				pinSvg.appendChild(g);
			});

			// Drawer
			drawer.innerHTML = '';
			if (actv) {
				var color = effColor(actv);
				drawer.appendChild(el('div', { className: 'blm-drawer-head' }, [
					el('span', { className: 'blm-drawer-cat', style: { color: color } }, [actv.category || '—']),
					el('span', { className: 'blm-drawer-counter' }, [
						'PIN ' + String(PINS.findIndex(function (x) { return x.id === actv.id; }) + 1).padStart(2, '0')
						+ ' / ' + String(PINS.length).padStart(2, '0')
					])
				]));
				var body = el('div', { className: 'blm-drawer-body' });
				body.appendChild(el('h2', { className: 'blm-drawer-title' }, [actv.name]));
				body.appendChild(el('div', { className: 'blm-drawer-rule', style: { background: color } }));

				if (actv.image) {
					body.appendChild(el('div', { className: 'blm-drawer-photo' }, [
						el('img', { src: actv.image, alt: actv.name })
					]));
				} else {
					body.appendChild(el('div', { className: 'blm-drawer-photo blm-drawer-photo-placeholder' }, [
						el('span', null, [actv.name.toUpperCase() + ' — PHOTO'])
					]));
				}

				var blurb = el('div', { className: 'blm-drawer-blurb' });
				blurb.innerHTML = actv.blurb || '';
				body.appendChild(blurb);

				if (actv.drive || actv.distance) {
					body.appendChild(el('div', { className: 'blm-stats' }, [
						actv.drive ? el('div', { className: 'blm-stat' }, [
							el('span', { className: 'blm-stat-label' }, ['Drive']),
							el('span', { className: 'blm-stat-value' }, [actv.drive])
						]) : null,
						actv.distance ? el('div', { className: 'blm-stat' }, [
							el('span', { className: 'blm-stat-label' }, ['Distance']),
							el('span', { className: 'blm-stat-value' }, [actv.distance])
						]) : null
					]));
				}
				if (actv.tags && actv.tags.length) {
					var tagRow = el('div', { className: 'blm-tagrow' });
					actv.tags.forEach(function (t) { tagRow.appendChild(el('span', { className: 'blm-tag' }, [t])); });
					body.appendChild(tagRow);
				}
				var btnRow = el('div', { className: 'blm-btnrow' });
				if (actv.cta_url) btnRow.appendChild(el('a', { className: 'blm-btn-primary', href: actv.cta_url }, ['Plan A Trip']));
				if (actv.directions_url) btnRow.appendChild(el('a', { className: 'blm-btn-secondary', href: actv.directions_url, target: '_blank', rel: 'noopener' }, ['Directions ↗']));
				if (btnRow.children.length) body.appendChild(btnRow);

				drawer.appendChild(body);
			} else {
				drawer.appendChild(el('div', { className: 'blm-empty' }, ['No map points yet. Add some in Wayfinder Map → Add Map Point.']));
			}
		}

		paint();
		applyTransform();

		// External event hook (used by [bandit_locations_list] rows)
		root.addEventListener('bandit-lm:setActive', function (e) {
			if (e.detail && typeof e.detail.id !== 'undefined') {
				activeId = e.detail.id;
				paint();
				root.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	// ---------- LIST COMPONENT ----------
	function renderList(root) {
		root.innerHTML = '';
		root.classList.add('bandit-lm-rendered');

		var filter = root.dataset.filter || 'All';
		var cols = (root.dataset.columns || 'number,name,category,drive,distance,action').split(',');
		var visible = filter === 'All' ? PINS : PINS.filter(function (p) { return p.category === filter; });

		var wrap = el('div', { className: 'blm-list' });
		var head = el('div', { className: 'blm-list-head' });
		var colLabels = { number: '#', name: 'Place', category: 'Type', drive: 'Drive', distance: 'Distance', action: '' };
		cols.forEach(function (c) {
			head.appendChild(el('div', { className: 'blm-list-cell blm-list-cell-' + c }, [colLabels[c] || '']));
		});
		wrap.appendChild(head);

		visible.forEach(function (p, i) {
			var row = el('button', {
				type: 'button',
				className: 'blm-list-row',
				onClick: function () {
					var map = document.querySelector('.bandit-lm-map-only');
					if (map) map.dispatchEvent(new CustomEvent('bandit-lm:setActive', { detail: { id: p.id } }));
				}
			});
			cols.forEach(function (c) {
				var cell = el('div', { className: 'blm-list-cell blm-list-cell-' + c });
				if (c === 'number') cell.textContent = String(i + 1).padStart(2, '0');
				else if (c === 'name') cell.textContent = p.name;
				else if (c === 'category') {
					cell.appendChild(el('span', { className: 'blm-list-dot', style: { background: p.category_color } }));
					cell.appendChild(document.createTextNode(p.category || ''));
				}
				else if (c === 'drive') cell.textContent = p.drive || '';
				else if (c === 'distance') cell.textContent = p.distance || '';
				else if (c === 'action') cell.textContent = 'View →';
				row.appendChild(cell);
			});
			wrap.appendChild(row);
		});

		if (!visible.length) wrap.appendChild(el('div', { className: 'blm-empty' }, ['No map points yet.']));
		root.appendChild(wrap);
	}

	// ---------- BOOT ----------
	function boot() {
		document.querySelectorAll('.bandit-lm-root').forEach(function (root) {
			if (root.classList.contains('bandit-lm-rendered')) return;
			var kind = root.dataset.component;
			if (kind === 'map') renderMap(root);
			else if (kind === 'list') renderList(root);
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
