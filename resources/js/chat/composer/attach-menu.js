// Menu lampiran (foto, kamera, file, polling) dan panel emoji.

import { refreshAttachmentLabel, syncAttachmentFromInput } from './attachment.js';
import { attachMenu, attachMenuButton, attachmentInput, cameraButton, cameraInput, emojiButton, emojiPanel, insertAtCursor, mentionInput, voiceButton } from './elements.js';
import { showActionToast } from './toast.js';

let openPollComposerFromAny = null;

/** Pembuka composer polling didaftarkan oleh menu grup (dipakai tombol "Polling" di menu lampiran). */
export const setPollComposerOpener = (opener) => {
	openPollComposerFromAny = opener;
};

export const initAttachMenu = () => {
	cameraButton?.addEventListener('click', () => {
		if (!(cameraInput instanceof HTMLInputElement)) {
			attachmentInput.accept = 'image/*';
			attachmentInput.setAttribute('capture', 'environment');
			attachmentInput.click();
			return;
		}

		cameraInput.click();
	});

	if (cameraInput instanceof HTMLInputElement) {
		cameraInput.addEventListener('change', () => {
			syncAttachmentFromInput(cameraInput);
			refreshAttachmentLabel();
		});
	}

	voiceButton?.addEventListener('click', () => {
		attachmentInput.accept = 'image/*,video/*,audio/*';
		attachmentInput.removeAttribute('capture');
		attachmentInput.click();
	});

	// Attachment menu (Telegram-style sub-menu)
	attachMenuButton?.addEventListener('click', (event) => {
		event.preventDefault();
		emojiPanel?.classList.add('hidden');
		attachMenu?.classList.toggle('hidden');
	});

	attachMenu?.querySelector('[data-attach-photo]')?.addEventListener('click', () => {
		attachMenu.classList.add('hidden');
		attachmentInput.accept = 'image/*,video/*';
		attachmentInput.removeAttribute('capture');
		attachmentInput.click();
	});

	attachMenu?.querySelector('[data-attach-camera]')?.addEventListener('click', () => {
		attachMenu.classList.add('hidden');
		if (cameraInput instanceof HTMLInputElement) {
			cameraInput.click();
		} else {
			attachmentInput.accept = 'image/*';
			attachmentInput.setAttribute('capture', 'environment');
			attachmentInput.click();
		}
	});

	attachMenu?.querySelector('[data-attach-file]')?.addEventListener('click', () => {
		attachMenu.classList.add('hidden');
		attachmentInput.accept = '*/*';
		attachmentInput.removeAttribute('capture');
		attachmentInput.click();
	});

	attachMenu?.querySelector('[data-attach-poll]')?.addEventListener('click', () => {
		attachMenu.classList.add('hidden');
		if (typeof openPollComposerFromAny === 'function') {
			openPollComposerFromAny();
			return;
		}

		showActionToast('Menu polling belum tersedia');
	});

	// Close attach menu on outside click
	document.addEventListener('click', (e) => {
		if (attachMenu && !attachMenu.classList.contains('hidden')) {
			if (!attachMenu.contains(e.target) && e.target !== attachMenuButton && !attachMenuButton?.contains(e.target)) {
				attachMenu.classList.add('hidden');
			}
		}
	});

	emojiButton?.addEventListener('click', (event) => {
		event.preventDefault();
		attachMenu?.classList.add('hidden');
		emojiPanel?.classList.toggle('hidden');
	});

	emojiPanel?.addEventListener('click', (event) => {
		const button = event.target.closest('[data-emoji-item]');
		if (!button || !(mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
			return;
		}

		const emoji = button.getAttribute('data-emoji-item') || '';
		if (!emoji) {
			return;
		}

		insertAtCursor(mentionInput, emoji);
		emojiPanel.classList.add('hidden');
	});

	document.addEventListener('click', (event) => {
		if (!emojiPanel || !emojiButton) {
			return;
		}

		if (emojiPanel.contains(event.target) || emojiButton.contains(event.target)) {
			return;
		}

		emojiPanel.classList.add('hidden');
	});
};
