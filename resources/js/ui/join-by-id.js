// Form "gabung dengan ID grup" di dashboard: arahkan ke /join/{id}.

export const initJoinById = () => {
	const form = document.querySelector('[data-join-by-id-form]');
	const input = document.querySelector('[data-join-by-id-input]');
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
};
