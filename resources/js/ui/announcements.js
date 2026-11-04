// Pengumuman superadmin (partials/announcements): popup dibuka saat halaman dimuat; "Tutup" mencatat
// penutupan di server (sesi) lalu menyembunyikannya.

export const initAnnouncements = () => {
	const root = document.querySelector('[data-announcements]');
	if (!root) {
		return;
	}

	const dialog = root.querySelector('[data-announcement-dialog]');
	if (dialog instanceof HTMLDialogElement) {
		dialog.showModal();
		// Fokus di dialognya, bukan tautan pertama di isi pengumuman.
		dialog.focus();
	}

	const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
	root.querySelectorAll('[data-announcement-dismiss]').forEach((button) => {
		button.addEventListener('click', () => {
			const card = button.closest('[data-announcement]');
			const id = card?.getAttribute('data-announcement');
			if (!card || !id) {
				return;
			}
			fetch(`${root.getAttribute('data-dismiss-url')}/${encodeURIComponent(id)}/tutup`, {
				method: 'POST',
				headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
				credentials: 'same-origin',
			}).catch(() => {});
			const host = card.closest('[data-announcement-dialog]');
			if (host instanceof HTMLDialogElement) {
				host.close();
			}
			(host || card.closest('.nc-announce-banner-wrap') || card).remove();
		});
	});
};
