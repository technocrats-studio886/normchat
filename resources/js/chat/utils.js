// Utilitas kecil: request API chat, scroll, format, dan escaping.

import { chatFeed, csrfToken } from './context.js';

export const scrollChatToBottom = (behavior = 'auto') => {
	if (!chatFeed) {
		return;
	}

	chatFeed.scrollTo({ top: chatFeed.scrollHeight, behavior });
};

export const chatApiRequest = async (url, { method = 'GET', body } = {}) => {
	const headers = {
		'Accept': 'application/json',
		'X-Requested-With': 'XMLHttpRequest',
	};

	if (csrfToken) {
		headers['X-CSRF-TOKEN'] = csrfToken;
	}

	if (body && !(body instanceof FormData)) {
		headers['Content-Type'] = 'application/json';
	}

	const response = await fetch(url, {
		method,
		headers,
		body: body instanceof FormData ? body : (body ? JSON.stringify(body) : undefined),
		credentials: 'same-origin',
	});

	let payload = null;
	try {
		payload = await response.json();
	} catch {
		payload = null;
	}

	if (!response.ok) {
		throw new Error(payload?.message || 'request_failed');
	}

	return payload;
};

// Karakter kendali arah (RLO dkk.) dibuang supaya "tagihan \u202E0005" tidak tampil sebagai "5000" dan
// tidak membalik teks di sekitarnya. Server sudah membuangnya untuk input baru (App\Support\Bidi); ini untuk data lama.
const BIDI_CONTROLS = /[\u202A-\u202E\u2066-\u2069]/g;

export const escapeHtml = (text) => String(text)
	.replace(BIDI_CONTROLS, '')
	.replace(/&/g, '&amp;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#039;');

export const escapeAttr = (text) => String(text)
	.replace(BIDI_CONTROLS, '')
	.replace(/&/g, '&amp;')
	.replace(/"/g, '&quot;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;');
