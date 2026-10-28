// Status baca, tombol scroll-ke-bawah, dan penanda pesan baru.

import { chatFeed, chatReadUrlFromServer, hasReadBeforeFromServer, lastReadMessageIdFromServer, latestMessageIdFromServer, unreadCountFromServer } from './context.js';
import { highlightMessageInFeed } from './highlight.js';
import { chatApiRequest, scrollChatToBottom } from './utils.js';

const scrollBottomBtn = document.querySelector('[data-scroll-bottom]');
const scrollBottomCount = scrollBottomBtn?.querySelector('[data-scroll-bottom-count]');
const initialMessageCount = Number(chatFeed?.getAttribute('data-message-count') || chatFeed?.querySelectorAll('[data-message-id]').length || 0);
let latestKnownMessageId = latestMessageIdFromServer;
let serverLastReadMessageId = lastReadMessageIdFromServer;
let hasVisitedGroupBefore = hasReadBeforeFromServer;
let firstOpenPendingCount = unreadCountFromServer > 0 ? unreadCountFromServer : initialMessageCount;
let shouldShowFirstOpenSkip = !hasVisitedGroupBefore && initialMessageCount > 0;
let markReadInFlight = false;
let markReadQueued = false;
let pendingSkipReplySourceMessageId = 0;
let clearPendingSkipReplyTimer = null;

const syncScrollBottomMode = () => {
	if (!(scrollBottomBtn instanceof HTMLElement)) {
		return;
	}

	const inReplyMode = pendingSkipReplySourceMessageId > 0;
	scrollBottomBtn.dataset.mode = inReplyMode ? 'reply' : 'latest';
	scrollBottomBtn.setAttribute('aria-label', inReplyMode ? 'Kembali ke pesan yang membalas' : 'Lewati ke pesan terbaru');
};

export const setPendingSkipReplySourceMessageId = (messageId) => {
	const numericId = Number(messageId || 0);
	if (!Number.isFinite(numericId) || numericId <= 0) {
		pendingSkipReplySourceMessageId = 0;
		if (clearPendingSkipReplyTimer) {
			window.clearTimeout(clearPendingSkipReplyTimer);
			clearPendingSkipReplyTimer = null;
		}
		syncScrollBottomMode();
		return;
	}

	pendingSkipReplySourceMessageId = numericId;
	syncScrollBottomMode();
	if (clearPendingSkipReplyTimer) {
		window.clearTimeout(clearPendingSkipReplyTimer);
	}
	clearPendingSkipReplyTimer = window.setTimeout(() => {
		pendingSkipReplySourceMessageId = 0;
		clearPendingSkipReplyTimer = null;
		syncScrollBottomMode();
	}, 15000);
};

const extractLatestMessageIdFromDom = () => {
	if (!chatFeed) {
		return 0;
	}

	const nodes = Array.from(chatFeed.querySelectorAll('[data-message-id]'));
	let maxId = 0;
	nodes.forEach((node) => {
		const messageId = Number(node.getAttribute('data-message-id') || 0);
		if (Number.isFinite(messageId) && messageId > maxId) {
			maxId = messageId;
		}
	});

	return maxId;
};

export const markGroupRead = async (targetMessageId = 0) => {
	if (!chatReadUrlFromServer) {
		return;
	}

	if (markReadInFlight) {
		markReadQueued = true;
		return;
	}

	const latestTargetId = targetMessageId > 0
		? targetMessageId
		: Math.max(latestKnownMessageId, extractLatestMessageIdFromDom());

	if (latestTargetId <= 0 || latestTargetId <= serverLastReadMessageId) {
		return;
	}

	markReadInFlight = true;
	try {
		const result = await chatApiRequest(chatReadUrlFromServer, {
			method: 'POST',
			body: {
				last_read_message_id: latestTargetId,
			},
		});

		const nextReadId = Number(result?.last_read_message_id || 0);
		if (Number.isFinite(nextReadId) && nextReadId > 0) {
			serverLastReadMessageId = Math.max(serverLastReadMessageId, nextReadId);
		}
	} catch {
		// Gagal sinkron status baca tidak boleh mengganggu chat di jaringan yang putus-sambung.
	} finally {
		markReadInFlight = false;
		if (markReadQueued) {
			markReadQueued = false;
			markGroupRead();
		}
	}
};

export const markGroupVisited = () => {
	if (!shouldShowFirstOpenSkip && hasVisitedGroupBefore && firstOpenPendingCount <= 0) {
		return;
	}

	shouldShowFirstOpenSkip = false;
	hasVisitedGroupBefore = true;
	firstOpenPendingCount = 0;
	markGroupRead();
};

export const syncScrollBottomCount = () => {
	if (!(scrollBottomCount instanceof HTMLElement)) {
		return;
	}

	if (pendingSkipReplySourceMessageId > 0) {
		scrollBottomCount.classList.add('hidden');
		scrollBottomCount.textContent = '';
		return;
	}

	if (shouldShowFirstOpenSkip && firstOpenPendingCount > 0) {
		scrollBottomCount.textContent = String(firstOpenPendingCount);
		scrollBottomCount.classList.remove('hidden');
		return;
	}

	scrollBottomCount.classList.add('hidden');
	scrollBottomCount.textContent = '';
};

export let refreshScrollBottomButton = () => {};

/** Catat ID pesan terbaru yang diketahui (dari realtime atau kiriman sendiri). */
export const rememberLatestMessageId = (messageId) => {
	latestKnownMessageId = Math.max(latestKnownMessageId, Number(messageId || 0));
};

export const resetLatestMessageIdFromDom = () => {
	latestKnownMessageId = extractLatestMessageIdFromDom();
};

/** Pesan orang lain masuk saat user tidak di dasar feed. */
export const noteUnseenIncomingMessage = () => {
	shouldShowFirstOpenSkip = true;
	firstOpenPendingCount += 1;
};

export const initReadState = () => {
	latestKnownMessageId = Math.max(latestKnownMessageId, extractLatestMessageIdFromDom());

	if (chatFeed) {
		const jumpToLatest = () => {
			scrollChatToBottom('auto');
		};
		requestAnimationFrame(jumpToLatest);
		// Re-run after images/fonts load so height is final.
		window.setTimeout(jumpToLatest, 80);
		window.setTimeout(jumpToLatest, 260);
		window.addEventListener('load', jumpToLatest, { once: true });
		window.setTimeout(() => {
			markGroupRead();
			syncScrollBottomCount();
		}, 300);
	}

	if (chatFeed && scrollBottomBtn) {
		const checkScrollPos = () => {
			syncScrollBottomMode();
			const distFromBottom = chatFeed.scrollHeight - chatFeed.scrollTop - chatFeed.clientHeight;
			if (distFromBottom < 64) {
				markGroupVisited();
				markGroupRead();
			}

			syncScrollBottomCount();

			if (distFromBottom > 200 || shouldShowFirstOpenSkip || pendingSkipReplySourceMessageId > 0) {
				scrollBottomBtn.classList.remove('hidden');
				scrollBottomBtn.classList.add('inline-flex');
			} else {
				scrollBottomBtn.classList.add('hidden');
				scrollBottomBtn.classList.remove('inline-flex');
			}
		};

		refreshScrollBottomButton = checkScrollPos;
		chatFeed.addEventListener('scroll', checkScrollPos, { passive: true });
		scrollBottomBtn.addEventListener('click', () => {
			if (pendingSkipReplySourceMessageId > 0) {
				const sourceId = pendingSkipReplySourceMessageId;
				setPendingSkipReplySourceMessageId(0);
				if (highlightMessageInFeed(sourceId)) {
					checkScrollPos();
					return;
				}
			}

			scrollChatToBottom('smooth');
			markGroupVisited();
			syncScrollBottomCount();
			checkScrollPos();
		});
		checkScrollPos();
	}
};
