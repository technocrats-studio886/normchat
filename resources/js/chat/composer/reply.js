// Target balasan (reply) di composer. Mode edit ada di edit-mode.js.

import { mentionInput, replyClearButton, replyPreview, replyPreviewContent, replyPreviewSender, replyQuoteInput, replyToInput } from './elements.js';

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
};

export const normalizeReplyQuote = (value) => String(value || '')
	.replace(/\s+/g, ' ')
	.trim()
	.slice(0, 500);

/** Teks yang sedang diblok di dalam pesan itu sendiri, bila ada. */
const selectedTextIn = (messageNode) => {
	const selection = window.getSelection();
	if (!selection || selection.rangeCount === 0 || !messageNode.contains(selection.getRangeAt(0).commonAncestorContainer)) {
		return '';
	}

	return normalizeReplyQuote(selection.toString());
};

/**
 * Jadikan pesan sebagai target balasan. $quote = bagian yang dikutip; tanpa $quote dipakai teks
 * yang sedang diblok di pesan itu, atau seluruh pesan.
 */
export const setReplyTarget = (messageNode, quote = null) => {
	if (!(messageNode instanceof HTMLElement) || !(replyToInput instanceof HTMLInputElement)) {
		return;
	}

	const messageId = String(messageNode.getAttribute('data-message-id') || '').trim();
	if (!messageId) {
		return;
	}

	const fromSelection = quote === null;
	const normalizedQuote = fromSelection ? selectedTextIn(messageNode) : normalizeReplyQuote(quote);

	replyToInput.value = messageId;
	if (replyQuoteInput instanceof HTMLInputElement) {
		replyQuoteInput.value = normalizedQuote;
	}
	if (replyPreviewSender instanceof HTMLElement) {
		replyPreviewSender.textContent = `Membalas ${messageNode.dataset.messageSenderName || 'Anggota'}`;
	}
	if (replyPreviewContent instanceof HTMLElement) {
		replyPreviewContent.textContent = normalizedQuote || String(messageNode.dataset.messageContent || '').trim();
	}
	if (replyPreview instanceof HTMLElement) {
		replyPreview.classList.remove('hidden');
		replyPreview.classList.add('flex');
	}
	if (fromSelection && normalizedQuote) {
		window.getSelection()?.removeAllRanges();
	}

	mentionInput?.focus();
};

export const initReply = () => {
	replyClearButton?.addEventListener('click', clearReplyTarget);
};
