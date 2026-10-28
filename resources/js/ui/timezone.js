// Kirim zona waktu browser ke akun bila berbeda dari yang tersimpan, agar jam yang ditulis server
// (sidebar, riwayat, ekspor) memakai zona waktu user (App\Support\UserTime).

import { chatApiRequest } from '../chat/utils.js';

export const initTimezoneSync = () => {
	const meta = document.querySelector('meta[name="auth-user-timezone"]');
	const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
	if (!meta || !zone || meta.getAttribute('content') === zone) {
		return;
	}

	chatApiRequest(meta.dataset.timezoneUrl, { method: 'POST', body: { timezone: zone } })
		.then(() => meta.setAttribute('content', zone))
		.catch(() => {});
};
