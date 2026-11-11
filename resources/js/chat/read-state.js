// Status baca, tombol scroll-ke-bawah, dan penanda pesan baru.

import { chatFeed, chatReadUrlFromServer, feedState, groupId, hasReadBeforeFromServer, lastReadMessageIdFromServer, latestMessageIdFromServer, unreadCountFromServer } from './context.js';
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
	// Potongan pesan lama (hasil pencarian): pesan terbaru belum terlihat, jadi belum boleh ditandai terbaca.
	if (!chatReadUrlFromServer || feedState.detached) {
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
		// Notifikasi push grup ini di perangkat ini ditutup (resources/js/push.js).
		window.dispatchEvent(new CustomEvent('nc:group-read', { detail: { groupId: Number(groupId || 0) } }));
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
	if (feedState.detached) {
		return;
	}

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

	if (feedState.detached) {
		scrollBottomCount.textContent = feedState.pendingNew > 99 ? '99+' : String(feedState.pendingNew);
		scrollBottomCount.classList.toggle('hidden', feedState.pendingNew <= 0);
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

/** Pemisah di atas pesan belum dibaca pertama (pesan orang lain sesudah last_read_message_id). */
const placeUnreadSeparator = () => {
	if (!chatFeed || !hasVisitedGroupBefore || serverLastReadMessageId <= 0 || unreadCountFromServer <= 0) {
		return null;
	}
	const nodes = Array.from(chatFeed.querySelectorAll(':scope > [data-message-id], :scope > [data-system-message-id]'));
	const idOf = (node) => Number(node.getAttribute('data-message-id') || node.getAttribute('data-system-message-id') || 0);
	const first = nodes.find((node) => idOf(node) > serverLastReadMessageId && node.dataset.messageMine !== '1') || nodes[0];
	if (!first) {
		return null;
	}
	const separator = document.createElement('div');
	separator.className = 'nc-unread-sep';
	separator.setAttribute('role', 'separator');
	separator.setAttribute('data-unread-separator', '');
	// Banyak pesan terlewat: Normi bisa meringkasnya (chat/tools/index.js, App\Http\Controllers\GroupToolsController::catchUp).
	const canCatchUp = unreadCountFromServer >= 5 && chatFeed.getAttribute('data-group-frozen') !== '1' && chatFeed.hasAttribute('data-tools-base');
	separator.innerHTML = `<span>${unreadCountFromServer} pesan belum dibaca${canCatchUp ? ` <button type="button" class="nc-catch-up" data-catch-up="${serverLastReadMessageId}">Ringkas dengan Normi</button>` : ''}</span>`;
	chatFeed.insertBefore(separator, first);
	return separator;
};

export const initReadState = () => {
	latestKnownMessageId = Math.max(latestKnownMessageId, extractLatestMessageIdFromDom());

	if (chatFeed) {
		// Ada pesan belum dibaca dan grup pernah dibuka: mulai di bagian terakhir yang dilihat, dengan pemisah
		// "N pesan belum dibaca" di atas pesan belum dibaca pertama (docs/rancangan-alur-chat-wa.md poin 1).
		const unreadSeparator = placeUnreadSeparator();
		let userScrolled = false;
		const markUserScroll = () => { userScrolled = true; };
		chatFeed.addEventListener('wheel', markUserScroll, { passive: true, once: true });
		chatFeed.addEventListener('touchmove', markUserScroll, { passive: true, once: true });
		const jumpToStart = () => {
			if (userScrolled) return;
			if (unreadSeparator?.isConnected) {
				chatFeed.scrollTop = Math.max(0, unreadSeparator.offsetTop - chatFeed.offsetTop - 72);
				return;
			}
			scrollChatToBottom('auto');
		};
		requestAnimationFrame(jumpToStart);
		// Re-run after images/fonts load so height is final.
		window.setTimeout(jumpToStart, 80);
		window.setTimeout(jumpToStart, 260);
		window.addEventListener('load', jumpToStart, { once: true });
		window.setTimeout(() => {
			// Dibuka di pemisah belum dibaca: pesan baru ditandai terbaca saat user menggulir sampai bawah
			// (penangan scroll di bawah), bukan langsung saat dibuka.
			if (!unreadSeparator) {
				markGroupRead();
			}
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

			if (distFromBottom > 200 || shouldShowFirstOpenSkip || pendingSkipReplySourceMessageId > 0 || feedState.detached) {
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
			// Sedang di potongan pesan lama: muat ulang pesan terbaru, bukan menggulir ke dasar potongan.
			if (feedState.detached && feedState.restore) {
				feedState.restore().then(() => {
					markGroupVisited();
					syncScrollBottomCount();
					checkScrollPos();
				});
				return;
			}

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
