// Pengiriman pesan: kirim/edit, pesan sementara (optimistic), dan status gagal.

import { authUserId, chatFeed, pendingMessagesByLocalId } from '../context.js';
import { renderMermaidInNode } from '../markdown.js';
import { carryOverMedia, revokeLater } from '../media-cache.js';
import { buildMessageNode } from '../messages.js';
import { refreshPollCardsFromFeed } from '../polls.js';
import { markGroupRead, markGroupVisited, rememberLatestMessageId } from '../read-state.js';
import { noteAwaitingAi, typingIndicator } from '../realtime.js';
import { chatApiRequest, scrollChatToBottom } from '../utils.js';
import { initVoicePlayers } from '../voice-player.js';
import { refreshAttachmentLabel } from './attachment.js';
import { applyMediaEdits } from './media-editor.js';
import { isEditing, submitEdit } from './edit-mode.js';
import { attachmentInput, cameraInput, chatForm, detectAttachmentMessageType, emojiPanel, mentionInput, replyQuoteInput, replyToInput, sendButton } from './elements.js';
import { clearReplyTarget, normalizeReplyQuote } from './reply.js';
import { showActionToast } from './toast.js';
import { whisperTyping } from './typing.js';

import { checkFileLimits, uploadDraftBlock, uploadRequestKey, finishUploadRequest } from './upload-draft.js';
import { refreshLengthNote } from './length-limit.js';

let pendingLocalMessageCounter = 0;
let chatSubmitInFlight = false;

export const initSend = () => {
	let retry = null;
	const showSendError = (message) => {
		for (const box of document.querySelectorAll('[data-send-error], [data-preview-send-error]')) {
			const label = box.querySelector('[data-send-error-text]');
			if (label) label.textContent = message;
			box.hidden = !message;
			const replace = box.querySelector('[data-upload-replace]');
			if (replace) replace.hidden = !attachmentInput.files?.length;
		}
	};
	document.addEventListener('click', (event) => {
		if (event.target.closest?.('[data-upload-retry]')) chatForm.requestSubmit();
		if (event.target.closest?.('[data-upload-replace]')) chatForm.querySelector('[data-attach-file]')?.click();
	});
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
			sender_name: 'Kamu',
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
			timeEl.textContent = 'Gagal terkirim';
			timeEl.classList.remove('text-slate-400');
			timeEl.classList.add('text-danger-ink');
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
			revokeLater(pending.blobUrl);

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
		// Gambar lokal tetap tampil sampai gambar dari server selesai dimuat (tanpa kedip).
		carryOverMedia(pending.node, finalNode);
		pending.node.replaceWith(finalNode);
		initVoicePlayers(finalNode);
		renderMermaidInNode(finalNode);
		revokeLater(pending.blobUrl);

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

		// Coretan, teks, dan stiker di pratinjau foto digabung ke fotonya dulu.
		chatSubmitInFlight = true;
		try {
			await applyMediaEdits();
		} catch {
			// Gagal menggabung (browser lama): foto asli tetap terkirim.
		} finally {
			chatSubmitInFlight = false;
		}

		const contentText = mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement
			? mentionInput.value.trim()
			: '';
		const selectedFile = attachmentInput.files?.[0] instanceof File ? attachmentInput.files?.[0] : null;

		// Pesan kepanjangan ditolak di sini: tidak ada bubble "Mengirim…" untuk pesan yang pasti ditolak server.
		if (refreshLengthNote()) {
			mentionInput.focus();
			return;
		}

		if (isEditing()) {
			if (selectedFile) {
				showActionToast('Lampiran tidak bisa ditambahkan saat mengedit');
				return;
			}

			setSendingUi(true);
			chatSubmitInFlight = true;
			try {
				await submitEdit(contentText);
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
        const blocked = checkFileLimits() || uploadDraftBlock();
        if (blocked) { showSendError(blocked); return; }
        // A retry after reloading (or reselecting the same file) must use the same server key.
        chatSubmitInFlight = true;
        let digest = '';
        try {
            if (selectedFile) {
                const hash = await crypto.subtle.digest('SHA-256', await selectedFile.arrayBuffer());
                digest = Array.from(new Uint8Array(hash), b => b.toString(16).padStart(2,'0')).join('');
            }
        } catch {
            showSendError('Lampiran belum bisa dibaca. Pilih ulang file untuk mengirimnya.');
            chatSubmitInFlight = false;
            return;
        }
        const signature = JSON.stringify([...formData].filter(([name]) => name !== '_token').map(([name,value]) =>
            [name, value instanceof File ? [value.name,value.size,digest] : value]));
        const key = uploadRequestKey(signature);
        if (retry?.key !== key) retry = {signature, key};
		if (retry.failedNode) retry.failedNode.remove();
		formData.set('client_message_id', retry.key);
		showSendError('');
		const localId = appendPendingMessageNode(contentText, selectedFile, pendingReplyContext);


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
				const problem = await response.json().catch(() => ({}));
				const reason = Object.values(problem.errors || {}).flat()[0] || problem.message;
				throw new Error(reason || (response.status === 413 ? 'Ukuran lampiran terlalu besar.' : 'Pesan belum terkirim.'));
			}

			const result = await response.json();
			if (!result?.message?.id) throw new Error('Penerimaan pesan belum terkonfirmasi.');
			finishUploadRequest(key);
			retry = null;
			if (mentionInput.value.trim() === contentText && (attachmentInput.files?.[0] || null) === selectedFile) {
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

			}
			if (localId && result?.message) {
				replacePendingMessage(localId, result.message);
			}
			// Pertanyaan ke Normi diterima: tampilkan langsung status yang sesuai (mengetik / membuat gambar / ...).
			noteAwaitingAi(result?.message);
		} catch (error) {
			const reason = error instanceof TypeError ? 'Koneksi terputus.' : error.message;
			showSendError(reason + ' Draf tersedia. Perbaiki lampiran jika perlu, lalu tekan Kirim lagi.');
			if (localId) {
				if (retry) retry.failedNode = pendingMessagesByLocalId.get(localId)?.node;
				markPendingMessageFailed(localId);
			}
		} finally {
			setSendingUi(false);
			chatSubmitInFlight = false;
			whisperTyping(false);
		}
	});

	// Sebelum koneksi realtime ditutup saat halaman ditinggalkan (realtime.js, event pagehide; bukan
	// beforeunload agar halaman tetap bisa masuk Back-Forward Cache).
	window.addEventListener('nc:realtime-closing', () => {
		whisperTyping(false);
	});
};
