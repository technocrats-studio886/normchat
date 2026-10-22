// Target balasan (reply) dan mode edit pesan di composer.

import { mentionInput, replyClearButton, replyPreview, replyPreviewContent, replyPreviewSender, replyQuoteInput, replyToInput } from './elements.js';

let editingMessageId = 0;

export const getEditingMessageId = () => editingMessageId;
export const startEditing = (messageId) => {
	editingMessageId = messageId;
};

export const clearEditingState = (clearInput = false) => {
	editingMessageId = 0;
	if (clearInput && (mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
		mentionInput.value = '';
		mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
	}
};

export const clearReplyTarget = () => {
	if (replyToInput instanceof HTMLInputElement) {
		replyToInput.value = '';
	}

	if (replyQuoteInput instanceof HTMLInputElement) {
		replyQuoteInput.value = '';
	}

	if (replyPreview instanceof HTMLElement) {
		replyPreview.classList.add('hidden');
		replyPreview.classList.remove('flex');
	}

	if (editingMessageId > 0) {
		clearEditingState();
	}
};

export const normalizeReplyQuote = (value) => String(value || '')
	.replace(/\s+/g, ' ')
	.trim()
	.slice(0, 500);

const getSelectedReplyQuoteFromMessage = (messageNode) => {
	if (!(messageNode instanceof HTMLElement)) {
		return '';
	}

	const selection = window.getSelection();
	if (!selection || selection.rangeCount === 0) {
		return '';
	}

	const selectedText = normalizeReplyQuote(selection.toString() || '');
	if (selectedText === '') {
		return '';
	}

	const selectionRange = selection.getRangeAt(0);
	const commonNode = selectionRange.commonAncestorContainer;
	if (!messageNode.contains(commonNode)) {
		return '';
	}

	return selectedText;
};

export const setReplyTarget = (messageNode) => {
	if (!(messageNode instanceof HTMLElement) || !(replyToInput instanceof HTMLInputElement)) {
		return;
	}

	const messageId = String(messageNode.getAttribute('data-message-id') || '').trim();
	if (!messageId) {
		return;
	}

	const sender = String(messageNode.dataset.messageSenderName || 'User');
	const rawContent = String(messageNode.dataset.messageContent || '').trim();
	const type = String(messageNode.dataset.messageType || 'text').toLowerCase();
	const fallback = type === 'image' ? '[Gambar]' : (type === 'voice' ? '[Voice note]' : '[Lampiran]');
	const content = rawContent !== '' ? rawContent : fallback;
	const selectedQuote = getSelectedReplyQuoteFromMessage(messageNode);

	replyToInput.value = messageId;
	if (replyQuoteInput instanceof HTMLInputElement) {
		replyQuoteInput.value = selectedQuote;
	}
	clearEditingState();
	if (replyPreviewSender instanceof HTMLElement) {
		replyPreviewSender.textContent = `Membalas ${sender}`;
	}
	if (replyPreviewContent instanceof HTMLElement) {
		replyPreviewContent.textContent = selectedQuote || content;
	}
	if (replyPreview instanceof HTMLElement) {
		replyPreview.classList.remove('hidden');
		replyPreview.classList.add('flex');
	}

	const selection = window.getSelection();
	if (selection && selectedQuote !== '') {
		selection.removeAllRanges();
	}

	mentionInput?.focus();
};

/* ── setReplyTarget with optional partial text ── */
export const setReplyTargetWithText = (messageNode, selectedText) => {
	if (!(messageNode instanceof HTMLElement) || !(replyToInput instanceof HTMLInputElement)) return;
	const messageId = String(messageNode.getAttribute('data-message-id') || '').trim();
	if (!messageId) return;

	const sender = String(messageNode.dataset.messageSenderName || 'User');
	const rawContent = String(messageNode.dataset.messageContent || '').trim();
	const type = String(messageNode.dataset.messageType || 'text').toLowerCase();
	const fallback = type === 'image' ? '[Gambar]' : (type === 'voice' ? '[Voice note]' : '[Lampiran]');
	const normalizedSelectedText = normalizeReplyQuote(selectedText);
	const content = normalizedSelectedText || (rawContent !== '' ? rawContent : fallback);

	replyToInput.value = messageId;
	if (replyQuoteInput instanceof HTMLInputElement) {
		replyQuoteInput.value = normalizedSelectedText;
	}
	clearEditingState();
	if (replyPreviewSender instanceof HTMLElement) {
		replyPreviewSender.textContent = `Membalas ${sender}`;
	}
	if (replyPreviewContent instanceof HTMLElement) {
		replyPreviewContent.textContent = content;
	}
	if (replyPreview instanceof HTMLElement) {
		replyPreview.classList.remove('hidden');
		replyPreview.classList.add('flex');
	}
	mentionInput?.focus();
};

export const initReply = () => {
	replyClearButton?.addEventListener('click', clearReplyTarget);
};
