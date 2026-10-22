// Pencarian grup: di beranda mobile dan di sidebar desktop. Tiap kotak pencarian
// menyaring kartu di dalam wadah [data-group-search-scope] miliknya sendiri.

export const initGroupSearch = () => {
	document.querySelectorAll('[data-group-search-input]').forEach((searchInput) => {
		const scope = searchInput.closest('[data-group-search-scope]') || document;
		const emptyEl = scope.querySelector('[data-group-search-empty]');
		const cards = Array.from(scope.querySelectorAll('[data-group-card]'));

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
	});
};
