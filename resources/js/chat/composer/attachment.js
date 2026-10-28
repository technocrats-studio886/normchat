// Lampiran terpilih di composer: sinkronisasi input, pratinjau, dan tombol kirim/rekam.

import { formatFileSize } from '../attachments.js';
import { attachmentInput, cameraInput, chatForm, composerVoiceButton, mentionInput, sendButton } from './elements.js';
import { ensureMediaAttachmentPreviewEl } from './media-editor.js';

export const syncAttachmentFromInput = (sourceInput) => {
	const file = sourceInput?.files?.[0];
	if (!file) {
		return;
	}

	const transfer = new DataTransfer();
	transfer.items.add(file);
	attachmentInput.files = transfer.files;
};

let attachmentPreviewUrl = null;
const revokeAttachmentPreviewUrl = () => {
	if (!attachmentPreviewUrl) {
		return;
	}
	URL.revokeObjectURL(attachmentPreviewUrl);
	attachmentPreviewUrl = null;
};

export const clearSelectedAttachment = () => {
	attachmentInput.value = '';
	if (cameraInput instanceof HTMLInputElement) {
		cameraInput.value = '';
	}
	revokeAttachmentPreviewUrl();
	refreshAttachmentLabel();
};

const ensureFileAttachmentPreviewEl = () => {
	let el = chatForm.querySelector('[data-attachment-preview-file]');
	if (el instanceof HTMLElement) {
		return el;
	}

	el = document.createElement('div');
	el.setAttribute('data-attachment-preview-file', '1');
	el.className = 'mb-2 hidden items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5';
	el.innerHTML = `
	<span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-200 text-slate-600"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg></span>
	<div class="min-w-0 flex-1">
		<p class="truncate text-sm font-semibold text-slate-700" data-attachment-preview-file-name>Dokumen</p>
		<p class="text-[11px] text-slate-500" data-attachment-preview-file-size>Siap dikirim</p>
	</div>
	<button type="button" class="rounded-full p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600" data-attachment-preview-file-clear aria-label="Batal lampiran">
		<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
	</button>
`;

	const composer = chatForm.querySelector('[data-composer-main]');
	if (composer && composer.parentNode) {
		composer.parentNode.insertBefore(el, composer);
	} else {
		chatForm.appendChild(el);
	}

	el.querySelector('[data-attachment-preview-file-clear]')?.addEventListener('click', clearSelectedAttachment);
	return el;
};

export const refreshAttachmentLabel = () => {
	const file = attachmentInput.files?.[0];
	const filePreviewEl = ensureFileAttachmentPreviewEl();
	const mediaPreviewEl = ensureMediaAttachmentPreviewEl(clearSelectedAttachment);
	const previewImg = mediaPreviewEl.querySelector('[data-attachment-preview-media-img]');
	const previewVideo = mediaPreviewEl.querySelector('[data-attachment-preview-media-video]');
	const previewMediaName = mediaPreviewEl.querySelector('[data-attachment-preview-media-name]');
	const previewMediaCaption = mediaPreviewEl.querySelector('[data-attachment-preview-media-caption]');
	const previewMediaCaptionInput = mediaPreviewEl.querySelector('[data-attachment-preview-caption-input]');
	const previewFileName = filePreviewEl.querySelector('[data-attachment-preview-file-name]');
	const previewFileSize = filePreviewEl.querySelector('[data-attachment-preview-file-size]');

	revokeAttachmentPreviewUrl();

	if (previewImg instanceof HTMLImageElement) {
		previewImg.src = '';
		previewImg.classList.add('hidden');
	}
	if (previewVideo instanceof HTMLVideoElement) {
		previewVideo.pause();
		previewVideo.removeAttribute('src');
		previewVideo.load();
		previewVideo.classList.add('hidden');
	}
	mediaPreviewEl.dispatchEvent(new Event('preview:reset-annotations'));
	if (previewMediaCaptionInput instanceof HTMLInputElement) {
		previewMediaCaptionInput.value = mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement
			? mentionInput.value
			: '';
	}
	mediaPreviewEl.dispatchEvent(new Event('preview:resize-doodle'));

	if (!file) {
		mediaPreviewEl.classList.add('hidden');
		filePreviewEl.classList.add('hidden');
		filePreviewEl.classList.remove('flex');
		refreshSendVoiceToggle();
		return;
	}

	const lowerMime = String(file.type || '').toLowerCase();
	const isImage = lowerMime.startsWith('image/');
	const isVideo = lowerMime.startsWith('video/');

	if (isImage || isVideo) {
		attachmentPreviewUrl = URL.createObjectURL(file);
		if (previewMediaName instanceof HTMLElement) {
			previewMediaName.textContent = file.name;
		}
		if (previewMediaCaption instanceof HTMLElement) {
			previewMediaCaption.textContent = '';
			previewMediaCaption.classList.add('hidden');
		}

		if (isImage && previewImg instanceof HTMLImageElement) {
			previewImg.src = attachmentPreviewUrl;
			previewImg.classList.remove('hidden');
		}

		if (isVideo && previewVideo instanceof HTMLVideoElement) {
			previewVideo.src = attachmentPreviewUrl;
			previewVideo.classList.remove('hidden');
		}

		filePreviewEl.classList.add('hidden');
		filePreviewEl.classList.remove('flex');
		mediaPreviewEl.classList.remove('hidden');
		mediaPreviewEl.dispatchEvent(new Event('preview:sync-caption'));
		mediaPreviewEl.dispatchEvent(new Event('preview:resize-doodle'));
		refreshSendVoiceToggle();
		return;
	}

	if (previewFileName instanceof HTMLElement) {
		previewFileName.textContent = file.name;
	}
	if (previewFileSize instanceof HTMLElement) {
		previewFileSize.textContent = formatFileSize(file.size);
	}

	mediaPreviewEl.classList.add('hidden');
	filePreviewEl.classList.remove('hidden');
	filePreviewEl.classList.add('flex');
	refreshSendVoiceToggle();
};

/** Tombol kirim tampil bila ada teks, lampiran, atau sedang mengedit; selain itu tombol rekam suara. */
export const refreshSendVoiceToggle = () => {
	if (!(mentionInput instanceof HTMLTextAreaElement)) {
		return;
	}

	const hasText = mentionInput.value.trim().length > 0;
	const hasAttachment = attachmentInput instanceof HTMLInputElement && (attachmentInput.files?.length || 0) > 0;
	const canSend = hasText || hasAttachment || document.documentElement.classList.contains('nc-editing');
	if (sendButton instanceof HTMLElement) {
		sendButton.classList.toggle('hidden', !canSend);
	}
	if (composerVoiceButton instanceof HTMLElement) {
		composerVoiceButton.classList.toggle('hidden', canSend);
	}
};
