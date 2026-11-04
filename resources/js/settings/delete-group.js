// Dialog hapus grup: tombol hapus aktif hanya setelah nama grup diketik persis.
// Pembukanya bisa lebih dari satu (menu Lainnya dan Pengaturan di Info grup).

export const initDeleteGroupDialog = () => {
	const overlay = document.querySelector('[data-delete-group-overlay]');
	const closeButton = overlay?.querySelector('[data-close-delete-group]');
	const input = overlay?.querySelector('[data-delete-group-confirm-input]');
	const submit = overlay?.querySelector('[data-delete-group-submit]');
	if (!overlay || !closeButton || !input || !submit) {
		return;
	}
	// Di luar jendela Info grup, supaya posisi fixed-nya tidak terpotong wadah yang bergulir.
	document.body.appendChild(overlay);

	const expected = overlay.getAttribute('data-expected-name') || '';
	const show = () => {
		overlay.classList.remove('hidden');
		overlay.classList.add('flex');
		input.value = '';
		submit.disabled = true;
		input.focus();
	};
	const hide = () => {
		overlay.classList.add('hidden');
		overlay.classList.remove('flex');
	};

	document.addEventListener('click', (event) => {
		if (event.target instanceof Element && event.target.closest('[data-open-delete-group]')) {
			event.preventDefault();
			show();
		}
	});
	closeButton.addEventListener('click', hide);
	overlay.addEventListener('click', (event) => {
		if (event.target === overlay) {
			hide();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
			event.preventDefault();
			hide();
		}
	});
	input.addEventListener('input', () => {
		submit.disabled = input.value.trim() !== expected;
	});
};
