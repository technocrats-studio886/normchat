// Tombol lihat/sembunyikan password: <button data-password-toggle="idInput"> berisi ikon
// [data-eye-icon="off"] (tampil saat password tersembunyi) dan [data-eye-icon="on"].

export const initPasswordToggles = () => {
	document.querySelectorAll('[data-password-toggle]').forEach((button) => {
		button.addEventListener('click', () => {
			const input = document.getElementById(button.getAttribute('data-password-toggle') || '');
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			const makeVisible = input.type === 'password';
			input.type = makeVisible ? 'text' : 'password';
			button.querySelector('[data-eye-icon="off"]')?.classList.toggle('hidden', makeVisible);
			button.querySelector('[data-eye-icon="on"]')?.classList.toggle('hidden', !makeVisible);
		});
	});
};
