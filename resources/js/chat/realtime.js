// Event realtime (Reverb/Echo): pesan baru, edit, hapus, polling, mengetik, mention.

import { refreshModelLocks } from './composer/ai-options.js';
import { cancelEditModeFor } from './composer/edit-mode.js';
import { authUserId, chatFeed, groupId, pendingMessagesByLocalId } from './context.js';
import { ensureMentionToastStack, highlightMessageInFeed } from './highlight.js';
import { renderMermaidInNode } from './markdown.js';
import { buildMessageNode, flashEditedMessage, replaceRenderedMessageByPayload } from './messages.js';
import { mergePollStatsIntoCache, refreshPollCardsFromFeed } from './polls.js';
import { markGroupRead, markGroupVisited, noteUnseenIncomingMessage, refreshScrollBottomButton, rememberLatestMessageId, syncScrollBottomCount } from './read-state.js';
import { escapeHtml, scrollChatToBottom } from './utils.js';
import { initVoicePlayers } from './voice-player.js';

const showMentionToast = (payload) => {
	const stack = ensureMentionToastStack();
	const senderName = escapeHtml(payload?.sender_name || 'Seseorang');
	const groupName = escapeHtml(payload?.group_name || 'group');
	const previewText = escapeHtml(String(payload?.content || '').slice(0, 80));
	const messageId = Number(payload?.message_id || 0);
	const targetGroupId = Number(payload?.group_id || 0);
	const currentGroupId = Number(groupId || 0);
	const isCurrentGroupOpen = currentGroupId > 0 && targetGroupId > 0 && currentGroupId === targetGroupId;

	const toast = document.createElement('button');
	toast.type = 'button';
	toast.className = 'pointer-events-auto rounded-2xl border border-primary-line bg-white px-3 py-2 text-left shadow-lg shadow-slate-900/10 transition hover:bg-primary-soft';
	toast.innerHTML = `
		<p class="text-[11px] font-semibold uppercase tracking-wide text-primary-ink">Kamu disebut</p>
		<p class="mt-0.5 text-sm font-semibold text-slate-800">${senderName} menyebutmu di ${groupName}</p>
		<p class="mt-0.5 text-xs text-slate-500">${previewText}</p>
	`;

	toast.addEventListener('click', () => {
		if (isCurrentGroupOpen && highlightMessageInFeed(messageId)) {
			toast.remove();
			return;
		}

		window.location.href = String(payload?.chat_url || `/groups/${targetGroupId}/chat`);
	});

	stack.appendChild(toast);
	window.setTimeout(() => {
		if (toast.isConnected) {
			toast.remove();
		}
	}, 6500);

	if (isCurrentGroupOpen) {
		highlightMessageInFeed(messageId);
	}

	if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
		try {
			new Notification('Normi Chat', {
				body: `${payload?.sender_name || 'Seseorang'} menyebutmu di ${payload?.group_name || 'grup'}`,
			});
		} catch {
			// Notifikasi bisa diblokir browser; abaikan.
		}
	}
};

const seenMentionKeys = new Set();
export let groupChannel = null;
export const typingIndicator = document.querySelector('[data-typing-indicator]');

/** Pesan sementara (dikirim dari perangkat ini) diganti pesan aslinya dari server. */
const replacePending = (localId, pending, message) => {
	const finalNode = buildMessageNode(message);
	pending.node.replaceWith(finalNode);
	initVoicePlayers(finalNode);
	renderMermaidInNode(finalNode);
	if (typeof pending.blobUrl === 'string') {
		URL.revokeObjectURL(pending.blobUrl);
	}
	pendingMessagesByLocalId.delete(localId);
	markGroupVisited();
	scrollChatToBottom('auto');
	markGroupRead(Number(message.id || 0));
	refreshPollCardsFromFeed();
};

/** Pesan sendiri yang cocok dengan salah satu pesan sementara (isi, jenis, nama lampiran); bila tidak ada, yang tertua. */
const findPendingFor = (message) => {
	const entries = Array.from(pendingMessagesByLocalId.entries()).filter(([localId, pending]) => {
		if (!pending?.node?.isConnected) {
			pendingMessagesByLocalId.delete(localId);
			return false;
		}
		return true;
	});
	return entries.find(([, pending]) => pending.messageType === String(message.message_type || '').toLowerCase()
		&& String(pending.content || '').trim() === String(message.content || '').trim()
		&& String(pending.attachmentOriginalName || '') === String(message.attachment_original_name || ''))
		|| entries[0]
		|| null;
};

/** Jawaban Normi datang: pertanyaannya boleh diedit lagi. */
const markAnswered = (message) => {
	const questionId = Number(message?.reply_to?.id || 0);
	if (message?.sender_type === 'ai' && questionId > 0) {
		chatFeed?.querySelector(`[data-message-id="${questionId}"]`)?.removeAttribute('data-message-awaiting-ai');
	}
};

/** Pesan baru dari kanal grup, atau pemberitahuan Normi dari kanal pribadi user. */
const onMessageSent = (payload) => {
	const message = payload?.message;
	if (!chatFeed || !message || Number(payload.group_id || 0) !== Number(groupId || 0)) {
		return;
	}

	rememberLatestMessageId(message.id);
	markAnswered(message);
	if (message.group_credits_remaining !== undefined) {
		refreshModelLocks(message.group_credits_remaining);
	}

	const distFromBottomBefore = chatFeed.scrollHeight - chatFeed.scrollTop - chatFeed.clientHeight;
	const isMine = message.sender_type === 'user' && Number(message.sender_id || 0) === authUserId;

	if (isMine && pendingMessagesByLocalId.size > 0) {
		const match = findPendingFor(message);
		if (match) {
			replacePending(match[0], match[1], message);
			return;
		}
	}

	if (chatFeed.querySelector(`[data-message-id="${message.id}"]`)) {
		return;
	}

	const node = buildMessageNode(message);
	if (typingIndicator && typingIndicator.parentNode === chatFeed) {
		chatFeed.insertBefore(node, typingIndicator);
	} else {
		chatFeed.appendChild(node);
	}
	initVoicePlayers(node);
	renderMermaidInNode(node);

	if (isMine || distFromBottomBefore < 170) {
		if (isMine) {
			markGroupVisited();
		}
		scrollChatToBottom(isMine ? 'auto' : 'smooth');
		markGroupRead(Number(message.id || 0));
	} else {
		noteUnseenIncomingMessage();
		syncScrollBottomCount();
		refreshScrollBottomButton();
	}
	refreshPollCardsFromFeed();
};

export const initRealtime = () => {
	if (window.Echo && authUserId > 0) {
		const userChannel = window.Echo.private(`App.Models.User.${authUserId}`);
		// Pemberitahuan Normi yang hanya untuk user ini (saldo habis, model terkunci, dll.).
		userChannel.listen('.message.sent', onMessageSent);
		userChannel.listen('.mention.tagged', (payload) => {
			if (!payload) {
				return;
			}

			if (Number(payload.sender_id || 0) === authUserId) {
				return;
			}

			const dedupeKey = `${payload.group_id || 0}:${payload.message_id || 0}:${authUserId}`;
			if (seenMentionKeys.has(dedupeKey)) {
				return;
			}

			seenMentionKeys.add(dedupeKey);
			if (seenMentionKeys.size > 250) {
				const firstKey = seenMentionKeys.values().next().value;
				if (firstKey) {
					seenMentionKeys.delete(firstKey);
				}
			}

			showMentionToast(payload);
		});
	}

	if (window.Echo && groupId) {
		groupChannel = window.Echo.private(`group.${groupId}`);
		const activeTypingUsers = new Map();

		const renderTypingIndicator = () => {
			if (!(typingIndicator instanceof HTMLElement)) {
				return;
			}

			const labels = Array.from(activeTypingUsers.values());
			if (!labels.length) {
				typingIndicator.classList.add('hidden');
				typingIndicator.textContent = '';
				return;
			}

			typingIndicator.classList.remove('hidden');
			if (labels.length === 1) {
				typingIndicator.textContent = `${labels[0]} sedang mengetik...`;
				return;
			}

			typingIndicator.textContent = `${labels.length} orang sedang mengetik...`;
		};

		const updateTypingState = (key, label, isTyping) => {
			if (!key) {
				return;
			}

			if (isTyping) {
				activeTypingUsers.set(key, label || 'User');
			} else {
				activeTypingUsers.delete(key);
			}

			renderTypingIndicator();
		};

		groupChannel.listenForWhisper('typing', (payload) => {
			const senderId = Number(payload?.sender_id || 0);
			if (senderId === authUserId) {
				return;
			}

			updateTypingState(
				`user:${senderId || payload?.sender_name || 'unknown'}`,
				String(payload?.sender_name || 'User'),
				Boolean(payload?.is_typing)
			);
		});

		groupChannel.listen('.typing.status', (payload) => {
			const actorType = String(payload?.actor_type || '');
			if (actorType !== 'ai') {
				return;
			}

			updateTypingState('ai', String(payload?.sender_name || 'Normi AI'), Boolean(payload?.is_typing));
		});

		groupChannel.listen('.message.sent', onMessageSent);

		groupChannel.listen('.message.updated', (payload) => {
			if (!chatFeed || !payload?.message) {
				return;
			}

			const messageId = Number(payload.message.id || 0);
			if (!Number.isFinite(messageId) || messageId <= 0) {
				return;
			}

			markAnswered(payload.message);
			const updatedNode = replaceRenderedMessageByPayload(payload.message);
			if (payload.message.is_edited) {
				flashEditedMessage(updatedNode);
			}
			rememberLatestMessageId(messageId);
			refreshScrollBottomButton();
			refreshPollCardsFromFeed();
		});

		// Hapus untuk semua: pesan diganti jejak "Pesan ini dihapus".
		groupChannel.listen('.message.deleted', (payload) => {
			if (!chatFeed || !payload?.message?.id) {
				return;
			}

			replaceRenderedMessageByPayload(payload.message);
			cancelEditModeFor(payload.message.id);
			refreshScrollBottomButton();
			refreshPollCardsFromFeed();
		});

		groupChannel.listen('.poll.voted', (payload) => {
			if (!payload?.poll) {
				return;
			}

			mergePollStatsIntoCache(payload.poll);
			refreshPollCardsFromFeed();
		});

		groupChannel.listen('.group.membership.changed', (payload) => {
			const payloadGroupId = Number(payload?.group_id || 0);
			const currentGroupId = Number(groupId || 0);
			if (Number.isFinite(currentGroupId) && currentGroupId > 0 && payloadGroupId !== currentGroupId) {
				return;
			}

			const targetUserId = Number(payload?.target_user_id || 0);
			if (!Number.isFinite(targetUserId) || targetUserId <= 0 || targetUserId !== authUserId) {
				return;
			}

			if (String(payload?.action || '') === 'removed') {
				window.location.href = '/groups';
			}
		});
	}
};
