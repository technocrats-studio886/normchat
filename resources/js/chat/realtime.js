// Event realtime (Reverb/Echo): pesan baru, edit, hapus, polling, mengetik, mention.

import { refreshModelLocks } from './composer/ai-options.js';
import { cancelEditModeFor } from './composer/edit-mode.js';
import { authUserId, chatFeed, feedState, groupId, pendingMessagesByLocalId } from './context.js';
import { ensureMentionToastStack, highlightMessageInFeed } from './highlight.js';
import { renderMermaidInNode } from './markdown.js';
import { personColor } from './attachments.js';
import { attachLinkPreview } from './link-preview.js';
import { carryOverMedia, revokeLater } from './media-cache.js';
import { avatarHtml, buildMessageNode, flashEditedMessage, replaceRenderedMessageByPayload } from './messages.js';
import { mergePollStatsIntoCache, refreshPollCardsFromFeed } from './polls.js';
import { markGroupRead, markGroupVisited, noteUnseenIncomingMessage, refreshScrollBottomButton, rememberLatestMessageId, syncScrollBottomCount } from './read-state.js';
import { chatApiRequest, escapeHtml, scrollChatToBottom } from './utils.js';
import { initVoicePlayers } from './voice-player.js';
import { playNotifySound } from '../ui/notify-sound.js';

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

// ── Status mengetik / aktivitas Normi ─────────────────────────────
// Normi hanya "sedang mengetik" untuk jawaban teks; gambar, riset, canvas, dan dokumen punya status
// sendiri (App\Services\Ai\Responders\AiResponder::activity(), laporan user 9 Nov 2026).
const ACTIVITY_TEXT = {
	typing: 'sedang mengetik',
	image: 'sedang membuat gambar',
	research: 'sedang melakukan deep research',
	canvas: 'sedang menyusun canvas',
	document: 'sedang menyiapkan dokumen',
};
// Mode pertanyaan (App\Enums\AiMode) → aktivitas yang akan dikerjakan Normi.
const ACTIVITY_FOR_MODE = { image: 'image', deep_research: 'research', canvas: 'canvas' };

const imageIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 15-5-5L5 21"/></svg>';

const activeTypingUsers = new Map();
let optimisticAiTimer = 0;

const joinNames = (names) => (names.length === 1
	? names[0]
	: (names.length === 2 ? `${names[0]} dan ${names[1]}` : `${names.slice(0, -1).join(', ')}, dan ${names[names.length - 1]}`));

const renderTypingIndicator = () => {
	if (!(typingIndicator instanceof HTMLElement)) {
		return;
	}

	const people = Array.from(activeTypingUsers.values());
	if (!people.length) {
		typingIndicator.classList.add('hidden');
		typingIndicator.innerHTML = '';
		return;
	}

	// Foto profil bulat yang saling menumpuk (maks. 3) + kalimat per aktivitas:
	// "A dan B sedang mengetik · Normi AI sedang membuat gambar".
	const typingNames = people.filter((person) => (person.activity || 'typing') === 'typing').map((person) => person.name);
	const segments = typingNames.length ? [`${joinNames(typingNames)} ${ACTIVITY_TEXT.typing}`] : [];
	people.filter((person) => (person.activity || 'typing') !== 'typing').forEach((person) => {
		segments.push(`${person.name} ${ACTIVITY_TEXT[person.activity] || ACTIVITY_TEXT.typing}`);
	});
	const avatars = people.slice(0, 3).map((person) => `<span class="nc-typing-avatar" style="--person: ${person.isAi ? 'var(--nc-ai-name)' : personColor(person.id)}">${avatarHtml({ sender_avatar_url: person.avatar }, person.isAi)}</span>`).join('');
	// Normi sedang membuat gambar: placeholder gambar berdenyut (skeleton transitions.dev) di posisi gambarnya nanti.
	const imagePending = people.some((person) => person.isAi && person.activity === 'image')
		? `<div class="nc-ai-image-pending" aria-hidden="true"><span class="nc-ai-image-pending-icon">${imageIconSvg}</span></div>`
		: '';
	typingIndicator.classList.remove('hidden');
	typingIndicator.classList.toggle('has-media', imagePending !== '');
	typingIndicator.innerHTML = `<span class="nc-typing-avatars" aria-hidden="true">${avatars}</span><span class="nc-typing-text">${escapeHtml(segments.join(' · '))}<span class="nc-typing-dots" aria-hidden="true"><i></i><i></i><i></i></span></span>${imagePending}`;
};

const updateTypingState = (key, person, isTyping) => {
	if (!key) {
		return;
	}

	if (isTyping) {
		activeTypingUsers.set(key, person);
	} else {
		activeTypingUsers.delete(key);
	}

	renderTypingIndicator();
};

const aiPerson = (name, activity) => ({ id: 0, name: String(name || 'Normi AI'), avatar: '', isAi: true, activity: ACTIVITY_TEXT[activity] ? activity : 'typing' });

/** Status Normi dari server (event typing.status): berlaku sampai server mengirim is_typing=false. */
const setAiActivity = (name, activity, isTyping) => {
	window.clearTimeout(optimisticAiTimer);
	updateTypingState('ai', aiPerson(name, activity), isTyping);
};

/**
 * Pertanyaan ke Normi baru diterima server: tampilkan langsung status yang diperkirakan dari modenya,
 * sebelum antrean mulai memproses. Hilang sendiri bila server tidak mengonfirmasi (mis. permintaan
 * ditolak), dan diganti status sebenarnya begitu event typing.status datang.
 */
export const noteAwaitingAi = (message) => {
	if (message?.awaiting_ai !== true || activeTypingUsers.has('ai')) {
		return;
	}
	updateTypingState('ai', aiPerson('Normi AI', ACTIVITY_FOR_MODE[String(message?.ai_mode || '')] || 'typing'), true);
	window.clearTimeout(optimisticAiTimer);
	optimisticAiTimer = window.setTimeout(() => updateTypingState('ai', null, false), 45_000);
};

// Untuk uji browser tanpa server realtime: terapkan status mengetik secara langsung.
window.ncTyping = { set: updateTypingState, ai: setAiActivity };

/** Pesan sementara (dikirim dari perangkat ini) diganti pesan aslinya dari server. */
const replacePending = (localId, pending, message) => {
	const finalNode = buildMessageNode(message);
	carryOverMedia(pending.node, finalNode);
	pending.node.replaceWith(finalNode);
	initVoicePlayers(finalNode);
	renderMermaidInNode(finalNode);
	revokeLater(pending.blobUrl);
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

/** Jawaban Normi datang: pertanyaannya boleh diedit lagi, dan status Normi selesai bila tak ada antrean lain. */
const markAnswered = (message) => {
	const questionId = Number(message?.reply_to?.id || 0);
	if (message?.sender_type === 'ai' && questionId > 0) {
		chatFeed?.querySelector(`[data-message-id="${questionId}"]`)?.removeAttribute('data-message-awaiting-ai');
		if (!chatFeed?.querySelector('[data-message-awaiting-ai]')) {
			window.clearTimeout(optimisticAiTimer);
			updateTypingState('ai', null, false);
		}
	}
};

/** Saat chat dibuka, pertanyaan terakhir yang masih diproses Normi langsung menampilkan statusnya. */
const showPendingAiFromFeed = () => {
	const waiting = chatFeed?.querySelectorAll('[data-message-awaiting-ai="1"]');
	const last = waiting && waiting.length ? waiting[waiting.length - 1] : null;
	if (!(last instanceof HTMLElement) || activeTypingUsers.has('ai')) {
		return;
	}
	updateTypingState('ai', aiPerson('Normi AI', ACTIVITY_FOR_MODE[last.dataset.messageAiMode || ''] || 'typing'), true);
	window.clearTimeout(optimisticAiTimer);
	optimisticAiTimer = window.setTimeout(() => updateTypingState('ai', null, false), 5 * 60_000);
};

/** Pesan yang masuk selama halaman tersimpan di Back-Forward Cache disusulkan dari server. */
const catchUpMessages = async () => {
	const recentUrl = document.querySelector('[data-message-search-bar]')?.getAttribute('data-recent-url') || '';
	if (!recentUrl || !chatFeed || feedState.detached) {
		return;
	}

	try {
		const data = await chatApiRequest(recentUrl);
		const messages = Array.isArray(data?.messages) ? data.messages : [];
		messages.forEach((message) => {
			const node = chatFeed.querySelector(`[data-message-id="${Number(message?.id || 0)}"]`);
			if (node instanceof HTMLElement) {
				if (message.awaiting_ai !== true) {
					node.removeAttribute('data-message-awaiting-ai');
				}
				return;
			}
			onMessageSent({ group_id: groupId, message });
		});
		showPendingAiFromFeed();
	} catch {
		// Luring: pesan baru tetap datang lewat realtime setelah tersambung lagi.
	}
};

/**
 * Back-Forward Cache: koneksi WebSocket yang terbuka membuat Chrome dan Safari menolak menyimpan halaman,
 * jadi koneksi realtime ditutup saat halaman ditinggalkan dan disambung lagi saat halaman dipulihkan
 * (kanal didaftarkan ulang otomatis oleh pusher-js), lalu pesan yang terlewat disusulkan.
 */
const resumeAfterBackForwardCache = () => {
	window.addEventListener('pagehide', () => {
		// Beri kesempatan bagian lain mengirim pesan terakhir (mis. berhenti mengetik) sebelum koneksi ditutup.
		window.dispatchEvent(new Event('nc:realtime-closing'));
		try {
			window.Echo?.connector?.pusher?.disconnect();
		} catch {
			// Realtime tidak aktif di halaman ini.
		}
	});
	window.addEventListener('pageshow', (event) => {
		if (!event.persisted) {
			return;
		}
		try {
			window.Echo?.connector?.pusher?.connect();
		} catch {
			// Realtime tidak aktif di halaman ini.
		}
		// Status mengetik lama tidak berlaku lagi.
		activeTypingUsers.clear();
		renderTypingIndicator();
		catchUpMessages();
	});
};

/** Pesan baru dari kanal grup, atau pemberitahuan Normi dari kanal pribadi user. */
const onMessageSent = (payload) => {
	const message = payload?.message;
	if (!chatFeed || !message || Number(payload.group_id || 0) !== Number(groupId || 0)) {
		return;
	}

	rememberLatestMessageId(message.id);
	markAnswered(message);
	noteAwaitingAi(message);
	if (message.group_credits_remaining !== undefined) {
		refreshModelLocks(message.group_credits_remaining);
	}

	const distFromBottomBefore = chatFeed.scrollHeight - chatFeed.scrollTop - chatFeed.clientHeight;
	const isMine = message.sender_type === 'user' && Number(message.sender_id || 0) === authUserId;
	if (!isMine && document.hidden) {
		playNotifySound();
	}

	// Feed sedang menampilkan potongan pesan lama (hasil pencarian): pesan baru tidak disisipkan, hanya dihitung.
	// Pesan yang baru dikirim dari perangkat ini tetap diproses karena pesan sementaranya ada di feed.
	if (feedState.detached && !(isMine && pendingMessagesByLocalId.size > 0)) {
		if (!chatFeed.querySelector(`[data-message-id="${message.id}"]`)) {
			feedState.pendingNew += 1;
		}
		syncScrollBottomCount();
		refreshScrollBottomButton();
		return;
	}

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
	resumeAfterBackForwardCache();
	showPendingAiFromFeed();
	// Halaman ini hasil prefetch (speculation rules): isinya bisa tertinggal beberapa saat, susulkan pesannya.
	try {
		if (String(performance.getEntriesByType('navigation')[0]?.deliveryType || '').includes('prefetch')) {
			catchUpMessages();
		}
	} catch {
		// Browser tanpa Navigation Timing Level 2.
	}

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
			playNotifySound();
		});
	}

	if (window.Echo && groupId) {
		groupChannel = window.Echo.private(`group.${groupId}`);

		groupChannel.listenForWhisper('typing', (payload) => {
			const senderId = Number(payload?.sender_id || 0);
			if (senderId === authUserId) {
				return;
			}

			const fullName = String(payload?.sender_name || 'Anggota').trim();
			updateTypingState(
				`user:${senderId || fullName || 'unknown'}`,
				// Nama depan saja supaya tiga nama tetap muat satu baris.
				{ id: senderId, name: fullName.split(/\s+/)[0] || 'Anggota', avatar: String(payload?.sender_avatar || ''), isAi: false },
				Boolean(payload?.is_typing)
			);
		});

		groupChannel.listen('.typing.status', (payload) => {
			const actorType = String(payload?.actor_type || '');
			if (actorType !== 'ai') {
				return;
			}

			setAiActivity(payload?.sender_name, String(payload?.activity || 'typing'), Boolean(payload?.is_typing));
		});

		groupChannel.listen('.message.sent', onMessageSent);
		// Pratinjau tautan selesai dibaca di antrean: pasang kartunya di bubble.
		groupChannel.listen('.link.preview.ready', (payload) => attachLinkPreview(chatFeed, payload?.message_id, payload?.link_preview));

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

			if (['removed', 'left'].includes(String(payload?.action || ''))) {
				window.location.href = '/groups';
			}
			// Pemilik mengirim riwayat pesan: muat ulang supaya pesan sebelum bergabung tampil.
			if (String(payload?.action || '') === 'history_shared') {
				window.location.reload();
			}
		});
	}
};
