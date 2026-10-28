// Feed chat: render pesan awal dari data halaman, jaga penanda rangkaian pesan, dan ganti foto
// profil yang gagal dimuat dengan avatar siluet.

import { chatFeed } from './context.js';
import { renderMermaidInNode } from './markdown.js';
import { applyMessageRuns, avatarFallbackHtml, buildMessageNode } from './messages.js';
import { refreshPollCardsFromFeed } from './polls.js';
import { initVoicePlayers } from './voice-player.js';

const readInitialMessages = () => {
	const source = document.querySelector('[data-initial-messages]');
	if (!source) {
		return [];
	}

	try {
		const messages = JSON.parse(source.textContent || '[]');
		return Array.isArray(messages) ? messages : [];
	} catch {
		return [];
	} finally {
		source.remove();
	}
};

/** Hapus keterangan "Belum ada pesan" begitu pesan pertama masuk (terkirim, pending, atau realtime). */
const removeEmptyStateOnFirstMessage = () => {
	const emptyState = chatFeed?.querySelector('[data-feed-empty]');
	if (!emptyState) {
		return;
	}

	const observer = new MutationObserver(() => {
		if (chatFeed.querySelector('.nc-msg')) {
			emptyState.remove();
			observer.disconnect();
		}
	});
	observer.observe(chatFeed, { childList: true });
};

/** Rangkaian dihitung ulang setiap kali pesan ditambah, diganti, dihapus, atau disembunyikan. */
const keepMessageRunsUpdated = () => {
	let scheduled = false;
	const schedule = () => {
		if (scheduled) {
			return;
		}
		scheduled = true;
		window.requestAnimationFrame(() => {
			scheduled = false;
			applyMessageRuns(chatFeed);
		});
	};

	new MutationObserver(schedule).observe(chatFeed, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
};

export const initRenderedFeed = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return;
	}

	const typingIndicator = chatFeed.querySelector('[data-typing-indicator]');
	const fragment = document.createDocumentFragment();
	readInitialMessages().forEach((message) => fragment.appendChild(buildMessageNode(message)));
	chatFeed.insertBefore(fragment, typingIndicator);

	applyMessageRuns(chatFeed);
	keepMessageRunsUpdated();
	removeEmptyStateOnFirstMessage();
	initVoicePlayers(chatFeed);
	renderMermaidInNode(chatFeed);
	refreshPollCardsFromFeed();

	// Foto profil gagal dimuat (link kedaluwarsa, diblokir): pakai avatar siluet.
	chatFeed.addEventListener('error', (event) => {
		const image = event.target;
		if (image instanceof HTMLImageElement && image.hasAttribute('data-avatar-photo')) {
			image.outerHTML = avatarFallbackHtml();
		}
	}, true);
};
