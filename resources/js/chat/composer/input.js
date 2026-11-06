// Kolom ketik composer: tinggi otomatis dan sinyal mengetik.

import { scrollChatToBottom } from '../utils.js';
import { refreshAttachmentLabel, refreshSendVoiceToggle } from './attachment.js';
import { attachmentInput, chatForm, emojiPanel, mentionInput, recordingIndicator } from './elements.js';
import { noteTyping, stopTyping } from './typing.js';

export const initComposerInput = () => {
	attachmentInput.addEventListener('change', refreshAttachmentLabel);

	if (mentionInput instanceof HTMLTextAreaElement) {
		// Satu baris: [+] [teks] [model] [kirim] dalam satu pill. Begitu teks tidak muat satu baris, kolom ketik
		// melebar penuh di atas dan tombol pindah ke baris bawah (ala ChatGPT/Gemini), tinggi sampai ±40% layar.
		const pill = mentionInput.closest('.composer-pill');
		const SINGLE_LINE = 44;
		const resizeComposerInput = () => {
			pill?.classList.remove('is-multiline');
			mentionInput.style.height = 'auto';
			if (mentionInput.value !== '' && mentionInput.scrollHeight > SINGLE_LINE) {
				pill?.classList.add('is-multiline');
				mentionInput.style.height = 'auto';
			}
			const viewport = window.visualViewport?.height || window.innerHeight;
			const max = Math.max(96, Math.round(viewport * 0.4));
			mentionInput.style.height = `${Math.min(mentionInput.scrollHeight, max)}px`;
			mentionInput.style.overflowY = mentionInput.scrollHeight > max ? 'auto' : 'hidden';
		};
		// Banyak bagian mengisi kolom ini langsung (draf, edit pesan, mention, kirim, kode ke kolom ketik)
		// tanpa event input: setter value ikut menyesuaikan tinggi.
		const valueSetter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');
		let resizeFrame = 0;
		Object.defineProperty(mentionInput, 'value', {
			configurable: true,
			get() {
				return valueSetter.get.call(this);
			},
			set(next) {
				valueSetter.set.call(this, next);
				window.cancelAnimationFrame(resizeFrame);
				resizeFrame = window.requestAnimationFrame(resizeComposerInput);
			},
		});
		window.visualViewport?.addEventListener('resize', resizeComposerInput);

		// Jarak bawah feed dan tombol "ke bawah" mengikuti tinggi area ketik (--nc-composer-h), supaya pesan
		// terakhir tidak tertutup saat kolom ketik membesar. Bila sedang di paling bawah, tetap di paling bawah.
		const shell = mentionInput.closest('.composer-shell');
		const feed = document.querySelector('[data-chat-feed]');
		if (shell instanceof HTMLElement && typeof ResizeObserver === 'function') {
			new ResizeObserver(() => {
				const atBottom = feed instanceof HTMLElement && feed.scrollHeight - feed.scrollTop - feed.clientHeight < 120;
				document.documentElement.style.setProperty('--nc-composer-h', `${Math.ceil(shell.getBoundingClientRect().height)}px`);
				if (atBottom) feed.scrollTop = feed.scrollHeight;
			}).observe(shell);
		}

		mentionInput.addEventListener('focus', () => {
			window.setTimeout(() => scrollChatToBottom('auto'), 70);
			window.setTimeout(() => scrollChatToBottom('auto'), 220);
		});

		mentionInput.addEventListener('input', resizeComposerInput);
		mentionInput.addEventListener('input', refreshSendVoiceToggle);
		refreshSendVoiceToggle();
		mentionInput.addEventListener('input', () => {
			if (mentionInput.value.trim().length === 0) {
				stopTyping();
				return;
			}

			noteTyping();
		});
		resizeComposerInput();
	}

	chatForm.addEventListener('submit', () => {
		emojiPanel?.classList.add('hidden');
		recordingIndicator?.classList.add('hidden');
		stopTyping();
	});
};
