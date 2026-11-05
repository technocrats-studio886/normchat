// Pengganti atribut onsubmit/onchange (diblokir Content-Security-Policy):
// - form[data-confirm]: minta konfirmasi lewat dialog aplikasi sebelum dikirim
//   (data-confirm = pertanyaan, data-confirm-label = tombol, bawaan "Lanjutkan");
// - [data-auto-submit]: kirim form begitu pilihan berubah (filter riwayat).

import { openDialog } from './dialog.js';

/** [data-char-count] + maxlength: tampilkan "12/60" di bawah kolom, merah saat sisa ≤ 10% (batas nama/deskripsi grup). */
const initCharCounters = () => {
	document.querySelectorAll('[data-char-count][maxlength]').forEach((field) => {
		const max = Number(field.getAttribute('maxlength') || 0);
		if (!max) return;
		const counter = document.createElement('span');
		counter.className = 'nc-char-count';
		counter.setAttribute('aria-live', 'polite');
		const host = field.parentElement?.classList.contains('flex') ? field.parentElement : field;
		host.insertAdjacentElement('afterend', counter);
		const update = () => {
			const length = [...field.value].length;
			counter.textContent = `${length}/${max}`;
			counter.classList.toggle('is-near', max - length <= Math.ceil(max * 0.1));
		};
		field.addEventListener('input', update);
		update();
	});
};

export const initFormHelpers = () => {
	initCharCounters();
	document.addEventListener('submit', async (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') {
			return;
		}

		event.preventDefault();
		const choice = await openDialog({
			title: form.dataset.confirm,
			choices: [{ label: form.dataset.confirmLabel || 'Lanjutkan', value: 'ok', tone: 'danger' }],
		});
		if (choice) {
			form.dataset.confirmed = '1';
			form.requestSubmit();
		}
	});

	document.addEventListener('change', (event) => {
		const field = event.target;
		if (field instanceof HTMLElement && field.hasAttribute('data-auto-submit')) {
			field.closest('form')?.requestSubmit();
		}
	});
};
