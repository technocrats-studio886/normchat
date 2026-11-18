// Caption di pratinjau media: sinkron dua arah dengan kolom ketik composer, tombol kirim.

import { chatForm, mentionInput } from '../elements.js';
import { bindPreviewMentionMenu } from './mention-menu.js';

export const bindCaption = (el, mentionSuggestions) => {
	const previewCaptionInput = el.querySelector('[data-attachment-preview-caption-input]');
	const previewCaptionSend = el.querySelector('[data-attachment-preview-caption-send]');
	const previewCaptionMentionMenu = el.querySelector('[data-preview-caption-mention-menu]');

	const fileButton = el.querySelector('[data-preview-make-file]');
	let fileMode = false;
	const syncComposerCaption = (text) => {
		if (fileMode && !/(^|\s)@normi(?:-[a-z]+)?(?![\w-])/i.test(text)) {
			text = '@normi-file ' + text;
		}
		if (!(mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
			return;
		}
		if (mentionInput.value === text) {
			return;
		}
		mentionInput.value = text;
		mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
	};

	if (previewCaptionInput instanceof HTMLInputElement) {
		previewCaptionInput.addEventListener('input', () => {
			syncComposerCaption(previewCaptionInput.value);
		});
		bindPreviewMentionMenu(previewCaptionInput, previewCaptionMentionMenu, mentionSuggestions, syncComposerCaption);
	}

	if (previewCaptionSend instanceof HTMLButtonElement) {
		previewCaptionSend.addEventListener('click', () => {
			if (previewCaptionInput instanceof HTMLInputElement) {
				syncComposerCaption(previewCaptionInput.value.trim());
			}
			chatForm.requestSubmit();
		});
	}

	const syncCaptionFromComposer = () => {
		if (!(previewCaptionInput instanceof HTMLInputElement)) {
			return;
		}
		const composerValue = mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement
			? mentionInput.value
			: '';
		fileMode = /(^|\s)@normi-file(?![\w-])/i.test(composerValue);
		fileButton?.setAttribute('aria-pressed', String(fileMode));
		if (fileButton) fileButton.textContent = fileMode ? '✓ Buat file' : 'Buat file';
		previewCaptionInput.value = fileMode
			? composerValue.replace(/(^|\s)@normi-file(?![\w-])\s*/i, '$1').trimStart()
			: composerValue;
	};
	el.addEventListener('preview:sync-caption', syncCaptionFromComposer);
};
