// Pencarian pesan di dalam grup.

import { clearSearchHighlightsInFeed, highlightSearchInMessage } from './highlight.js';

export const initMessageSearch = () => {
	(() => {
		const bar = document.querySelector('[data-message-search-bar]');
		const input = document.querySelector('[data-message-search-input]');
		const countEl = document.querySelector('[data-message-search-count]');
		const openBtn = document.querySelector('[data-open-search]');
		const closeBtn = document.querySelector('[data-close-search]');
		const feed = document.querySelector('[data-chat-feed]');
		if (!bar || !input || !openBtn || !closeBtn || !feed) return;

		const show = () => {
			bar.classList.remove('hidden');
			bar.classList.add('flex');
			input.focus();
		};
		const hide = () => {
			bar.classList.add('hidden');
			bar.classList.remove('flex');
			input.value = '';
			applyFilter('');
		};
		const applyFilter = (raw) => {
			const q = raw.trim().toLowerCase();
			const nodes = feed.querySelectorAll('[data-message-id]');
			let matches = 0;
			clearSearchHighlightsInFeed(feed);
			nodes.forEach((node) => {
				if (!q) {
					node.classList.remove('hidden');
					return;
				}
				const content = String(node.dataset.messageContent || '').toLowerCase();
				const sender = String(node.dataset.messageSenderName || '').toLowerCase();
				const match = content.includes(q) || sender.includes(q);
				node.classList.toggle('hidden', !match);
				if (match) {
					highlightSearchInMessage(node, raw.trim());
					matches++;
				}
			});
			if (countEl) countEl.textContent = q ? `${matches} hasil` : '';
		};

		openBtn.addEventListener('click', show);
		closeBtn.addEventListener('click', hide);
		input.addEventListener('input', () => applyFilter(input.value));
		input.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });
	})();
};
