// Aksi yang tersedia untuk sebuah pesan, dari data di node pesan (messages.js buildMessageNode).
// Server memeriksa ulang setiap aksi (App\Services\Chat\MessageActions).

import { chatFeed } from '../context.js';

const viewerIsModerator = () => chatFeed?.getAttribute('data-viewer-is-moderator') === '1';
const groupIsFrozen = () => chatFeed?.getAttribute('data-group-frozen') === '1';

/** Grup dibekukan: hanya aksi yang tidak menulis ke grup. */
const READ_ONLY = ['copy', 'versions', 'select', 'link', 'delete'];

const flag = (node, key) => node.dataset[key] === '1';

export const messageFacts = (node) => ({
	id: Number(node.getAttribute('data-message-id') || 0),
	isMine: flag(node, 'messageMine'),
	isAi: node.dataset.senderKey === 'ai',
	isDeleted: flag(node, 'messageDeleted'),
	isNotice: flag(node, 'messageNotice'),
	isAnswer: flag(node, 'messageAnswer'),
	isEdited: flag(node, 'messageEdited'),
	isPoll: flag(node, 'messagePoll'),
	isForwarded: flag(node, 'messageForwarded'),
	awaitingAi: flag(node, 'messageAwaitingAi'),
	type: String(node.dataset.messageType || 'text'),
	hasText: String(node.dataset.messageText || '').trim() !== '',
	replyToId: Number(node.dataset.messageReplyTo || 0),
});

export const canEdit = (facts) => facts.isMine && !facts.isDeleted && !facts.isForwarded && !facts.isPoll
	&& ['text', 'image', 'video'].includes(facts.type);

/** Hapus untuk semua: pesan sendiri, semua pesan bagi moderator, jawaban Normi bagi semua anggota. */
export const canDeleteForAll = (facts) => facts.id > 0 && !facts.isDeleted && !facts.isNotice && !groupIsFrozen()
	&& (facts.isAnswer || facts.isMine || (!facts.isAi && viewerIsModerator()));

/**
 * Daftar aksi untuk pesan, berurutan. Pesan pending (id lokal) tidak punya aksi.
 * @returns {string[]} reply, copy, forward, edit, versions, selectText, select, link, delete
 */
export const actionsFor = (node) => {
	const actions = allActionsFor(node);
	return groupIsFrozen() ? actions.filter((action) => READ_ONLY.includes(action)) : actions;
};

const allActionsFor = (node) => {
	const facts = messageFacts(node);
	if (facts.id <= 0) {
		return [];
	}
	if (facts.isDeleted) {
		return ['select', 'delete'];
	}
	if (facts.isNotice) {
		return ['copy', 'delete'];
	}

	return [
		'reply',
		facts.hasText ? 'copy' : null,
		facts.isPoll ? null : 'forward',
		canEdit(facts) ? 'edit' : null,
		facts.isEdited && !facts.isAi ? 'versions' : null,
		facts.isEdited && facts.isAnswer ? 'versions' : null,
		facts.hasText ? 'selectText' : null,
		'select',
		'link',
		'delete',
	].filter(Boolean);
};
