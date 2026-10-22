// Event realtime (Reverb/Echo): pesan baru, edit, hapus, polling, mengetik, mention.

import { authUserId, chatFeed, groupId, pendingMessagesByLocalId } from './context.js';
import { ensureMentionToastStack, highlightMessageInFeed } from './highlight.js';
import { renderMermaidInNode } from './markdown.js';
import { buildMessageNode, flashEditedMessage, removeRenderedMessageById, replaceRenderedMessageByPayload, resolveEditedFlashTone } from './messages.js';
import { mergePollStatsIntoCache, refreshPollCardsFromFeed } from './polls.js';
import { markGroupRead, markGroupVisited, noteUnseenIncomingMessage, refreshScrollBottomButton, rememberLatestMessageId, resetLatestMessageIdFromDom, syncScrollBottomCount } from './read-state.js';
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
	toast.className = 'pointer-events-auto rounded-2xl border border-coral-200 bg-white px-3 py-2 text-left shadow-lg shadow-slate-900/10 transition hover:bg-coral-50';
	toast.innerHTML = `
		<p class="text-[11px] font-semibold uppercase tracking-wide text-coral-700">Kamu disebut</p>
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
			// Ignore notification API failures in restricted browsers.
		}
	}
};

const seenMentionKeys = new Set();
export let groupChannel = null;
export const typingIndicator = document.querySelector('[data-typing-indicator]');

export const initRealtime = () => {
	if (window.Echo && authUserId > 0) {
		window.Echo.private(`App.Models.User.${authUserId}`).listen('.mention.tagged', (payload) => {
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

		groupChannel.listen('.message.sent', (payload) => {
			if (!chatFeed || !payload?.message) {
				return;
			}

			rememberLatestMessageId(payload.message.id);

			const distFromBottomBefore = chatFeed.scrollHeight - chatFeed.scrollTop - chatFeed.clientHeight;
			const senderId = Number(payload.message.sender_id || 0);
			const isMine = payload.message.sender_type === 'user' && senderId === authUserId;

			if (
				payload.message.sender_type === 'user'
				&& Number(payload.message.sender_id || 0) === authUserId
				&& pendingMessagesByLocalId.size > 0
			) {
				const normalizedContent = String(payload.message.content || '').trim();
				const normalizedAttachmentName = String(payload.message.attachment_original_name || '');
				for (const [localId, pending] of pendingMessagesByLocalId.entries()) {
					if (!pending?.node?.isConnected) {
						pendingMessagesByLocalId.delete(localId);
						continue;
					}

					if (pending.messageType !== String(payload.message.message_type || '').toLowerCase()) {
						continue;
					}

					if (String(pending.content || '').trim() !== normalizedContent) {
						continue;
					}

					if (String(pending.attachmentOriginalName || '') !== normalizedAttachmentName) {
						continue;
					}

					try {
						const finalNode = buildMessageNode(payload.message);
						finalNode.setAttribute('data-message-id', String(payload.message.id));
						finalNode.id = `message-${payload.message.id}`;
						pending.node.replaceWith(finalNode);
						initVoicePlayers(finalNode);
						renderMermaidInNode(finalNode);
					} catch {}

					if (typeof pending.blobUrl === 'string') {
						URL.revokeObjectURL(pending.blobUrl);
					}

					pendingMessagesByLocalId.delete(localId);
					markGroupVisited();
					scrollChatToBottom('auto');
					markGroupRead(Number(payload.message.id || 0));
					refreshPollCardsFromFeed();
					return;
				}
			}

			if (
				payload.message.sender_type === 'user'
				&& Number(payload.message.sender_id || 0) === authUserId
				&& pendingMessagesByLocalId.size > 0
			) {
				const [fallbackLocalId, fallbackPending] = pendingMessagesByLocalId.entries().next().value || [];
				if (fallbackLocalId && fallbackPending?.node?.isConnected) {
					try {
						const finalNode = buildMessageNode(payload.message);
						finalNode.setAttribute('data-message-id', String(payload.message.id));
						finalNode.id = `message-${payload.message.id}`;
						fallbackPending.node.replaceWith(finalNode);
						initVoicePlayers(finalNode);
						renderMermaidInNode(finalNode);
					} catch {}

					if (typeof fallbackPending.blobUrl === 'string') {
						URL.revokeObjectURL(fallbackPending.blobUrl);
					}

					pendingMessagesByLocalId.delete(fallbackLocalId);
					markGroupVisited();
					scrollChatToBottom('auto');
					markGroupRead(Number(payload.message.id || 0));
					refreshPollCardsFromFeed();
					return;
				}
			}

			const duplicate = chatFeed.querySelector(`[data-message-id="${payload.message.id}"]`);
			if (duplicate) {
				return;
			}

			const node = buildMessageNode(payload.message);
			node.setAttribute('data-message-id', String(payload.message.id));
			node.id = `message-${payload.message.id}`;
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
				markGroupRead(Number(payload.message.id || 0));
			} else {
				if (!isMine) {
					noteUnseenIncomingMessage();
					syncScrollBottomCount();
				}
				refreshScrollBottomButton();
			}
			refreshPollCardsFromFeed();
		});

		groupChannel.listen('.message.updated', (payload) => {
			if (!chatFeed || !payload?.message) {
				return;
			}

			const messageId = Number(payload.message.id || 0);
			if (!Number.isFinite(messageId) || messageId <= 0) {
				return;
			}

			const updatedNode = replaceRenderedMessageByPayload(payload.message);
			if (payload.message.is_edited) {
				const flashTone = resolveEditedFlashTone(payload.message);
				flashEditedMessage(updatedNode, flashTone);
			}
			rememberLatestMessageId(messageId);
			refreshScrollBottomButton();
			refreshPollCardsFromFeed();
		});

		groupChannel.listen('.message.deleted', (payload) => {
			if (!chatFeed) {
				return;
			}

			const messageId = Number(payload?.message_id || 0);
			if (!Number.isFinite(messageId) || messageId <= 0) {
				return;
			}

			removeRenderedMessageById(messageId);
			resetLatestMessageIdFromDom();
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
