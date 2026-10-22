// Caption di pratinjau media: sinkron dua arah dengan kolom ketik composer, tombol kirim.

import { chatForm, mentionInput } from '../elements.js';
import { bindPreviewMentionMenu } from './mention-menu.js';

export const bindCaption = (el, mentionSuggestions) => {
	const previewCaptionInput = el.querySelector('[data-attachment-preview-caption-input]');
	const previewCaptionSend = el.querySelector('[data-attachment-preview-caption-send]');
	const previewCaptionMentionMenu = el.querySelector('[data-preview-caption-mention-menu]');

	const syncComposerCaption = (text) => {
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
		previewCaptionInput.value = composerValue;
	};
	el.addEventListener('preview:sync-caption', syncCaptionFromComposer);
};
