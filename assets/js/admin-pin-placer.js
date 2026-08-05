/**
 * Visual pin placement tool — runs inside the WP admin
 * Activates on two element types:
 *   #bandit-lm-placer        — placing the active pin (post editor)
 *   #bandit-lm-hotel-placer  — placing the hotel pin (settings page)
 */
(function () {
	'use strict';

	function buildPlacer(root, opts) {
		var x = parseFloat(root.dataset.x || opts.defaultX || 50);
		var y = parseFloat(root.dataset.y || opts.defaultY || 50);
		var hotelX = parseFloat(root.dataset.hotelX || 50);
		var hotelY = parseFloat(root.dataset.hotelY || 50);
		var hotelColor = root.dataset.hotelColor || '#CA5A35';
		var showHotel = root.dataset.showHotel === '1' && !opts.placingHotel;
		var mapUrl = root.dataset.mapUrl;

		root.classList.add('blm-placer');
		root.innerHTML = '';

		var inner = document.createElement('div');
		inner.className = 'blm-placer-inner';
		root.appendChild(inner);

		if (mapUrl) {
			var img = document.createElement('img');
			img.src = mapUrl;
			img.alt = '';
			img.className = 'blm-placer-bg';
			inner.appendChild(img);
		} else {
			var fb = document.createElement('div');
			fb.className = 'blm-placer-fallback';
			fb.textContent = 'Upload a map image in Bandit Map → Map Settings first.';
			inner.appendChild(fb);
		}

		// Hotel pin (reference, non-interactive)
		if (showHotel) {
			var hp = document.createElement('div');
			hp.className = 'blm-placer-hotel';
			hp.style.left = hotelX + '%';
			hp.style.top  = hotelY + '%';
			hp.style.borderColor = hotelColor;
			hp.innerHTML = '<span style="background:' + hotelColor + '"></span><label>Hotel</label>';
			inner.appendChild(hp);
		}

		// Active pin (draggable)
		var pin = document.createElement('div');
		pin.className = 'blm-placer-pin';
		pin.innerHTML = '<span class="blm-placer-pin-dot"></span><span class="blm-placer-pin-ring"></span>';
		pin.style.left = x + '%';
		pin.style.top  = y + '%';
		inner.appendChild(pin);

		// Crosshair lines while hovering
		var hLine = document.createElement('div'); hLine.className = 'blm-placer-hline';
		var vLine = document.createElement('div'); vLine.className = 'blm-placer-vline';
		inner.appendChild(hLine); inner.appendChild(vLine);

		var readout = document.createElement('div');
		readout.className = 'blm-placer-readout';
		readout.textContent = x.toFixed(1) + '%, ' + y.toFixed(1) + '%';
		root.appendChild(readout);

		var help = document.createElement('div');
		help.className = 'blm-placer-help';
		help.textContent = 'Click to place · Drag the pin · Or type X/Y below';
		root.appendChild(help);

		var xInput = document.getElementById(opts.xInputId);
		var yInput = document.getElementById(opts.yInputId);

		function setPos(nx, ny, syncInputs) {
			nx = Math.max(0, Math.min(100, nx));
			ny = Math.max(0, Math.min(100, ny));
			x = nx; y = ny;
			pin.style.left = x + '%';
			pin.style.top  = y + '%';
			readout.textContent = x.toFixed(1) + '%, ' + y.toFixed(1) + '%';
			if (syncInputs !== false) {
				if (xInput) xInput.value = x.toFixed(1);
				if (yInput) yInput.value = y.toFixed(1);
			}
		}

		function fromEvent(e) {
			var rect = inner.getBoundingClientRect();
			var cx = (e.clientX - rect.left) / rect.width * 100;
			var cy = (e.clientY - rect.top) / rect.height * 100;
			return { x: cx, y: cy };
		}

		inner.addEventListener('mousemove', function (e) {
			var p = fromEvent(e);
			hLine.style.top = p.y + '%';
			vLine.style.left = p.x + '%';
		});

		inner.addEventListener('click', function (e) {
			if (e.target === pin || pin.contains(e.target)) return;
			var p = fromEvent(e);
			setPos(p.x, p.y);
		});

		// Drag pin
		var dragging = false;
		pin.addEventListener('mousedown', function (e) {
			e.preventDefault();
			dragging = true;
			pin.classList.add('is-dragging');
		});
		document.addEventListener('mousemove', function (e) {
			if (!dragging) return;
			var p = fromEvent(e);
			setPos(p.x, p.y);
		});
		document.addEventListener('mouseup', function () {
			if (dragging) {
				dragging = false;
				pin.classList.remove('is-dragging');
			}
		});

		// Sync from input fields if user types
		if (xInput) {
			xInput.addEventListener('input', function () {
				setPos(parseFloat(xInput.value) || 0, y, false);
			});
		}
		if (yInput) {
			yInput.addEventListener('input', function () {
				setPos(x, parseFloat(yInput.value) || 0, false);
			});
		}
	}

	function init() {
		var placer = document.getElementById('bandit-lm-placer');
		if (placer) {
			buildPlacer(placer, {
				xInputId: 'bandit_x',
				yInputId: 'bandit_y',
				placingHotel: false
			});
		}
		var hotelPlacer = document.getElementById('bandit-lm-hotel-placer');
		if (hotelPlacer) {
			hotelPlacer.dataset.x = hotelPlacer.dataset.hotelX;
			hotelPlacer.dataset.y = hotelPlacer.dataset.hotelY;
			buildPlacer(hotelPlacer, {
				xInputId: 'bandit_hotel_x',
				yInputId: 'bandit_hotel_y',
				placingHotel: true
			});
		}

		// Settings page: Choose Image media picker
		var pickBtn = document.getElementById('bandit_map_image_pick');
		if (pickBtn && typeof wp !== 'undefined' && wp.media) {
			pickBtn.addEventListener('click', function (e) {
				e.preventDefault();
				var frame = wp.media({
					title: 'Choose Map Image',
					button: { text: 'Use This Image' },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					document.getElementById('bandit_map_image_id').value = att.id;
					var preview = document.getElementById('bandit_map_image_preview');
					preview.innerHTML = '<img src="' + att.url + '" style="max-width:520px;height:auto;border:1px solid #ddd;display:block;" />';
					// Update placer background live
					if (hotelPlacer) {
						hotelPlacer.dataset.mapUrl = att.url;
						var existing = hotelPlacer.querySelector('.blm-placer-bg');
						if (existing) existing.src = att.url;
						else {
							var fb = hotelPlacer.querySelector('.blm-placer-fallback');
							if (fb) fb.remove();
							var newImg = document.createElement('img');
							newImg.src = att.url; newImg.className = 'blm-placer-bg';
							hotelPlacer.querySelector('.blm-placer-inner').insertBefore(newImg, hotelPlacer.querySelector('.blm-placer-inner').firstChild);
						}
					}
				});
				frame.open();
			});
		}
		var clearBtn = document.getElementById('bandit_map_image_clear');
		if (clearBtn) {
			clearBtn.addEventListener('click', function (e) {
				e.preventDefault();
				if (!confirm('Remove map image?')) return;
				document.getElementById('bandit_map_image_id').value = '';
				document.getElementById('bandit_map_image_preview').innerHTML = '<em>No image set.</em>';
			});
		}
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
