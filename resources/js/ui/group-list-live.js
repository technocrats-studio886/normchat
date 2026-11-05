// Daftar grup (sidebar desktop dan daftar HP) yang hidup (docs/rancangan-alur-chat-wa.md):
// - pesan baru di grup mana pun → baris naik ke atas (di bawah grup berdraf), pratinjau/jam/belum dibaca diperbarui;
// - draf dari kolom ketik → baris naik paling atas dengan label "Draf" (window.ncGroupList.setDraft).

const firstWord = (name) => String(name || 'Anggota').trim().split(/\s+/)[0] || 'Anggota';
const timeLabel = (iso) => {
	const date = iso ? new Date(iso) : new Date();
	return `${String(date.getHours()).padStart(2, '0')}.${String(date.getMinutes()).padStart(2, '0')}`;
};
const summary = (message) => {
	const text = String(message.content || '').replace(/\s+/g, ' ').trim();
	if (message.is_deleted) return 'Pesan ini dihapus';
	if (text) return text.length > 60 ? `${text.slice(0, 60)}…` : text;
	const kind = String(message.message_type || '');
	if (kind === 'image') return 'Gambar';
	if (kind === 'voice') return 'Pesan suara';
	if (kind === 'canvas') return 'Canvas';
	return message.attachment_original_name || 'Lampiran';
};

export const initGroupListLive = () => {
	const lists = [...document.querySelectorAll('[data-group-list]')];
	if (lists.length === 0) return;
	const viewerId = Number(lists[0].dataset.viewerId || 0);
	const rowsOf = (groupId) => document.querySelectorAll(`[data-group-row="${groupId}"]`);

	/** Berdraf dulu (draf terbaru), lalu aktivitas terbaru. */
	const reorder = (list) => {
		const rows = [...list.querySelectorAll(':scope > [data-group-row]')];
		const key = (row) => [row.dataset.draftAt || '', row.dataset.activityAt || ''];
		const sorted = [...rows].sort((a, b) => {
			const [da, aa] = key(a);
			const [db, ab] = key(b);
			return db.localeCompare(da) || ab.localeCompare(aa);
		});
		if (sorted.every((row, index) => row === rows[index])) return;
		const anchor = rows[0]?.previousSibling ?? null;
		const fragment = document.createDocumentFragment();
		sorted.forEach((row) => fragment.appendChild(row));
		list.insertBefore(fragment, anchor ? anchor.nextSibling : list.firstChild);
	};
	const reorderAll = () => lists.forEach(reorder);

	const setUnread = (row, count) => {
		row.dataset.unread = String(count);
		const label = count > 99 ? '99+' : String(count);
		row.querySelectorAll('[data-row-badge], [data-row-dot]').forEach((badge) => {
			badge.textContent = label;
			badge.hidden = count <= 0;
		});
	};

	const onMessage = (payload) => {
		const message = payload?.message;
		const groupId = Number(payload?.group_id || message?.group_id || 0);
		if (!message || !groupId) return;
		const isMine = Number(message.sender_id || 0) === viewerId && message.sender_type === 'user';
		const isSystem = message.sender_type === 'system';
		const sender = message.sender_type === 'ai' ? 'Normi AI' : (isMine ? 'Kamu' : firstWord(message.sender_name));
		const preview = isSystem ? String(message.system?.text || '') : `${sender}: ${summary(message)}`;
		rowsOf(groupId).forEach((row) => {
			row.dataset.activityAt = message.created_at || new Date().toISOString();
			const last = row.querySelector('[data-row-last]');
			if (last) last.textContent = preview;
			const time = row.querySelector('[data-row-time]');
			if (time) time.textContent = timeLabel(message.created_at);
			if (!isMine && !isSystem && !row.hasAttribute('data-row-active')) setUnread(row, Number(row.dataset.unread || 0) + 1);
		});
		reorderAll();
	};

	window.ncGroupList = {
		setDraft(groupId, text) {
			rowsOf(groupId).forEach((row) => {
				// Grup yang sedang dibuka: draf baru tampil setelah pindah grup (dirender server).
				if (row.hasAttribute('data-row-active')) return;
				const draft = row.querySelector('[data-row-draft]');
				const last = row.querySelector('[data-row-last]');
				const has = typeof text === 'string' && text.trim() !== '';
				row.dataset.draftAt = has ? new Date().toISOString() : '';
				if (draft) {
					draft.hidden = !has;
					const draftText = draft.querySelector('[data-row-draft-text]');
					if (draftText) draftText.textContent = has ? text.replace(/\s+/g, ' ').slice(0, 80) : '';
				}
				if (last) last.hidden = has;
			});
			reorderAll();
		},
		// Untuk uji dan pemakaian lain: terapkan event pesan secara langsung.
		applyMessage: onMessage,
	};

	if (!window.Echo) return;
	const ids = new Set([...document.querySelectorAll('[data-group-row]')].map((row) => row.dataset.groupRow));
	ids.forEach((id) => window.Echo.private(`group.${id}`).listen('.message.sent', onMessage));
};
