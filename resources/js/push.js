// Notifikasi push per perangkat (App\Http\Controllers\PushController, App\Services\Push\MessagePush).
// - Izin diminta hanya lewat tombol (kartu ajakan atau Akun > Notifikasi), tidak saat halaman dibuka.
// - Selama tab Normi Chat terlihat, server diberi tahu "sedang membuka aplikasi" supaya tidak ada notifikasi
//   di perangkat mana pun untuk pesan yang sudah terlihat di sini.
// - Grup yang dibaca di perangkat ini menutup notifikasinya; keluar akun mencabut langganan perangkat ini.

const pushKey = document.querySelector('meta[name="nc-push-key"]')?.getAttribute('content') || '';
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const PROMPT_DISMISS_KEY = 'nc-push-prompt-dismissed';
const PROMPT_SNOOZE_MS = 7 * 24 * 60 * 60 * 1000;
const PRESENCE_INTERVAL_MS = 45_000;

const isStandalone = () => window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = () => /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

/**
 * Status dukungan push di perangkat ini:
 * unsupported, ios-install (iPhone/iPad harus "Tambahkan ke Layar Utama" dulu), denied, default, granted.
 */
export const pushStatus = () => {
	if (!pushKey) return 'unsupported';
	const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
	if (!supported) return isIos() && !isStandalone() ? 'ios-install' : 'unsupported';
	return Notification.permission;
};

const keyBytes = (base64Url) => {
	const base64 = (base64Url + '='.repeat((4 - (base64Url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
	return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
};

const sameKey = (buffer) => {
	if (!buffer) return false;
	const current = keyBytes(pushKey);
	const existing = new Uint8Array(buffer);
	return existing.length === current.length && existing.every((value, index) => value === current[index]);
};

const deviceName = () => {
	const ua = navigator.userAgent;
	const os = /Android/.test(ua) ? 'Android' : (isIos() ? 'iOS' : (/Mac/.test(ua) ? 'macOS' : (/Windows/.test(ua) ? 'Windows' : (/Linux/.test(ua) ? 'Linux' : 'Perangkat'))));
	const browser = /Edg\//.test(ua) ? 'Edge' : (/Firefox\//.test(ua) ? 'Firefox' : (/Chrome\//.test(ua) ? 'Chrome' : (/Safari\//.test(ua) ? 'Safari' : 'Browser')));
	return `${browser} di ${os}${isStandalone() ? ' (aplikasi)' : ''}`;
};

const api = (url, method, body) => fetch(url, {
	method,
	credentials: 'same-origin',
	headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
	body: body ? JSON.stringify(body) : undefined,
});

let registrationPromise = null;
const registration = () => {
	registrationPromise ??= navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(() => navigator.serviceWorker.ready);
	return registrationPromise;
};

/** Pastikan perangkat ini berlangganan dengan kunci server sekarang, lalu daftarkan ke server. */
const ensureSubscription = async () => {
	const reg = await registration();
	let subscription = await reg.pushManager.getSubscription();
	if (subscription && !sameKey(subscription.options?.applicationServerKey)) {
		// Kunci VAPID server berganti: langganan lama tidak bisa dipakai lagi.
		await subscription.unsubscribe().catch(() => undefined);
		subscription = null;
	}
	subscription ??= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(pushKey) });

	const json = subscription.toJSON();
	const response = await api('/push/subscriptions', 'POST', { endpoint: json.endpoint, keys: json.keys, device: deviceName() });
	if (!response.ok) throw new Error('subscribe_failed');
	return subscription;
};

/** Minta izin (harus dari ketukan user) lalu berlangganan. Mengembalikan status akhir. */
export const enablePush = async () => {
	const status = pushStatus();
	if (status === 'unsupported' || status === 'ios-install') return status;
	const permission = status === 'granted' ? 'granted' : await Notification.requestPermission();
	if (permission !== 'granted') return permission;
	await ensureSubscription();
	return 'granted';
};

/** Matikan notifikasi di perangkat ini saja. */
export const disablePush = async () => {
	if (!('serviceWorker' in navigator)) return;
	const reg = await navigator.serviceWorker.getRegistration('/');
	const subscription = await reg?.pushManager.getSubscription();
	if (!subscription) return;
	await api('/push/subscriptions', 'DELETE', { endpoint: subscription.endpoint }).catch(() => undefined);
	await subscription.unsubscribe().catch(() => undefined);
};

export const currentSubscription = async () => {
	if (!('serviceWorker' in navigator)) return null;
	const reg = await navigator.serviceWorker.getRegistration('/');
	return (await reg?.pushManager.getSubscription()) || null;
};

export const sendTestPush = async () => {
	const subscription = await currentSubscription();
	if (!subscription) return false;
	const response = await api('/push/test', 'POST', { endpoint: subscription.endpoint });
	return response.ok && (await response.json().catch(() => ({})))?.ok === true;
};

// ── Sinyal "sedang membuka Normi Chat" ─────────────────────────
let presenceTimer = 0;
const sendPresence = (active) => {
	if (active) {
		api('/push/presence', 'POST', { active: true }).catch(() => undefined);
		return;
	}
	// Tab disembunyikan/ditutup: sendBeacon tetap terkirim walau halaman sedang ditinggalkan.
	const body = new FormData();
	body.append('_token', csrf());
	body.append('active', '0');
	if (!navigator.sendBeacon?.('/push/presence', body)) {
		api('/push/presence', 'POST', { active: false }).catch(() => undefined);
	}
};

const syncPresence = () => {
	window.clearInterval(presenceTimer);
	if (document.visibilityState === 'visible') {
		sendPresence(true);
		presenceTimer = window.setInterval(() => sendPresence(true), PRESENCE_INTERVAL_MS);
	} else {
		sendPresence(false);
	}
};

// ── Kartu ajakan ───────────────────────────────────────────────
const promptSnoozed = () => {
	try {
		return Date.now() - Number(window.localStorage.getItem(PROMPT_DISMISS_KEY) || 0) < PROMPT_SNOOZE_MS;
	} catch {
		return false;
	}
};

const renderPrompt = () => {
	const slot = document.querySelector('[data-push-prompt-slot]');
	const status = pushStatus();
	if (!slot || promptSnoozed() || !['default', 'ios-install'].includes(status)) return;
	// Di halaman chat ruangnya sempit: petunjuk iPhone (panjang) hanya di daftar grup dan Akun > Notifikasi.
	const inChat = slot.closest('[data-chat-shell]') !== null;
	if (inChat && status === 'ios-install') return;

	const card = document.createElement('div');
	card.className = inChat ? 'nc-push-prompt is-compact' : 'nc-push-prompt';
	card.setAttribute('role', 'region');
	card.setAttribute('aria-label', 'Notifikasi');
	card.innerHTML = status === 'ios-install'
		? `<p class="nc-push-prompt-text">Supaya notifikasi pesan muncul di iPhone, buka Normi Chat dari Layar Utama. Ketuk tombol Bagikan di Safari, pilih <b>Tambahkan ke Layar Utama</b>, lalu buka dari ikon itu.</p>
			<div class="nc-push-prompt-actions"><button type="button" class="btn-secondary px-3 py-1.5 text-xs" data-push-later>Nanti</button></div>`
		: `<p class="nc-push-prompt-text">${inChat ? 'Nyalakan notifikasi pesan baru?' : 'Nyalakan notifikasi supaya pesan baru tetap sampai walau Normi Chat sedang tertutup.'}</p>
			<div class="nc-push-prompt-actions">
				<button type="button" class="btn-secondary px-3 py-1.5 text-xs" data-push-later>Nanti</button>
				<button type="button" class="btn-primary px-3 py-1.5 text-xs" data-push-enable>Nyalakan</button>
			</div>`;
	slot.replaceChildren(card);

	card.querySelector('[data-push-later]')?.addEventListener('click', () => {
		try {
			window.localStorage.setItem(PROMPT_DISMISS_KEY, String(Date.now()));
		} catch {
			// Penyimpanan diblokir: kartu muncul lagi di kunjungan berikutnya.
		}
		card.remove();
	});
	card.querySelector('[data-push-enable]')?.addEventListener('click', async (event) => {
		const button = event.currentTarget;
		button.disabled = true;
		const result = await enablePush().catch(() => 'error');
		if (result === 'granted') {
			card.innerHTML = '<p class="nc-push-prompt-text">Notifikasi menyala di perangkat ini.</p>';
			window.setTimeout(() => card.remove(), 2500);
		} else if (result === 'denied') {
			card.innerHTML = '<p class="nc-push-prompt-text">Notifikasi diblokir browser. Izinkan lewat pengaturan situs browser, lalu muat ulang halaman.</p>';
		} else {
			button.disabled = false;
		}
	});
};

/** Tutup notifikasi grup yang sudah dibaca di perangkat ini. */
const closeGroupNotifications = (groupId) => {
	if (!groupId || !navigator.serviceWorker?.controller) return;
	navigator.serviceWorker.controller.postMessage({ type: 'nc-read', groupId });
};

export const initPush = () => {
	if (!pushKey || !document.querySelector('meta[name="auth-user-id"]')) return;

	syncPresence();
	document.addEventListener('visibilitychange', syncPresence);
	window.addEventListener('pagehide', () => sendPresence(false));
	window.addEventListener('pageshow', (event) => {
		if (event.persisted) syncPresence();
	});

	const status = pushStatus();
	if ('serviceWorker' in navigator) {
		// SW lama (cache halaman) diganti SW khusus notifikasi ini.
		registration().catch(() => undefined);
	}
	if (status === 'granted') {
		// Langganan diperbarui diam-diam (langganan bisa kedaluwarsa atau kunci server berganti).
		ensureSubscription().catch(() => undefined);
	}
	renderPrompt();

	window.addEventListener('nc:group-read', (event) => closeGroupNotifications(Number(event.detail?.groupId || 0)));
	const chatGroupId = Number(document.querySelector('[data-chat-feed]')?.getAttribute('data-chat-group-id') || 0);
	if (chatGroupId && document.visibilityState === 'visible') {
		navigator.serviceWorker?.ready.then(() => closeGroupNotifications(chatGroupId)).catch(() => undefined);
	}

	// Keluar akun: perangkat ini berhenti menerima notifikasi akun tersebut.
	document.addEventListener('submit', async (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || form.dataset.pushLogoutDone === '1') return;
		if (!/\/logout$/.test(new URL(form.action, window.location.href).pathname)) return;
		event.preventDefault();
		form.dataset.pushLogoutDone = '1';
		await Promise.race([disablePush().catch(() => undefined), new Promise((resolve) => window.setTimeout(resolve, 1500))]);
		form.submit();
	});
};
