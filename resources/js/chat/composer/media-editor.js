// Pratinjau lampiran foto/video layar penuh: caption, coretan, teks, dan emoji.
// Bagian-bagiannya ada di media-editor/; file ini merakit elemen dan toolbar alatnya.

import { showActionToast } from './toast.js';
import { bindAnnotations } from './media-editor/annotations.js';
import { bindCaption } from './media-editor/caption.js';
import { bindDoodle } from './media-editor/doodle.js';
import { flattenImage } from './media-editor/flatten.js';
import { loadPreviewMentionSuggestions } from './media-editor/mention-menu.js';
import { mediaPreviewTemplate } from './media-editor/template.js';

/** Diisi saat editor dibuat: menggabungkan hasil edit ke foto terpilih (dipanggil send.js sebelum mengirim). */
let applyEdits = async () => undefined;

/** Coretan, teks, dan stiker digabung ke foto di kolom lampiran; tanpa edit, foto tidak diubah. */
export const applyMediaEdits = () => applyEdits();

export const ensureMediaAttachmentPreviewEl = (onClear) => {
	let el = document.querySelector('[data-attachment-preview-media]');
	if (el instanceof HTMLElement) {
		return el;
	}

	el = document.createElement('div');
	el.setAttribute('data-attachment-preview-media', '1');
	// Menutupi seluruh layar di HP maupun desktop; isinya dibatasi lebarnya di tengah (laporan user 11 Nov 2026:
	// di desktop dulu hanya kolom selebar HP di tengah chat).
	el.className = 'nc-media-preview fixed inset-0 z-[80] hidden';
	el.innerHTML = mediaPreviewTemplate;

	el.querySelector('[data-attachment-preview-media-clear]')?.addEventListener('click', onClear);

	const previewMediaCaption = el.querySelector('[data-attachment-preview-media-caption]');
	const previewMediaImg = el.querySelector('[data-attachment-preview-media-img]');
	const previewMediaVideo = el.querySelector('[data-attachment-preview-media-video]');
	const penSizeGroup = el.querySelector('[data-preview-pen-sizes]');
	const textControls = el.querySelector('[data-preview-text-controls]');
	const previewStage = el.querySelector('[data-attachment-preview-stage]');
	const stickerControls = el.querySelector('[data-preview-sticker-controls]');
	const toolButtons = Array.from(el.querySelectorAll('[data-preview-tool-button="1"]'));

	// placementMode: '' | 'sticker' | 'text' — alat yang aktif saat media diketuk.
	const state = { doodleEnabled: false, placementMode: '' };
	const mentionSuggestions = loadPreviewMentionSuggestions();

	const updateToolButtonState = () => {
		toolButtons.forEach((button) => {
			const tool = String(button.getAttribute('data-preview-tool') || '');
			const isActive = (tool === 'doodle' && state.doodleEnabled) || (tool !== 'doodle' && state.placementMode === tool);
			button.classList.toggle('bg-navy-500/80', isActive);
			button.classList.toggle('text-navy-950', isActive);
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
		if (textControls instanceof HTMLElement) {
			const showTextControls = !state.doodleEnabled && state.placementMode === 'text';
			textControls.classList.toggle('hidden', !showTextControls);
			textControls.classList.toggle('flex', showTextControls);
		}
	};

	bindCaption(el, mentionSuggestions);
	const doodle = bindDoodle(el, state);
	const annotations = bindAnnotations(el, state, updateToolButtonState);

	applyEdits = async () => {
		const input = document.querySelector('[data-chat-attachment]');
		const file = input?.files?.[0];
		annotations.commit();
		const items = annotations.items();
		if (!(file instanceof File) || !file.type.startsWith('image/') || file.type === 'image/gif'
			|| !(previewMediaImg instanceof HTMLImageElement) || !previewMediaImg.complete || !(previewStage instanceof HTMLElement)
			|| (!doodle.hasStrokes() && items.length === 0)) {
			return;
		}
		annotations.deselect();
		const edited = await flattenImage({ img: previewMediaImg, stage: previewStage, file, paintDoodle: doodle.paint, items });
		const transfer = new DataTransfer();
		transfer.items.add(edited);
		input.files = transfer.files;
	};

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
			togglePlacementMode('sticker', 'Ketuk foto untuk menaruh emoji, lalu geser untuk memindahkannya');
			return;
		}

		if (tool === 'text') {
			togglePlacementMode('text', 'Ketuk foto di tempat teks mau ditulis');
			return;
		}

		if (tool === 'doodle' && doodle.available) {
			annotations.commit();
			doodle.setEnabled(!state.doodleEnabled);
			state.placementMode = '';
			updateToolButtonState();
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
