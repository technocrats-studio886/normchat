// Kolom ketik composer: tinggi otomatis, batal edit dengan Escape, dan sinyal mengetik.

import { scrollChatToBottom } from '../utils.js';
import { refreshAttachmentLabel, refreshSendVoiceToggle } from './attachment.js';
import { attachmentInput, chatForm, emojiPanel, mentionInput, recordingIndicator } from './elements.js';
import { clearEditingState, getEditingMessageId } from './reply.js';
import { showActionToast } from './toast.js';
import { noteTyping, stopTyping } from './typing.js';

export const initComposerInput = () => {
	attachmentInput.addEventListener('change', refreshAttachmentLabel);

	if (mentionInput instanceof HTMLTextAreaElement) {
		const resizeComposerInput = () => {
			mentionInput.style.height = 'auto';
			mentionInput.style.height = `${Math.min(mentionInput.scrollHeight, 96)}px`;
		};

		mentionInput.addEventListener('focus', () => {
			window.setTimeout(() => scrollChatToBottom('auto'), 70);
			window.setTimeout(() => scrollChatToBottom('auto'), 220);
		});

		mentionInput.addEventListener('keydown', (event) => {
			if (event.key !== 'Escape' || getEditingMessageId() <= 0) {
				return;
			}

			event.preventDefault();
			clearEditingState(true);
			showActionToast('Batal mengedit');
		});

		mentionInput.addEventListener('input', resizeComposerInput);
		mentionInput.addEventListener('input', refreshSendVoiceToggle);
		refreshSendVoiceToggle();
		mentionInput.addEventListener('input', () => {
			if (mentionInput.value.trim().length === 0) {
				stopTyping();
				return;
			}

			noteTyping();
		});
		resizeComposerInput();
	}

	chatForm.addEventListener('submit', () => {
		emojiPanel?.classList.add('hidden');
		recordingIndicator?.classList.add('hidden');
		stopTyping();
	});
};
