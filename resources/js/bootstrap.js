import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
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
	});
}
