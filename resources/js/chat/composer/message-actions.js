// Aksi pesan: pilih, salin, balas, edit, hapus, sembunyikan, swipe-to-reply, dan tekan lama.

import { chatFeed, hiddenMessagesStorageKey } from '../context.js';
import { highlightMessageInFeed } from '../highlight.js';
import { removeRenderedMessageById } from '../messages.js';
import { refreshScrollBottomButton, resetLatestMessageIdFromDom } from '../read-state.js';
import { chatApiRequest, escapeHtml } from '../utils.js';
import { clearSelectedAttachment } from './attachment.js';
import { buildMessageApiUrl, mentionInput, replyToInput } from './elements.js';
import { clearEditingState, clearReplyTarget, getEditingMessageId, setReplyTarget, setReplyTargetWithText, startEditing } from './reply.js';
import { showActionToast } from './toast.js';

export const initMessageActions = () => {
	const selectedMessageIds = new Set();

	const toggleMessageSelection = (messageNode) => {
		if (!(messageNode instanceof HTMLElement)) {
			return;
		}

		const messageId = String(messageNode.getAttribute('data-message-id') || '').trim();
		if (!messageId) {
			return;
		}

		if (selectedMessageIds.has(messageId)) {
			selectedMessageIds.delete(messageId);
			messageNode.classList.remove('nc-message-selected');
			showActionToast('Batal memilih');
			return;
		}

		selectedMessageIds.add(messageId);
		messageNode.classList.add('nc-message-selected');
		showActionToast(`${selectedMessageIds.size} pesan dipilih`);
	};

	/* ── Message popup (long-press) ── */
	let activePopup = null;
	let activePopupCleanup = null;
	const dismissPopup = () => {
		if (typeof activePopupCleanup === 'function') {
			activePopupCleanup();
		}
		activePopupCleanup = null;
		if (activePopup) {
			activePopup.remove();
			activePopup = null;
		}
	};

	const showSelectTextPopup = (messageNode, sender, messageText) => {
		dismissPopup();
		const safeMessageText = String(messageText || '').trim();
		const selectOverlay = document.createElement('div');
		selectOverlay.className = 'nc-popup-overlay';
		selectOverlay.innerHTML = `
		<div class="nc-popup-card" onclick="event.stopPropagation()">
			<div class="nc-popup-header">
				<span class="nc-popup-sender">${escapeHtml(sender)}</span>
				<button type="button" class="nc-popup-close" data-popup-close>&times;</button>
			</div>
			<div class="nc-popup-body nc-popup-body--quote" data-quote-picker-body>
				<p class="nc-quote-picker" data-quote-picker-words>${escapeHtml(safeMessageText)}</p>
				<p class="nc-quote-result" data-quote-picker-result>Pilih teks, lalu tekan tombol di bawah.</p>
			</div>
			<div class="nc-popup-actions">
				<button type="button" class="nc-popup-btn" data-popup-reply-sel disabled>Balas teks ini</button>
			</div>
		</div>
	`;
		document.body.appendChild(selectOverlay);
		activePopup = selectOverlay;

		const wordsContainer = selectOverlay.querySelector('[data-quote-picker-words]');
		const resultEl = selectOverlay.querySelector('[data-quote-picker-result]');
		const btnReplySel = selectOverlay.querySelector('[data-popup-reply-sel]');
		const getSelectedQuote = () => {
			const selection = window.getSelection();
			if (!selection || selection.rangeCount === 0) {
				return '';
			}

			const selectedText = String(selection.toString() || '').trim();
			if (selectedText === '' || !(wordsContainer instanceof HTMLElement)) {
				return '';
			}

			const selectionRange = selection.getRangeAt(0);
			const commonNode = selectionRange.commonAncestorContainer;
			if (!wordsContainer.contains(commonNode)) {
				return '';
			}

			return selectedText;
		};

		const renderSelectionState = () => {
			const quote = getSelectedQuote();
			if (resultEl instanceof HTMLElement) {
				resultEl.textContent = quote !== ''
					? `"${quote}"`
					: 'Pilih teks, lalu tekan tombol di bawah.';
			}

			if (btnReplySel instanceof HTMLButtonElement) {
				btnReplySel.disabled = quote === '';
			}
		};

		renderSelectionState();
		document.addEventListener('selectionchange', renderSelectionState);
		activePopupCleanup = () => {
			document.removeEventListener('selectionchange', renderSelectionState);
		};

		const cleanup = () => {
			activePopupCleanup = null;
			document.removeEventListener('selectionchange', renderSelectionState);
			dismissPopup();
		};

		selectOverlay.addEventListener('click', (e) => {
			if (e.target === selectOverlay) {
				cleanup();
			}
		});
		selectOverlay.querySelector('[data-popup-close]')?.addEventListener('click', cleanup);
		btnReplySel?.addEventListener('click', () => {
			const text = getSelectedQuote();
			cleanup();
			if (text) {
				setReplyTargetWithText(messageNode, text);
			}
		});
	};

	const viewerIsModerator = String(chatFeed?.getAttribute('data-viewer-is-moderator') || '0') === '1';

	const showConfirmDialog = ({ title, message, confirmLabel = 'Hapus', cancelLabel = 'Batal', danger = true, checkboxLabel = '', checkboxDefault = false, checkboxDisabled = false } = {}) => new Promise((resolve) => {
		const overlay = document.createElement('div');
		overlay.className = 'fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/45 px-5';
		overlay.innerHTML = `
		<div class="w-full max-w-xs rounded-2xl bg-white p-5 shadow-2xl">
			<p class="text-sm font-semibold text-slate-900">${escapeHtml(title || 'Konfirmasi')}</p>
			${message ? `<p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">${escapeHtml(message)}</p>` : ''}
			${checkboxLabel ? `
					<label class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-[13px] text-slate-700 ${checkboxDisabled ? 'opacity-50' : 'cursor-pointer'}">
						<input type="checkbox" data-confirm-checkbox class="h-4 w-4 rounded border-slate-300" ${checkboxDefault ? 'checked' : ''} ${checkboxDisabled ? 'disabled' : ''}>
						<span>${escapeHtml(checkboxLabel)}</span>
					</label>
				` : ''}
			<div class="mt-4 flex items-center justify-end gap-2">
				<button type="button" data-confirm-cancel class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">${escapeHtml(cancelLabel)}</button>
				<button type="button" data-confirm-ok class="rounded-xl px-3 py-2 text-xs font-bold text-white ${danger ? 'bg-coral-500 hover:bg-coral-600' : 'bg-navy-600 hover:bg-navy-700'}">${escapeHtml(confirmLabel)}</button>
			</div>
		</div>
	`;
		const checkbox = overlay.querySelector('[data-confirm-checkbox]');
		const cleanup = (confirmed) => {
			const checked = checkbox instanceof HTMLInputElement ? checkbox.checked : false;
			overlay.remove();
			resolve(confirmed ? { confirmed: true, checked } : { confirmed: false, checked: false });
		};
		overlay.addEventListener('click', (e) => { if (e.target === overlay) cleanup(false); });
		overlay.querySelector('[data-confirm-cancel]').addEventListener('click', () => cleanup(false));
		overlay.querySelector('[data-confirm-ok]').addEventListener('click', () => cleanup(true));
		document.body.appendChild(overlay);
	});

	const loadHiddenMessageIds = () => {
		try {
			const raw = window.localStorage.getItem(hiddenMessagesStorageKey);
			const arr = raw ? JSON.parse(raw) : [];
			return new Set(Array.isArray(arr) ? arr.map(String) : []);
		} catch { return new Set(); }
	};
	const hiddenMessageIds = loadHiddenMessageIds();
	if (chatFeed instanceof HTMLElement && hiddenMessageIds.size > 0) {
		hiddenMessageIds.forEach((id) => {
			const node = chatFeed.querySelector(`[data-message-id="${id}"]`);
			if (node instanceof HTMLElement) node.classList.add('hidden');
		});
	}

	const showMessagePopup = (messageNode) => {
		dismissPopup();
		const rawContent = String(messageNode.dataset.messageContent || '').trim();
		const type = String(messageNode.dataset.messageType || 'text').toLowerCase();
		const fallback = type === 'image' ? '[Gambar]' : (type === 'voice' ? '[Voice note]' : '[Lampiran]');
		const messageText = rawContent !== '' ? rawContent : fallback;
		const sender = String(messageNode.dataset.messageSenderName || 'User');
		const isMine = messageNode.closest('.flex.justify-end') !== null;
		const canEdit = isMine && type === 'text' && rawContent !== '';
		const canDelete = viewerIsModerator;

		const overlay = document.createElement('div');
		overlay.className = 'nc-popup-overlay';
		overlay.innerHTML = `
		<div class="nc-popup-card" onclick="event.stopPropagation()">
			<div class="nc-popup-preview">${escapeHtml(messageText.length > 200 ? messageText.slice(0, 200) + '...' : messageText)}</div>
			<div class="nc-popup-menu">
				<button type="button" class="nc-popup-menu-item" data-popup-reply>
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 0 1 0 10H9m-6-10 4-4m-4 4 4 4"/></svg>
					Balas
				</button>
				<button type="button" class="nc-popup-menu-item" data-popup-copy>
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
					Salin
				</button>
				<button type="button" class="nc-popup-menu-item" data-popup-select-text>
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h10M4 17h12"/></svg>
					Pilih teks
				</button>
				<button type="button" class="nc-popup-menu-item" data-popup-select-message>
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M5.25 6.75h13.5A2.25 2.25 0 0 1 21 9v9a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18V9a2.25 2.25 0 0 1 2.25-2.25Z"/></svg>
					Pilih pesan
				</button>
				<button type="button" class="nc-popup-menu-item" data-popup-pin>
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 4.5 3.75 3.75m-10.5 9.75 2.625-2.625m0 0 5.625-5.625m-5.625 5.625L3.75 20.25m7.875-4.875 4.875-4.875a2.652 2.652 0 1 0-3.75-3.75L7.875 11.625"/></svg>
					Salin tautan
				</button>
				<button type="button" class="nc-popup-menu-item" data-popup-forward>
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 10H7a5 5 0 0 0 0 10h4m10-10-4-4m4 4-4 4"/></svg>
					Teruskan
				</button>
				${canEdit ? `<button type="button" class="nc-popup-menu-item" data-popup-edit>
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.93Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5"/></svg>
						Edit
					</button>` : ''}
				${canDelete ? `<button type="button" class="nc-popup-menu-item nc-popup-menu-danger" data-popup-delete>
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21A48.1 48.1 0 0 0 12 5.5a48.1 48.1 0 0 0-7.228.29m14.456 0-.263 13.883A2.25 2.25 0 0 1 15.916 21.75H8.084a2.25 2.25 0 0 1-2.244-2.077L5.572 5.79m14.456 0c-.338-.052-.676-.107-1.022-.166m-12 .562c.34-.059.68-.114 1.022-.165"/></svg>
						Hapus
					</button>` : ''}
			</div>
		</div>
	`;
		document.body.appendChild(overlay);
		activePopup = overlay;

		overlay.addEventListener('click', (e) => {
			if (e.target === overlay) dismissPopup();
		});
		overlay.querySelector('[data-popup-reply]')?.addEventListener('click', () => {
			dismissPopup();
			setReplyTarget(messageNode);
		});
		overlay.querySelector('[data-popup-copy]')?.addEventListener('click', () => {
			if (navigator.clipboard) {
				navigator.clipboard.writeText(messageText).then(() => {
					showActionToast('Pesan disalin');
				}).catch(() => {});
			}
			dismissPopup();
		});
		overlay.querySelector('[data-popup-select-text]')?.addEventListener('click', () => {
			showSelectTextPopup(messageNode, sender, messageText);
		});
		overlay.querySelector('[data-popup-select-message]')?.addEventListener('click', () => {
			dismissPopup();
			toggleMessageSelection(messageNode);
		});
		overlay.querySelector('[data-popup-pin]')?.addEventListener('click', () => {
			const messageId = String(messageNode.getAttribute('data-message-id') || '').trim();
			const deepLink = `${window.location.origin}${window.location.pathname}#message-${messageId}`;
			if (navigator.clipboard) {
				navigator.clipboard.writeText(deepLink).then(() => {
					showActionToast('Tautan disalin');
				}).catch(() => {
					showActionToast('Gagal menyalin tautan');
				});
			}
			highlightMessageInFeed(messageId);
			dismissPopup();
		});
		overlay.querySelector('[data-popup-forward]')?.addEventListener('click', () => {
			const forwardText = `${sender}: ${messageText}`;
			if (navigator.share) {
				navigator.share({ text: forwardText }).catch(() => {
					if (navigator.clipboard) {
						navigator.clipboard.writeText(forwardText).then(() => {
							showActionToast('Pesan disalin');
						}).catch(() => {});
					}
				});
			} else if (navigator.clipboard) {
				navigator.clipboard.writeText(forwardText).then(() => {
					showActionToast('Pesan disalin');
				}).catch(() => {});
			}
			dismissPopup();
		});
		overlay.querySelector('[data-popup-edit]')?.addEventListener('click', () => {
			dismissPopup();
			if (!(mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
				return;
			}

			const messageId = Number(messageNode.getAttribute('data-message-id') || 0);
			if (!Number.isFinite(messageId) || messageId <= 0) {
				return;
			}

			clearSelectedAttachment();
			clearReplyTarget();
			startEditing(messageId);
			mentionInput.value = rawContent;
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
			mentionInput.focus();
			try {
				const endPos = mentionInput.value.length;
				mentionInput.setSelectionRange(endPos, endPos);
			} catch {
				// Ignore unsupported selection APIs.
			}
			showActionToast('Ubah pesan, lalu kirim');
		});
		overlay.querySelector('[data-popup-delete]')?.addEventListener('click', async () => {
			dismissPopup();

			const messageId = Number(messageNode.getAttribute('data-message-id') || 0);
			if (!Number.isFinite(messageId) || messageId <= 0) {
				return;
			}
			const result = await showConfirmDialog({
				title: 'Hapus pesan?',
				message: 'Pesan akan hilang untuk semua anggota.',
				confirmLabel: 'Hapus',
				cancelLabel: 'Batal',
				danger: true,
			});
			if (!result.confirmed) {
				return;
			}

			const deleteUrl = buildMessageApiUrl(messageId);
			if (!deleteUrl) {
				showActionToast('Gagal menghapus pesan');
				return;
			}

			try {
				await chatApiRequest(deleteUrl, { method: 'DELETE' });
				removeRenderedMessageById(messageId);
				selectedMessageIds.delete(String(messageId));

				if (replyToInput instanceof HTMLInputElement && Number(replyToInput.value || 0) === messageId) {
					clearReplyTarget();
				}

				if (getEditingMessageId() === messageId) {
					clearEditingState(true);
				}

				resetLatestMessageIdFromDom();
				refreshScrollBottomButton();
				showActionToast('Pesan dihapus');
			} catch {
				showActionToast('Gagal menghapus pesan');
			}
		});
	};

	if (chatFeed instanceof HTMLElement) {
		const SWIPE_THRESHOLD = 56;
		const LONG_PRESS_MS = 520;
		let startX = 0;
		let startY = 0;
		let activeNode = null;
		let longPressTimer = null;
		let swipeReplyReady = false;
		let swipeReplyHapticSent = false;

		const clearLongPress = () => {
			if (longPressTimer) {
				window.clearTimeout(longPressTimer);
				longPressTimer = null;
			}
		};

		const resetTransform = (node) => {
			if (node instanceof HTMLElement) {
				node.style.transform = '';
				node.style.transition = 'transform 180ms ease';
				window.setTimeout(() => { node.style.transition = ''; }, 200);
			}
		};

		const clearActive = () => {
			if (activeNode) resetTransform(activeNode);
			activeNode = null;
			swipeReplyReady = false;
			swipeReplyHapticSent = false;
			clearLongPress();
		};

		const commitGestureOnRelease = () => {
			if (!(activeNode instanceof HTMLElement)) {
				return;
			}

			const targetNode = activeNode;
			const shouldReply = swipeReplyReady;
			clearActive();

			if (shouldReply) {
				setReplyTarget(targetNode);
			}
		};

		chatFeed.addEventListener('pointerdown', (event) => {
			if (event.button !== 0) {
				activeNode = null;
				return;
			}

			const node = event.target instanceof HTMLElement
				? event.target.closest('[data-message-id]')
				: null;
			if (!(node instanceof HTMLElement)) {
				activeNode = null;
				return;
			}

			if (event.target.closest('input, textarea, [data-voice-toggle], [data-voice-progress]')) {
				activeNode = null;
				return;
			}

			activeNode = node;
			swipeReplyReady = false;
			swipeReplyHapticSent = false;
			startX = event.clientX;
			startY = event.clientY;
			node.style.transition = '';
			clearLongPress();
			longPressTimer = window.setTimeout(() => {
				if (activeNode) {
					try { navigator.vibrate?.(15); } catch {}
					showMessagePopup(activeNode);
					resetTransform(activeNode);
					activeNode = null;
				}
			}, LONG_PRESS_MS);
		});

		chatFeed.addEventListener('pointermove', (event) => {
			if (!activeNode) return;

			const deltaX = event.clientX - startX;
			const deltaY = event.clientY - startY;

			if (Math.abs(deltaY) > 18) {
				clearActive();
				return;
			}

			if (Math.abs(deltaX) > 18) {
				clearLongPress();
			}

			// swipe RIGHT only (deltaX > 0)
			if (deltaX > 6) {
				const dragX = Math.min(90, deltaX);
				activeNode.style.transform = `translateX(${dragX}px)`;
				const passesThreshold = deltaX > SWIPE_THRESHOLD && deltaX > Math.abs(deltaY) + 8;
				swipeReplyReady = passesThreshold;
				if (passesThreshold && !swipeReplyHapticSent) {
					try { navigator.vibrate?.(10); } catch {}
					swipeReplyHapticSent = true;
				}
				if (event.cancelable) event.preventDefault();
			} else if (deltaX < -6) {
				// block left swipe — just cancel
				clearActive();
				return;
			} else {
				swipeReplyReady = false;
			}
		}, { passive: false });

		chatFeed.addEventListener('pointerup', commitGestureOnRelease);
		chatFeed.addEventListener('pointercancel', clearActive);
		chatFeed.addEventListener('pointerleave', clearActive);

		chatFeed.addEventListener('contextmenu', (event) => {
			const node = event.target instanceof HTMLElement
				? event.target.closest('[data-message-id]')
				: null;
			if (node) event.preventDefault();
		});

		chatFeed.addEventListener('dblclick', (event) => {
			const node = event.target instanceof HTMLElement
				? event.target.closest('[data-message-id]')
				: null;
			if (node instanceof HTMLElement) {
				setReplyTarget(node);
			}
		});
	}
};
