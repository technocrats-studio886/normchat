// Coretan di atas foto: warna, ukuran pena, dan urungkan. Tiap goresan disimpan dengan koordinat relatif
// terhadap foto (0..1), jadi bisa digambar ulang saat ukuran layar berubah dan digabung ke foto beresolusi asli
// saat dikirim (media-editor/flatten.js).

/** @param {{ doodleEnabled: boolean }} state status editor bersama */
export const bindDoodle = (el, state) => {
	const canvas = el.querySelector('[data-attachment-preview-doodle]');
	const stage = el.querySelector('[data-attachment-preview-stage]');
	const sizeButtons = Array.from(el.querySelectorAll('[data-preview-pen-size]'));
	const colorButtons = Array.from(el.querySelectorAll('[data-preview-pen-color]'));
	const undoButton = el.querySelector('[data-preview-doodle-undo]');

	/** @type {Array<{ color: string, width: number, points: Array<[number, number]> }>} lebar relatif terhadap lebar foto */
	const strokes = [];
	let current = null;
	let penSize = 6;
	let penColor = '#ef4444';

	const stageSize = () => ({
		width: Math.max(1, stage instanceof HTMLElement ? stage.clientWidth : 1),
		height: Math.max(1, stage instanceof HTMLElement ? stage.clientHeight : 1),
	});

	const drawStroke = (ctx, stroke, width, height) => {
		if (!stroke.points.length) return;
		ctx.strokeStyle = stroke.color;
		ctx.fillStyle = stroke.color;
		ctx.lineWidth = stroke.width * width;
		ctx.lineCap = 'round';
		ctx.lineJoin = 'round';
		if (stroke.points.length === 1) {
			const [x, y] = stroke.points[0];
			ctx.beginPath();
			ctx.arc(x * width, y * height, (stroke.width * width) / 2, 0, Math.PI * 2);
			ctx.fill();
			return;
		}
		ctx.beginPath();
		ctx.moveTo(stroke.points[0][0] * width, stroke.points[0][1] * height);
		// Kurva halus lewat titik tengah antar titik.
		for (let i = 1; i < stroke.points.length - 1; i += 1) {
			const [x, y] = stroke.points[i];
			const [nx, ny] = stroke.points[i + 1];
			ctx.quadraticCurveTo(x * width, y * height, ((x + nx) / 2) * width, ((y + ny) / 2) * height);
		}
		const last = stroke.points[stroke.points.length - 1];
		ctx.lineTo(last[0] * width, last[1] * height);
		ctx.stroke();
	};

	const redraw = () => {
		if (!(canvas instanceof HTMLCanvasElement)) return;
		const ctx = canvas.getContext('2d');
		if (!ctx) return;
		const { width, height } = stageSize();
		ctx.setTransform(1, 0, 0, 1, 0, 0);
		ctx.clearRect(0, 0, canvas.width, canvas.height);
		const ratio = canvas.width / width;
		ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
		strokes.forEach((stroke) => drawStroke(ctx, stroke, width, height));
		if (current) drawStroke(ctx, current, width, height);
	};

	const resize = () => {
		if (!(canvas instanceof HTMLCanvasElement)) return;
		const { width, height } = stageSize();
		const ratio = Math.max(1, window.devicePixelRatio || 1);
		canvas.width = Math.floor(width * ratio);
		canvas.height = Math.floor(height * ratio);
		redraw();
	};

	const syncUi = () => {
		sizeButtons.forEach((button) => {
			const active = Number(button.getAttribute('data-preview-pen-size')) === penSize;
			button.classList.toggle('bg-white', active);
			button.classList.toggle('text-navy-950', active);
			button.classList.toggle('bg-white/25', !active);
			button.classList.toggle('text-white', !active);
		});
		colorButtons.forEach((button) => {
			const active = button.getAttribute('data-preview-pen-color') === penColor;
			button.classList.toggle('ring-2', active);
			button.classList.toggle('ring-white', active);
			button.classList.toggle('ring-offset-2', active);
			button.classList.toggle('ring-offset-navy-950', active);
		});
		if (undoButton instanceof HTMLButtonElement) undoButton.disabled = strokes.length === 0;
	};

	const setEnabled = (enabled) => {
		state.doodleEnabled = enabled;
		if (canvas instanceof HTMLCanvasElement) {
			// Coretan yang sudah ada tetap terlihat walau mode coretan dimatikan.
			canvas.classList.toggle('hidden', !enabled && strokes.length === 0);
			canvas.classList.toggle('pointer-events-none', !enabled);
			canvas.classList.toggle('pointer-events-auto', enabled);
			canvas.style.cursor = enabled ? 'crosshair' : '';
		}
		if (enabled) resize();
	};

	const pointOf = (event) => {
		const rect = canvas.getBoundingClientRect();
		return [
			Math.min(1, Math.max(0, (event.clientX - rect.left) / Math.max(1, rect.width))),
			Math.min(1, Math.max(0, (event.clientY - rect.top) / Math.max(1, rect.height))),
		];
	};

	if (canvas instanceof HTMLCanvasElement) {
		canvas.addEventListener('pointerdown', (event) => {
			if (!state.doodleEnabled) return;
			event.preventDefault();
			canvas.setPointerCapture?.(event.pointerId);
			current = { color: penColor, width: penSize / stageSize().width, points: [pointOf(event)] };
			redraw();
		});
		canvas.addEventListener('pointermove', (event) => {
			if (!current) return;
			event.preventDefault();
			const events = typeof event.getCoalescedEvents === 'function' ? event.getCoalescedEvents() : [event];
			(events.length ? events : [event]).forEach((pointerEvent) => current.points.push(pointOf(pointerEvent)));
			redraw();
		});
		const finish = () => {
			if (!current) return;
			strokes.push(current);
			current = null;
			redraw();
			syncUi();
		};
		canvas.addEventListener('pointerup', finish);
		canvas.addEventListener('pointercancel', finish);
		canvas.addEventListener('lostpointercapture', finish);
	}

	sizeButtons.forEach((button) => button.addEventListener('click', () => {
		penSize = Number(button.getAttribute('data-preview-pen-size')) || penSize;
		syncUi();
	}));
	colorButtons.forEach((button) => button.addEventListener('click', () => {
		penColor = button.getAttribute('data-preview-pen-color') || penColor;
		syncUi();
	}));
	undoButton?.addEventListener('click', () => {
		strokes.pop();
		redraw();
		syncUi();
	});

	const clear = () => {
		strokes.length = 0;
		current = null;
		redraw();
		syncUi();
	};
	syncUi();

	return {
		available: canvas instanceof HTMLCanvasElement,
		setEnabled,
		clear,
		resize,
		hasStrokes: () => strokes.length > 0,
		/** Gambar semua goresan ke $ctx berukuran $width × $height (foto beresolusi asli). */
		paint: (ctx, width, height) => strokes.forEach((stroke) => drawStroke(ctx, stroke, width, height)),
	};
};
