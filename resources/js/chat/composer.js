// Composer chat: lampiran, pilihan AI (model & mode), editor media, reply, aksi pesan, rekam suara, dan pengiriman.
// Bagian-bagiannya ada di chat/composer/; urutan init mengikuti urutan pendaftaran listener semula.

import { initAiOptions } from './composer/ai-options.js';
import { initAttachMenu } from './composer/attach-menu.js';
import { attachmentInput, chatForm } from './composer/elements.js';
import { initFeedClicks } from './composer/feed-clicks.js';
import { initComposerInput } from './composer/input.js';
import { initMessageActions } from './composer/message-actions.js';
import { initReplyPreviewPicker } from './composer/quote-picker.js';
import { initReply } from './composer/reply.js';
import { initSend } from './composer/send.js';
import { initVoiceRecorder } from './composer/voice-recorder.js';

export { setPollComposerOpener } from './composer/attach-menu.js';
export { chatForm, chatMessagesBaseUrl, isDesktopKeyboard, mentionInput } from './composer/elements.js';

export const initComposer = () => {
	if (!chatForm || !(attachmentInput instanceof HTMLInputElement)) {
		return;
	}

	initReply();
	initReplyPreviewPicker();
	initFeedClicks();
	initMessageActions();
	initAttachMenu();
	initAiOptions();
	initVoiceRecorder();
	initComposerInput();
	initSend();
};
