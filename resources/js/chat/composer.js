// Composer chat: lampiran, pilihan AI (model & mode), editor media, reply, aksi pesan (chat/actions/),
// mode edit, rekam suara, dan pengiriman.
// Bagian-bagiannya ada di chat/composer/; urutan init mengikuti urutan pendaftaran listener semula.

import { initMessageMenu } from './actions/menu.js';
import { initSelectMode } from './actions/select-mode.js';
import { initAiOptions } from './composer/ai-options.js';
import { initAttachMenu } from './composer/attach-menu.js';
import { attachmentInput, chatForm } from './composer/elements.js';
import { initFeedClicks } from './composer/feed-clicks.js';
import { initEditMode } from './composer/edit-mode.js';
import { initComposerInput } from './composer/input.js';
import { initMessageActions } from './composer/message-actions.js';
import { initReplyPreviewPicker } from './composer/quote-picker.js';
import { initReply } from './composer/reply.js';
import { initSend } from './composer/send.js';
import { initVoiceRecorder } from './composer/voice-recorder.js';

export { setPollComposerOpener } from './composer/attach-menu.js';
export { chatForm, chatMessagesBaseUrl, isDesktopKeyboard, mentionInput } from './composer/elements.js';

export const initComposer = () => {
	// Aksi pesan & klik feed juga dipakai di grup yang dibekukan (tanpa form composer).
	initSelectMode();
	initFeedClicks();
	initMessageMenu();
	if (!chatForm || !(attachmentInput instanceof HTMLInputElement)) {
		return;
	}

	initReply();
	initReplyPreviewPicker();
	initMessageActions();
	initEditMode();
	initAttachMenu();
	initAiOptions();
	initVoiceRecorder();
	initComposerInput();
	initSend();
};
