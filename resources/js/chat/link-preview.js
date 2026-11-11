// Kartu pratinjau tautan di bubble chat (docs/rancangan-pratinjau-tautan.md): gambar dari situs asal, judul,
// deskripsi, domain. Data dari MessagePresenter (`link_preview`) atau event realtime `link.preview.ready`.

import { escapeAttr, escapeHtml } from './utils.js';

export const linkPreviewHtml = (card) => {
	if (!card || !card.url) return '';
	const image = card.image_url
		? `<span class="nc-msg-link-image"><img src="${escapeAttr(card.image_url)}" alt="" loading="lazy" referrerpolicy="no-referrer" data-link-image></span>`
		: '';
	const title = card.title ? `<span class="nc-msg-link-title">${escapeHtml(card.title)}</span>` : '';
	const description = card.description ? `<span class="nc-msg-link-desc">${escapeHtml(card.description)}</span>` : '';
	return `<a href="${escapeAttr(card.url)}" target="_blank" rel="noopener noreferrer nofollow" class="nc-msg-link-card" data-link-card>${image}<span class="nc-msg-link-body">${title}${description}<span class="nc-msg-link-domain">${escapeHtml(card.site_name || card.domain || '')}</span></span></a>`;
};

/** Event link.preview.ready: pasang kartu di bubble pesan itu (sekali). */
export const attachLinkPreview = (feed, messageId, card) => {
	const node = feed?.querySelector(`[data-message-id="${Number(messageId)}"]`);
	const bubble = node?.querySelector('[data-message-bubble]');
	if (!bubble || bubble.querySelector('[data-link-card]') || !card) return;
	const text = bubble.querySelector(':scope > [data-message-text]');
	const holder = document.createElement('div');
	holder.innerHTML = linkPreviewHtml(card);
	const element = holder.firstElementChild;
	if (element) bubble.insertBefore(element, text || bubble.querySelector(':scope > .nc-inline-time'));
};

/** Gambar pratinjau yang gagal dimuat (kedaluwarsa/diblokir situs): tampilkan tanpa gambar / ikon tautan. */
export const initLinkImageFallback = () => {
	document.addEventListener('error', (event) => {
		const image = event.target;
		if (!(image instanceof HTMLImageElement) || !image.hasAttribute('data-link-image')) return;
		const wrapper = image.closest('.nc-msg-link-image');
		if (wrapper) wrapper.remove();
		else image.remove();
	}, true);
};
