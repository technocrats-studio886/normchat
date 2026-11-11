// Setelan Rekap mingguan di Info grup (App\Http\Controllers\GroupToolsController::weeklyRecap).

const DAYS = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export const initWeeklyRecap = () => {
	document.addEventListener('submit', async (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || !form.matches('[data-weekly-recap]')) return;
		event.preventDefault();
		const status = form.querySelector('[data-weekly-recap-status]');
		const button = form.querySelector('button[type="submit"]');
		const enabled = form.querySelector('input[name="enabled"]')?.checked === true;
		const day = Number(form.querySelector('select[name="day"]')?.value || 1);
		const hour = Number(form.querySelector('select[name="hour"]')?.value || 7);
		button && (button.disabled = true);
		try {
			const response = await fetch(form.dataset.weeklyRecap, {
				method: 'PUT',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
				body: JSON.stringify({ enabled, day, hour }),
			});
			if (!response.ok) throw new Error(String(response.status));
			const label = enabled ? `${DAYS[day]}, ${String(hour).padStart(2, '0')}.00` : 'Mati';
			document.querySelectorAll('[data-weekly-recap-label]').forEach((node) => { node.textContent = label; });
			if (status) status.textContent = enabled ? 'Tersimpan. Rekap berikutnya ' + label + '.' : 'Rekap mingguan dimatikan.';
		} catch {
			if (status) status.textContent = 'Gagal menyimpan. Coba lagi.';
		} finally {
			button && (button.disabled = false);
		}
	});
};
