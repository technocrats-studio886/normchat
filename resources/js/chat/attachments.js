// Markup lampiran pesan (gambar, voice, video, file) dan pratinjau reply.

import { renderCanvasCard } from './canvas.js';
import { escapeAttr, escapeHtml } from './utils.js';

export const formatAudioTime = (seconds) => {
	if (!Number.isFinite(seconds) || seconds < 0) {
		return '0:00';
	}

	const whole = Math.floor(seconds);
	const min = Math.floor(whole / 60);
	const sec = String(whole % 60).padStart(2, '0');
	return `${min}:${sec}`;
};

export const formatFileSize = (bytes) => {
	const size = Number(bytes || 0);
	if (!Number.isFinite(size) || size <= 0) {
		return 'Dokumen';
	}

	if (size < 1024) {
		return `${size} B`;
	}

	if (size < 1024 * 1024) {
		return `${(size / 1024).toFixed(1)} KB`;
	}

	if (size < 1024 * 1024 * 1024) {
		return `${(size / (1024 * 1024)).toFixed(1)} MB`;
	}

	return `${(size / (1024 * 1024 * 1024)).toFixed(1)} GB`;
};

export const playIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>';
export const pauseIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6 4.75A.75.75 0 0 1 6.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Zm5 0A.75.75 0 0 1 11.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Z" /></svg>';
const voiceWaveHeights = ['h-2', 'h-3', 'h-2', 'h-4', 'h-2', 'h-3', 'h-4', 'h-2', 'h-3', 'h-2', 'h-4', 'h-2'];

const buildVoicePlayerMarkup = (safeUrl, sourceType, palette) => {
	const isMine = palette === 'mine';
	const playerClass = isMine
		? 'border-white/15 text-white'
		: (palette === 'ai' ? 'border-coral-200 bg-coral-50 text-coral-900' : 'border-slate-200 bg-slate-100 text-slate-800');
	const playerStyle = isMine ? 'style="background: var(--nc-mine);"' : '';
	const tone = palette === 'ai' ? 'coral' : 'navy';
	const buttonClass = isMine ? 'bg-white/20 text-white' : (tone === 'coral' ? 'bg-coral-500 text-white' : 'bg-navy-500 text-white');
	const sliderClass = isMine ? '' : (tone === 'coral' ? 'accent-coral-500' : 'accent-navy-500');
	const sliderStyle = isMine ? 'style="accent-color: rgba(255,255,255,0.85);"' : '';
	const timerClass = isMine ? 'text-white/80' : 'text-slate-500';
	const waveClass = isMine ? 'bg-white/70' : (tone === 'coral' ? 'bg-coral-500/70' : 'bg-navy-500/70');
	const waveHtml = voiceWaveHeights
		.map((heightClass) => `<span class="${heightClass} w-0.5 rounded-full ${waveClass} opacity-45" data-voice-bar></span>`)
		.join('');

	return `
		<div class="mb-2 inline-block w-55 max-w-full rounded-2xl border px-3 py-2 transition ${playerClass}" ${playerStyle} data-voice-player="1">
			<audio preload="metadata" class="hidden" data-voice-audio>
				<source src="${safeUrl}" type="${sourceType}">
			</audio>
			<div class="flex items-center gap-2">
				<button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${buttonClass}" data-voice-toggle aria-label="Putar" aria-pressed="false">
					${playIconSvg}
				</button>
				<div class="min-w-0 flex-1">
					<div class="mb-1 flex h-4 items-end gap-0.5" data-voice-wave>${waveHtml}</div>
					<input type="range" min="0" max="1000" value="0" class="h-1 w-full cursor-pointer ${sliderClass}" ${sliderStyle} data-voice-progress>
					<div class="mt-1 flex items-center justify-between text-[11px] ${timerClass}">
						<span data-voice-current>0:00</span>
						<span data-voice-duration>0:00</span>
					</div>
				</div>
			</div>
			<p class="mt-1 hidden text-[11px] ${timerClass}" data-voice-fallback-note>Putar lewat pemutar di bawah.</p>
		</div>
	`;
};

export const renderAttachment = (message, palette = 'slate', options = {}) => {
	const url = message?.attachment_url || '';
	if (!url) {
		return '';
	}

	const mergedCaption = options?.mergedCaption === true;

	if (String(message?.message_type || '').toLowerCase() === 'canvas') {
		return renderCanvasCard(message);
	}

	const mime = String(message?.attachment_mime || '').toLowerCase();
	const type = String(message?.message_type || 'text').toLowerCase();
	const attachmentName = escapeHtml(message?.attachment_original_name || 'Lampiran');
	const attachmentSizeLabel = escapeHtml(formatFileSize(message?.attachment_size));
	const safeUrl = escapeAttr(url);
	const safeNameAttr = escapeAttr(message?.attachment_original_name || 'lampiran');
	const isVideo = type === 'video' || mime.startsWith('video/');

	if (type === 'image' || mime.startsWith('image/')) {
		const border = palette === 'mine' ? 'border-navy-200 bg-navy-50' : (palette === 'ai' ? 'border-coral-100 bg-coral-50' : 'border-slate-200 bg-white');
		if (mergedCaption) {
			return `
				<a href="${safeUrl}" class="block overflow-hidden rounded-t-[20px]" data-message-body="1" data-attachment-open="1" data-attachment-kind="image" data-attachment-name="${safeNameAttr}" data-attachment-frame="1">
					<img src="${safeUrl}" alt="Gambar" class="h-auto max-h-72 w-full object-cover" />
				</a>
			`;
		}

		return `
			<a href="${safeUrl}" class="mb-2 block overflow-hidden rounded-2xl border ${border}" data-message-body="1" data-attachment-open="1" data-attachment-kind="image" data-attachment-name="${safeNameAttr}">
				<img src="${safeUrl}" alt="Gambar" class="h-auto max-h-72 w-full object-cover" />
			</a>
		`;
	}

	if (isVideo) {
		const frameClass = palette === 'mine'
			? 'border-navy-200 bg-navy-50'
			: (palette === 'ai' ? 'border-coral-200 bg-coral-50' : 'border-slate-200 bg-white');
		if (mergedCaption) {
			return `
				<button type="button" class="block w-full overflow-hidden rounded-t-[20px] bg-black" data-message-body="1" data-attachment-open="1" data-attachment-kind="video" data-attachment-url="${safeUrl}" data-attachment-name="${safeNameAttr}" data-attachment-frame="1">
					<div class="relative">
						<video src="${safeUrl}" class="h-auto max-h-72 w-full bg-black object-cover" muted playsinline preload="metadata"></video>
						<span class="absolute bottom-2 right-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-sm font-bold text-white">▶</span>
					</div>
				</button>
			`;
		}

		return `
			<button type="button" class="mb-2 block w-full overflow-hidden rounded-2xl border ${frameClass}" data-message-body="1" data-attachment-open="1" data-attachment-kind="video" data-attachment-url="${safeUrl}" data-attachment-name="${safeNameAttr}">
				<div class="relative">
					<video src="${safeUrl}" class="h-auto max-h-72 w-full bg-black object-cover" muted playsinline preload="metadata"></video>
					<span class="absolute bottom-2 right-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-sm font-bold text-white">▶</span>
				</div>
				<p class="px-3 py-2 text-left text-xs font-medium text-slate-700">${attachmentName}</p>
			</button>
		`;
	}

	if (type === 'voice' || mime.startsWith('audio/') || mime === 'video/webm') {
		const sourceType = escapeAttr(mime === 'video/webm' ? 'audio/webm' : (message?.attachment_mime || 'audio/webm'));
		return buildVoicePlayerMarkup(safeUrl, sourceType, palette).replace('data-voice-player="1"', 'data-voice-player="1" data-message-body="1"');
	}

	const fileToneClass = palette === 'mine'
		? 'border-navy-200 bg-navy-50 text-navy-800'
		: (palette === 'ai' ? 'border-coral-200 bg-coral-50 text-coral-800' : 'border-slate-200 bg-white text-slate-700');
	const fileMarginClass = mergedCaption ? '' : 'mb-2';

	return `
		<button type="button" class="${fileMarginClass} block w-full rounded-2xl border ${fileToneClass} p-3 text-left" data-message-body="1" data-attachment-download="1" data-attachment-url="${safeUrl}" data-attachment-name="${safeNameAttr}">
			<div class="flex items-center gap-3">
				<span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-black/10 text-base">📄</span>
				<div class="min-w-0 flex-1">
					<p class="truncate text-sm font-semibold">${attachmentName}</p>
					<p class="text-[11px] opacity-75">${attachmentSizeLabel}</p>
				</div>
				<span class="text-[11px] font-semibold opacity-80">Buka</span>
			</div>
		</button>
	`;
};

export const renderReplyPreview = (message, palette = 'slate') => {
	const replyTo = message?.reply_to;
	if (!replyTo || !replyTo.id) {
		return '';
	}

	const sender = escapeHtml(replyTo.sender_name || 'User');
	const quoteText = String(replyTo.quote_text || '').trim();
	const safeQuoteAttr = escapeAttr(quoteText);
	let summary = String(replyTo.quote_text || '').trim();
	if (!summary) {
		summary = String(replyTo.content || '').trim();
	}
	if (!summary) {
		const replyType = String(replyTo.message_type || '').toLowerCase();
		summary = replyType === 'image' ? '[Gambar]' : (replyType === 'voice' ? '[Voice note]' : '[Lampiran]');
	}

	const safeSummary = escapeHtml(summary.slice(0, 120));
	const toneClass = palette === 'mine'
		? 'nc-reply-chip--mine'
		: (palette === 'ai' ? 'nc-reply-chip--ai' : 'nc-reply-chip--other');

	return `
		<button type="button" data-reply-jump="${replyTo.id}" data-reply-quote="${safeQuoteAttr}" class="nc-reply-chip ${toneClass} mb-1 block w-full rounded-xl px-3 py-1.5 text-left text-xs">
			<p class="nc-reply-chip-sender font-semibold">Membalas ${sender}</p>
			<p class="truncate">${safeSummary}</p>
		</button>
	`;
};
