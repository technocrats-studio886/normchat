// Pratinjau foto profil sebelum diunggah.

export const initAvatarPreview = () => {
	const input = document.querySelector('[data-avatar-input]');
	if (!(input instanceof HTMLInputElement)) {
		return;
	}

	input.addEventListener('change', () => {
		const file = input.files?.[0];
		if (!file) {
			return;
		}

		const reader = new FileReader();
		reader.onload = (event) => {
			const preview = document.querySelector('[data-avatar-preview]');
			if (preview instanceof HTMLImageElement) {
				preview.src = String(event.target?.result || '');
				preview.classList.remove('hidden');
			}
			document.querySelector('[data-avatar-initial]')?.classList.add('hidden');
		};
		reader.readAsDataURL(file);
	});
};
