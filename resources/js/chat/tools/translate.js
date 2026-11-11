// Terjemahkan pesan sekali ketuk (App\Services\Chat\MessageTranslator): hasil tampil di bawah teks asli.

import { renderMarkdown } from '../markdown.js';
import { showActionToast } from '../composer/toast.js';
import { escapeHtml } from '../utils.js';
import { toolsRequest } from './api.js';

export const translateMessage = async (node) => {
	const bubble = node.querySelector('[data-message-bubble]');
	const existing = bubble?.querySelector('[data-translation]');
	if (existing) {
		existing.remove();
		return;
	}
	const id = Number(node.getAttribute('data-message-id') || 0);
	if (!bubble || id <= 0) return;

	const block = document.createElement('div');
	block.className = 'nc-translation is-loading';
	block.setAttribute('data-translation', '');
	block.innerHTML = '<p class="nc-translation-label">Menerjemahkan…</p>';
	const anchor = bubble.querySelector('[data-msg-body-text]') || bubble.querySelector('.nc-inline-time');
	anchor ? anchor.after(block) : bubble.appendChild(block);

	try {
		const { translation } = await toolsRequest(`/messages/${id}/translate`, 'POST');
		const isAi = node.dataset.senderKey === 'ai';
		const body = isAi ? `<div class="ai-markdown">${renderMarkdown(escapeHtml(translation.text))}</div>` : `<p class="whitespace-pre-wrap">${escapeHtml(translation.text)}</p>`;
		block.classList.remove('is-loading');
		block.innerHTML = `<p class="nc-translation-label">Terjemahan dari ${escapeHtml(translation.source)}<button type="button" data-translation-hide>Sembunyikan</button></p>${body}`;
		block.querySelector('[data-translation-hide]')?.addEventListener('click', () => block.remove());
	} catch (error) {
		block.remove();
		showActionToast(error?.message && error.message !== 'request_failed' ? error.message : 'Gagal menerjemahkan');
	}
};
