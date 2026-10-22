// Dialog hapus grup: tombol hapus aktif hanya setelah nama grup diketik persis.

export const initDeleteGroupDialog = () => {
	const openButton = document.querySelector('[data-open-delete-group]');
	const overlay = document.querySelector('[data-delete-group-overlay]');
	const closeButton = document.querySelector('[data-close-delete-group]');
	const input = document.querySelector('[data-delete-group-confirm-input]');
	const submit = document.querySelector('[data-delete-group-submit]');
	if (!openButton || !overlay || !closeButton || !input || !submit) {
		return;
	}

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

	openButton.addEventListener('click', show);
	closeButton.addEventListener('click', hide);
	overlay.addEventListener('click', (event) => {
		if (event.target === overlay) {
			hide();
		}
	});
	input.addEventListener('input', () => {
		submit.disabled = input.value.trim() !== expected;
	});
};
