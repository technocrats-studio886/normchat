// Lembar (bottom sheet di HP, jendela di tengah di desktop) untuk Tugas, Pengingat, Pesan terjadwal, Sematan.

import { escapeHtml } from '../utils.js';

let current = null;

/**
 * @param {{ title: string, html: string, onClose?: () => void }} options
 * @returns {{ card: HTMLElement, body: HTMLElement, close: () => void, setTitle: (title: string) => void }}
 */
export const openSheet = ({ title, html, onClose }) => {
	current?.close();
	const previousFocus = document.activeElement;
	const overlay = document.createElement('div');
	overlay.className = 'nc-sheet-overlay';
	overlay.innerHTML = `
		<div class="nc-sheet" role="dialog" aria-modal="true" aria-labelledby="nc-sheet-title">
			<div class="nc-sheet-head">
				<h2 id="nc-sheet-title" class="nc-sheet-title">${escapeHtml(title)}</h2>
				<button type="button" class="nc-sheet-close" data-sheet-close aria-label="Tutup">
					<svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
				</button>
			</div>
			<div class="nc-sheet-body" data-sheet-body>${html}</div>
		</div>`;
	const card = overlay.querySelector('.nc-sheet');
	const body = overlay.querySelector('[data-sheet-body]');
	const onKey = (event) => {
		if (event.key === 'Escape') close();
	};
	const close = () => {
		if (!overlay.isConnected) return;
		overlay.remove();
		document.removeEventListener('keydown', onKey);
		if (current?.card === card) current = null;
		onClose?.();
		if (previousFocus instanceof HTMLElement) previousFocus.focus({ preventScroll: true });
	};
	overlay.addEventListener('click', (event) => {
		if (event.target === overlay || event.target.closest('[data-sheet-close]')) close();
	});
	document.addEventListener('keydown', onKey);
	document.body.appendChild(overlay);
	card.focus?.();
	current = {
		card,
		body,
		close,
		setTitle: (value) => { overlay.querySelector('#nc-sheet-title').textContent = value; },
	};
	return current;
};

/** Sheet yang sedang terbuka, bila jenisnya $kind (data-sheet-kind pada isinya). */
export const openSheetOf = (kind) => (current?.body.querySelector(`[data-sheet-kind="${kind}"]`) ? current : null);

/** Input datetime-local bawaan browser memakai waktu lokal; nilainya diubah ke ISO dengan zona waktu. */
export const localInputValue = (date) => {
	const pad = (value) => String(value).padStart(2, '0');
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

export const isoFromLocalInput = (value) => {
	const date = new Date(value);
	return Number.isNaN(date.valueOf()) ? '' : date.toISOString();
};

/** Pilihan cepat waktu: nanti malam 19.00, besok 07.00, Senin 08.00 (yang sudah lewat tidak ditampilkan). */
export const quickTimes = () => {
	const now = new Date();
	const at = (days, hour) => {
		const date = new Date(now);
		date.setDate(date.getDate() + days);
		date.setHours(hour, 0, 0, 0);
		return date;
	};
	const toMonday = ((8 - now.getDay()) % 7) || 7;
	return [
		{ label: 'Nanti malam 19.00', date: at(0, 19) },
		{ label: 'Besok 07.00', date: at(1, 7) },
		{ label: 'Senin 08.00', date: at(toMonday, 8) },
	].filter((item) => item.date.valueOf() > now.valueOf() + 60_000);
};
