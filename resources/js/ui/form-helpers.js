// Pengganti atribut onsubmit/onchange (diblokir Content-Security-Policy):
// - form[data-confirm]: minta konfirmasi lewat dialog aplikasi sebelum dikirim
//   (data-confirm = pertanyaan, data-confirm-label = tombol, bawaan "Lanjutkan");
// - [data-auto-submit]: kirim form begitu pilihan berubah (filter riwayat).

import { openDialog } from './dialog.js';

export const initFormHelpers = () => {
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
