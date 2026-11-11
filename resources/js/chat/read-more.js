// Bubble yang terlalu panjang (±15 baris) dipotong dengan "Lihat selengkapnya", seperti aplikasi chat pada
// umumnya (keputusan user 11 Nov 2026). Dibuka di tempat; "Tampilkan lebih sedikit" memotongnya lagi.
// Pesan yang dicari atau dituju lewat kutipan otomatis dibuka (expandMessage, dipanggil highlight.js).

import { chatFeed } from './context.js';

/** Dipotong hanya bila sisanya cukup berarti (lebih dari ±2 baris), supaya tidak memotong satu baris saja. */
const SLACK_PX = 44;
const expanded = new Set();
let observer = null;

const clampHeight = (text) => parseFloat(getComputedStyle(text).getPropertyValue('--nc-clamp-height')) * parseFloat(getComputedStyle(document.documentElement).fontSize) || 352;

const messageIdOf = (text) => text.closest('[data-message-id]')?.getAttribute('data-message-id') || '';

const toggleButton = (text) => {
	let button = text.nextElementSibling?.matches?.('[data-read-more]') ? text.nextElementSibling : null;
	if (!button) {
		button = document.createElement('button');
		button.type = 'button';
		button.className = 'nc-read-more';
		button.setAttribute('data-read-more', '1');
		text.after(button);
	}
	return button;
};

const setClamped = (text, clamped) => {
	text.classList.toggle('is-clamped', clamped);
	const button = toggleButton(text);
	button.textContent = clamped ? 'Lihat selengkapnya' : 'Tampilkan lebih sedikit';
	button.setAttribute('aria-expanded', clamped ? 'false' : 'true');
};

const evaluate = (text) => {
	if (!(text instanceof HTMLElement) || !text.isConnected || text.dataset.readMoreChecked === '1') {
		return;
	}
	const limit = clampHeight(text);
	if (text.scrollHeight <= limit + SLACK_PX) {
		return;
	}
	text.dataset.readMoreChecked = '1';
	observer?.unobserve(text);
	setClamped(text, !expanded.has(messageIdOf(text)));
};

/** Buka bubble ini (mis. hasil pencarian atau tujuan lompatan kutipan ada di bagian yang terpotong). */
export const expandMessage = (messageNode) => {
	const text = messageNode?.querySelector?.('[data-message-text].is-clamped');
	if (text instanceof HTMLElement) {
		expanded.add(messageIdOf(text));
		setClamped(text, false);
	}
};

const watch = (root) => {
	root.querySelectorAll?.('[data-message-text]').forEach((text) => {
		if (text.dataset.readMoreChecked === '1') return;
		// Isi bisa membesar setelah dirender (diagram Mermaid, tabel, gambar): diperiksa lagi saat ukurannya berubah.
		observer ? observer.observe(text) : evaluate(text);
		window.requestAnimationFrame(() => evaluate(text));
	});
};

export const initReadMore = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return;
	}

	if ('ResizeObserver' in window) {
		observer = new ResizeObserver((entries) => entries.forEach((entry) => evaluate(entry.target)));
	}
	watch(chatFeed);
	new MutationObserver((mutations) => {
		mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
			if (node instanceof HTMLElement) watch(node);
		}));
	}).observe(chatFeed, { childList: true, subtree: true });

	chatFeed.addEventListener('click', (event) => {
		const button = event.target instanceof Element ? event.target.closest('[data-read-more]') : null;
		const text = button?.previousElementSibling;
		if (!(text instanceof HTMLElement) || !text.matches('[data-message-text]')) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		const id = messageIdOf(text);
		const collapse = !text.classList.contains('is-clamped');
		collapse ? expanded.delete(id) : expanded.add(id);
		setClamped(text, collapse);
		if (collapse) {
			// Dipotong lagi: awal pesan tetap terlihat.
			text.closest('[data-message-id]')?.scrollIntoView({ block: 'nearest' });
		}
	});
};
