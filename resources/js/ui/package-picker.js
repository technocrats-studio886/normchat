// Pemilih jumlah paket Normkredit (partials/package-picker): total bayar dan label tombol ikut berubah.

const formatIdr = (value) => 'Rp' + Number(value || 0).toLocaleString('id-ID');
const formatCredits = (value) => Number(value || 0).toLocaleString('id-ID');

const initPackagePicker = (root) => {
	const form = root.closest('form');
	const input = root.querySelector('input[name="packages"]');
	const unitCredits = Number(root.dataset.unitCredits);
	const unitBase = Number(root.dataset.unitBase);
	const maxPackages = Number(root.dataset.maxPackages);
	const feePercent = Number(root.dataset.feePercent);
	const submitLabel = root.dataset.submitLabel || '';

	const sync = () => {
		const packages = Math.min(maxPackages, Math.max(1, Math.floor(Number(input.value) || 1)));
		input.value = packages;
		const base = unitBase * packages;
		// Sama dengan NormkreditPricing::serviceFee() (dibulatkan ke atas).
		const fee = Math.ceil(Math.round(base * feePercent / 100 * 1e6) / 1e6);
		root.querySelector('[data-package-credits]').textContent = formatCredits(unitCredits * packages);
		form.dispatchEvent(new CustomEvent('credits-change', { detail: { credits: unitCredits * packages } }));
		form.querySelector('[data-quote-total]').textContent = formatIdr(base + fee);
		form.querySelector('[data-package-submit]').textContent = `Bayar ${formatIdr(base + fee)} · ${submitLabel}`;
	};

	root.querySelectorAll('[data-package-step]').forEach((button) => {
		button.addEventListener('click', () => {
			input.value = Number(input.value) + Number(button.dataset.packageStep);
			sync();
		});
	});
	sync();
};

export const initPackagePickers = () => {
	document.querySelectorAll('[data-package-picker]').forEach(initPackagePicker);
};
