// Info grup > Pengaturan > Notifikasi grup: bisukan 8 jam / 1 minggu / selalu, atau nyalakan lagi
// (App\Http\Controllers\PushController::mute).

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export const initGroupMute = () => {
	document.addEventListener('click', async (event) => {
		const option = event.target instanceof Element ? event.target.closest('[data-group-mute-option]') : null;
		const row = option?.closest('[data-group-mute]');
		if (!option || !row) return;

		const duration = option.getAttribute('data-group-mute-option');
		row.querySelectorAll('[data-group-mute-option]').forEach((button) => { button.disabled = true; });
		try {
			const response = await fetch(row.getAttribute('data-group-mute'), {
				method: 'PUT',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
				body: JSON.stringify({ duration }),
			});
			const result = await response.json();
			if (!response.ok) throw new Error('mute_failed');
			const label = row.querySelector('[data-group-mute-label]');
			if (label) label.textContent = result.label || '';
			row.querySelectorAll('[data-group-mute-option]').forEach((button) => {
				button.setAttribute('aria-pressed', button === option ? 'true' : 'false');
			});
		} catch {
			// Gagal (luring): pilihan lama tetap berlaku.
		} finally {
			row.querySelectorAll('[data-group-mute-option]').forEach((button) => { button.disabled = false; });
		}
	});
};
