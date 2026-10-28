// Hapus pesan: "untuk semua" (diganti jejak "Pesan ini dihapus") atau "untuk saya" (disembunyikan
// dari tampilan sendiri, tersimpan di akun). Pemberitahuan Normi langsung disembunyikan tanpa dialog.

import { chatMessagesBaseUrl } from '../composer/elements.js';
import { cancelEditModeFor } from '../composer/edit-mode.js';
import { clearReplyTarget } from '../composer/reply.js';
import { replyToInput } from '../composer/elements.js';
import { showActionToast } from '../composer/toast.js';
import { refreshPollCardsFromFeed } from '../polls.js';
import { refreshScrollBottomButton, resetLatestMessageIdFromDom } from '../read-state.js';
import { replaceRenderedMessageByPayload } from '../messages.js';
import { chatApiRequest } from '../utils.js';
import { openDialog } from '../../ui/dialog.js';
import { canDeleteForAll, messageFacts } from './rules.js';

const forgetComposerState = (ids) => {
	if (replyToInput instanceof HTMLInputElement && ids.includes(Number(replyToInput.value || 0))) {
		clearReplyTarget();
	}
	ids.forEach(cancelEditModeFor);
};

/** Sembunyikan untuk saya: node dihapus dari feed. */
export const hideMessages = async (ids) => {
	await chatApiRequest(`${chatMessagesBaseUrl}/hide`, { method: 'POST', body: { message_ids: ids } });
	ids.forEach((id) => document.querySelector(`[data-chat-feed] [data-message-id="${id}"]`)?.remove());
};

const deleteForAll = async (ids) => {
	for (const id of ids) {
		const result = await chatApiRequest(`${chatMessagesBaseUrl}/${id}`, { method: 'DELETE' });
		if (result?.message) {
			replaceRenderedMessageByPayload(result.message);
		}
	}
};

/** @returns {Promise<boolean>} true bila pesan dihapus */
export const deleteMessages = async (nodes) => {
	const facts = nodes.map(messageFacts).filter((item) => item.id > 0);
	if (facts.length === 0) {
		return false;
	}
	const ids = facts.map((item) => item.id);

	let choice = 'me';
	const onlyNotices = facts.every((item) => item.isNotice);
	if (!onlyNotices) {
		const forAll = facts.every(canDeleteForAll);
		const many = facts.length > 1;
		choice = await openDialog({
			title: many ? `Hapus ${facts.length} pesan?` : 'Hapus pesan?',
			message: forAll
				? `"Hapus untuk semua" mengganti ${many ? 'pesan-pesan ini' : 'pesan ini'} dengan tulisan "Pesan ini dihapus" bagi semua anggota.`
				: `${many ? 'Pesan-pesan ini' : 'Pesan ini'} hanya dihapus dari tampilanmu.`,
			choices: forAll
				? [{ label: 'Hapus untuk semua', value: 'all', tone: 'danger' }, { label: 'Hapus untuk saya', value: 'me', tone: 'plain' }]
				: [{ label: 'Hapus untuk saya', value: 'me', tone: 'danger' }],
		});
		if (!choice) {
			return false;
		}
	}

	try {
		if (choice === 'all') {
			await deleteForAll(ids);
		} else {
			await hideMessages(ids);
		}
	} catch (error) {
		showActionToast(error?.message && error.message !== 'request_failed' ? error.message : 'Gagal menghapus pesan');
		return false;
	}

	forgetComposerState(ids);
	resetLatestMessageIdFromDom();
	refreshScrollBottomButton();
	refreshPollCardsFromFeed();
	showActionToast(ids.length > 1 ? `${ids.length} pesan dihapus` : 'Pesan dihapus');
	return true;
};
