// Pilihan pembagian Normkredit yang dibeli ke kredit bersama (partials/share-picker).
// Jumlah NK ikut berubah saat package-picker mengirim event `credits-change` pada form.

const formatCredits = (value) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });

const initSharePicker = (root) => {
	const toggle = root.querySelector('[data-share-toggle]');
	const slider = root.querySelector('[data-share-slider]');
	const value = root.querySelector('[data-share-value]');
	let credits = Number(root.dataset.credits);

	const sync = () => {
		const percent = toggle.checked ? Number(slider.value) : 0;
		value.value = percent;
		root.querySelector('[data-share-slider-wrap]').hidden = !toggle.checked;
		const shared = Math.round(credits * percent) / 100;
		root.querySelector('[data-share-shared]').textContent = `${formatCredits(shared)} (${percent}%)`;
		root.querySelector('[data-share-protected]').textContent = formatCredits(credits - shared);
	};

	toggle.addEventListener('change', () => {
		if (toggle.checked && Number(slider.value) === 0) {
			slider.value = 50;
		}
		sync();
	});
	slider.addEventListener('input', sync);
	root.closest('form')?.addEventListener('credits-change', (event) => {
		credits = event.detail.credits;
		sync();
	});
	sync();
};

export const initSharePickers = () => {
	document.querySelectorAll('[data-share-picker]').forEach(initSharePicker);
};
