// Cari pesan di seluruh riwayat grup (docs/rancangan-cari-pesan.md).
// - Server mencari (ChatController::search) dan mengembalikan hasil terbaru dulu; browser menandai katanya.
// - HP: bilah menutupi header chat, dengan ↑ ↓ dan "x dari y". Desktop: bilah di bawah header, hasil di kolom kiri.
// - Hasil di luar pesan yang dimuat: feed diganti potongan di sekitarnya (ChatController::around) sampai pencarian ditutup
//   atau "Ke pesan terbaru" ditekan.

import { personColor } from './attachments.js';
import { buildMessageNode, avatarFallbackHtml } from './messages.js';
import { chatFeed, feedState } from './context.js';
import { animateJumpFocus, clearSearchHighlightsInFeed, highlightSearchInFeed, highlightSearchInMessage } from './highlight.js';
import { renderMermaidInNode } from './markdown.js';
import { refreshPollCardsFromFeed } from './polls.js';
import { refreshScrollBottomButton, resetLatestMessageIdFromDom, syncScrollBottomCount } from './read-state.js';
import { chatApiRequest, escapeAttr, escapeHtml, scrollChatToBottom } from './utils.js';
import { initVoicePlayers } from './voice-player.js';

const MIN_LENGTH = 2;
const TYPE_DELAY_MS = 250;

const escapeRegex = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const dateYearFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
const timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

/** Hari ini tampil jamnya, selain itu tanggalnya (tahun hanya bila bukan tahun ini). */
const resultDate = (iso) => {
	const date = new Date(iso);
	if (Number.isNaN(date.valueOf())) return '';
	const now = new Date();
	if (date.toDateString() === now.toDateString()) return timeFormat.format(date);
	return (date.getFullYear() === now.getFullYear() ? dateFormat : dateYearFormat).format(date);
};

/** Cuplikan dari server dengan kata yang dicari ditandai <mark>; teks lain di-escape. */
export const snippetHtml = (snippet, query) => {
	const text = String(snippet || '');
	const phrase = String(query || '').trim().replace(/\s+/g, ' ');
	if (!phrase) return escapeHtml(text);
	const pattern = new RegExp(escapeRegex(phrase).replace(/ /g, '\\s+'), 'gi');
	let html = '';
	let last = 0;
	for (const match of text.matchAll(pattern)) {
		if (match[0] === '') continue;
		html += `${escapeHtml(text.slice(last, match.index))}<mark class="nc-inline-highlight-search">${escapeHtml(match[0])}</mark>`;
		last = match.index + match[0].length;
	}
	return html + escapeHtml(text.slice(last));
};

const resultRowHtml = (result, query, index) => {
	const isAi = result.sender_type === 'ai';
	const photo = result.sender_avatar_url
		? `<img src="${escapeAttr(result.sender_avatar_url)}" alt="" class="nc-avatar-img" loading="lazy" referrerpolicy="no-referrer" data-avatar-photo>`
		: (isAi ? '<img src="/icons/icon-192.png" alt="" class="nc-avatar-img">' : avatarFallbackHtml());
	return `<button type="button" class="nc-search-result" data-search-result="${index}" data-message-id-ref="${result.id}" style="--person: ${isAi ? 'var(--nc-ai-name)' : personColor(result.sender_id)}">
		<span class="nc-avatar nc-search-result-avatar${isAi ? ' nc-avatar--ai' : ''}" aria-hidden="true">${photo}</span>
		<span class="min-w-0 flex-1">
			<span class="flex items-baseline gap-2">
				<span class="nc-search-result-name">${escapeHtml(result.sender_name)}</span>
				<span class="nc-search-result-date">${escapeHtml(resultDate(result.created_at))}</span>
			</span>
			<span class="nc-search-result-snippet">${snippetHtml(result.snippet, query)}</span>
		</span>
	</button>`;
};

let jumpHandler = null;

/**
 * Lompat ke satu pesan di feed, juga yang di luar pesan yang dimuat (feed diganti potongan di sekitarnya,
 * dengan pita "Menampilkan pesan lama" dan tombol "Ke pesan terbaru"). Mengembalikan false bila pesan tidak ditemukan.
 */
export const jumpToMessage = (messageId) => (jumpHandler ? jumpHandler(Number(messageId)) : Promise.resolve(false));

export const initMessageSearch = () => {
	const bar = document.querySelector('[data-message-search-bar]');
	const input = document.querySelector('[data-message-search-input]');
	const countEl = document.querySelector('[data-message-search-count]');
	const olderBtn = document.querySelector('[data-search-older]');
	const newerBtn = document.querySelector('[data-search-newer]');
	const opener = document.querySelector('[data-open-search]');
	const banner = document.querySelector('[data-old-banner]');
	const panel = document.querySelector('[data-search-panel]');
	const panelList = document.querySelector('[data-search-panel-list]');
	const panelStatus = document.querySelector('[data-search-panel-status]');
	const sidebar = document.querySelector('[data-sidebar]');
	if (!bar || !input || !chatFeed) return;

	const searchUrl = bar.dataset.searchUrl || '';
	const aroundTemplate = bar.dataset.aroundUrlTemplate || '';
	const recentUrl = bar.dataset.recentUrl || '';
	const typingIndicator = chatFeed.querySelector('[data-typing-indicator]');

	// Hasil dari server, terbaru dulu. index = hasil yang sedang dipilih (0 = terbaru).
	const state = { open: false, query: '', results: [], total: 0, hasMore: false, index: -1, loading: false, seq: 0, reopenedSidebar: false };
	let typeTimer = 0;

	/* ---------- tampilan ---------- */

	const syncControls = () => {
		const count = state.results.length;
		if (countEl) {
			countEl.textContent = state.query.length < MIN_LENGTH ? '' : (state.total === 0 ? (state.loading ? '' : 'Tidak ada hasil') : `${state.index + 1} dari ${state.total}`);
		}
		if (olderBtn) olderBtn.disabled = count === 0 || (state.index >= count - 1 && !state.hasMore);
		if (newerBtn) newerBtn.disabled = count === 0 || state.index <= 0;
		if (panelStatus) {
			panelStatus.textContent = state.query.length < MIN_LENGTH
				? 'Ketik minimal 2 huruf.'
				: (state.loading && count === 0 ? 'Mencari…' : (state.total === 0 ? 'Tidak ada pesan yang cocok.' : `${state.total} pesan ditemukan`));
		}
		panelList?.querySelectorAll('[data-search-result]').forEach((row) => {
			const active = Number(row.dataset.searchResult) === state.index;
			row.classList.toggle('is-active', active);
			if (active) row.scrollIntoView({ block: 'nearest' });
		});
	};

	const renderPanel = (append = false) => {
		if (!panelList) return;
		if (!append) panelList.innerHTML = '';
		const start = append ? panelList.querySelectorAll('[data-search-result]').length : 0;
		panelList.insertAdjacentHTML('beforeend', state.results.slice(start).map((result, offset) => resultRowHtml(result, state.query, start + offset)).join(''));
	};

	const markFeed = () => {
		if (state.query.length >= MIN_LENGTH) highlightSearchInFeed(chatFeed, state.query);
		else clearSearchHighlightsInFeed(chatFeed);
	};

	/* ---------- feed: potongan pesan lama ---------- */

	const messageNodes = () => Array.from(chatFeed.querySelectorAll(':scope > .nc-msg, :scope > .nc-system-msg'));

	const renderMessages = (messages) => {
		messageNodes().forEach((node) => node.remove());
		chatFeed.querySelectorAll(':scope > .nc-unread-sep, :scope > .nc-day-sep').forEach((node) => node.remove());
		const fragment = document.createDocumentFragment();
		const built = messages.map((message) => buildMessageNode(message));
		built.forEach((node) => fragment.appendChild(node));
		if (typingIndicator && typingIndicator.parentNode === chatFeed) chatFeed.insertBefore(fragment, typingIndicator);
		else chatFeed.appendChild(fragment);
		built.forEach((node) => {
			initVoicePlayers(node);
			renderMermaidInNode(node);
		});
		refreshPollCardsFromFeed();
		return built;
	};

	const setCreatedCard = (visible) => {
		chatFeed.querySelector(':scope > .nc-created-card')?.classList.toggle('hidden', !visible);
	};

	const setDetached = (detached) => {
		feedState.detached = detached;
		banner?.classList.toggle('hidden', !detached);
		if (detached) {
			feedState.pendingNew = 0;
			setCreatedCard(false);
		}
		refreshScrollBottomButton();
	};

	/** Kembali ke 80 pesan terakhir. Pesan yang sedang dikirim dari perangkat ini tetap di tempatnya. */
	const restoreLatest = async ({ scroll = true } = {}) => {
		if (!feedState.detached) return;
		try {
			const data = await chatApiRequest(recentUrl);
			const pending = Array.from(chatFeed.querySelectorAll(':scope > [data-pending-message]'));
			renderMessages(data.messages || []);
			pending.forEach((node) => {
				if (typingIndicator && typingIndicator.parentNode === chatFeed) chatFeed.insertBefore(node, typingIndicator);
				else chatFeed.appendChild(node);
			});
			setCreatedCard(data.show_created_card === true);
			setDetached(false);
			resetLatestMessageIdFromDom();
			markFeed();
			if (scroll) scrollChatToBottom('auto');
			syncScrollBottomCount();
			refreshScrollBottomButton();
		} catch {
			// Jaringan putus: tetap di potongan lama; tombol "Ke pesan terbaru" masih bisa dicoba lagi.
		}
	};
	feedState.restore = restoreLatest;

	const nodeFor = (id) => chatFeed.querySelector(`:scope > [data-message-id="${id}"]`);

	const focusNode = (node) => {
		if (!(node instanceof HTMLElement)) return;
		node.scrollIntoView({ behavior: 'auto', block: 'center' });
		animateJumpFocus(node);
		chatFeed.querySelectorAll('mark.is-current').forEach((mark) => mark.classList.remove('is-current'));
		node.querySelectorAll('mark.nc-inline-highlight-search').forEach((mark) => mark.classList.add('is-current'));
	};

	/** Gulir ke pesan hasil; bila tidak ada di feed, muat potongan di sekitarnya dulu. */
	const showMessage = async (id, seq, { requireOpen = true } = {}) => {
		let node = nodeFor(id);
		if (!node) {
			try {
				const data = await chatApiRequest(aroundTemplate.replace('__MESSAGE__', String(id)));
				if (seq !== state.seq || (requireOpen && !state.open)) return false;
				renderMessages(data.messages || []);
				setDetached(true);
				markFeed();
				node = nodeFor(id);
			} catch {
				return false;
			}
		}
		focusNode(node);
		return node instanceof HTMLElement;
	};

	jumpHandler = (id) => (id > 0 ? showMessage(id, state.seq, { requireOpen: false }) : Promise.resolve(false));

	/* ---------- pencarian ---------- */

	const select = async (index) => {
		if (index < 0 || index >= state.results.length) return;
		state.index = index;
		syncControls();
		await showMessage(state.results[index].id, state.seq);
	};

	const fetchResults = async (beforeId = 0) => {
		const params = new URLSearchParams({ q: state.query });
		if (beforeId > 0) params.set('before_id', String(beforeId));
		return chatApiRequest(`${searchUrl}?${params}`);
	};

	const loadMore = async () => {
		if (state.loading || !state.hasMore || !state.results.length) return false;
		const seq = state.seq;
		state.loading = true;
		try {
			const data = await fetchResults(state.results[state.results.length - 1].id);
			if (seq !== state.seq) return false;
			state.results.push(...(data.results || []));
			state.hasMore = data.has_more === true;
			state.total = Number(data.total || state.total);
			renderPanel(true);
			return true;
		} catch {
			return false;
		} finally {
			state.loading = false;
			syncControls();
		}
	};

	const runSearch = async () => {
		const query = input.value.trim().replace(/\s+/g, ' ');
		const seq = ++state.seq;
		state.query = query;
		state.results = [];
		state.total = 0;
		state.hasMore = false;
		state.index = -1;
		state.loading = query.length >= MIN_LENGTH;
		renderPanel();
		markFeed();
		syncControls();
		if (query.length < MIN_LENGTH) return;

		try {
			const data = await fetchResults();
			if (seq !== state.seq) return;
			state.results = data.results || [];
			state.total = Number(data.total || 0);
			state.hasMore = data.has_more === true;
		} catch {
			if (seq !== state.seq) return;
		}
		state.loading = false;
		renderPanel();
		markFeed();
		syncControls();
		// Langsung melompat ke hasil terbaru, seperti WhatsApp.
		if (state.results.length) await select(0);
	};

	const older = async () => {
		if (state.index + 1 >= state.results.length) {
			if (!(await loadMore())) return;
		}
		await select(state.index + 1);
	};
	const newer = () => select(state.index - 1);

	/* ---------- buka / tutup ---------- */

	const open = () => {
		if (state.open) {
			input.focus();
			input.select();
			return;
		}
		state.open = true;
		bar.classList.remove('hidden');
		bar.classList.add('flex');
		document.documentElement.classList.add('nc-searching');
		// Panel hasil perlu kolom kiri yang terbuka; sidebar ramping dibuka sementara.
		const desktop = window.matchMedia('(min-width: 1024px)').matches;
		if (desktop && sidebar && document.documentElement.classList.contains('nc-sidebar-collapsed')) {
			sidebar.querySelector('[data-sidebar-toggle]')?.click();
			state.reopenedSidebar = true;
		}
		sidebar?.classList.add('is-searching');
		panel?.classList.remove('hidden');
		syncControls();
		input.focus();
	};

	const close = () => {
		if (!state.open) return;
		state.open = false;
		state.seq += 1;
		window.clearTimeout(typeTimer);
		state.query = '';
		state.results = [];
		state.total = 0;
		state.hasMore = false;
		state.index = -1;
		state.loading = false;
		input.value = '';
		bar.classList.add('hidden');
		bar.classList.remove('flex');
		document.documentElement.classList.remove('nc-searching');
		sidebar?.classList.remove('is-searching');
		panel?.classList.add('hidden');
		if (panelList) panelList.innerHTML = '';
		clearSearchHighlightsInFeed(chatFeed);
		chatFeed.querySelectorAll('mark.is-current').forEach((mark) => mark.classList.remove('is-current'));
		if (state.reopenedSidebar) {
			state.reopenedSidebar = false;
			if (!document.documentElement.classList.contains('nc-sidebar-collapsed')) sidebar?.querySelector('[data-sidebar-toggle]')?.click();
		}
		if (feedState.detached) restoreLatest();
	};

	/* ---------- peristiwa ---------- */

	opener?.addEventListener('click', open);
	document.querySelectorAll('[data-close-search]').forEach((button) => button.addEventListener('click', close));
	olderBtn?.addEventListener('click', older);
	newerBtn?.addEventListener('click', newer);
	banner?.querySelector('[data-old-banner-latest]')?.addEventListener('click', () => restoreLatest());

	input.addEventListener('input', () => {
		window.clearTimeout(typeTimer);
		typeTimer = window.setTimeout(runSearch, TYPE_DELAY_MS);
	});
	input.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			close();
		} else if (event.key === 'Enter') {
			event.preventDefault();
			window.clearTimeout(typeTimer);
			if (input.value.trim().replace(/\s+/g, ' ') !== state.query) runSearch();
			else if (event.shiftKey) newer();
			else older();
		}
	});

	panelList?.addEventListener('click', (event) => {
		const row = event.target instanceof Element ? event.target.closest('[data-search-result]') : null;
		if (row) select(Number(row.dataset.searchResult));
	});
	panelList?.addEventListener('scroll', () => {
		if (panelList.scrollHeight - panelList.scrollTop - panelList.clientHeight < 120) loadMore();
	}, { passive: true });

	// Pesan terkirim/masuk atau diganti selagi mencari: tandai kata di pesan yang belum ditandai.
	let markScheduled = false;
	new MutationObserver(() => {
		if (!state.open || markScheduled) return;
		markScheduled = true;
		window.requestAnimationFrame(() => {
			markScheduled = false;
			if (!state.open || state.query.length < MIN_LENGTH) return;
			chatFeed.querySelectorAll(':scope > .nc-msg').forEach((node) => {
				if (!node.querySelector('mark.nc-inline-highlight-search')) highlightSearchInMessage(node, state.query);
			});
		});
	}).observe(chatFeed, { childList: true });

	// Mengirim pesan dari potongan lama: kembali ke pesan terbaru dulu (pesan yang dikirim tetap tampil).
	document.querySelector('[data-chat-form]')?.addEventListener('submit', () => {
		if (feedState.detached) restoreLatest();
	}, true);
};
