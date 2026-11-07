import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { initSessionGuard, reloadForSession } from './session-guard.js';

window.axios = axios;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

initSessionGuard();

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

const path = window.location.pathname || '';
const isPaymentFlowPath =
	path === '/groups/create'
	|| /^\/groups\/[^/]+\/join$/.test(path)
	|| path === '/groups/payment-callback'
	|| path.startsWith('/subscription');

const shouldBootRealtime = Boolean(reverbKey) && !isPaymentFlowPath;

if (shouldBootRealtime) {
    const configuredHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const wsHost = configuredHost === 'reverb' ? window.location.hostname : configuredHost;

	window.Echo = new Echo({
		broadcaster: 'reverb',
		key: reverbKey,
		wsHost,
		wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
		wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
		forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
		enabledTransports: ['ws', 'wss'],
		// Izin kanal privat lewat fetch sendiri: dengan Accept JSON, sesi yang habis dijawab 401 (bukan dialihkan
		// ke /login berulang kali), lalu halaman dimuat ulang sekali agar pengguna dibawa ke halaman masuk.
		authorizer: (channel) => ({
			authorize: (socketId, callback) => {
				fetch('/broadcasting/auth', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
					},
					body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
				})
					.then((response) => {
						if (response.status === 401 || response.status === 419) {
							reloadForSession();
							throw new Error('session_ended');
						}
						if (!response.ok) throw new Error(`broadcast_auth_${response.status}`);
						return response.json();
					})
					.then((data) => callback(null, data))
					.catch((error) => callback(error, null));
			},
		}),
	});
}
