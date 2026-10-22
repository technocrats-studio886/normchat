// Klik di feed: lompat ke pesan yang dibalas, buka/unduh lampiran, dan vote polling.

import { chatFeed } from '../context.js';
import { highlightMessageInFeed } from '../highlight.js';
import { buildPollVoteUrl, mergePollStatsIntoCache, refreshPollCardsFromFeed } from '../polls.js';
import { setPendingSkipReplySourceMessageId } from '../read-state.js';
import { chatApiRequest, escapeAttr, escapeHtml } from '../utils.js';
import { showActionToast } from './toast.js';

export const initFeedClicks = () => {
	const inferAttachmentKind = (trigger, messageNode) => {
		const explicitKind = String(trigger?.getAttribute('data-attachment-kind') || '').toLowerCase();
		if (explicitKind) {
			return explicitKind;
		}

		if (trigger instanceof HTMLElement && trigger.querySelector('img')) {
			return 'image';
		}

		const messageType = String(messageNode?.dataset?.messageType || '').toLowerCase();
		if (messageType === 'image' || messageType === 'voice' || messageType === 'video') {
			return messageType;
		}

		const mime = String(messageNode?.dataset?.messageAttachmentMime || '').toLowerCase();
		if (mime.startsWith('image/')) {
			return 'image';
		}
		if (mime.startsWith('video/')) {
			return 'video';
		}
		if (mime.startsWith('audio/')) {
			return 'voice';
		}

		const href = String(trigger?.getAttribute('href') || trigger?.getAttribute('data-attachment-url') || '').toLowerCase();
		if (/\.(jpg|jpeg|png|webp|gif|bmp|heic|heif)(\?|$)/.test(href)) {
			return 'image';
		}
		if (/\.(mp4|mov|m4v|webm|3gp|mkv)(\?|$)/.test(href)) {
			return 'video';
		}
		if (/\.(mp3|wav|ogg|aac|m4a)(\?|$)/.test(href)) {
			return 'voice';
		}

		return 'file';
	};

	const openAttachmentPreview = ({ url, kind = 'image', name = 'Lampiran' }) => {
		if (!url) {
			return;
		}

		const safeUrl = escapeAttr(url);
		const safeName = escapeHtml(name || 'Lampiran');
		const overlay = document.createElement('div');
		overlay.className = 'fixed inset-0 z-[95] bg-slate-950';

		const isVideo = kind === 'video';
		overlay.innerHTML = isVideo
			? `
			<div class="mx-auto flex h-full w-full max-w-md touch-none items-center justify-center overflow-hidden p-3" data-media-swipe-stage="1">
				<video src="${safeUrl}" controls playsinline preload="metadata" class="max-h-full w-full select-none rounded-2xl bg-black object-contain" autoplay data-media-preview-el="1"></video>
			</div>
			<p class="pointer-events-none absolute inset-x-0 bottom-6 px-4 text-center text-xs font-medium text-white/70">Geser untuk menutup</p>
		`
			: `
			<div class="mx-auto flex h-full w-full max-w-md touch-none items-center justify-center overflow-hidden p-3" data-media-swipe-stage="1">
				<img src="${safeUrl}" alt="${safeName}" class="max-h-full w-full select-none rounded-2xl object-contain" draggable="false" data-media-preview-el="1" />
			</div>
			<p class="pointer-events-none absolute inset-x-0 bottom-6 px-4 text-center text-xs font-medium text-white/70">Geser untuk menutup</p>
		`;

		const close = () => {
			overlay.remove();
			document.body.style.overflow = '';
			document.removeEventListener('keydown', onEscape);
		};

		const onEscape = (event) => {
			if (event.key === 'Escape') {
				close();
			}
		};

		document.body.style.overflow = 'hidden';
		document.addEventListener('keydown', onEscape);

		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				close();
			}
		});

		const stage = overlay.querySelector('[data-media-swipe-stage="1"]');
		const media = overlay.querySelector('[data-media-preview-el="1"]');
		if (stage instanceof HTMLElement && media instanceof HTMLElement) {
			let dragging = false;
			let startX = 0;
			let startY = 0;
			let deltaX = 0;
			let deltaY = 0;

			const applyTransform = (x, y) => {
				media.style.transform = `translate(${x}px, ${y}px)`;
				const distance = Math.min(1, Math.sqrt((x * x) + (y * y)) / 220);
				overlay.style.opacity = String(1 - (distance * 0.5));
			};

			const resetTransform = () => {
				media.style.transition = 'transform 180ms ease';
				overlay.style.transition = 'opacity 180ms ease';
				applyTransform(0, 0);
				window.setTimeout(() => {
					media.style.transition = '';
					overlay.style.transition = '';
				}, 190);
			};

			stage.addEventListener('pointerdown', (event) => {
				dragging = true;
				startX = event.clientX;
				startY = event.clientY;
				deltaX = 0;
				deltaY = 0;
				stage.setPointerCapture(event.pointerId);
			});

			stage.addEventListener('pointermove', (event) => {
				if (!dragging) {
					return;
				}
				deltaX = event.clientX - startX;
				deltaY = event.clientY - startY;
				applyTransform(deltaX, deltaY);
			});

			const finishSwipe = () => {
				if (!dragging) {
					return;
				}
				dragging = false;
				const distance = Math.sqrt((deltaX * deltaX) + (deltaY * deltaY));
				if (distance > 72) {
					close();
					return;
				}
				resetTransform();
			};

			stage.addEventListener('pointerup', finishSwipe);
			stage.addEventListener('pointercancel', finishSwipe);
		}

		document.body.appendChild(overlay);
	};

	const downloadAttachmentInApp = async (url, filename = 'lampiran') => {
		if (!url) {
			return;
		}

		try {
			const response = await fetch(url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
				},
			});

			if (!response.ok) {
				throw new Error('download_failed');
			}

			const blob = await response.blob();
			const objectUrl = URL.createObjectURL(blob);
			const link = document.createElement('a');
			link.href = objectUrl;
			link.download = filename || 'lampiran';
			document.body.appendChild(link);
			link.click();
			link.remove();
			URL.revokeObjectURL(objectUrl);
			showActionToast('Diunduh');
		} catch {
			showActionToast('Gagal mengunduh');
		}
	};

	if (chatFeed instanceof HTMLElement) {
		chatFeed.addEventListener('click', async (event) => {
			const replyJumpTrigger = event.target.closest('[data-reply-jump], a[href^="#message-"]');
			if (replyJumpTrigger instanceof HTMLElement) {
				event.preventDefault();
				event.stopPropagation();
				if (typeof event.stopImmediatePropagation === 'function') {
					event.stopImmediatePropagation();
				}
				const sourceMessageNode = replyJumpTrigger.closest('[data-message-id]');
				const sourceMessageId = Number(sourceMessageNode?.getAttribute('data-message-id') || 0);
				setPendingSkipReplySourceMessageId(sourceMessageId);

				const fallbackTarget = String(replyJumpTrigger.getAttribute('href') || '').replace('#message-', '');
				const targetId = Number(replyJumpTrigger.getAttribute('data-reply-jump') || fallbackTarget || 0);
				const replyQuote = String(replyJumpTrigger.getAttribute('data-reply-quote') || '').trim();
				if (!Number.isFinite(targetId) || targetId <= 0) {
					return;
				}

				if (!highlightMessageInFeed(targetId, replyQuote)) {
					showActionToast('Pesan asli tidak ditemukan');
				}
				return;
			}

			const attachmentTrigger = event.target.closest('[data-attachment-open="1"],[data-attachment-download="1"],a[href*="/messages/"][href*="/attachment"]');
			if (attachmentTrigger instanceof HTMLElement) {
				event.preventDefault();
				const messageNode = attachmentTrigger.closest('[data-message-id]');
				const attachmentUrl = String(attachmentTrigger.getAttribute('href') || attachmentTrigger.getAttribute('data-attachment-url') || '').trim();
				const attachmentName = String(
					attachmentTrigger.getAttribute('data-attachment-name')
					|| messageNode?.dataset?.messageAttachmentName
					|| 'lampiran'
				).trim();
				const kind = inferAttachmentKind(attachmentTrigger, messageNode);

				if (kind === 'image' || kind === 'video') {
					openAttachmentPreview({
						url: attachmentUrl,
						kind,
						name: attachmentName || 'Lampiran',
					});
					return;
				}

				if (kind === 'voice') {
					return;
				}

				await downloadAttachmentInApp(attachmentUrl, attachmentName || 'lampiran');
				return;
			}

			const voteButton = event.target.closest('[data-poll-vote]');
			if (!(voteButton instanceof HTMLButtonElement)) {
				return;
			}

			event.preventDefault();

			if (voteButton.dataset.pollSubmitting === '1') {
				return;
			}

			const pollId = Number(voteButton.getAttribute('data-poll-id') || 0);
			const optionNumber = Number(voteButton.getAttribute('data-poll-option') || 0);
			if (!Number.isFinite(pollId) || pollId <= 0 || !Number.isFinite(optionNumber) || optionNumber <= 0) {
				return;
			}

			const voteUrl = buildPollVoteUrl(pollId);
			if (!voteUrl) {
				showActionToast('Polling belum bisa dipilih');
				return;
			}

			voteButton.dataset.pollSubmitting = '1';
			voteButton.disabled = true;

			try {
				const result = await chatApiRequest(voteUrl, {
					method: 'POST',
					body: {
						option_number: optionNumber,
					},
				});

				if (result?.poll) {
					mergePollStatsIntoCache(result.poll);
				}

				refreshPollCardsFromFeed();
				showActionToast('Pilihan tersimpan');
			} catch {
				showActionToast('Gagal memilih. Coba lagi');
			} finally {
				delete voteButton.dataset.pollSubmitting;
				voteButton.disabled = false;
			}
		});
	}
};
