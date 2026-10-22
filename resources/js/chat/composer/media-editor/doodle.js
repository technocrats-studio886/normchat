// Mode coretan di atas media: kanvas, ukuran pena, dan gambar garis halus dari pointer.

import { showActionToast } from '../toast.js';

/** @param {{ doodleEnabled: boolean }} state status editor bersama */
export const bindDoodle = (el, state) => {
	const previewDoodleCanvas = el.querySelector('[data-attachment-preview-doodle]');
	const previewStage = el.querySelector('[data-attachment-preview-stage]');
	const penSizeButtons = Array.from(el.querySelectorAll('[data-preview-pen-size]'));

	let drawing = false;
	let penSize = 4;
	let lastX = 0;
	let lastY = 0;

	/** Tampilkan/sembunyikan kanvas; saat aktif, sentuhan di media dipakai untuk menggambar. */
	const setEnabled = (enabled) => {
		state.doodleEnabled = enabled;
		if (previewDoodleCanvas instanceof HTMLCanvasElement) {
			previewDoodleCanvas.classList.toggle('hidden', !enabled);
			previewDoodleCanvas.classList.toggle('pointer-events-none', !enabled);
			previewDoodleCanvas.classList.toggle('pointer-events-auto', enabled);
			previewDoodleCanvas.style.touchAction = enabled ? 'none' : '';
		}
		if (previewStage instanceof HTMLElement) {
			previewStage.style.touchAction = enabled ? 'none' : '';
		}
	};

	const clear = () => {
		if (previewDoodleCanvas instanceof HTMLCanvasElement) {
			const ctx = previewDoodleCanvas.getContext('2d');
			if (ctx) {
				ctx.clearRect(0, 0, previewDoodleCanvas.width, previewDoodleCanvas.height);
			}
		}
	};

	const updatePenSizeUi = () => {
		penSizeButtons.forEach((button) => {
			const size = Number(button.getAttribute('data-preview-pen-size') || 0);
			const active = Math.abs(size - penSize) < 0.1;
			button.classList.toggle('bg-navy-500/80', active);
			button.classList.toggle('text-navy-950', active);
			button.classList.toggle('bg-white/25', !active);
			button.classList.toggle('text-white', !active);
		});
	};

	const resizeDoodleCanvas = () => {
		if (!(previewDoodleCanvas instanceof HTMLCanvasElement) || !(previewStage instanceof HTMLElement)) {
			return;
		}
		const pixelRatio = Math.max(1, window.devicePixelRatio || 1);
		const width = Math.max(1, Math.floor(previewStage.clientWidth));
		const height = Math.max(1, Math.floor(previewStage.clientHeight));
		previewDoodleCanvas.width = Math.floor(width * pixelRatio);
		previewDoodleCanvas.height = Math.floor(height * pixelRatio);
		previewDoodleCanvas.style.width = `${width}px`;
		previewDoodleCanvas.style.height = `${height}px`;
		const ctx = previewDoodleCanvas.getContext('2d');
		if (!ctx) {
			return;
		}
		ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
		ctx.imageSmoothingEnabled = true;
		ctx.lineCap = 'round';
		ctx.lineJoin = 'round';
		ctx.lineWidth = penSize;
		ctx.strokeStyle = '#F3F5F8';
		ctx.globalCompositeOperation = 'source-over';
	};

	const getCanvasPoint = (event) => {
		if (!(previewDoodleCanvas instanceof HTMLCanvasElement)) {
			return null;
		}
		const rect = previewDoodleCanvas.getBoundingClientRect();
		return {
			x: event.clientX - rect.left,
			y: event.clientY - rect.top,
		};
	};

	const drawLine = (x1, y1, x2, y2) => {
		if (!(previewDoodleCanvas instanceof HTMLCanvasElement)) {
			return;
		}
		const ctx = previewDoodleCanvas.getContext('2d');
		if (!ctx) {
			return;
		}
		const distance = Math.hypot(x2 - x1, y2 - y1);
		const steps = Math.max(1, Math.ceil(distance / 0.45));
		ctx.beginPath();
		ctx.moveTo(x1, y1);
		for (let i = 1; i <= steps; i++) {
			const t = i / steps;
			ctx.lineTo(x1 + ((x2 - x1) * t), y1 + ((y2 - y1) * t));
		}
		ctx.stroke();
	};

	if (previewDoodleCanvas instanceof HTMLCanvasElement) {
		previewDoodleCanvas.addEventListener('pointerdown', (event) => {
			if (!state.doodleEnabled) {
				return;
			}
			event.preventDefault();
			const point = getCanvasPoint(event);
			if (!point) {
				return;
			}
			drawing = true;
			lastX = point.x;
			lastY = point.y;
			const ctx = previewDoodleCanvas.getContext('2d');
			if (ctx) {
				ctx.beginPath();
				ctx.fillStyle = '#F3F5F8';
				ctx.arc(point.x, point.y, penSize / 2, 0, Math.PI * 2);
				ctx.fill();
			}
			previewDoodleCanvas.setPointerCapture(event.pointerId);
		});

		const handleDoodlePointerMove = (event) => {
			if (!state.doodleEnabled || !drawing) {
				return;
			}
			event.preventDefault();
			const coalesced = typeof event.getCoalescedEvents === 'function' ? event.getCoalescedEvents() : [event];
			coalesced.forEach((pointerEvent) => {
				const point = getCanvasPoint(pointerEvent);
				if (!point) {
					return;
				}
				drawLine(lastX, lastY, point.x, point.y);
				lastX = point.x;
				lastY = point.y;
			});
		};

		previewDoodleCanvas.addEventListener('pointermove', handleDoodlePointerMove);
		previewDoodleCanvas.addEventListener('pointerrawupdate', handleDoodlePointerMove);

		const stopDrawing = () => {
			drawing = false;
		};
		previewDoodleCanvas.addEventListener('pointerup', stopDrawing);
		previewDoodleCanvas.addEventListener('pointercancel', stopDrawing);
	}

	if (penSizeButtons.length > 0) {
		penSizeButtons.forEach((button) => {
			button.addEventListener('click', () => {
				const nextSize = Number(button.getAttribute('data-preview-pen-size') || penSize);
				if (!Number.isFinite(nextSize) || nextSize <= 0) {
					return;
				}
				penSize = nextSize;
				updatePenSizeUi();
				if (previewDoodleCanvas instanceof HTMLCanvasElement) {
					const ctx = previewDoodleCanvas.getContext('2d');
					if (ctx) {
						ctx.lineWidth = penSize;
					}
				}
				showActionToast(`Ukuran pena: ${penSize}`);
			});
		});
		updatePenSizeUi();
	}

	return {
		available: previewDoodleCanvas instanceof HTMLCanvasElement,
		setEnabled,
		clear,
		resize: resizeDoodleCanvas,
	};
};
