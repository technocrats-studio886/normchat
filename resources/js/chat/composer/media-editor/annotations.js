// Anotasi di atas media: stiker emoji dan teks yang bisa digeser, beserta editor teksnya.

import { showActionToast } from '../toast.js';
import { bindPreviewMentionMenu } from './mention-menu.js';

/**
 * @param {{ doodleEnabled: boolean, placementMode: string }} state status editor bersama
 * @param {() => void} refreshTools perbarui tampilan tombol alat
 */
export const bindAnnotations = (el, state, mentionSuggestions, refreshTools) => {
	const previewStage = el.querySelector('[data-attachment-preview-stage]');
	const previewAnnotationLayer = el.querySelector('[data-attachment-preview-annotations]');
	const stickerButtons = Array.from(el.querySelectorAll('[data-preview-sticker]'));
	const stickerSizeButtons = Array.from(el.querySelectorAll('[data-preview-sticker-size]'));
	const textEditor = el.querySelector('[data-preview-text-editor]');
	const textInput = el.querySelector('[data-preview-text-input]');
	const textApplyButton = el.querySelector('[data-preview-text-apply]');
	const textCancelButton = el.querySelector('[data-preview-text-cancel]');
	const textCloseButton = el.querySelector('[data-preview-text-close]');
	const textSizeButtons = Array.from(el.querySelectorAll('[data-preview-text-size]'));
	const textColorButtons = Array.from(el.querySelectorAll('[data-preview-text-color]'));
	const previewTextMentionMenu = el.querySelector('[data-preview-text-mention-menu]');

	bindPreviewMentionMenu(textInput, previewTextMentionMenu, mentionSuggestions);

	let selectedSticker = '😀';
	let stickerSize = 42;
	let textSize = 32;
	let textColor = '#ffffff';
	let selectedAnnotation = null;
	let pendingTextPlacement = null;

	const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

	const selectAnnotation = (node) => {
		if (selectedAnnotation && selectedAnnotation !== node) {
			selectedAnnotation.classList.remove('ring-2', 'ring-navy-300', 'ring-offset-1', 'ring-offset-black/40');
		}

		selectedAnnotation = node instanceof HTMLElement ? node : null;
		if (selectedAnnotation) {
			selectedAnnotation.classList.add('ring-2', 'ring-navy-300', 'ring-offset-1', 'ring-offset-black/40');
		}
	};

	const applyAnnotationSize = (node, nextSize) => {
		if (!(node instanceof HTMLElement) || !Number.isFinite(nextSize) || nextSize <= 0) {
			return;
		}

		node.style.fontSize = `${nextSize}px`;
		node.dataset.annotationSize = String(nextSize);
	};

	const makeAnnotationDraggable = (node) => {
		if (!(node instanceof HTMLElement) || !(previewStage instanceof HTMLElement)) {
			return;
		}

		node.classList.add('pointer-events-auto', 'cursor-move', 'touch-none', 'select-none');
		let dragOffsetX = 0;
		let dragOffsetY = 0;
		let draggingAnnotation = false;

		node.addEventListener('pointerdown', (event) => {
			if (state.doodleEnabled) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			selectAnnotation(node);
			draggingAnnotation = true;
			const rect = node.getBoundingClientRect();
			dragOffsetX = event.clientX - (rect.left + (rect.width / 2));
			dragOffsetY = event.clientY - (rect.top + (rect.height / 2));
			node.setPointerCapture(event.pointerId);
		});

		node.addEventListener('pointermove', (event) => {
			if (!draggingAnnotation) {
				return;
			}

			event.preventDefault();
			const stageRect = previewStage.getBoundingClientRect();
			const x = clamp(event.clientX - stageRect.left - dragOffsetX, 0, stageRect.width);
			const y = clamp(event.clientY - stageRect.top - dragOffsetY, 0, stageRect.height);
			node.style.left = `${x}px`;
			node.style.top = `${y}px`;
		});

		const stopDragging = () => {
			draggingAnnotation = false;
		};

		node.addEventListener('pointerup', stopDragging);
		node.addEventListener('pointercancel', stopDragging);
	};

	const createStickerAnnotation = (x, y) => {
		if (!(previewAnnotationLayer instanceof HTMLElement)) {
			return;
		}

		const stickerNode = document.createElement('span');
		stickerNode.className = 'absolute -translate-x-1/2 -translate-y-1/2 drop-shadow';
		stickerNode.style.left = `${x}px`;
		stickerNode.style.top = `${y}px`;
		stickerNode.style.lineHeight = '1';
		stickerNode.textContent = selectedSticker;
		stickerNode.dataset.annotationType = 'sticker';
		applyAnnotationSize(stickerNode, stickerSize);
		previewAnnotationLayer.appendChild(stickerNode);
		makeAnnotationDraggable(stickerNode);
		selectAnnotation(stickerNode);
	};

	const createTextAnnotation = (x, y, textValue) => {
		if (!(previewAnnotationLayer instanceof HTMLElement)) {
			return;
		}

		const textNode = document.createElement('span');
		textNode.className = 'absolute max-w-[74%] -translate-x-1/2 -translate-y-1/2 rounded-md bg-black/45 px-2 py-1 text-center font-semibold shadow';
		textNode.style.left = `${x}px`;
		textNode.style.top = `${y}px`;
		textNode.style.wordBreak = 'break-word';
		textNode.style.color = textColor;
		textNode.textContent = textValue;
		textNode.dataset.annotationType = 'text';
		applyAnnotationSize(textNode, textSize);
		previewAnnotationLayer.appendChild(textNode);
		makeAnnotationDraggable(textNode);
		selectAnnotation(textNode);
	};

	const openTextEditor = (x, y) => {
		if (!(textEditor instanceof HTMLElement) || !(textInput instanceof HTMLInputElement)) {
			return;
		}

		pendingTextPlacement = { x, y };
		textEditor.classList.remove('hidden');
		textEditor.classList.add('flex', 'flex-col');
		textInput.value = '';
		window.setTimeout(() => textInput.focus(), 30);
	};

	const closeTextEditor = () => {
		if (!(textEditor instanceof HTMLElement)) {
			return;
		}

		textEditor.classList.add('hidden');
		textEditor.classList.remove('flex', 'flex-col');
		pendingTextPlacement = null;
	};

	const updateStickerUi = () => {
		stickerButtons.forEach((button) => {
			const isActive = button.getAttribute('data-preview-sticker') === selectedSticker;
			button.classList.toggle('bg-navy-500/85', isActive);
			button.classList.toggle('bg-white/18', !isActive);
		});

		stickerSizeButtons.forEach((button) => {
			const size = Number(button.getAttribute('data-preview-sticker-size') || 0);
			const active = Math.abs(size - stickerSize) < 0.1;
			button.classList.toggle('bg-navy-500/85', active);
			button.classList.toggle('text-navy-950', active);
			button.classList.toggle('bg-white/25', !active);
			button.classList.toggle('text-white', !active);
		});
	};

	const updateTextEditorUi = () => {
		textSizeButtons.forEach((button) => {
			const size = Number(button.getAttribute('data-preview-text-size') || 0);
			const active = Math.abs(size - textSize) < 0.1;
			button.classList.toggle('bg-navy-500/85', active);
			button.classList.toggle('text-navy-950', active);
			button.classList.toggle('bg-white/20', !active);
			button.classList.toggle('text-white', !active);
		});

		textColorButtons.forEach((button) => {
			const color = String(button.getAttribute('data-preview-text-color') || '#ffffff').toLowerCase();
			const active = color === String(textColor).toLowerCase();
			button.classList.toggle('ring-2', active);
			button.classList.toggle('ring-navy-300', active);
			button.classList.toggle('ring-offset-1', active);
			button.classList.toggle('ring-offset-black/70', active);
		});
	};

	if (previewStage instanceof HTMLElement) {
		previewStage.addEventListener('click', (event) => {
			if (!state.placementMode || state.doodleEnabled || !(previewAnnotationLayer instanceof HTMLElement)) {
				return;
			}

			const rect = previewStage.getBoundingClientRect();
			const x = event.clientX - rect.left;
			const y = event.clientY - rect.top;

			if (!Number.isFinite(x) || !Number.isFinite(y)) {
				return;
			}

			if (state.placementMode === 'text') {
				openTextEditor(x, y);
				return;
			}

			if (state.placementMode === 'sticker') {
				createStickerAnnotation(x, y);
			}
		});
	}

	if (previewAnnotationLayer instanceof HTMLElement) {
		previewAnnotationLayer.addEventListener('click', (event) => {
			const target = event.target.closest('[data-annotation-type]');
			if (target instanceof HTMLElement) {
				event.stopPropagation();
				selectAnnotation(target);
				return;
			}

			selectAnnotation(null);
		}, true);
	}

	if (stickerButtons.length > 0) {
		stickerButtons.forEach((button) => {
			button.addEventListener('click', (event) => {
				event.stopPropagation();
				state.doodleEnabled = false;
				selectedSticker = String(button.getAttribute('data-preview-sticker') || '😀');
				updateStickerUi();
				refreshTools();
				if (selectedAnnotation && selectedAnnotation.dataset.annotationType === 'sticker') {
					selectedAnnotation.textContent = selectedSticker;
				}
			});
		});
	}

	if (stickerSizeButtons.length > 0) {
		stickerSizeButtons.forEach((button) => {
			button.addEventListener('click', (event) => {
				event.stopPropagation();
				state.doodleEnabled = false;
				const nextSize = Number(button.getAttribute('data-preview-sticker-size') || stickerSize);
				if (!Number.isFinite(nextSize) || nextSize <= 0) {
					return;
				}

				stickerSize = nextSize;
				updateStickerUi();
				refreshTools();
				if (selectedAnnotation && selectedAnnotation.dataset.annotationType === 'sticker') {
					applyAnnotationSize(selectedAnnotation, stickerSize);
				}
			});
		});
	}

	if (textSizeButtons.length > 0) {
		textSizeButtons.forEach((button) => {
			button.addEventListener('click', () => {
				const nextSize = Number(button.getAttribute('data-preview-text-size') || textSize);
				if (!Number.isFinite(nextSize) || nextSize <= 0) {
					return;
				}

				textSize = nextSize;
				updateTextEditorUi();
				if (selectedAnnotation && selectedAnnotation.dataset.annotationType === 'text') {
					applyAnnotationSize(selectedAnnotation, textSize);
				}
			});
		});
	}

	if (textColorButtons.length > 0) {
		textColorButtons.forEach((button) => {
			button.addEventListener('click', () => {
				textColor = String(button.getAttribute('data-preview-text-color') || '#ffffff');
				updateTextEditorUi();
				if (selectedAnnotation && selectedAnnotation.dataset.annotationType === 'text') {
					selectedAnnotation.style.color = textColor;
				}
			});
		});
	}

	const applyTextPlacement = () => {
		if (!(textInput instanceof HTMLInputElement) || !pendingTextPlacement) {
			closeTextEditor();
			return;
		}

		const value = String(textInput.value || '').trim();
		if (!value) {
			showActionToast('Teks masih kosong');
			textInput.focus();
			return;
		}

		createTextAnnotation(pendingTextPlacement.x, pendingTextPlacement.y, value);
		closeTextEditor();
		showActionToast('Teks ditambahkan. Geser untuk memindahkan');
	};

	textApplyButton?.addEventListener('click', applyTextPlacement);
	textCancelButton?.addEventListener('click', closeTextEditor);
	textCloseButton?.addEventListener('click', closeTextEditor);
	textInput?.addEventListener('keydown', (event) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			applyTextPlacement();
		}
	});

	updateStickerUi();
	updateTextEditorUi();

	const clear = () => {
		if (previewAnnotationLayer instanceof HTMLElement) {
			previewAnnotationLayer.innerHTML = '';
		}
		selectedAnnotation = null;
		closeTextEditor();
	};

	return { clear };
};
