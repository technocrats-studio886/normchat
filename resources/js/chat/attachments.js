// Lampiran pesan (gambar, video, voice, file, canvas) dan kutipan balasan, sebagai blok di dalam
// bubble. Warna diturunkan dari warna teks bubble (currentColor), jadi cocok di bubble sendiri,
// anggota lain, maupun Normi AI, di tema terang dan gelap.

import { renderCanvasCard } from './canvas.js';
import { parsePollContent } from './polls.js';
import { escapeAttr, escapeHtml } from './utils.js';

export const formatAudioTime = (seconds) => {
	if (!Number.isFinite(seconds) || seconds < 0) {
		return '0:00';
	}

	const whole = Math.floor(seconds);
	return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
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
		return `${(size / 1024).toFixed(1).replace('.', ',')} KB`;
	}
	if (size < 1024 * 1024 * 1024) {
		return `${(size / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
	}

	return `${(size / (1024 * 1024 * 1024)).toFixed(1).replace('.', ',')} GB`;
};

export const playIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>';
export const pauseIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6 4.75A.75.75 0 0 1 6.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Zm5 0A.75.75 0 0 1 11.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Z" /></svg>';
const documentIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>';
const voiceWaveHeights = [8, 12, 8, 16, 8, 12, 16, 8, 12, 8, 16, 8];

const voicePlayerMarkup = (safeUrl, sourceType) => `
	<div class="nc-voice" data-voice-player="1">
		<audio preload="metadata" class="hidden" data-voice-audio>
			<source src="${safeUrl}" type="${sourceType}">
		</audio>
		<button type="button" class="nc-voice-toggle" data-voice-toggle aria-label="Putar" aria-pressed="false">${playIconSvg}</button>
		<div class="min-w-0 flex-1">
			<div class="nc-voice-wave" data-voice-wave>${voiceWaveHeights.map((h) => `<span style="height:${h}px" data-voice-bar></span>`).join('')}</div>
			<input type="range" min="0" max="1000" value="0" class="nc-voice-progress" data-voice-progress aria-label="Posisi pemutaran">
			<div class="nc-voice-times"><span data-voice-current>0:00</span><span data-voice-duration>0:00</span></div>
		</div>
		<p class="hidden text-[11px] opacity-75" data-voice-fallback-note>Putar lewat pemutar di bawah.</p>
	</div>
`;

/** Lampiran sebagai blok di dalam bubble; '' bila pesan tanpa lampiran. */
export const renderAttachment = (message) => {
	const url = message?.attachment_url || '';
	if (!url) {
		return '';
	}

	const type = String(message?.message_type || 'text').toLowerCase();
	if (type === 'canvas') {
		return renderCanvasCard(message);
	}

	const mime = String(message?.attachment_mime || '').toLowerCase();
	const safeUrl = escapeAttr(url);
	const name = message?.attachment_original_name || 'Lampiran';
	const safeNameAttr = escapeAttr(name);

	if (type === 'image' || mime.startsWith('image/')) {
		const alt = escapeAttr(`Gambar dari ${message?.sender_name || 'anggota'}`);
		return `<a href="${safeUrl}" class="nc-bubble-media" data-attachment-open="1" data-attachment-kind="image" data-attachment-name="${safeNameAttr}"><img src="${safeUrl}" alt="${alt}" class="nc-media-image" loading="lazy"></a>`;
	}

	if (type === 'video' || (mime.startsWith('video/') && mime !== 'video/webm')) {
		return `
			<button type="button" class="nc-bubble-media nc-bubble-video" data-attachment-open="1" data-attachment-kind="video" data-attachment-url="${safeUrl}" data-attachment-name="${safeNameAttr}" aria-label="Putar video ${safeNameAttr}">
				<video src="${safeUrl}" class="nc-media-image" muted playsinline preload="metadata"></video>
				<span class="nc-video-play">${playIconSvg}</span>
			</button>
		`;
	}

	if (type === 'voice' || mime.startsWith('audio/') || mime === 'video/webm') {
		const sourceType = escapeAttr(mime === 'video/webm' ? 'audio/webm' : (message?.attachment_mime || 'audio/webm'));
		return voicePlayerMarkup(safeUrl, sourceType);
	}

	return `
		<button type="button" class="nc-file" data-attachment-download="1" data-attachment-url="${safeUrl}" data-attachment-name="${safeNameAttr}">
			<span class="nc-file-icon">${documentIconSvg}</span>
			<span class="min-w-0 flex-1">
				<span class="block truncate text-sm font-semibold">${escapeHtml(name)}</span>
				<span class="block text-[11px] opacity-75">${escapeHtml(formatFileSize(message?.attachment_size))}</span>
			</span>
		</button>
	`;
};

const quoteSummary = (replyTo) => {
	if (replyTo.is_deleted) {
		return 'Pesan ini dihapus';
	}
	const poll = parsePollContent(replyTo.content);
	if (poll && !String(replyTo.quote_text || '').trim()) {
		return `Polling: ${poll.question}`;
	}
	const quote = String(replyTo.quote_text || '').trim() || String(replyTo.content || '').trim();
	if (quote) {
		return quote;
	}

	return {
		image: 'Gambar', voice: 'Pesan suara', video: 'Video', canvas: 'Canvas',
	}[String(replyTo.message_type || '').toLowerCase()] || 'Lampiran';
};

/** Kutipan pesan yang dibalas, di bagian atas bubble; ketuk untuk melompat ke pesan aslinya. */
export const renderQuote = (message) => {
	const replyTo = message?.reply_to;
	if (!replyTo || !replyTo.id) {
		return '';
	}

	const color = replyTo.sender_type === 'ai' ? 'var(--nc-ai-name)' : personColor(replyTo.sender_id);

	return `
		<button type="button" class="nc-quote" style="--q: ${color}" data-reply-jump="${Number(replyTo.id)}" data-reply-quote="${escapeAttr(String(replyTo.quote_text || '').trim())}">
			<span class="nc-quote-name">${escapeHtml(replyTo.sender_name || 'Anggota')}</span>
			<span class="nc-quote-text">${escapeHtml(quoteSummary(replyTo).slice(0, 160))}</span>
		</button>
	`;
};

/** Warna nama tetap per anggota (delapan warna, didefinisikan per tema di app.css). */
export const personColor = (senderId) => `var(--nc-person-${Math.abs(Number(senderId) || 0) % 8})`;
