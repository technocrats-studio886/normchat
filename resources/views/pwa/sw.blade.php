// Service worker Normi Chat: HANYA notifikasi push (resources/js/push.js, App\Services\Push\MessagePush).
// Tidak ada handler fetch dan tidak ada cache halaman: versi lama (normchat-v7) menyimpan halaman di cache dan
// membuat tampilan basi, jadi cache lama dihapus saat SW ini aktif. Page cache bolak-balik halaman memakai
// Back-Forward Cache browser (App\Http\Middleware\PreventStaleHtml), bukan SW.
const ICON = '/icons/icon-192.png';
const BADGE = '/icons/favicon-32.png';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys()
			.then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
			.catch(() => undefined)
			.then(() => self.clients.claim())
	);
});

/** Ada tab Normi Chat yang sedang terlihat di grup ini: notifikasi tidak perlu tampil. */
const groupIsOpen = async (groupId) => {
	const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
	return windows.some((client) => client.visibilityState === 'visible'
		&& new URL(client.url).pathname === `/groups/${groupId}/chat`);
};

const setBadge = (count) => {
	try {
		if (count > 0 && self.navigator.setAppBadge) return self.navigator.setAppBadge(count);
		if (self.navigator.clearAppBadge) return self.navigator.clearAppBadge();
	} catch {
		// Lencana ikon tidak didukung.
	}
	return undefined;
};

self.addEventListener('push', (event) => {
	let data = {};
	try {
		data = event.data ? event.data.json() : {};
	} catch {
		data = { title: 'Normi Chat', body: event.data ? event.data.text() : 'Pesan baru' };
	}

	event.waitUntil((async () => {
		await setBadge(Number(data.badge_total || 0));
		// Push tanpa notifikasi hanya aman di Chrome/Firefox saat ada tab yang terlihat; Safari mencabut izin push
		// situs yang berulang kali tidak menampilkan notifikasi, jadi di Safari notifikasi selalu tampil.
		const ua = self.navigator.userAgent || '';
		const isSafari = /Safari/.test(ua) && !/Chrome|Chromium|CriOS|Android|Edg/.test(ua);
		if (!isSafari && data.type === 'message' && data.group_id && await groupIsOpen(data.group_id)) {
			return;
		}
		await self.registration.showNotification(String(data.title || 'Normi Chat'), {
			body: String(data.body || ''),
			icon: ICON,
			badge: BADGE,
			tag: String(data.tag || 'normi'),
			renotify: true,
			timestamp: Number(data.sent_at || Date.now()),
			data: { url: String(data.url || '/groups'), groupId: Number(data.group_id || 0) },
		});
	})());
});

self.addEventListener('notificationclick', (event) => {
	event.notification.close();
	const target = new URL(event.notification.data?.url || '/groups', self.location.origin);

	event.waitUntil((async () => {
		const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
		const same = windows.find((client) => new URL(client.url).pathname === target.pathname);
		if (same) {
			await same.focus();
			same.postMessage({ type: 'nc-push-open', url: target.href });
			return;
		}
		const any = windows.find((client) => new URL(client.url).origin === self.location.origin);
		if (any && 'navigate' in any) {
			await any.focus();
			await any.navigate(target.href);
			return;
		}
		await self.clients.openWindow(target.href);
	})());
});

// Halaman memberi tahu grup yang sudah dibaca di perangkat ini: notifikasinya ditutup.
self.addEventListener('message', (event) => {
	const data = event.data || {};
	if (data.type === 'nc-read' && data.groupId) {
		event.waitUntil(self.registration.getNotifications({ tag: `group-${Number(data.groupId)}` })
			.then((list) => list.forEach((notification) => notification.close())));
	}
	if (data.type === 'nc-badge') {
		event.waitUntil(Promise.resolve(setBadge(Number(data.count || 0))));
	}
});

self.addEventListener('pushsubscriptionchange', (event) => {
	// Browser memperbarui langganan: halaman berikutnya yang dibuka mendaftarkannya ulang (push.js).
	event.waitUntil(Promise.resolve());
});
