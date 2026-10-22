// Pengiriman pesan: kirim/edit, pesan sementara (optimistic), dan status gagal.

import { authUserId, chatFeed, pendingMessagesByLocalId } from '../context.js';
import { renderMermaidInNode } from '../markdown.js';
import { buildMessageNode, flashEditedMessage, replaceRenderedMessageByPayload, resolveEditedFlashTone } from '../messages.js';
import { refreshPollCardsFromFeed } from '../polls.js';
import { markGroupRead, markGroupVisited, rememberLatestMessageId } from '../read-state.js';
import { typingIndicator } from '../realtime.js';
import { chatApiRequest, scrollChatToBottom } from '../utils.js';
import { initVoicePlayers } from '../voice-player.js';
import { refreshAttachmentLabel } from './attachment.js';
import { attachmentInput, buildMessageApiUrl, cameraInput, chatForm, detectAttachmentMessageType, emojiPanel, mentionInput, replyQuoteInput, replyToInput, sendButton } from './elements.js';
import { clearEditingState, clearReplyTarget, getEditingMessageId, normalizeReplyQuote } from './reply.js';
import { showActionToast } from './toast.js';
import { whisperTyping } from './typing.js';

let pendingLocalMessageCounter = 0;
let chatSubmitInFlight = false;

export const initSend = () => {
	const setSendingUi = (isSending) => {
		if (sendButton instanceof HTMLButtonElement) {
			sendButton.disabled = isSending;
			sendButton.classList.toggle('opacity-70', isSending);
			sendButton.classList.toggle('cursor-wait', isSending);
		}

		const mediaPreviewSendButton = document.querySelector('[data-attachment-preview-caption-send]');
		if (mediaPreviewSendButton instanceof HTMLButtonElement) {
			mediaPreviewSendButton.disabled = isSending;
			mediaPreviewSendButton.classList.toggle('opacity-70', isSending);
			mediaPreviewSendButton.classList.toggle('cursor-wait', isSending);
		}
	};

	const appendPendingMessageNode = (content, file, replyContext = null) => {
		if (!chatFeed) {
			return null;
		}

		const localId = `local-${Date.now()}-${++pendingLocalMessageCounter}`;
		const blobUrl = file instanceof File ? URL.createObjectURL(file) : null;
		const messageType = detectAttachmentMessageType(file);
		const pendingMessage = {
			id: localId,
			message_type: messageType,
			sender_type: 'user',
			sender_id: authUserId,
			sender_name: 'You',
			content: content || '',
			reply_to: replyContext && replyContext.id ? replyContext : null,
			attachment_url: blobUrl,
			attachment_mime: file instanceof File ? file.type : null,
			attachment_original_name: file instanceof File ? file.name : null,
			attachment_size: file instanceof File ? file.size : null,
			created_at: new Date().toISOString(),
			is_pending: true,
		};

		const node = buildMessageNode(pendingMessage);
		node.setAttribute('data-local-message-id', localId);
		node.setAttribute('data-pending-message', '1');
		if (typingIndicator && typingIndicator.parentNode === chatFeed) {
			chatFeed.insertBefore(node, typingIndicator);
		} else {
			chatFeed.appendChild(node);
		}
		initVoicePlayers(node);
		scrollChatToBottom('smooth');

		pendingMessagesByLocalId.set(localId, {
			node,
			blobUrl,
			content: content || '',
			messageType,
			attachmentOriginalName: file instanceof File ? file.name : '',
		});
		return localId;
	};

	const markPendingMessageFailed = (localId) => {
		const pending = pendingMessagesByLocalId.get(localId);
		if (!pending?.node) {
			return;
		}

		pending.node.setAttribute('data-pending-message', 'failed');
		const timeEl = pending.node.querySelector('p:last-child');
		if (timeEl instanceof HTMLElement) {
			timeEl.textContent = 'You • Gagal terkirim';
			timeEl.classList.remove('text-slate-400');
			timeEl.classList.add('text-rose-500');
		}

		pendingMessagesByLocalId.delete(localId);
	};

	const replacePendingMessage = (localId, serverMessage) => {
		const pending = pendingMessagesByLocalId.get(localId);
		if (!pending?.node || !pending.node.isConnected) {
			pendingMessagesByLocalId.delete(localId);
			return;
		}

		const serverMessageId = Number(serverMessage?.id || 0);
		const duplicateNode = (
			chatFeed instanceof HTMLElement
			&& serverMessageId > 0
		)
			? chatFeed.querySelector(`[data-message-id="${serverMessageId}"]`)
			: null;

		if (duplicateNode instanceof HTMLElement && duplicateNode !== pending.node) {
			pending.node.remove();

			if (typeof pending.blobUrl === 'string') {
				URL.revokeObjectURL(pending.blobUrl);
			}

			pendingMessagesByLocalId.delete(localId);
			rememberLatestMessageId(serverMessageId);
			scrollChatToBottom('auto');
			markGroupRead(serverMessageId);
			refreshPollCardsFromFeed();
			return;
		}

		const finalNode = buildMessageNode(serverMessage);
		finalNode.setAttribute('data-message-id', String(serverMessageId || serverMessage.id));
		finalNode.id = `message-${serverMessageId || serverMessage.id}`;
		pending.node.replaceWith(finalNode);
		initVoicePlayers(finalNode);
		renderMermaidInNode(finalNode);

		if (typeof pending.blobUrl === 'string') {
			URL.revokeObjectURL(pending.blobUrl);
		}

		pendingMessagesByLocalId.delete(localId);
		rememberLatestMessageId(serverMessage?.id);
		scrollChatToBottom('auto');
		markGroupRead(Number(serverMessage?.id || 0));
		refreshPollCardsFromFeed();
	};

	chatForm.addEventListener('submit', async (event) => {
		event.preventDefault();

		if (chatSubmitInFlight) {
			return;
		}

		const actionUrl = chatForm.getAttribute('action') || '';
		if (!actionUrl) {
			return;
		}

		const contentText = mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement
			? mentionInput.value.trim()
			: '';
		const selectedFile = attachmentInput.files?.[0] instanceof File ? attachmentInput.files?.[0] : null;

		if (getEditingMessageId() > 0) {
			if (!contentText) {
				showActionToast('Isi pesan tidak boleh kosong saat edit');
				return;
			}

			if (selectedFile) {
				showActionToast('Edit teks tidak menerima lampiran baru');
				return;
			}

			const updateUrl = buildMessageApiUrl(getEditingMessageId());
			if (!updateUrl) {
				showActionToast('URL edit pesan tidak tersedia');
				return;
			}

			setSendingUi(true);
			chatSubmitInFlight = true;
			try {
				const result = await chatApiRequest(updateUrl, {
					method: 'PATCH',
					body: {
						content: contentText,
					},
				});

				if (result?.message) {
					const updatedNode = replaceRenderedMessageByPayload(result.message);
					if (result.message.is_edited) {
						const flashTone = resolveEditedFlashTone(result.message);
						flashEditedMessage(updatedNode, flashTone);
					}
				}

				clearEditingState(true);
				showActionToast('Pesan berhasil diperbarui');
			} catch {
				showActionToast('Gagal memperbarui pesan');
			} finally {
				setSendingUi(false);
				chatSubmitInFlight = false;
			}

			return;
		}

		if (!contentText && !selectedFile) {
			return;
		}

		markGroupVisited();

		const replyToId = replyToInput instanceof HTMLInputElement
			? Number(replyToInput.value || 0)
			: 0;
		const replyQuoteText = replyQuoteInput instanceof HTMLInputElement
			? normalizeReplyQuote(replyQuoteInput.value)
			: '';
		let pendingReplyContext = null;
		if (replyToId > 0 && chatFeed instanceof HTMLElement) {
			const replySource = chatFeed.querySelector(`[data-message-id="${replyToId}"]`);
			if (replySource instanceof HTMLElement) {
				const sourceType = String(replySource.dataset.messageType || 'text').toLowerCase();
				const sourceRawContent = String(replySource.dataset.messageContent || '').trim();
				const sourceFallback = sourceType === 'image'
					? '[Gambar]'
					: (sourceType === 'voice' ? '[Voice note]' : '[Lampiran]');
				pendingReplyContext = {
					id: replyToId,
					sender_name: String(replySource.dataset.messageSenderName || 'User'),
					message_type: sourceType,
					content: sourceRawContent !== '' ? sourceRawContent : sourceFallback,
					quote_text: replyQuoteText,
				};
			}
		}

		const formData = new FormData(chatForm);
		const localId = appendPendingMessageNode(contentText, selectedFile, pendingReplyContext);

		if (mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement) {
			mentionInput.value = '';
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
		}
		attachmentInput.value = '';
		if (cameraInput instanceof HTMLInputElement) {
			cameraInput.value = '';
		}
		refreshAttachmentLabel();
		emojiPanel?.classList.add('hidden');
		clearReplyTarget();
		whisperTyping(false);

		setSendingUi(true);
		chatSubmitInFlight = true;
		try {
			const response = await fetch(actionUrl, {
				method: 'POST',
				body: formData,
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				credentials: 'same-origin',
			});

			if (!response.ok) {
				throw new Error('send_failed');
			}

			const result = await response.json();
			if (localId && result?.message) {
				replacePendingMessage(localId, result.message);
			}
		} catch {
			if (localId) {
				markPendingMessageFailed(localId);
			}
		} finally {
			setSendingUi(false);
			chatSubmitInFlight = false;
			whisperTyping(false);
		}
	});

	window.addEventListener('beforeunload', () => {
		whisperTyping(false);
	});
};
