// Elemen DOM composer dan helper kecil yang dipakai bersama.

export const chatForm = document.querySelector('[data-chat-form]');
// Dari feed, agar aksi pesan tetap jalan di grup yang dibekukan (tanpa form composer).
export const chatMessagesBaseUrl = String(document.querySelector('[data-chat-feed]')?.dataset.messagesUrl || chatForm?.getAttribute('action') || '');

export const buildMessageApiUrl = (messageId) => {
	if (!chatMessagesBaseUrl || !messageId) {
		return '';
	}

	return `${chatMessagesBaseUrl}/${messageId}`;
};

export const attachmentInput = chatForm?.querySelector('[data-chat-attachment]');
export const cameraInput = chatForm?.querySelector('[data-chat-camera-input]');
export const cameraButton = chatForm?.querySelector('[data-open-camera]');
export const voiceButton = chatForm?.querySelector('[data-pick-voice]');
export const attachMenuButton = chatForm?.querySelector('[data-open-attach-menu]');
export const attachMenu = chatForm?.querySelector('[data-attach-menu]');
export const recordVoiceButton = chatForm?.querySelector('[data-record-voice]');
export const voiceRecorderPanel = chatForm?.querySelector('[data-voice-recorder-panel]');
export const voiceCancelButton = chatForm?.querySelector('[data-voice-cancel]');
export const voiceStopSendButton = chatForm?.querySelector('[data-voice-stop-send]');
export const voiceTimer = chatForm?.querySelector('[data-voice-timer]');
export const composerMain = chatForm?.querySelector('[data-composer-main]');
export const emojiButton = chatForm?.querySelector('[data-open-emoji]');
export const emojiPanel = chatForm?.querySelector('[data-emoji-panel]');
export const recordingIndicator = chatForm?.querySelector('[data-recording-indicator]');
export const mentionInput = chatForm?.querySelector('[data-mention-input]');
export const sendButton = chatForm?.querySelector('[data-chat-send]');
export const replyToInput = chatForm?.querySelector('[data-reply-to-input]');
export const replyQuoteInput = chatForm?.querySelector('[data-reply-quote-input]');
export const replyPreview = chatForm?.querySelector('[data-reply-preview]');
export const replyPreviewSender = chatForm?.querySelector('[data-reply-preview-sender]');
export const replyPreviewContent = chatForm?.querySelector('[data-reply-preview-content]');
export const replyClearButton = chatForm?.querySelector('[data-reply-clear]');
export const composerVoiceButton = chatForm?.querySelector('[data-composer-voice-btn]');

export const isDesktopKeyboard = (() => {
	const hasCoarsePointer = window.matchMedia?.('(pointer: coarse)').matches;
	const hasTouchPoints = (navigator.maxTouchPoints || 0) > 0;
	return !hasCoarsePointer && !hasTouchPoints;
})();

export const insertAtCursor = (input, text) => {
	const start = input.selectionStart ?? input.value.length;
	const end = input.selectionEnd ?? input.value.length;
	const next = `${input.value.slice(0, start)}${text}${input.value.slice(end)}`;
	input.value = next;
	const cursor = start + text.length;
	input.setSelectionRange(cursor, cursor);
	input.focus();
};

export const detectAttachmentMessageType = (file) => {
	if (!(file instanceof File)) {
		return 'text';
	}

	const mime = String(file.type || '').toLowerCase();
	const extension = String(file.name || '').toLowerCase().split('.').pop() || '';

	if (mime.startsWith('image/')) {
		return 'image';
	}

	if (mime.startsWith('audio/')) {
		return 'voice';
	}

	if (mime.startsWith('video/') || ['mp4', 'mov', 'm4v', 'webm', '3gp', 'mkv'].includes(extension)) {
		return 'video';
	}

	if (['mp3', 'wav', 'ogg', 'aac', 'm4a'].includes(extension)) {
		return 'voice';
	}

	return 'file';
};
