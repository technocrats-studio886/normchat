// Hidrasi pesan yang sudah dirender server (waktu lokal, voice, polling, markdown AI).

import { chatFeed } from './context.js';
import { renderMarkdown, renderMermaidInNode } from './markdown.js';
import { localizeExistingMessageTimes } from './messages.js';
import { hydrateExistingPollMessages, refreshPollCardsFromFeed } from './polls.js';
import { escapeHtml } from './utils.js';
import { initVoicePlayers } from './voice-player.js';

/** Hapus keterangan "Belum ada pesan" begitu pesan pertama masuk (terkirim, pending, atau realtime). */
const removeEmptyStateOnFirstMessage = () => {
	const emptyState = chatFeed?.querySelector('[data-feed-empty]');
	if (!emptyState) {
		return;
	}

	const observer = new MutationObserver(() => {
		if (chatFeed.querySelector('[data-message-id], [data-local-message-id]')) {
			emptyState.remove();
			observer.disconnect();
		}
	});
	observer.observe(chatFeed, { childList: true });
};

export const initRenderedFeed = () => {
	removeEmptyStateOnFirstMessage();
	localizeExistingMessageTimes();

	initVoicePlayers(document);

	hydrateExistingPollMessages();

	refreshPollCardsFromFeed();

	// Render markdown in server-rendered AI bubbles
	document.querySelectorAll('[data-ai-raw]').forEach((el) => {
		const raw = el.textContent.trim();
		if (!raw) return;
		el.innerHTML = renderMarkdown(escapeHtml(raw));
		renderMermaidInNode(el);

		// Expand parent wrapper for rich content
		if (el.innerHTML.includes('<table') || el.innerHTML.includes('mermaid')) {
			const wrapper = el.closest('[data-message-id]');
			if (wrapper) {
				wrapper.classList.remove('max-w-[80%]');
				wrapper.classList.add('max-w-[95%]');
			}
		}
	});
};
