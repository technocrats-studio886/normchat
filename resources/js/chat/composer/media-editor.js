// Pratinjau lampiran foto/video layar penuh: caption, coretan, teks, dan emoji.
// Bagian-bagiannya ada di media-editor/; file ini merakit elemen dan toolbar alatnya.

import { showActionToast } from './toast.js';
import { bindAnnotations } from './media-editor/annotations.js';
import { bindCaption } from './media-editor/caption.js';
import { bindDoodle } from './media-editor/doodle.js';
import { loadPreviewMentionSuggestions } from './media-editor/mention-menu.js';
import { mediaPreviewTemplate } from './media-editor/template.js';

export const ensureMediaAttachmentPreviewEl = (onClear) => {
	let el = document.querySelector('[data-attachment-preview-media]');
	if (el instanceof HTMLElement) {
		return el;
	}

	el = document.createElement('div');
	el.setAttribute('data-attachment-preview-media', '1');
	el.className = 'fixed inset-0 left-1/2 z-[55] hidden w-full max-w-md -translate-x-1/2 bg-slate-950/95';
	el.innerHTML = mediaPreviewTemplate;

	el.querySelector('[data-attachment-preview-media-clear]')?.addEventListener('click', onClear);

	const previewMediaCaption = el.querySelector('[data-attachment-preview-media-caption]');
	const previewMediaImg = el.querySelector('[data-attachment-preview-media-img]');
	const previewMediaVideo = el.querySelector('[data-attachment-preview-media-video]');
	const penSizeGroup = el.querySelector('[data-preview-pen-sizes]');
	const stickerControls = el.querySelector('[data-preview-sticker-controls]');
	const toolButtons = Array.from(el.querySelectorAll('[data-preview-tool-button="1"]'));

	// placementMode: '' | 'sticker' | 'text' — alat yang aktif saat media diketuk.
	const state = { doodleEnabled: false, placementMode: '' };
	const mentionSuggestions = loadPreviewMentionSuggestions();

	const updateToolButtonState = () => {
		toolButtons.forEach((button) => {
			const tool = String(button.getAttribute('data-preview-tool') || '');
			const isActive = (tool === 'doodle' && state.doodleEnabled) || (tool !== 'doodle' && state.placementMode === tool);
			button.classList.toggle('bg-emerald-500/80', isActive);
			button.classList.toggle('text-emerald-950', isActive);
			button.classList.toggle('bg-white/14', !isActive);
			button.classList.toggle('text-white', !isActive);
		});

		if (penSizeGroup instanceof HTMLElement) {
			const showPenControls = state.doodleEnabled === true;
			penSizeGroup.classList.toggle('hidden', !showPenControls);
			penSizeGroup.classList.toggle('flex', showPenControls);
		}

		if (stickerControls instanceof HTMLElement) {
			const showStickerControls = !state.doodleEnabled && state.placementMode === 'sticker';
			stickerControls.classList.toggle('hidden', !showStickerControls);
		}
	};

	bindCaption(el, mentionSuggestions);
	const doodle = bindDoodle(el, state);
	const annotations = bindAnnotations(el, state, mentionSuggestions, updateToolButtonState);

	const togglePlacementMode = (mode, hint) => {
		doodle.setEnabled(false);
		state.placementMode = state.placementMode === mode ? '' : mode;
		updateToolButtonState();
		if (state.placementMode === mode) {
			showActionToast(hint);
		}
	};

	el.querySelector('[data-attachment-preview-tools]')?.addEventListener('click', (event) => {
		const toolButton = event.target.closest('[data-preview-tool]');
		if (!(toolButton instanceof HTMLButtonElement)) {
			return;
		}

		const tool = String(toolButton.getAttribute('data-preview-tool') || '');
		if (tool === 'sticker') {
			togglePlacementMode('sticker', 'Tap media untuk menaruh emoji, lalu drag untuk geser');
			return;
		}

		if (tool === 'text') {
			togglePlacementMode('text', 'Tap media untuk menaruh teks');
			return;
		}

		if (tool === 'doodle' && doodle.available) {
			doodle.setEnabled(!state.doodleEnabled);
			state.placementMode = '';
			updateToolButtonState();
			if (state.doodleEnabled) {
				doodle.resize();
				showActionToast('Mode coretan aktif');
			} else {
				showActionToast('Mode coretan dimatikan');
			}
		}
	});

	const resetEditor = () => {
		annotations.clear();
		doodle.clear();
		doodle.setEnabled(false);
		state.placementMode = '';
		if (previewMediaCaption instanceof HTMLElement) {
			previewMediaCaption.textContent = '';
			previewMediaCaption.classList.add('hidden');
		}
		updateToolButtonState();
	};

	window.addEventListener('resize', doodle.resize);
	previewMediaImg?.addEventListener('load', doodle.resize);
	previewMediaVideo?.addEventListener('loadedmetadata', doodle.resize);
	el.addEventListener('preview:resize-doodle', doodle.resize);
	el.addEventListener('preview:reset-annotations', resetEditor);
	updateToolButtonState();

	document.body.appendChild(el);
	return el;
};
