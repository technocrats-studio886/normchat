// Pencarian grup di beranda.

export const initGroupSearch = () => {
	(() => {
		const searchInput = document.querySelector('[data-group-search-input]');
		const emptyEl = document.querySelector('[data-group-search-empty]');
		if (!searchInput) return;
		const cards = Array.from(document.querySelectorAll('[data-group-card]'));
		searchInput.addEventListener('input', () => {
			const q = searchInput.value.trim().toLowerCase();
			let matches = 0;
			cards.forEach((card) => {
				const hay = String(card.getAttribute('data-group-search') || '').toLowerCase();
				const show = !q || hay.includes(q);
				card.classList.toggle('hidden', !show);
				if (show) matches++;
			});
			if (emptyEl) emptyEl.classList.toggle('hidden', matches > 0 || !q);
		});
	})();
};
