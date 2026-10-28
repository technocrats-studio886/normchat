// Satu templat untuk setiap pesan di feed: pesan awal halaman (feed.js) maupun realtime/pending.
// Isi pesan (nama, kutipan, lampiran, teks, jam) berada dalam satu bubble; pesan anggota lain dan
// Normi AI diberi avatar. Pesan beruntun dari pengirim yang sama membentuk satu rangkaian
// (applyMessageRuns): nama hanya di pesan pertama, avatar hanya di pesan terakhir.

import { personColor, renderAttachment, renderQuote } from './attachments.js';
import { authUserId, chatFeed } from './context.js';
import { renderMarkdown, renderMermaidInNode } from './markdown.js';
import { parsePollContent, refreshPollCardsFromFeed, renderPollCard } from './polls.js';
import { escapeAttr, escapeHtml } from './utils.js';
import { initVoicePlayers } from './voice-player.js';

/** Pesan berselang lebih dari ini tidak digabung dalam satu rangkaian. */
const RUN_GAP_MS = 10 * 60 * 1000;

const timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

const parseDate = (value) => {
	const date = value ? new Date(value) : null;
	return date && !Number.isNaN(date.valueOf()) ? date : null;
};

const userIconSvg = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z"/></svg>';

/** Siluet Heroicons di lingkaran warna anggota, untuk yang belum punya foto atau fotonya gagal dimuat. */
export const avatarFallbackHtml = () => `<span class="nc-avatar-fallback">${userIconSvg}</span>`;

const avatarHtml = (message, isAi) => {
	if (isAi) {
		return '<span class="nc-avatar nc-avatar--ai" aria-hidden="true"><img src="/icons/icon-192.png" alt="" class="nc-avatar-img"></span>';
	}

	const photo = String(message?.sender_avatar_url || '');
	return `<span class="nc-avatar" aria-hidden="true">${photo
		? `<img src="${escapeAttr(photo)}" alt="" class="nc-avatar-img" loading="lazy" referrerpolicy="no-referrer" data-avatar-photo>`
		: avatarFallbackHtml()}</span>`;
};

/**
 * Jam pesan, didahului tanda "diedit" (pesan anggota) atau "Dijawab ulang" (jawaban Normi). Tanda itu
 * membuka popup Version dari pertanyaannya.
 */
const timeHtml = (message, createdAt) => {
	const editedAt = parseDate(message?.edited_at);
	const isAiAnswer = message?.sender_type === 'ai';
	const versionsOf = isAiAnswer ? Number(message?.reply_to?.id || 0) : Number(message?.id || 0);
	const editedMark = message?.is_edited === true
		? `<button type="button" class="nc-edited-mark" data-open-versions="${versionsOf}" title="${escapeAttr(editedAt ? `${isAiAnswer ? 'Dijawab ulang' : 'Diedit'} ${dateTimeFormat.format(editedAt)}` : 'Lihat versi')}">${isAiAnswer ? 'Dijawab ulang' : 'diedit'}</button>`
		: '';
	const time = createdAt ? timeFormat.format(createdAt) : '';

	return time || editedMark ? `<span class="nc-inline-time">${editedMark}${time}</span>` : '';
};

export const buildMessageNode = (message) => {
	const isAi = message?.sender_type === 'ai';
	const isMine = !isAi && message?.sender_type === 'user' && Number(message?.sender_id || 0) === authUserId;
	const tone = isAi ? 'ai' : (isMine ? 'mine' : 'other');
	const createdAt = parseDate(message?.created_at);
	const rawContent = String(message?.content || '');
	const pollData = parsePollContent(rawContent);
	const type = String(message?.message_type || 'text');
	const senderName = String(message?.sender_name || (isAi ? 'Normi AI' : 'Anggota'));
	const personVar = isAi ? 'var(--nc-ai-name)' : personColor(message?.sender_id);

	const wrapper = document.createElement('div');
	wrapper.className = `nc-msg nc-msg--${tone}`;
	wrapper.setAttribute('data-message-id', String(message?.id ?? ''));
	if (message?.id && !String(message.id).startsWith('local-')) {
		wrapper.id = `message-${message.id}`;
	}
	wrapper.style.setProperty('--person', personVar);
	wrapper.style.touchAction = 'pan-y';
	wrapper.style.userSelect = 'none';
	wrapper.style.webkitUserSelect = 'none';
	const isDeleted = message?.is_deleted === true;
	Object.assign(wrapper.dataset, {
		messageSenderId: String(Number(message?.sender_id || 0)),
		senderKey: isAi ? 'ai' : `user:${Number(message?.sender_id || 0)}`,
		messageCreatedAt: createdAt ? String(createdAt.valueOf()) : '',
		messageSenderName: isMine ? 'Kamu' : senderName,
		messageContent: isDeleted ? 'Pesan ini dihapus' : (pollData ? `Polling: ${pollData.question}` : rawContent.trim()) || ({ image: '[Gambar]', voice: '[Pesan suara]', video: '[Video]', canvas: '[Canvas]' }[type] || '[Lampiran]'),
		// Teks yang disalin: polling ditulis rapi tanpa penanda 📊 POLL.
		messageText: pollData ? [`Polling: ${pollData.question}`, ...pollData.options.map((option) => `${option.number}. ${option.label}`)].join('\n') : rawContent.trim(),
		messageType: type,
		messageAttachmentMime: String(message?.attachment_mime || ''),
		messageAttachmentName: String(message?.attachment_original_name || ''),
		messageReplyTo: String(Number(message?.reply_to?.id || 0)),
	});
	// Penanda untuk aturan aksi (actions/rules.js).
	[
		['messageMine', isMine],
		['messageDeleted', isDeleted],
		['messageNotice', message?.is_notice === true],
		['messageAnswer', message?.is_answer === true],
		['messageEdited', message?.is_edited === true],
		['messagePoll', Boolean(pollData)],
		['messageForwarded', Boolean(message?.forwarded_from)],
		['messageAwaitingAi', message?.awaiting_ai === true],
	].forEach(([key, on]) => {
		if (on) {
			wrapper.dataset[key] = '1';
		}
	});
	wrapper.classList.toggle('is-deleted', isDeleted);
	wrapper.classList.toggle('is-notice', message?.is_notice === true);

	const nameHtml = isMine ? '' : `<p class="nc-msg-name">${escapeHtml(senderName)}</p>`;
	const quoteHtml = renderQuote(message);
	const attachmentHtml = renderAttachment(message);
	const renderedMarkdown = isAi && rawContent && !pollData ? renderMarkdown(escapeHtml(rawContent)) : '';
	const isWide = renderedMarkdown.includes('<table') || renderedMarkdown.includes('mermaid');
	const textHtml = pollData || !rawContent
		? ''
		: (isAi
			? `<div class="ai-markdown">${renderedMarkdown}</div>`
			: `<span class="whitespace-pre-wrap">${escapeHtml(rawContent)}</span>`);
	const isMediaOnly = !textHtml && /nc-bubble-media/.test(attachmentHtml);

	const forwardedHtml = message?.forwarded_from
		? `<p class="nc-forwarded">Diteruskan dari <b>${escapeHtml(message.forwarded_from)}</b></p>`
		: '';
	const noticeHtml = message?.is_notice === true ? '<p class="nc-notice-label">Hanya terlihat olehmu</p>' : '';
	const deletedHtml = `<p class="nc-msg-deleted">${message?.deleted_by_moderator ? 'Dihapus oleh moderator' : 'Pesan ini dihapus'}</p>`;

	const bubble = isDeleted
		? `<div class="nc-bubble bubble-${tone}" data-message-body="1" data-message-bubble>${nameHtml}${deletedHtml}${timeHtml({ ...message, is_edited: false }, createdAt)}</div>`
		: (pollData
			? renderPollCard({ poll: pollData, tone, messageId: message?.id, headerHtml: nameHtml + forwardedHtml + quoteHtml, inlineTimeHtml: timeHtml(message, createdAt) })
			: `<div class="nc-bubble bubble-${tone}${isMediaOnly ? ' is-media-only' : ''}" data-message-body="1" data-message-bubble>${nameHtml}${noticeHtml}${forwardedHtml}${quoteHtml}${attachmentHtml}${textHtml}${timeHtml(message, createdAt)}</div>`);
	const pendingHtml = message?.is_pending === true ? '<p class="nc-msg-pending">Mengirim…</p>' : '';

	wrapper.innerHTML = `${isMine ? '' : avatarHtml(message, isAi)}<div class="nc-msg-stack${isWide ? ' is-wide' : ''}">${bubble}${pendingHtml}</div>`;
	// Bubble bisa difokus dengan Tab agar aksi pesan (actions/menu.js) terjangkau keyboard.
	wrapper.querySelector('[data-message-bubble]')?.setAttribute('tabindex', '0');
	return wrapper;
};

/** Tandai awal & akhir setiap rangkaian pesan beruntun dari pengirim yang sama. */
export const applyMessageRuns = (feed = chatFeed) => {
	if (!(feed instanceof HTMLElement)) {
		return;
	}

	const nodes = Array.from(feed.querySelectorAll(':scope > .nc-msg:not(.hidden)'));
	const sameRun = (a, b) => a && b
		&& a.dataset.senderKey === b.dataset.senderKey
		&& Math.abs(Number(b.dataset.messageCreatedAt || 0) - Number(a.dataset.messageCreatedAt || 0)) <= RUN_GAP_MS;

	nodes.forEach((node, index) => {
		node.classList.toggle('is-run-start', !sameRun(nodes[index - 1], node));
		node.classList.toggle('is-run-end', !sameRun(node, nodes[index + 1]));
	});
};

export const replaceRenderedMessageByPayload = (message) => {
	if (!chatFeed || !message?.id) {
		return null;
	}

	const messageId = Number(message.id || 0);
	if (messageId <= 0) {
		return null;
	}

	const currentNode = chatFeed.querySelector(`[data-message-id="${messageId}"]`);
	if (!(currentNode instanceof HTMLElement)) {
		return null;
	}

	// Kutipan di pesan lain ikut berubah saat pesan aslinya dihapus untuk semua.
	if (message.is_deleted === true) {
		chatFeed.querySelectorAll(`.nc-quote[data-reply-jump="${messageId}"] .nc-quote-text`).forEach((text) => {
			text.textContent = 'Pesan ini dihapus';
		});
	}

	const nextNode = buildMessageNode(message);
	nextNode.setAttribute('data-message-id', String(messageId));
	nextNode.id = `message-${messageId}`;
	currentNode.replaceWith(nextNode);
	initVoicePlayers(nextNode);
	renderMermaidInNode(nextNode);
	refreshPollCardsFromFeed();

	return nextNode;
};

/** Kilatan singkat pada bubble yang baru diedit (oleh sendiri maupun anggota lain). */
export const flashEditedMessage = (node) => {
	const target = node instanceof HTMLElement ? (node.querySelector('[data-message-bubble]') || node) : null;
	if (!target) {
		return;
	}

	target.classList.remove('nc-edited-flash');
	void target.offsetWidth;
	target.classList.add('nc-edited-flash');
	target.addEventListener('animationend', () => target.classList.remove('nc-edited-flash'), { once: true });
};

