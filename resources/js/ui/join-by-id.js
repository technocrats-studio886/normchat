// Form "gabung dengan ID grup" (beranda mobile dan sidebar desktop): arahkan ke /join/{id}.

export const initJoinById = () => {
	document.querySelectorAll('[data-join-by-id-form]').forEach((form) => {
		const input = form.querySelector('[data-join-by-id-input]');
		if (!(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) {
			return;
		}

		const joinBaseUrl = form.getAttribute('data-join-by-id-form') || '/join';
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			const id = String(input.value || '').trim();
			if (!id) {
				return;
			}

			window.location.href = `${joinBaseUrl}/${encodeURIComponent(id)}`;
		});
	});

	// Tombol di kepala sidebar desktop membuka/menutup form gabung.
	document.querySelectorAll('[data-sidebar-join-toggle]').forEach((toggle) => {
		const form = toggle.closest('aside')?.querySelector('[data-sidebar-join-form]');
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		toggle.addEventListener('click', () => {
			const opening = form.classList.contains('hidden');
			form.classList.toggle('hidden', !opening);
			form.classList.toggle('flex', opening);
			toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
			if (opening) {
				form.querySelector('[data-join-by-id-input]')?.focus();
			}
		});
	});
};
