// Penanda tanggal (docs/rancangan-alur-chat-wa.md poin 6):
// - pita "Hari ini" / "Kemarin" / "2 November" di antara pesan beda hari;
// - penanda melayang di tengah atas saat digulir, hilang 1,5 detik setelah guliran berhenti.

import { chatFeed } from './context.js';

const HIDE_AFTER_MS = 1500;
const ITEMS = ':scope > .nc-msg:not(.hidden), :scope > .nc-system-msg';

const dayKey = (date) => `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;

export const dayLabel = (timestamp, now = new Date()) => {
	const date = new Date(Number(timestamp));
	const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
	const that = new Date(date.getFullYear(), date.getMonth(), date.getDate());
	const diff = Math.round((today - that) / 86400000);
	if (diff === 0) return 'Hari ini';
	if (diff === 1) return 'Kemarin';
	return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', ...(date.getFullYear() !== now.getFullYear() ? { year: 'numeric' } : {}) });
};

/** Pasang/rapikan pita tanggal. Idempoten: tidak mengubah DOM bila sudah benar (aman dipanggil dari MutationObserver). */
export const applyDaySeparators = (feed = chatFeed) => {
	if (!(feed instanceof HTMLElement)) return;
	const wanted = new Set();
	let previousKey = null;
	feed.querySelectorAll(ITEMS).forEach((node) => {
		const stamp = Number(node.dataset.messageCreatedAt || 0);
		if (!stamp) return;
		const key = dayKey(new Date(stamp));
		if (key === previousKey) return;
		previousKey = key;
		let separator = node.previousElementSibling;
		while (separator && separator.classList.contains('nc-unread-sep')) separator = separator.previousElementSibling;
		if (!(separator?.classList.contains('nc-day-sep') && separator.dataset.day === key)) {
			separator = document.createElement('div');
			separator.className = 'nc-day-sep';
			separator.dataset.day = key;
			separator.setAttribute('role', 'separator');
			separator.innerHTML = `<span>${dayLabel(stamp)}</span>`;
			const before = node.previousElementSibling?.classList.contains('nc-unread-sep') ? node.previousElementSibling : node;
			feed.insertBefore(separator, before);
		}
		separator.dataset.stamp = String(stamp);
		wanted.add(separator);
	});
	feed.querySelectorAll(':scope > .nc-day-sep').forEach((separator) => {
		if (!wanted.has(separator)) separator.remove();
	});
};

/** Penanda melayang: tanggal bagian teratas yang terlihat, tampil saat digulir lalu hilang 1,5 detik kemudian. */
export const initFloatingDay = (feed = chatFeed) => {
	const pill = document.querySelector('[data-day-float]');
	if (!(feed instanceof HTMLElement) || !(pill instanceof HTMLElement)) return;
	let hideTimer = 0;
	let lastTop = feed.scrollTop;
	// Guliran otomatis (membuka chat, pesan baru masuk) tidak memunculkan penanda; hanya guliran oleh user.
	let userScrolling = false;
	let userTimer = 0;
	const markUser = () => {
		userScrolling = true;
		window.clearTimeout(userTimer);
		userTimer = window.setTimeout(() => { userScrolling = false; }, 800);
	};
	['wheel', 'touchmove', 'pointerdown', 'keydown'].forEach((type) => feed.addEventListener(type, markUser, { passive: true }));

	const topLabel = () => {
		const feedTop = feed.getBoundingClientRect().top;
		let label = '';
		for (const node of feed.querySelectorAll(ITEMS)) {
			const box = node.getBoundingClientRect();
			if (box.bottom > feedTop + 8) {
				label = node.dataset.messageCreatedAt ? dayLabel(node.dataset.messageCreatedAt) : '';
				break;
			}
		}
		return label;
	};

	feed.addEventListener('scroll', () => {
		// Hanya guliran sungguhan (bukan perubahan tinggi kecil saat gambar dimuat).
		if (Math.abs(feed.scrollTop - lastTop) < 4) return;
		lastTop = feed.scrollTop;
		if (!userScrolling) return;
		const label = topLabel();
		if (!label) return;
		pill.textContent = label;
		pill.classList.add('is-visible');
		window.clearTimeout(hideTimer);
		hideTimer = window.setTimeout(() => pill.classList.remove('is-visible'), HIDE_AFTER_MS);
	}, { passive: true });
};
