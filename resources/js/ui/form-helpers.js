// Pengganti atribut onsubmit/onchange (diblokir Content-Security-Policy):
// - form[data-confirm]: minta konfirmasi sebelum dikirim;
// - [data-auto-submit]: kirim form begitu pilihan berubah (filter riwayat).

export const initFormHelpers = () => {
	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
			event.preventDefault();
		}
	});

	document.addEventListener('change', (event) => {
		const field = event.target;
		if (field instanceof HTMLElement && field.hasAttribute('data-auto-submit')) {
			field.closest('form')?.requestSubmit();
		}
	});
};
