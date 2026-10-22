// Sinyal "sedang mengetik" ke anggota lain lewat whisper Echo.

import { authUserId } from '../context.js';
import { groupChannel } from '../realtime.js';

let typingStopTimer = null;
let lastTypingWhisperAt = 0;

export const whisperTyping = (isTyping) => {
	if (!groupChannel || typeof groupChannel.whisper !== 'function' || !authUserId) {
		return;
	}

	const now = Date.now();
	if (isTyping && now - lastTypingWhisperAt < 750) {
		return;
	}

	lastTypingWhisperAt = now;
	groupChannel.whisper('typing', {
		sender_id: authUserId,
		sender_name: (document.querySelector('meta[name="auth-user-name"]')?.getAttribute('content') || 'User'),
		is_typing: isTyping,
	});
};

/** Kirim sinyal mengetik; otomatis berhenti 1,5 detik setelah ketikan terakhir. */
export const noteTyping = () => {
	whisperTyping(true);
	if (typingStopTimer) {
		window.clearTimeout(typingStopTimer);
	}
	typingStopTimer = window.setTimeout(() => {
		whisperTyping(false);
	}, 1500);
};

export const stopTyping = () => {
	if (typingStopTimer) {
		window.clearTimeout(typingStopTimer);
		typingStopTimer = null;
	}
	whisperTyping(false);
};
