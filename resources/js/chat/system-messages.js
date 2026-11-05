// Pemberitahuan di tengah chat (App\Services\Chat\SystemMessages): bergabung, keluar, dikeluarkan, riwayat
// dikirim, ganti nama/foto/deskripsi. Teks disusun per penonton ("Kamu …"); pemilik melihat tautan
// "Kirim riwayat pesan" pada pemberitahuan bergabung selama riwayat anggota itu masih tertutup.

import { openDialog } from '../ui/dialog.js';
import { authUserId, chatFeed, csrfToken } from './context.js';
import { escapeHtml } from './utils.js';

const quoted = (value) => `«${String(value || '')}»`;

export const systemText = (message) => {
	const system = message?.system || {};
	const meta = system.meta || {};
	const isMeActor = Number(meta.actor_id || 0) === authUserId;
	const actor = isMeActor ? 'Kamu' : String(meta.actor_name || 'Anggota');
	const target = Number(meta.target_id || 0) === authUserId ? 'kamu' : String(meta.target_name || 'anggota');
	switch (system.event) {
		case 'member_joined': return `${actor} bergabung lewat tautan undangan.`;
		case 'member_left': return `${actor} keluar.`;
		case 'member_removed': return `${actor} mengeluarkan ${target}.`;
		case 'history_shared': return `${actor} mengirim riwayat pesan ke ${target}.`;
		case 'name_changed': return `${actor} mengganti nama grup dari ${quoted(meta.before)} menjadi ${quoted(meta.after)}.`;
		case 'photo_changed': return `${actor} mengganti foto grup.`;
		case 'photo_removed': return `${actor} menghapus foto grup.`;
		case 'description_changed': return String(meta.after || '').trim() ? `${actor} mengubah deskripsi grup.` : `${actor} menghapus deskripsi grup.`;
		default: return String(system.text || message?.content || '');
	}
};

export const buildSystemNode = (message) => {
	const system = message?.system || {};
	const meta = system.meta || {};
	const node = document.createElement('div');
	node.className = 'nc-system-msg';
	node.setAttribute('data-system-message-id', String(message?.id ?? ''));
	node.setAttribute('role', 'note');
	const created = Date.parse(String(message?.created_at || ''));
	node.dataset.messageCreatedAt = Number.isFinite(created) ? String(created) : '';

	let details = '';
	if (system.event === 'description_changed') {
		const before = String(meta.before || '').trim();
		const after = String(meta.after || '').trim();
		details = `${before ? `<span class="nc-system-detail is-before">${escapeHtml(before)}</span>` : ''}${after ? `<span class="nc-system-detail">${escapeHtml(after)}</span>` : ''}`;
	}
	const viewerIsOwner = chatFeed?.dataset.viewerIsOwner === '1';
	const action = system.event === 'member_joined' && system.history_pending && viewerIsOwner && Number(meta.actor_id || 0) !== authUserId
		? ` <button type="button" class="nc-system-action" data-share-history="${Number(meta.actor_id || 0)}" data-share-history-name="${escapeHtml(meta.actor_name || 'anggota')}">Kirim riwayat pesan</button>`
		: '';
	node.innerHTML = `<p class="nc-system-pill">${escapeHtml(systemText(message))}${action}${details}</p>`;
	return node;
};

/** Tautan "Kirim riwayat pesan" (pemilik). */
export const initHistoryShare = () => {
	if (!chatFeed) return;
	chatFeed.addEventListener('click', async (event) => {
		const button = event.target instanceof Element ? event.target.closest('[data-share-history]') : null;
		if (!(button instanceof HTMLButtonElement)) return;
		const name = button.dataset.shareHistoryName || 'anggota ini';
		const choice = await openDialog({
			title: `Kirim riwayat pesan ke ${name}?`,
			message: `${name} akan bisa membaca semua pesan sebelum ia bergabung, termasuk media dan berkasnya.`,
			choices: [{ label: 'Kirim riwayat', value: 'share', tone: 'primary' }],
		});
		if (!choice) return;
		button.disabled = true;
		try {
			const url = String(chatFeed.dataset.historyUrlTemplate || '').replace(/\/history\/0$/, `/history/${button.dataset.shareHistory}`);
			const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, credentials: 'same-origin' });
			if (!response.ok) throw new Error(String(response.status));
			// Semua tautan untuk anggota ini (bila ia pernah bergabung lebih dari sekali) selesai.
			chatFeed.querySelectorAll(`[data-share-history="${button.dataset.shareHistory}"]`).forEach((other) => {
				other.replaceWith(Object.assign(document.createElement('span'), { className: 'nc-system-done', textContent: 'Riwayat terkirim' }));
			});
		} catch {
			button.disabled = false;
			button.textContent = 'Gagal, coba lagi';
		}
	});
};
