// Satu templat untuk setiap pesan di feed: pesan awal halaman (feed.js) maupun realtime/pending.
// Isi pesan (nama, kutipan, lampiran, teks, jam) berada dalam satu bubble; pesan anggota lain dan
// Normi AI diberi avatar. Pesan beruntun dari pengirim yang sama membentuk satu rangkaian
// (applyMessageRuns): nama hanya di pesan pertama, avatar hanya di pesan terakhir.

import { buildSystemNode } from './system-messages.js';
import { linkPreviewHtml } from './link-preview.js';
import { personColor, renderAttachment, renderQuote } from './attachments.js';
import { authUserId, chatFeed } from './context.js';
import { codeBlockHtml, FENCE_OPEN, renderMarkdown, renderMermaidInNode } from './markdown.js';
import { carryOverMedia } from './media-cache.js';
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

/**
 * Teks pesan anggota: teks biasa, kecuali blok ```bahasa … ``` yang tampil sebagai blok kode (bisa disalin
 * atau dimasukkan ke kolom ketik). Pesan tanpa pagar kode tetap satu span seperti sebelumnya.
 */
/** Tag Normi (@normi, @normi-image, @normi-research, ...) di teks yang sudah di-escape tampil sebagai tag. */
const NORMI_TAG_HTML = /(^|[\s(])(@normi(?:-[a-z]+)?)(?![\w-])/gi;
export const highlightNormiTags = (escaped) => escaped.replace(NORMI_TAG_HTML, '$1<span class="nc-tag">$2</span>');

const userTextHtml = (raw) => {
	if (!raw.includes('```')) {
		return `<span class="whitespace-pre-wrap">${highlightNormiTags(escapeHtml(raw))}</span>`;
	}
	const parts = [];
	let text = [];
	let code = null;
	let lang = '';
	const flushText = () => {
		const value = text.join('\n').replace(/^\n+|\n+$/g, '');
		if (value.trim() !== '') parts.push(`<span class="whitespace-pre-wrap">${highlightNormiTags(escapeHtml(value))}</span>`);
		text = [];
	};
	raw.split('\n').forEach((line) => {
		if (code === null) {
			const open = line.trim().match(FENCE_OPEN);
			if (open) {
				flushText();
				code = [];
				lang = open[1];
			} else {
				text.push(line);
			}
		} else if (line.trim() === '```') {
			parts.push(codeBlockHtml(lang, escapeHtml(code.join('\n'))));
			code = null;
		} else {
			code.push(line);
		}
	});
	// Pagar yang tidak ditutup: bila berisi kode tetap jadi blok kode; tanpa isi (``` nyasar) tetap teks biasa.
	if (code !== null && code.join('').trim() === '') text.push(`\`\`\`${lang}`);
	else if (code !== null) parts.push(codeBlockHtml(lang, escapeHtml(code.join('\n'))));
	flushText();
	return `<div class="nc-user-text">${parts.join('')}</div>`;
};

export const avatarHtml = (message, isAi) => {
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

const sparkSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path stroke-linejoin="round" d="M12 3.5 13.9 9l5.6 1.9-5.6 1.9L12 18.5l-1.9-5.7-5.6-1.9L10.1 9z"/></svg>';
const SUGGEST_LABELS = {
	image: 'Edit dengan Buat gambar',
	deep_research: 'Riset dengan Deep Research',
	canvas: 'Buka di Canvas',
	guided_learning: 'Belajar dengan Guided Learning',
	file: 'Buat sebagai file',
};

/** Tombol fitur yang disarankan Normi (App\Services\Chat\MessagePresenter::suggestionOf); diproses composer/feed-clicks.js. */
const suggestionHtml = (message) => {
	const suggestion = message?.suggestion;
	if (!suggestion?.mode || message?.is_deleted) {
		return '';
	}
	const label = SUGGEST_LABELS[suggestion.mode] || `Pakai ${suggestion.label || 'fitur ini'}`;
	return `<div><button type="button" class="nc-suggest" data-suggest-mode="${escapeAttr(suggestion.mode)}" data-suggest-tag="${escapeAttr(suggestion.tag || '')}" data-suggest-reply="${Number(suggestion.reply_to || 0)}" data-suggest-text="${escapeAttr(suggestion.text || '')}">${sparkSvg}${escapeHtml(label)}</button></div>`;
};

const zipSvg = '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 20h16"/></svg>';

/** Canvas dengan kode proyek asli (Next.js, Laravel, Flutter, ...): tautan unduh ZIP-nya. */
const canvasProjectHtml = (message) => {
	const project = message?.canvas_project;
	if (!project?.url) {
		return '';
	}
	const files = Number(project.files || 0);
	return `<a class="nc-canvas-project" href="${escapeAttr(project.url)}" download="${escapeAttr(project.name || 'proyek.zip')}">${zipSvg}Unduh kode proyek${files > 0 ? ` (${files} file)` : ''}</a>`;
};

export const buildMessageNode = (message) => {
	if (message?.sender_type === 'system') {
		return buildSystemNode(message);
	}
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
		// Fitur Normi: mode permintaan, atau fitur yang menghasilkan jawaban Normi (App\Enums\AiMode).
		messageAiMode: String(message?.ai_mode || ''),
		// Status Normi terakhir untuk pertanyaan yang masih dikerjakan (App\Services\Ai\AiActivity).
		messageAiActivity: String(message?.ai_activity?.activity || ''),
		messageAiDetail: String(message?.ai_activity?.detail || ''),
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
	// Dibungkus nc-msg-text: teks yang terlalu panjang dipotong dengan "Lihat selengkapnya" (chat/read-more.js).
	const textHtml = pollData || !rawContent
		? ''
		: `<div class="nc-msg-text" data-message-text>${isAi
			? `<div class="ai-markdown">${renderedMarkdown}</div>`
			: userTextHtml(rawContent)}</div>`;
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
			: `<div class="nc-bubble bubble-${tone}${isMediaOnly ? ' is-media-only' : ''}" data-message-body="1" data-message-bubble>${nameHtml}${noticeHtml}${forwardedHtml}${quoteHtml}${attachmentHtml}${isAi ? canvasProjectHtml(message) : linkPreviewHtml(message?.link_preview)}${textHtml}${isAi ? suggestionHtml(message) : ''}${timeHtml(message, createdAt)}</div>`);
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
	carryOverMedia(currentNode, nextNode);
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

