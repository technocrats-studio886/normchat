// Penjaga sesi di browser (laporan user 7 Nov 2026): halaman yang dipulihkan dari Back-Forward Cache
// atau yang sesinya berakhir di tab lain tidak boleh terus berjalan dengan sesi lama.

const RELOAD_KEY = 'nc-session-reload-at';

/** Muat ulang sekali; server lalu mengarahkan ke halaman masuk (atau ke grup bila sudah masuk lagi). */
export const reloadForSession = () => {
	try {
		const last = Number(window.sessionStorage.getItem(RELOAD_KEY) || 0);
		// Penjaga putaran: bila baru saja dimuat ulang karena sesi, jangan ulangi.
		if (Date.now() - last < 15000) return;
		window.sessionStorage.setItem(RELOAD_KEY, String(Date.now()));
	} catch {
		// sessionStorage tidak tersedia: tetap muat ulang sekali.
	}
	window.location.reload();
};

/**
 * Halaman dipulihkan dari Back-Forward Cache: tanyakan ke server apakah halaman ini masih sama.
 * Bila server kini mengalihkan (sudah keluar, atau sudah masuk di halaman masuk), muat ulang.
 */
export const initSessionGuard = () => {
	window.addEventListener('pageshow', (event) => {
		if (!event.persisted) return;
		fetch(window.location.href, { method: 'HEAD', credentials: 'same-origin', redirect: 'manual', cache: 'no-store' })
			.then((response) => {
				if (response.type === 'opaqueredirect' || response.status === 401 || response.status === 419) {
					reloadForSession();
				}
			})
			.catch(() => {
				// Luring: biarkan halaman apa adanya.
			});
	});
};
