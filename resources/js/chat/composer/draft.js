// Draf pesan per grup (docs/rancangan-alur-chat-wa.md): disimpan otomatis ke server 600 ms setelah berhenti
// mengetik, saat pindah halaman (sendBeacon), dan dihapus saat terkirim/dikosongkan. Sinkron antar perangkat,
// berlaku 24 jam. Daftar grup di sidebar ikut diperbarui (resources/js/ui/group-list-live.js).

import { csrfToken } from '../context.js';
import { isEditing } from './edit-mode.js';
import { chatForm, mentionInput, replyToInput } from './elements.js';
import { setReplyTarget } from './reply.js';

const SAVE_DELAY = 600;

export const initDraft = () => {
	const url = chatForm?.dataset.draftUrl;
	if (!url || !(mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
		return;
	}
	const groupId = Number(chatForm.dataset.groupId || 0);
	let saved = { content: '', reply: 0 };
	let timer = 0;

	const current = () => ({
		content: isEditing() ? saved.content : mentionInput.value,
		reply: isEditing() ? saved.reply : Number(replyToInput?.value || 0),
	});
	const changed = (state) => state.content !== saved.content || (state.content.trim() !== '' && state.reply !== saved.reply);
	const notifyList = (state) => window.ncGroupList?.setDraft(groupId, state.content.trim() !== '' ? state.content : null);

	const save = async ({ beacon = false } = {}) => {
		window.clearTimeout(timer);
		const state = current();
		if (!changed(state)) return;
		saved = state;
		notifyList(state);
		const body = new FormData();
		body.append('content', state.content);
		if (state.reply > 0) body.append('reply_to_message_id', String(state.reply));
		if (beacon && navigator.sendBeacon) {
			body.append('_token', csrfToken || '');
			navigator.sendBeacon(url, body);
			return;
		}
		body.append('_method', 'PUT');
		try {
			await fetch(url, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrfToken || '', Accept: 'application/json' }, credentials: 'same-origin', keepalive: true });
		} catch {
			// Gagal simpan (offline): dicoba lagi pada ketikan berikutnya atau saat halaman ditutup.
			saved = { content: '', reply: 0 };
		}
	};
	const schedule = () => {
		window.clearTimeout(timer);
		// Dikosongkan (termasuk setelah terkirim): langsung, supaya draf lama tidak sempat tersimpan ulang.
		if (mentionInput.value.trim() === '') {
			save();
			return;
		}
		timer = window.setTimeout(save, SAVE_DELAY);
	};

	// Isi draf yang tersimpan saat grup dibuka.
	try {
		const draft = JSON.parse(chatForm.dataset.draft || 'null');
		if (draft && typeof draft.content === 'string' && draft.content !== '') {
			mentionInput.value = draft.content;
			saved = { content: draft.content, reply: Number(draft.reply_to_message_id || 0) };
			const replyNode = saved.reply > 0 ? document.querySelector(`[data-chat-feed] [data-message-id="${saved.reply}"]`) : null;
			if (replyNode instanceof HTMLElement) setReplyTarget(replyNode);
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
			saved = current();
		}
	} catch {
		// Data draf rusak: abaikan, kolom ketik kosong.
	}

	mentionInput.addEventListener('input', schedule);
	replyToInput?.addEventListener('change', schedule);
	window.addEventListener('pagehide', () => save({ beacon: true }));
	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState === 'hidden') save({ beacon: true });
	});
	// Pindah grup lewat sidebar: simpan dulu.
	document.addEventListener('click', (event) => {
		if (event.target instanceof Element && event.target.closest('a[href]')) save({ beacon: true });
	}, true);
};
