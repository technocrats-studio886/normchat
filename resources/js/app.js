import './bootstrap';

const chatFeed = document.querySelector('[data-chat-feed]');
const groupId = chatFeed?.getAttribute('data-chat-group-id');
const authUserIdFromFeed = Number(chatFeed?.getAttribute('data-auth-user-id') || 0);
const authUserIdFromMeta = Number(document.querySelector('meta[name="auth-user-id"]')?.getAttribute('content') || 0);
const authUserId = authUserIdFromFeed || authUserIdFromMeta;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const lastReadMessageIdFromServer = Number(chatFeed?.getAttribute('data-last-read-message-id') || 0);
const latestMessageIdFromServer = Number(chatFeed?.getAttribute('data-latest-message-id') || 0);
const unreadCountFromServer = Number(chatFeed?.getAttribute('data-unread-count') || 0);
const hasReadBeforeFromServer = String(chatFeed?.getAttribute('data-has-read-before') || '0') === '1';
const chatReadUrlFromServer = String(chatFeed?.getAttribute('data-chat-read-url') || '');
const pendingMessagesByLocalId = new Map();
let pendingLocalMessageCounter = 0;
let chatSubmitInFlight = false;

const scrollChatToBottom = (behavior = 'auto') => {
	if (!chatFeed) {
		return;
	}

	chatFeed.scrollTo({ top: chatFeed.scrollHeight, behavior });
};

const chatApiRequest = async (url, { method = 'GET', body } = {}) => {
	const headers = {
		'Accept': 'application/json',
		'X-Requested-With': 'XMLHttpRequest',
	};

	if (csrfToken) {
		headers['X-CSRF-TOKEN'] = csrfToken;
	}

	if (body && !(body instanceof FormData)) {
		headers['Content-Type'] = 'application/json';
	}

	const response = await fetch(url, {
		method,
		headers,
		body: body instanceof FormData ? body : (body ? JSON.stringify(body) : undefined),
		credentials: 'same-origin',
	});

	let payload = null;
	try {
		payload = await response.json();
	} catch (_error) {
		payload = null;
	}

	if (!response.ok) {
		throw new Error(payload?.message || 'request_failed');
	}

	return payload;
};

const updateCreditsBadge = (payload) => {
	const creditsEl = document.querySelector('[data-group-credits-badge]');
	if (!creditsEl) {
		return;
	}

	const credits = Number(payload?.group_credits_remaining);
	if (!Number.isFinite(credits)) {
		return;
	}

	creditsEl.textContent = credits.toFixed(1);
};

const escapeHtml = (text) => String(text)
	.replace(/&/g, '&amp;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#039;');

const escapeAttr = (text) => String(text)
	.replace(/&/g, '&amp;')
	.replace(/"/g, '&quot;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;');

const renderMarkdown = (escaped) => {
	const lines = escaped.split('\n');
	const out = [];
	let inTable = false;
	let inMermaid = false;
	let mermaidLines = [];
	let inList = false;
	let listType = '';

	const closeList = () => {
		if (inList) {
			out.push(listType === 'ol' ? '</ol>' : '</ul>');
			inList = false;
		}
	};

	for (let i = 0; i < lines.length; i++) {
		const line = lines[i];

		// Mermaid code blocks
		if (line.trim() === '```mermaid') {
			closeList();
			inMermaid = true;
			mermaidLines = [];
			continue;
		}
		if (inMermaid) {
			if (line.trim() === '```') {
				inMermaid = false;
				const id = 'mermaid-' + Math.random().toString(36).slice(2, 10);
				out.push(`<pre class="mermaid" id="${id}">${mermaidLines.join('\n')}</pre>`);
				mermaidLines = [];
			} else {
				mermaidLines.push(line);
			}
			continue;
		}

		// Code blocks (generic)
		if (line.trim().startsWith('```')) {
			closeList();
			out.push(line.trim() === '```' ? '' : `<code class="text-xs">${line.replace(/```\w*/, '')}</code>`);
			continue;
		}

		// Table rows
		if (line.trim().match(/^\|(.+)\|$/)) {
			closeList();
			if (line.trim().match(/^\|[\s\-:|]+\|$/)) {
				continue; // separator row
			}
			const cells = line.trim().slice(1, -1).split('|').map(c => c.trim());
			if (!inTable) {
				inTable = true;
				out.push('<div class="overflow-x-auto my-2"><table class="w-full text-xs border-collapse">');
				out.push('<thead><tr>' + cells.map(c => `<th class="border border-slate-300 bg-slate-100 px-2 py-1 text-left font-semibold">${inlineFormat(c)}</th>`).join('') + '</tr></thead><tbody>');
			} else {
				out.push('<tr>' + cells.map(c => `<td class="border border-slate-200 px-2 py-1">${inlineFormat(c)}</td>`).join('') + '</tr>');
			}
			continue;
		}
		if (inTable) {
			inTable = false;
			out.push('</tbody></table></div>');
		}

		// Headings
		const headingMatch = line.match(/^(#{1,3})\s+(.+)/);
		if (headingMatch) {
			closeList();
			const level = headingMatch[1].length;
			const sizes = { 1: 'text-base font-bold', 2: 'text-sm font-bold', 3: 'text-sm font-semibold' };
			out.push(`<p class="${sizes[level] || 'font-bold'} mt-1">${inlineFormat(headingMatch[2])}</p>`);
			continue;
		}

		// Ordered list
		const olMatch = line.match(/^(\d+)\.\s+(.+)/);
		if (olMatch) {
			if (!inList || listType !== 'ol') {
				closeList();
				inList = true;
				listType = 'ol';
				out.push('<ol class="list-decimal pl-5 my-1 space-y-0.5 text-sm">');
			}
			out.push(`<li>${inlineFormat(olMatch[2])}</li>`);
			continue;
		}

		// Unordered list
		const ulMatch = line.match(/^[-*]\s+(.+)/);
		if (ulMatch) {
			if (!inList || listType !== 'ul') {
				closeList();
				inList = true;
				listType = 'ul';
				out.push('<ul class="list-disc pl-5 my-1 space-y-0.5 text-sm">');
			}
			out.push(`<li>${inlineFormat(ulMatch[1])}</li>`);
			continue;
		}

		closeList();

		// Horizontal rule
		if (line.trim() === '---' || line.trim() === '***') {
			out.push('<hr class="my-2 border-slate-200" />');
			continue;
		}

		// Empty line
		if (line.trim() === '') {
			out.push('<br/>');
			continue;
		}

		// Normal paragraph
		out.push(`<p>${inlineFormat(line)}</p>`);
	}

	closeList();
	if (inTable) out.push('</tbody></table></div>');

	return out.join('\n');
};

const inlineFormat = (text) => {
	return text
		.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
		.replace(/\*(.+?)\*/g, '<em>$1</em>')
		.replace(/`([^`]+)`/g, '<code class="rounded bg-slate-200 px-1 py-0.5 text-xs">$1</code>');
};

const renderMermaidInNode = (node) => {
	if (typeof mermaid === 'undefined') return;
	const blocks = node.querySelectorAll('pre.mermaid');
	blocks.forEach(async (block) => {
		if (block.dataset.processed) return;
		block.dataset.processed = '1';
		try {
			const id = block.id || ('m-' + Math.random().toString(36).slice(2, 8));
			const { svg } = await mermaid.render(id + '-svg', block.textContent);
			block.innerHTML = svg;
		} catch (_) {
			block.classList.add('text-xs', 'text-rose-400');
		}
	});
};

const formatAudioTime = (seconds) => {
	if (!Number.isFinite(seconds) || seconds < 0) {
		return '0:00';
	}

	const whole = Math.floor(seconds);
	const min = Math.floor(whole / 60);
	const sec = String(whole % 60).padStart(2, '0');
	return `${min}:${sec}`;
};

const playIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>';
const pauseIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6 4.75A.75.75 0 0 1 6.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Zm5 0A.75.75 0 0 1 11.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Z" /></svg>';
const voiceWaveHeights = ['h-2', 'h-3', 'h-2', 'h-4', 'h-2', 'h-3', 'h-4', 'h-2', 'h-3', 'h-2', 'h-4', 'h-2'];

const buildVoicePlayerMarkup = (safeUrl, sourceType, palette) => {
	const isMine = palette === 'blue';
	const playerClass = isMine
		? 'border-[#0b4a3d] bg-[#0f5f4e] text-emerald-50'
		: (palette === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-100 text-slate-800');
	const buttonClass = isMine ? 'bg-emerald-300 text-emerald-950' : 'bg-emerald-500 text-white';
	const sliderClass = isMine ? 'accent-emerald-300' : 'accent-emerald-500';
	const timerClass = isMine ? 'text-emerald-100' : 'text-slate-500';
	const waveClass = isMine ? 'bg-emerald-200/85' : 'bg-emerald-500/70';
	const waveHtml = voiceWaveHeights
		.map((heightClass) => `<span class="${heightClass} w-0.5 rounded-full ${waveClass} opacity-45" data-voice-bar></span>`)
		.join('');

	return `
		<div class="mb-2 inline-block w-55 max-w-full rounded-2xl border px-3 py-2 transition ${playerClass}" data-voice-player="1">
			<audio preload="metadata" class="hidden" data-voice-audio>
				<source src="${safeUrl}" type="${sourceType}">
			</audio>
			<div class="flex items-center gap-2">
				<button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${buttonClass}" data-voice-toggle aria-label="Play voice note" aria-pressed="false">
					${playIconSvg}
				</button>
				<div class="min-w-0 flex-1">
					<div class="mb-1 flex h-4 items-end gap-0.5" data-voice-wave>${waveHtml}</div>
					<input type="range" min="0" max="1000" value="0" class="h-1 w-full cursor-pointer ${sliderClass}" data-voice-progress>
					<div class="mt-1 flex items-center justify-between text-[11px] ${timerClass}">
						<span data-voice-current>0:00</span>
						<span data-voice-duration>0:00</span>
					</div>
				</div>
			</div>
			<p class="mt-1 hidden text-[11px] ${timerClass}" data-voice-fallback-note>Audio mode kompatibilitas aktif. Tap player native di bawah.</p>
		</div>
	`;
};

const renderAttachment = (message, palette = 'slate') => {
	const url = message?.attachment_url || '';
	if (!url) {
		return '';
	}

	const mime = String(message?.attachment_mime || '').toLowerCase();
	const type = String(message?.message_type || 'text').toLowerCase();
	const safeUrl = escapeAttr(url);

	if (type === 'image' || mime.startsWith('image/')) {
		const border = palette === 'blue' ? 'border-blue-200 bg-blue-50' : (palette === 'emerald' ? 'border-emerald-100 bg-emerald-50' : 'border-slate-200 bg-white');
		return `
			<a href="${safeUrl}" target="_blank" rel="noopener" class="mb-2 block overflow-hidden rounded-2xl border ${border}" data-message-body="1">
				<img src="${safeUrl}" alt="Gambar" class="h-auto max-h-64 w-full object-cover" />
			</a>
		`;
	}

	if (type === 'voice' || mime.startsWith('audio/') || mime === 'video/webm') {
		const sourceType = escapeAttr(mime === 'video/webm' ? 'audio/webm' : (message?.attachment_mime || 'audio/webm'));
		return buildVoicePlayerMarkup(safeUrl, sourceType, palette).replace('data-voice-player="1"', 'data-voice-player="1" data-message-body="1"');
	}

	const attachmentName = escapeHtml(message?.attachment_original_name || 'Lampiran');
	return `
		<a href="${safeUrl}" target="_blank" rel="noopener" class="mb-2 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-blue-600 underline" data-message-body="1">${attachmentName}</a>
	`;
};

const renderReplyPreview = (message, palette = 'slate') => {
	const replyTo = message?.reply_to;
	if (!replyTo || !replyTo.id) {
		return '';
	}

	const sender = escapeHtml(replyTo.sender_name || 'User');
	let summary = String(replyTo.content || '').trim();
	if (!summary) {
		const replyType = String(replyTo.message_type || '').toLowerCase();
		summary = replyType === 'image' ? '[Gambar]' : (replyType === 'voice' ? '[Voice note]' : '[Lampiran]');
	}

	const safeSummary = escapeHtml(summary.slice(0, 120));
	const boxClass = palette === 'blue'
		? 'border-l-[3px] border-blue-500 bg-blue-50 text-slate-700 shadow-sm hover:bg-blue-100'
		: (palette === 'emerald'
			? 'border-l-[3px] border-emerald-500 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
			: 'border-l-[3px] border-slate-400 bg-slate-50 text-slate-600 hover:bg-slate-100');
	const senderClass = palette === 'blue' ? 'text-blue-700' : (palette === 'emerald' ? 'text-emerald-700' : 'text-slate-700');
	const previewClass = palette === 'blue' ? 'text-slate-600' : '';

	return `
		<a href="#message-${replyTo.id}" class="mb-1 block rounded-xl px-3 py-1.5 text-xs ${boxClass}">
			<p class="font-semibold ${senderClass}">Membalas ${sender}</p>
			<p class="truncate ${previewClass}">${safeSummary}</p>
		</a>
	`;
};

const buildMessageNode = (message) => {
	const createdAt = message?.created_at ? new Date(message.created_at) : null;
	const timeText = createdAt && !Number.isNaN(createdAt.valueOf())
		? createdAt.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
		: '';

	const isAi = message?.sender_type === 'ai';
	const isMine = message?.sender_type === 'user' && Number(message?.sender_id || 0) === authUserId;
	const buildEditedMarkHtml = (tone) => {
		if (!message?.is_edited) {
			return '';
		}

		const editedAt = message?.edited_at ? new Date(message.edited_at) : null;
		const editedTitle = editedAt && !Number.isNaN(editedAt.valueOf())
			? `Diedit ${editedAt.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}`
			: 'Pesan telah diedit';

		return ` <span class="nc-edited-mark nc-edited-mark--${tone}" title="${escapeAttr(editedTitle)}" aria-label="${escapeAttr(editedTitle)}">diedit</span>`;
	};
	const editedMarkForMine = buildEditedMarkHtml('mine');
	const editedMarkForAi = buildEditedMarkHtml('ai');
	const editedMarkForOther = buildEditedMarkHtml('other');
	const content = escapeHtml(message?.content || '');

	const wrapper = document.createElement('div');
	wrapper.setAttribute('data-message-id', String(message?.id ?? ''));
	wrapper.style.touchAction = 'pan-y';
	wrapper.style.userSelect = 'none';
	wrapper.style.webkitUserSelect = 'none';
	const isPending = message?.is_pending === true;

	if (isMine) {
		const replyBlock = renderReplyPreview(message, 'blue');
		const attachmentBlock = renderAttachment(message, 'blue');
		const inlineTimeHtml = timeText
			? `<span class="nc-inline-time">${timeText}${editedMarkForMine}</span>`
			: '';
		const textBlock = content
			? `<div class="bubble-mine" data-message-body="1"><span class="whitespace-pre-wrap">${content}</span>${inlineTimeHtml}</div>`
			: '';
		const pendingTail = isPending
			? `<p class="mt-1 text-right text-[11px] text-slate-400"><span class="inline-flex items-center gap-1"><span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span>Mengirim...</span></p>`
			: (content ? '' : `<p class="mt-1 text-right text-[11px] text-slate-400">${timeText}${editedMarkForMine}</p>`);

		wrapper.className = 'flex justify-end';
		wrapper.innerHTML = `
			<div class="max-w-[75%]">
				${replyBlock}
				${attachmentBlock}
				${textBlock}
				${pendingTail}
			</div>
		`;
		wrapper.dataset.messageSenderName = String(message?.sender_name || 'You');
		wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
		wrapper.dataset.messageType = String(message?.message_type || 'text');
		return wrapper;
	}

	if (isAi) {
		const replyBlock = renderReplyPreview(message, 'emerald');
		const aiName = escapeHtml(message?.sender_name || 'NormAI');
		const attachmentBlock = renderAttachment(message, 'emerald');
		const renderedContent = content ? renderMarkdown(content) : '';
		const hasRichContent = renderedContent && (renderedContent.includes('<table') || renderedContent.includes('mermaid'));
		const inlineTimeHtml = timeText
			? `<span class="nc-inline-time">${timeText}${editedMarkForAi}</span>`
			: '';
		const textBlock = renderedContent
			? `<div class="bubble-ai ai-markdown overflow-hidden" data-message-body="1">${renderedContent}${inlineTimeHtml}</div>`
			: '';

		wrapper.className = hasRichContent ? 'max-w-[95%]' : 'max-w-[80%]';
		wrapper.innerHTML = `
			<p class="mb-1 text-[11px] font-semibold text-emerald-700">${aiName}</p>
			${replyBlock}
			${attachmentBlock}
			${textBlock}
			${renderedContent ? '' : `<p class="mt-1 text-[11px] font-medium text-emerald-700">${timeText}${editedMarkForAi}</p>`}
		`;
		wrapper.dataset.messageSenderName = String(message?.sender_name || 'NormAI');
		wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
		wrapper.dataset.messageType = String(message?.message_type || 'text');
		return wrapper;
	}

	const replyBlock = renderReplyPreview(message, 'slate');
	const senderName = escapeHtml(message?.sender_name || 'User');
	const attachmentBlock = renderAttachment(message, 'slate');
	const inlineTimeHtml = timeText
		? `<span class="nc-inline-time">${timeText}${editedMarkForOther}</span>`
		: '';
	const textBlock = content
		? `<div class="bubble-other" data-message-body="1"><span class="whitespace-pre-wrap">${content}</span>${inlineTimeHtml}</div>`
		: '';

	wrapper.className = 'max-w-[75%]';
	wrapper.innerHTML = `
		<p class="mb-1 text-[11px] text-slate-500">${senderName}</p>
		${replyBlock}
		${attachmentBlock}
		${textBlock}
		${content ? '' : `<p class="mt-1 text-[11px] text-slate-400">${timeText}${editedMarkForOther}</p>`}
	`;
	wrapper.dataset.messageSenderName = String(message?.sender_name || 'User');
	wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
	wrapper.dataset.messageType = String(message?.message_type || 'text');

	return wrapper;
};

const replaceRenderedMessageByPayload = (message) => {
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

	const nextNode = buildMessageNode(message);
	nextNode.setAttribute('data-message-id', String(messageId));
	nextNode.id = `message-${messageId}`;
	currentNode.replaceWith(nextNode);
	initVoicePlayers(nextNode);
	renderMermaidInNode(nextNode);

	return nextNode;
};

const resolveEditedFlashTone = (message) => {
	if (message?.sender_type === 'ai') {
		return 'ai';
	}

	if (message?.sender_type === 'user' && Number(message?.sender_id || 0) === authUserId) {
		return 'mine';
	}

	return 'other';
};

const flashEditedMessage = (node, tone = 'other') => {
	if (!(node instanceof HTMLElement)) {
		return;
	}
	const bodyCandidates = node.querySelectorAll('[data-message-body]');
	const flashTarget = bodyCandidates.length > 0
		? bodyCandidates[bodyCandidates.length - 1]
		: node;
	const normalizedTone = tone === 'mine' || tone === 'ai' || tone === 'other'
		? tone
		: 'other';

	flashTarget.classList.remove(
		'nc-message-edited-flash',
		'nc-message-edited-flash--mine',
		'nc-message-edited-flash--ai',
		'nc-message-edited-flash--other',
	);
	void flashTarget.offsetWidth;
	flashTarget.classList.add('nc-message-edited-flash', `nc-message-edited-flash--${normalizedTone}`);
	window.setTimeout(() => {
		flashTarget.classList.remove(
			'nc-message-edited-flash',
			'nc-message-edited-flash--mine',
			'nc-message-edited-flash--ai',
			'nc-message-edited-flash--other',
		);
	}, 950);
};

const removeRenderedMessageById = (messageId) => {
	if (!chatFeed || !messageId) {
		return false;
	}

	const target = chatFeed.querySelector(`[data-message-id="${messageId}"]`);
	if (!(target instanceof HTMLElement)) {
		return false;
	}

	target.remove();
	return true;
};

const localizeExistingMessageTimes = () => {
	document.querySelectorAll('[data-message-time]').forEach((el) => {
		const iso = el.getAttribute('data-message-time') || '';
		const label = el.getAttribute('data-time-label') || '';
		const edited = el.getAttribute('data-time-edited') === '1';
		const editedAtIso = el.getAttribute('data-time-edited-at') || '';
		const editedTone = el.getAttribute('data-time-tone') || 'other';
		if (!iso) {
			return;
		}

		const date = new Date(iso);
		if (Number.isNaN(date.valueOf())) {
			return;
		}

		const timeText = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
		const editedAt = editedAtIso ? new Date(editedAtIso) : null;
		const editedTitle = editedAt && !Number.isNaN(editedAt.valueOf())
			? `Diedit ${editedAt.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}`
			: 'Pesan telah diedit';
		const editedMarkHtml = edited
			? ` <span class="nc-edited-mark nc-edited-mark--${escapeAttr(editedTone)}" title="${escapeAttr(editedTitle)}" aria-label="${escapeAttr(editedTitle)}">diedit</span>`
			: '';
		const safeLabel = escapeHtml(label);
		const showLabel = label && editedTone !== 'other';
		el.innerHTML = showLabel
			? `${safeLabel} • ${timeText}${editedMarkHtml}`
			: `${timeText}${editedMarkHtml}`;
	});
};

const initVoicePlayers = (root = document) => {
	const container = root instanceof Element || root instanceof Document ? root : document;
	const players = container.querySelectorAll('[data-voice-player]:not([data-voice-ready="1"])');

	players.forEach((player) => {
		const audio = player.querySelector('[data-voice-audio]');
		const toggle = player.querySelector('[data-voice-toggle]');
		const progress = player.querySelector('[data-voice-progress]');
		const current = player.querySelector('[data-voice-current]');
		const duration = player.querySelector('[data-voice-duration]');
		const fallbackNote = player.querySelector('[data-voice-fallback-note]');
		const bars = Array.from(player.querySelectorAll('[data-voice-bar]'));

		if (!(audio instanceof HTMLAudioElement) || !(toggle instanceof HTMLButtonElement) || !(progress instanceof HTMLInputElement)) {
			return;
		}

		if (!audio.src) {
			const source = audio.querySelector('source');
			const sourceSrc = source?.getAttribute('src');
			if (sourceSrc) {
				audio.src = sourceSrc;
			}
		}

		const enableNativeFallback = () => {
			audio.classList.remove('hidden');
			audio.controls = true;
			audio.preload = 'auto';
			progress.disabled = true;
			progress.classList.add('opacity-50', 'cursor-not-allowed');
			toggle.disabled = true;
			toggle.classList.add('opacity-60', 'cursor-not-allowed');
			if (fallbackNote instanceof HTMLElement) {
				fallbackNote.classList.remove('hidden');
			}
		};

		const updateWave = (ratio) => {
			if (!bars.length) {
				return;
			}

			const activeIndex = Math.round(ratio * (bars.length - 1));
			bars.forEach((bar, index) => {
				bar.classList.toggle('opacity-95', index <= activeIndex);
				bar.classList.toggle('opacity-45', index > activeIndex);
				if (index > activeIndex) {
					bar.classList.remove('animate-pulse');
				}
			});
		};

		const applyDurationWidth = (seconds) => {
			const safeSeconds = Number.isFinite(seconds) && seconds > 0 ? seconds : 0;
			const normalizedSeconds = Math.min(safeSeconds, 180);
			let computedWidth = 205;

			if (normalizedSeconds > 0 && normalizedSeconds <= 3) {
				computedWidth = 170 + (normalizedSeconds * 12);
			} else if (normalizedSeconds <= 10) {
				computedWidth = 206 + ((normalizedSeconds - 3) * 7.5);
			} else if (normalizedSeconds <= 30) {
				computedWidth = 258 + ((normalizedSeconds - 10) * 2.7);
			} else {
				computedWidth = 312 + (Math.log10(normalizedSeconds - 29) * 9);
			}

			const targetWidth = Math.round(Math.max(180, Math.min(332, computedWidth)));

			player.style.width = `${targetWidth}px`;
			player.style.maxWidth = '100%';
			player.style.transition = 'width 180ms ease';
		};

		const updateUi = () => {
			const total = Number.isFinite(audio.duration) ? audio.duration : 0;
			const now = Number.isFinite(audio.currentTime) ? audio.currentTime : 0;
			const ratio = total > 0 ? Math.min(now / total, 1) : 0;
			applyDurationWidth(total);
			progress.value = String(Math.round(ratio * 1000));
			updateWave(ratio);
			if (current) {
				current.textContent = formatAudioTime(now);
			}
			if (duration) {
				duration.textContent = formatAudioTime(total);
			}
		};

		const setPlaying = (isPlaying) => {
			toggle.innerHTML = isPlaying ? pauseIconSvg : playIconSvg;
			toggle.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
			player.classList.toggle('ring-1', isPlaying);
			player.classList.toggle('ring-emerald-300/60', isPlaying);
			bars.forEach((bar, index) => {
				if (!isPlaying) {
					bar.classList.remove('animate-pulse');
					bar.style.animationDelay = '';
					return;
				}

				if (bar.classList.contains('opacity-95')) {
					bar.classList.add('animate-pulse');
					bar.style.animationDelay = `${(index % 6) * 0.08}s`;
				}
			});
		};

		audio.addEventListener('loadedmetadata', updateUi);
		audio.addEventListener('durationchange', updateUi);
		audio.addEventListener('timeupdate', updateUi);
		audio.addEventListener('error', enableNativeFallback);
		audio.addEventListener('ended', () => {
			audio.currentTime = 0;
			setPlaying(false);
			updateUi();
		});

		progress.addEventListener('input', () => {
			const total = Number.isFinite(audio.duration) ? audio.duration : 0;
			if (total <= 0) {
				return;
			}
			const seek = (Number(progress.value) / 1000) * total;
			audio.currentTime = seek;
			updateUi();
		});

		toggle.addEventListener('click', async () => {
			if (audio.paused) {
				document.querySelectorAll('[data-voice-audio]').forEach((other) => {
					if (other !== audio && other instanceof HTMLAudioElement) {
						other.pause();
					}
				});

				try {
					await audio.play();
					setPlaying(true);
				} catch (_error) {
					setPlaying(false);
					enableNativeFallback();
				}
				return;
			}

			audio.pause();
			setPlaying(false);
		});

		audio.addEventListener('pause', () => setPlaying(false));
		audio.addEventListener('play', () => setPlaying(true));

		player.setAttribute('data-voice-ready', '1');
		applyDurationWidth(audio.duration);
		updateUi();
	});
};

localizeExistingMessageTimes();
initVoicePlayers(document);

// Render markdown in server-rendered AI bubbles
document.querySelectorAll('[data-ai-raw]').forEach((el) => {
	const raw = el.textContent.trim();
	if (!raw) return;
	el.innerHTML = renderMarkdown(escapeHtml(raw));
	renderMermaidInNode(el);

	// Expand parent wrapper for rich content
	if (el.innerHTML.includes('<table') || el.innerHTML.includes('mermaid')) {
		const wrapper = el.closest('[data-message-id]');
		if (wrapper) {
			wrapper.classList.remove('max-w-[80%]');
			wrapper.classList.add('max-w-[95%]');
		}
	}
});

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

latestKnownMessageId = Math.max(latestKnownMessageId, extractLatestMessageIdFromDom());

const markGroupRead = async (targetMessageId = 0) => {
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
	} catch (_error) {
		// Ignore read-state sync failures to keep chat usable in flaky networks.
	} finally {
		markReadInFlight = false;
		if (markReadQueued) {
			markReadQueued = false;
			markGroupRead();
		}
	}
};

const markGroupVisited = () => {
	if (!shouldShowFirstOpenSkip && hasVisitedGroupBefore && firstOpenPendingCount <= 0) {
		return;
	}

	shouldShowFirstOpenSkip = false;
	hasVisitedGroupBefore = true;
	firstOpenPendingCount = 0;
	markGroupRead();
};

const syncScrollBottomCount = () => {
	if (!(scrollBottomCount instanceof HTMLElement)) {
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

if (chatFeed) {
	requestAnimationFrame(() => {
		if (hasVisitedGroupBefore) {
			scrollChatToBottom('auto');
			window.setTimeout(() => {
				markGroupRead();
			}, 180);
			return;
		}

		chatFeed.scrollTo({ top: 0, behavior: 'auto' });
		syncScrollBottomCount();
	});
}

const focusComposerInput = (delay = 0) => {
	window.setTimeout(() => {
		const input = document.querySelector('[data-mention-input]');
		if (input instanceof HTMLTextAreaElement || input instanceof HTMLInputElement) {
			input.focus();
		}
	}, delay);
};

requestAnimationFrame(() => focusComposerInput(0));
focusComposerInput(220);
focusComposerInput(650);
document.addEventListener('visibilitychange', () => {
	if (!document.hidden) {
		focusComposerInput(80);
	}
});

let refreshScrollBottomButton = () => {};
if (chatFeed && scrollBottomBtn) {
	const checkScrollPos = () => {
		const distFromBottom = chatFeed.scrollHeight - chatFeed.scrollTop - chatFeed.clientHeight;
		if (distFromBottom < 64) {
			markGroupVisited();
			markGroupRead();
		}

		syncScrollBottomCount();

		if (distFromBottom > 200 || shouldShowFirstOpenSkip) {
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
		scrollChatToBottom('smooth');
		markGroupVisited();
		syncScrollBottomCount();
		checkScrollPos();
	});
	checkScrollPos();
}

const ensureMentionToastStack = () => {
	let stack = document.querySelector('[data-mention-toast-stack]');
	if (stack) {
		return stack;
	}

	stack = document.createElement('div');
	stack.setAttribute('data-mention-toast-stack', '1');
	stack.className = 'pointer-events-none fixed right-3 top-3 z-[70] flex w-[calc(100%-1.5rem)] max-w-sm flex-col gap-2';
	document.body.appendChild(stack);
	return stack;
};

const highlightMessageInFeed = (messageId) => {
	if (!messageId) {
		return false;
	}

	const target = document.getElementById(`message-${messageId}`)
		|| chatFeed?.querySelector(`[data-message-id="${messageId}"]`);
	if (!(target instanceof HTMLElement)) {
		return false;
	}

	target.scrollIntoView({ behavior: 'smooth', block: 'center' });
	target.classList.add('ring-2', 'ring-amber-300', 'ring-offset-2', 'ring-offset-[#F7F7F7]', 'rounded-2xl');
	window.setTimeout(() => {
		target.classList.remove('ring-2', 'ring-amber-300', 'ring-offset-2', 'ring-offset-[#F7F7F7]', 'rounded-2xl');
	}, 2600);

	return true;
};

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
	toast.className = 'pointer-events-auto rounded-2xl border border-amber-200 bg-white px-3 py-2 text-left shadow-lg shadow-slate-900/10 transition hover:bg-amber-50';
	toast.innerHTML = `
		<p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Tag Masuk</p>
		<p class="mt-0.5 text-sm font-semibold text-slate-800">${senderName} men-tag kamu di #${groupName}</p>
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
			new Notification('Normchat mention', {
				body: `${payload?.sender_name || 'Seseorang'} men-tag kamu di #${payload?.group_name || 'group'}`,
			});
		} catch (_error) {
			// Ignore notification API failures in restricted browsers.
		}
	}
};

const seenMentionKeys = new Set();

if (window.Echo && authUserId > 0) {
	window.Echo.private(`App.Models.User.${authUserId}`).listen('.mention.tagged', (payload) => {
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
	});
}

let groupChannel = null;

const typingIndicator = document.querySelector('[data-typing-indicator]');

if (window.Echo && groupId) {
	groupChannel = window.Echo.private(`group.${groupId}`);
	const activeTypingUsers = new Map();

	const renderTypingIndicator = () => {
		if (!(typingIndicator instanceof HTMLElement)) {
			return;
		}

		const labels = Array.from(activeTypingUsers.values());
		if (!labels.length) {
			typingIndicator.classList.add('hidden');
			typingIndicator.textContent = '';
			return;
		}

		typingIndicator.classList.remove('hidden');
		if (labels.length === 1) {
			typingIndicator.textContent = `${labels[0]} sedang mengetik...`;
			return;
		}

		typingIndicator.textContent = `${labels.length} orang sedang mengetik...`;
	};

	const updateTypingState = (key, label, isTyping) => {
		if (!key) {
			return;
		}

		if (isTyping) {
			activeTypingUsers.set(key, label || 'User');
		} else {
			activeTypingUsers.delete(key);
		}

		renderTypingIndicator();
	};

	groupChannel.listenForWhisper('typing', (payload) => {
		const senderId = Number(payload?.sender_id || 0);
		if (senderId === authUserId) {
			return;
		}

		updateTypingState(
			`user:${senderId || payload?.sender_name || 'unknown'}`,
			String(payload?.sender_name || 'User'),
			Boolean(payload?.is_typing)
		);
	});

	groupChannel.listen('.typing.status', (payload) => {
		const actorType = String(payload?.actor_type || '');
		if (actorType !== 'ai') {
			return;
		}

		updateTypingState('ai', String(payload?.sender_name || 'NormAI'), Boolean(payload?.is_typing));
	});

	groupChannel.listen('.message.sent', (payload) => {
		if (!chatFeed || !payload?.message) {
			return;
		}

		latestKnownMessageId = Math.max(latestKnownMessageId, Number(payload.message.id || 0));

		const distFromBottomBefore = chatFeed.scrollHeight - chatFeed.scrollTop - chatFeed.clientHeight;
		const senderId = Number(payload.message.sender_id || 0);
		const isMine = payload.message.sender_type === 'user' && senderId === authUserId;

		updateCreditsBadge(payload.message);

		if (
			payload.message.sender_type === 'user'
			&& Number(payload.message.sender_id || 0) === authUserId
			&& pendingMessagesByLocalId.size > 0
		) {
			const normalizedContent = String(payload.message.content || '').trim();
			const normalizedAttachmentName = String(payload.message.attachment_original_name || '');
			for (const [localId, pending] of pendingMessagesByLocalId.entries()) {
				if (!pending?.node?.isConnected) {
					pendingMessagesByLocalId.delete(localId);
					continue;
				}

				if (pending.messageType !== String(payload.message.message_type || '').toLowerCase()) {
					continue;
				}

				if (String(pending.content || '').trim() !== normalizedContent) {
					continue;
				}

				if (String(pending.attachmentOriginalName || '') !== normalizedAttachmentName) {
					continue;
				}

				try {
					const finalNode = buildMessageNode(payload.message);
					finalNode.setAttribute('data-message-id', String(payload.message.id));
					finalNode.id = `message-${payload.message.id}`;
					pending.node.replaceWith(finalNode);
					initVoicePlayers(finalNode);
					renderMermaidInNode(finalNode);
				} catch (_) {}

				if (typeof pending.blobUrl === 'string') {
					URL.revokeObjectURL(pending.blobUrl);
				}

				pendingMessagesByLocalId.delete(localId);
				markGroupVisited();
				scrollChatToBottom('smooth');
				markGroupRead(Number(payload.message.id || 0));
				return;
			}
		}

		const duplicate = chatFeed.querySelector(`[data-message-id="${payload.message.id}"]`);
		if (duplicate) {
			return;
		}

		const node = buildMessageNode(payload.message);
		node.setAttribute('data-message-id', String(payload.message.id));
		node.id = `message-${payload.message.id}`;
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
			scrollChatToBottom('smooth');
			markGroupRead(Number(payload.message.id || 0));
		} else {
			if (!isMine) {
				shouldShowFirstOpenSkip = true;
				firstOpenPendingCount += 1;
				syncScrollBottomCount();
			}
			refreshScrollBottomButton();
		}
	});

	groupChannel.listen('.message.updated', (payload) => {
		if (!chatFeed || !payload?.message) {
			return;
		}

		const messageId = Number(payload.message.id || 0);
		if (!Number.isFinite(messageId) || messageId <= 0) {
			return;
		}

		const updatedNode = replaceRenderedMessageByPayload(payload.message);
		if (payload.message.is_edited) {
			const flashTone = resolveEditedFlashTone(payload.message);
			flashEditedMessage(updatedNode, flashTone);
		}
		latestKnownMessageId = Math.max(latestKnownMessageId, messageId);
		refreshScrollBottomButton();
	});

	groupChannel.listen('.message.deleted', (payload) => {
		if (!chatFeed) {
			return;
		}

		const messageId = Number(payload?.message_id || 0);
		if (!Number.isFinite(messageId) || messageId <= 0) {
			return;
		}

		removeRenderedMessageById(messageId);
		latestKnownMessageId = extractLatestMessageIdFromDom();
		refreshScrollBottomButton();
	});
}

const chatForm = document.querySelector('[data-chat-form]');
const chatMessagesBaseUrl = String(chatForm?.getAttribute('action') || '');
const buildMessageApiUrl = (messageId) => {
	if (!chatMessagesBaseUrl || !messageId) {
		return '';
	}

	return `${chatMessagesBaseUrl}/${messageId}`;
};
const attachmentInput = chatForm?.querySelector('[data-chat-attachment]');
const cameraInput = chatForm?.querySelector('[data-chat-camera-input]');
const attachmentLabel = chatForm?.querySelector('[data-attachment-label]');
const cameraButton = chatForm?.querySelector('[data-open-camera]');
const voiceButton = chatForm?.querySelector('[data-pick-voice]');
const attachMenuButton = chatForm?.querySelector('[data-open-attach-menu]');
const attachMenu = chatForm?.querySelector('[data-attach-menu]');
const recordVoiceButton = chatForm?.querySelector('[data-record-voice]');
const voiceRecorderPanel = chatForm?.querySelector('[data-voice-recorder-panel]');
const voiceCancelButton = chatForm?.querySelector('[data-voice-cancel]');
const voiceStopSendButton = chatForm?.querySelector('[data-voice-stop-send]');
const voiceTimer = chatForm?.querySelector('[data-voice-timer]');
const composerMain = chatForm?.querySelector('[data-composer-main]');
const emojiButton = chatForm?.querySelector('[data-open-emoji]');
const emojiPanel = chatForm?.querySelector('[data-emoji-panel]');
const recordingIndicator = chatForm?.querySelector('[data-recording-indicator]');
const mentionInput = chatForm?.querySelector('[data-mention-input]');
const sendButton = chatForm?.querySelector('[data-chat-send]');
const replyToInput = chatForm?.querySelector('[data-reply-to-input]');
const replyPreview = chatForm?.querySelector('[data-reply-preview]');
const replyPreviewSender = chatForm?.querySelector('[data-reply-preview-sender]');
const replyPreviewContent = chatForm?.querySelector('[data-reply-preview-content]');
const replyClearButton = chatForm?.querySelector('[data-reply-clear]');
const isDesktopKeyboard = (() => {
	const hasCoarsePointer = window.matchMedia?.('(pointer: coarse)').matches;
	const hasTouchPoints = (navigator.maxTouchPoints || 0) > 0;
	return !hasCoarsePointer && !hasTouchPoints;
})();

const insertAtCursor = (input, text) => {
	const start = input.selectionStart ?? input.value.length;
	const end = input.selectionEnd ?? input.value.length;
	const next = `${input.value.slice(0, start)}${text}${input.value.slice(end)}`;
	input.value = next;
	const cursor = start + text.length;
	input.setSelectionRange(cursor, cursor);
	input.focus();
};

const detectAttachmentMessageType = (file) => {
	if (!(file instanceof File)) {
		return 'text';
	}

	const mime = String(file.type || '').toLowerCase();
	const extension = String(file.name || '').toLowerCase().split('.').pop() || '';

	if (mime.startsWith('image/')) {
		return 'image';
	}

	if (mime.startsWith('audio/')) {
		return 'voice';
	}

	if (extension === 'webm' && mime.startsWith('video/')) {
		return 'voice';
	}

	if (['mp3', 'wav', 'ogg', 'aac', 'm4a'].includes(extension)) {
		return 'voice';
	}

	return 'file';
};

if (chatForm && attachmentInput instanceof HTMLInputElement) {
	const syncAttachmentFromInput = (sourceInput) => {
		const file = sourceInput?.files?.[0];
		if (!file) {
			return;
		}

		const transfer = new DataTransfer();
		transfer.items.add(file);
		attachmentInput.files = transfer.files;
	};

	let attachmentPreviewUrl = null;
	const ensureAttachmentPreviewEl = () => {
		let el = chatForm.querySelector('[data-attachment-preview]');
		if (el) return el;
		el = document.createElement('div');
		el.setAttribute('data-attachment-preview', '1');
		el.className = 'mb-2 hidden items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-2 py-2';
		el.innerHTML = `
			<img data-attachment-preview-img class="h-14 w-14 rounded-lg object-cover" alt="preview" />
			<div class="min-w-0 flex-1">
				<p class="truncate text-xs font-semibold text-blue-700" data-attachment-preview-name></p>
				<p class="text-[10px] text-blue-500">Siap dikirim</p>
			</div>
			<button type="button" class="rounded-md p-1 text-blue-500 hover:bg-blue-100" data-attachment-preview-clear aria-label="Hapus lampiran">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
			</button>
		`;
		const composer = chatForm.querySelector('[data-composer-main]');
		if (composer && composer.parentNode) {
			composer.parentNode.insertBefore(el, composer);
		} else {
			chatForm.appendChild(el);
		}
		el.querySelector('[data-attachment-preview-clear]')?.addEventListener('click', () => {
			attachmentInput.value = '';
			if (cameraInput instanceof HTMLInputElement) cameraInput.value = '';
			refreshAttachmentLabel();
		});
		return el;
	};

	const refreshAttachmentLabel = () => {
		const file = attachmentInput.files?.[0];
		const previewEl = ensureAttachmentPreviewEl();
		const previewImg = previewEl.querySelector('[data-attachment-preview-img]');
		const previewName = previewEl.querySelector('[data-attachment-preview-name]');

		if (attachmentPreviewUrl) {
			URL.revokeObjectURL(attachmentPreviewUrl);
			attachmentPreviewUrl = null;
		}

		if (!file) {
			attachmentLabel?.classList.add('hidden');
			previewEl.classList.add('hidden');
			previewEl.classList.remove('flex');
			return;
		}

		const isImage = String(file.type || '').toLowerCase().startsWith('image/');
		if (isImage && previewImg instanceof HTMLImageElement) {
			attachmentPreviewUrl = URL.createObjectURL(file);
			previewImg.src = attachmentPreviewUrl;
			previewImg.classList.remove('hidden');
			if (previewName) previewName.textContent = file.name;
			previewEl.classList.remove('hidden');
			previewEl.classList.add('flex');
			attachmentLabel?.classList.add('hidden');
			return;
		}

		// non-image: fallback ke label teks lama
		previewEl.classList.add('hidden');
		previewEl.classList.remove('flex');
		if (attachmentLabel) {
			attachmentLabel.textContent = file.name;
			attachmentLabel.classList.remove('hidden');
		}
	};

	let editingMessageId = 0;
	const clearEditingState = (clearInput = false) => {
		editingMessageId = 0;
		if (clearInput && (mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
			mentionInput.value = '';
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
		}
	};

	const clearReplyTarget = () => {
		if (replyToInput instanceof HTMLInputElement) {
			replyToInput.value = '';
		}

		if (replyPreview instanceof HTMLElement) {
			replyPreview.classList.add('hidden');
			replyPreview.classList.remove('flex');
		}

		if (editingMessageId > 0) {
			clearEditingState();
		}
	};

	const setReplyTarget = (messageNode) => {
		if (!(messageNode instanceof HTMLElement) || !(replyToInput instanceof HTMLInputElement)) {
			return;
		}

		const messageId = String(messageNode.getAttribute('data-message-id') || '').trim();
		if (!messageId) {
			return;
		}

		const sender = String(messageNode.dataset.messageSenderName || 'User');
		const rawContent = String(messageNode.dataset.messageContent || '').trim();
		const type = String(messageNode.dataset.messageType || 'text').toLowerCase();
		const fallback = type === 'image' ? '[Gambar]' : (type === 'voice' ? '[Voice note]' : '[Lampiran]');
		const content = rawContent !== '' ? rawContent : fallback;

		replyToInput.value = messageId;
		clearEditingState();
		if (replyPreviewSender instanceof HTMLElement) {
			replyPreviewSender.textContent = `Membalas ${sender}`;
		}
		if (replyPreviewContent instanceof HTMLElement) {
			replyPreviewContent.textContent = content;
		}
		if (replyPreview instanceof HTMLElement) {
			replyPreview.classList.remove('hidden');
			replyPreview.classList.add('flex');
		}

		mentionInput?.focus();
	};

	replyClearButton?.addEventListener('click', clearReplyTarget);

	/* ── setReplyTarget with optional partial text ── */
	const setReplyTargetWithText = (messageNode, selectedText) => {
		if (!(messageNode instanceof HTMLElement) || !(replyToInput instanceof HTMLInputElement)) return;
		const messageId = String(messageNode.getAttribute('data-message-id') || '').trim();
		if (!messageId) return;

		const sender = String(messageNode.dataset.messageSenderName || 'User');
		const rawContent = String(messageNode.dataset.messageContent || '').trim();
		const type = String(messageNode.dataset.messageType || 'text').toLowerCase();
		const fallback = type === 'image' ? '[Gambar]' : (type === 'voice' ? '[Voice note]' : '[Lampiran]');
		const content = selectedText || (rawContent !== '' ? rawContent : fallback);

		replyToInput.value = messageId;
		clearEditingState();
		if (replyPreviewSender instanceof HTMLElement) {
			replyPreviewSender.textContent = `Membalas ${sender}`;
		}
		if (replyPreviewContent instanceof HTMLElement) {
			replyPreviewContent.textContent = content;
		}
		if (replyPreview instanceof HTMLElement) {
			replyPreview.classList.remove('hidden');
			replyPreview.classList.add('flex');
		}
		mentionInput?.focus();
	};

	const selectedMessageIds = new Set();
	const showActionToast = (message) => {
		if (!message) {
			return;
		}

		const toast = document.createElement('div');
		toast.className = 'fixed left-1/2 bottom-24 z-[75] -translate-x-1/2 rounded-full bg-slate-900/95 px-3 py-1.5 text-xs font-medium text-white shadow-lg';
		toast.textContent = message;
		document.body.appendChild(toast);
		window.setTimeout(() => {
			if (toast.isConnected) {
				toast.remove();
			}
		}, 1800);
	};

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
			showActionToast('Pilihan pesan dibatalkan');
			return;
		}

		selectedMessageIds.add(messageId);
		messageNode.classList.add('nc-message-selected');
		showActionToast(`${selectedMessageIds.size} pesan dipilih`);
	};

	/* ── Message popup (long-press) ── */
	let activePopup = null;
	const dismissPopup = () => {
		if (activePopup) {
			activePopup.remove();
			activePopup = null;
		}
	};

	const showSelectTextPopup = (messageNode, sender, messageText) => {
		dismissPopup();
		const selectOverlay = document.createElement('div');
		selectOverlay.className = 'nc-popup-overlay';
		selectOverlay.innerHTML = `
			<div class="nc-popup-card" onclick="event.stopPropagation()">
				<div class="nc-popup-header">
					<span class="nc-popup-sender">${escapeHtml(sender)}</span>
					<button type="button" class="nc-popup-close" data-popup-close>&times;</button>
				</div>
				<div class="nc-popup-body" style="user-select:text;-webkit-user-select:text;">${escapeHtml(messageText)}</div>
				<div class="nc-popup-actions">
					<button type="button" class="nc-popup-btn nc-popup-btn-select" data-popup-copy-sel>Salin teks terpilih</button>
					<button type="button" class="nc-popup-btn" data-popup-reply-sel>Balas teks terpilih</button>
				</div>
			</div>
		`;
		document.body.appendChild(selectOverlay);
		activePopup = selectOverlay;

		const btnCopySel = selectOverlay.querySelector('[data-popup-copy-sel]');
		const btnReplySel = selectOverlay.querySelector('[data-popup-reply-sel]');

		const onSelChange = () => {
			const sel = window.getSelection();
			const text = sel ? sel.toString().trim() : '';
			if (btnCopySel) btnCopySel.disabled = !text;
			if (btnReplySel) btnReplySel.disabled = !text;
		};

		document.addEventListener('selectionchange', onSelChange);
		onSelChange();

		const cleanup = () => {
			document.removeEventListener('selectionchange', onSelChange);
			dismissPopup();
		};

		selectOverlay.addEventListener('click', (e) => {
			if (e.target === selectOverlay) {
				cleanup();
			}
		});
		selectOverlay.querySelector('[data-popup-close]')?.addEventListener('click', cleanup);
		btnCopySel?.addEventListener('click', () => {
			const sel = window.getSelection();
			const text = sel ? sel.toString().trim() : '';
			if (text && navigator.clipboard) {
				navigator.clipboard.writeText(text).then(() => {
					showActionToast('Teks terpilih disalin');
				}).catch(() => {});
			}
			cleanup();
		});
		btnReplySel?.addEventListener('click', () => {
			const sel = window.getSelection();
			const text = sel ? sel.toString().trim() : '';
			cleanup();
			if (text) {
				setReplyTargetWithText(messageNode, text);
			}
		});
	};

	const showMessagePopup = (messageNode) => {
		dismissPopup();
		const rawContent = String(messageNode.dataset.messageContent || '').trim();
		const type = String(messageNode.dataset.messageType || 'text').toLowerCase();
		const fallback = type === 'image' ? '[Gambar]' : (type === 'voice' ? '[Voice note]' : '[Lampiran]');
		const messageText = rawContent !== '' ? rawContent : fallback;
		const sender = String(messageNode.dataset.messageSenderName || 'User');
		const isMine = messageNode.closest('.flex.justify-end') !== null;
		const canEdit = isMine && type === 'text' && rawContent !== '';

		const overlay = document.createElement('div');
		overlay.className = 'nc-popup-overlay';
		overlay.innerHTML = `
			<div class="nc-popup-card" onclick="event.stopPropagation()">
				<div class="nc-popup-preview">${escapeHtml(messageText.length > 200 ? messageText.slice(0, 200) + '...' : messageText)}</div>
				<div class="nc-popup-menu">
					<button type="button" class="nc-popup-menu-item" data-popup-reply>
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 0 1 0 10H9m-6-10 4-4m-4 4 4 4"/></svg>
						Quote & Balas
					</button>
					<button type="button" class="nc-popup-menu-item" data-popup-copy>
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
						Salin ke perangkat
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
						Sematkan
					</button>
					<button type="button" class="nc-popup-menu-item" data-popup-forward>
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 10H7a5 5 0 0 0 0 10h4m10-10-4-4m4 4-4 4"/></svg>
						Teruskan
					</button>
					${canEdit ? `<button type="button" class="nc-popup-menu-item" data-popup-edit>
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.93Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5"/></svg>
						Edit cepat
					</button>` : ''}
					${isMine ? `<button type="button" class="nc-popup-menu-item nc-popup-menu-danger" data-popup-delete>
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
					showActionToast('Pesan disalin ke perangkat');
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
					showActionToast('Tautan pesan disematkan ke clipboard');
				}).catch(() => {
					showActionToast('Gagal menyalin tautan pesan');
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
							showActionToast('Pesan disalin untuk diteruskan');
						}).catch(() => {});
					}
				});
			} else if (navigator.clipboard) {
				navigator.clipboard.writeText(forwardText).then(() => {
					showActionToast('Pesan disalin untuk diteruskan');
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

			editingMessageId = messageId;
			mentionInput.value = rawContent;
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
			mentionInput.focus();
			try {
				const endPos = mentionInput.value.length;
				mentionInput.setSelectionRange(endPos, endPos);
			} catch (_error) {
				// Ignore unsupported selection APIs.
			}
			showActionToast('Mode edit aktif. Tekan kirim untuk menyimpan');
		});
		overlay.querySelector('[data-popup-delete]')?.addEventListener('click', async () => {
			dismissPopup();

			const messageId = Number(messageNode.getAttribute('data-message-id') || 0);
			if (!Number.isFinite(messageId) || messageId <= 0) {
				return;
			}

			if (!window.confirm('Hapus pesan ini?')) {
				return;
			}

			const deleteUrl = buildMessageApiUrl(messageId);
			if (!deleteUrl) {
				showActionToast('URL hapus pesan tidak tersedia');
				return;
			}

			try {
				await chatApiRequest(deleteUrl, { method: 'DELETE' });
				removeRenderedMessageById(messageId);
				selectedMessageIds.delete(String(messageId));

				if (replyToInput instanceof HTMLInputElement && Number(replyToInput.value || 0) === messageId) {
					clearReplyTarget();
				}

				if (editingMessageId === messageId) {
					clearEditingState(true);
				}

				latestKnownMessageId = extractLatestMessageIdFromDom();
				refreshScrollBottomButton();
				showActionToast('Pesan dihapus');
			} catch (_error) {
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
			clearLongPress();
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

			if (event.target.closest('a, button, input, textarea, [data-voice-toggle], [data-voice-progress]')) {
				activeNode = null;
				return;
			}

			activeNode = node;
			startX = event.clientX;
			startY = event.clientY;
			node.style.transition = '';
			clearLongPress();
			longPressTimer = window.setTimeout(() => {
				if (activeNode) {
					try { navigator.vibrate?.(15); } catch (_) {}
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
				if (event.cancelable) event.preventDefault();
			} else if (deltaX < -6) {
				// block left swipe — just cancel
				clearActive();
				return;
			}

			if (deltaX > SWIPE_THRESHOLD && deltaX > Math.abs(deltaY) + 8) {
				try { navigator.vibrate?.(10); } catch (_) {}
				setReplyTarget(activeNode);
				resetTransform(activeNode);
				activeNode = null;
				clearLongPress();
			}
		}, { passive: false });

		chatFeed.addEventListener('pointerup', clearActive);
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

	let typingStopTimer = null;
	let lastTypingWhisperAt = 0;
	const whisperTyping = (isTyping) => {
		if (!groupChannel || typeof groupChannel.whisper !== 'function' || !authUserId) {
			return;
		}

		const now = Date.now();
		if (isTyping && now - lastTypingWhisperAt < 750) {
			return;
		}

		lastTypingWhisperAt = now;
		groupChannel.whisper('typing', {
			sender_id: authUserId,
			sender_name: (document.querySelector('meta[name="auth-user-name"]')?.getAttribute('content') || 'User'),
			is_typing: isTyping,
		});
	};

	cameraButton?.addEventListener('click', () => {
		if (!(cameraInput instanceof HTMLInputElement)) {
			attachmentInput.accept = 'image/*';
			attachmentInput.setAttribute('capture', 'environment');
			attachmentInput.click();
			return;
		}

		cameraInput.click();
	});

	if (cameraInput instanceof HTMLInputElement) {
		cameraInput.addEventListener('change', () => {
			syncAttachmentFromInput(cameraInput);
			refreshAttachmentLabel();
		});
	}

	voiceButton?.addEventListener('click', () => {
		attachmentInput.accept = 'image/*,audio/*';
		attachmentInput.removeAttribute('capture');
		attachmentInput.click();
	});

	// Attachment menu (Telegram-style sub-menu)
	attachMenuButton?.addEventListener('click', (event) => {
		event.preventDefault();
		emojiPanel?.classList.add('hidden');
		attachMenu?.classList.toggle('hidden');
	});

	attachMenu?.querySelector('[data-attach-photo]')?.addEventListener('click', () => {
		attachMenu.classList.add('hidden');
		attachmentInput.accept = 'image/*';
		attachmentInput.removeAttribute('capture');
		attachmentInput.click();
	});

	attachMenu?.querySelector('[data-attach-camera]')?.addEventListener('click', () => {
		attachMenu.classList.add('hidden');
		if (cameraInput instanceof HTMLInputElement) {
			cameraInput.click();
		} else {
			attachmentInput.accept = 'image/*';
			attachmentInput.setAttribute('capture', 'environment');
			attachmentInput.click();
		}
	});

	attachMenu?.querySelector('[data-attach-file]')?.addEventListener('click', () => {
		attachMenu.classList.add('hidden');
		attachmentInput.accept = '*/*';
		attachmentInput.removeAttribute('capture');
		attachmentInput.click();
	});

	// Close attach menu on outside click
	document.addEventListener('click', (e) => {
		if (attachMenu && !attachMenu.classList.contains('hidden')) {
			if (!attachMenu.contains(e.target) && e.target !== attachMenuButton && !attachMenuButton?.contains(e.target)) {
				attachMenu.classList.add('hidden');
			}
		}
	});

	emojiButton?.addEventListener('click', (event) => {
		event.preventDefault();
		attachMenu?.classList.add('hidden');
		emojiPanel?.classList.toggle('hidden');
	});

	emojiPanel?.addEventListener('click', (event) => {
		const button = event.target.closest('[data-emoji-item]');
		if (!button || !(mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
			return;
		}

		const emoji = button.getAttribute('data-emoji-item') || '';
		if (!emoji) {
			return;
		}

		insertAtCursor(mentionInput, emoji);
		emojiPanel.classList.add('hidden');
	});

	document.addEventListener('click', (event) => {
		if (!emojiPanel || !emojiButton) {
			return;
		}

		if (emojiPanel.contains(event.target) || emojiButton.contains(event.target)) {
			return;
		}

		emojiPanel.classList.add('hidden');
	});

	const supportsRecorder = typeof window.MediaRecorder !== 'undefined' && navigator.mediaDevices?.getUserMedia;
	let recorder = null;
	let activeStream = null;
	let recordChunks = [];
	let timerInterval = null;
	let startedAt = 0;
	let sendOnStop = false;

	const updateTimer = () => {
		if (!voiceTimer || !startedAt) {
			return;
		}

		const sec = Math.floor((Date.now() - startedAt) / 1000);
		const minPart = Math.floor(sec / 60);
		const secPart = String(sec % 60).padStart(2, '0');
		voiceTimer.textContent = `${minPart}:${secPart}`;
	};

	const resetRecordingUi = () => {
		recordVoiceButton?.classList.remove('bg-rose-500');
		recordVoiceButton?.classList.add('bg-emerald-500');
		recordingIndicator?.classList.add('hidden');
		voiceRecorderPanel?.classList.add('hidden');
		voiceRecorderPanel?.classList.remove('flex');
		composerMain?.classList.remove('hidden');
		if (voiceTimer) {
			voiceTimer.textContent = '0:00';
		}
		if (timerInterval) {
			window.clearInterval(timerInterval);
			timerInterval = null;
		}
		startedAt = 0;
	};

	const showRecordingUi = () => {
		recordVoiceButton?.classList.remove('bg-emerald-500');
		recordVoiceButton?.classList.add('bg-rose-500');
		recordingIndicator?.classList.remove('hidden');
		composerMain?.classList.add('hidden');
		voiceRecorderPanel?.classList.remove('hidden');
		voiceRecorderPanel?.classList.add('flex');
		startedAt = Date.now();
		updateTimer();
		timerInterval = window.setInterval(updateTimer, 500);
	};

	const applyRecordedBlob = (blob) => {
		const file = new File([blob], `voice-${Date.now()}.webm`, { type: 'audio/webm' });
		const transfer = new DataTransfer();
		transfer.items.add(file);
		attachmentInput.files = transfer.files;
		refreshAttachmentLabel();
		chatForm.requestSubmit();
	};

	const stopRecording = (shouldSend) => {
		sendOnStop = shouldSend;
		if (recorder && recorder.state === 'recording') {
			recorder.stop();
			return;
		}

		resetRecordingUi();
	};

	recordVoiceButton?.addEventListener('click', async () => {
		if (!supportsRecorder) {
			attachmentInput.accept = 'audio/*';
			attachmentInput.removeAttribute('capture');
			attachmentInput.click();
			return;
		}

		try {
			activeStream = await navigator.mediaDevices.getUserMedia({ audio: true });
			recordChunks = [];
			recorder = new MediaRecorder(activeStream);

			recorder.ondataavailable = (event) => {
				if (event.data && event.data.size > 0) {
					recordChunks.push(event.data);
				}
			};

			recorder.onstop = () => {
				const blob = new Blob(recordChunks, { type: recorder.mimeType || 'audio/webm' });
				activeStream?.getTracks().forEach((track) => track.stop());
				activeStream = null;
				resetRecordingUi();
				if (sendOnStop && blob.size > 0) {
					applyRecordedBlob(blob);
				}
				sendOnStop = false;
			};

			recorder.start();
			showRecordingUi();
		} catch (_error) {
			resetRecordingUi();
			attachmentInput.accept = 'audio/*';
			attachmentInput.removeAttribute('capture');
			attachmentInput.click();
		}
	});

	voiceCancelButton?.addEventListener('click', () => {
		stopRecording(false);
	});

	voiceStopSendButton?.addEventListener('click', () => {
		stopRecording(true);
	});

	attachmentInput.addEventListener('change', refreshAttachmentLabel);

	if (mentionInput instanceof HTMLTextAreaElement) {
		const resizeComposerInput = () => {
			mentionInput.style.height = 'auto';
			mentionInput.style.height = `${Math.min(mentionInput.scrollHeight, 96)}px`;
		};

		mentionInput.addEventListener('keydown', (event) => {
			if (event.key !== 'Escape' || editingMessageId <= 0) {
				return;
			}

			event.preventDefault();
			clearEditingState(true);
			showActionToast('Mode edit dibatalkan');
		});

		mentionInput.addEventListener('input', resizeComposerInput);
		mentionInput.addEventListener('input', () => {
			const hasText = mentionInput.value.trim().length > 0;
			if (!hasText) {
				if (typingStopTimer) {
					window.clearTimeout(typingStopTimer);
					typingStopTimer = null;
				}
				whisperTyping(false);
				return;
			}

			whisperTyping(true);
			if (typingStopTimer) {
				window.clearTimeout(typingStopTimer);
			}
			typingStopTimer = window.setTimeout(() => {
				whisperTyping(false);
			}, 1500);
		});
		resizeComposerInput();
	}

	chatForm.addEventListener('submit', () => {
		emojiPanel?.classList.add('hidden');
		recordingIndicator?.classList.add('hidden');
		if (typingStopTimer) {
			window.clearTimeout(typingStopTimer);
			typingStopTimer = null;
		}
		whisperTyping(false);
		if (attachmentLabel) {
			attachmentLabel.classList.add('hidden');
		}
	});

	const setSendingUi = (isSending) => {
		if (!(sendButton instanceof HTMLButtonElement)) {
			return;
		}

		sendButton.disabled = isSending;
		sendButton.classList.toggle('opacity-70', isSending);
		sendButton.classList.toggle('cursor-wait', isSending);
	};

	const appendPendingMessageNode = (content, file) => {
		if (!chatFeed) {
			return null;
		}

		const localId = `local-${Date.now()}-${++pendingLocalMessageCounter}`;
		const blobUrl = file instanceof File ? URL.createObjectURL(file) : null;
		const messageType = detectAttachmentMessageType(file);
		const pendingMessage = {
			id: localId,
			message_type: messageType,
			sender_type: 'user',
			sender_id: authUserId,
			sender_name: 'You',
			content: content || '',
			attachment_url: blobUrl,
			attachment_mime: file instanceof File ? file.type : null,
			attachment_original_name: file instanceof File ? file.name : null,
			created_at: new Date().toISOString(),
			is_pending: true,
		};

		const node = buildMessageNode(pendingMessage);
		node.setAttribute('data-local-message-id', localId);
		node.setAttribute('data-pending-message', '1');
		if (typingIndicator && typingIndicator.parentNode === chatFeed) {
			chatFeed.insertBefore(node, typingIndicator);
		} else {
			chatFeed.appendChild(node);
		}
		initVoicePlayers(node);
		scrollChatToBottom('smooth');

		pendingMessagesByLocalId.set(localId, {
			node,
			blobUrl,
			content: content || '',
			messageType,
			attachmentOriginalName: file instanceof File ? file.name : '',
		});
		return localId;
	};

	const markPendingMessageFailed = (localId) => {
		const pending = pendingMessagesByLocalId.get(localId);
		if (!pending?.node) {
			return;
		}

		pending.node.setAttribute('data-pending-message', 'failed');
		const timeEl = pending.node.querySelector('p:last-child');
		if (timeEl instanceof HTMLElement) {
			timeEl.textContent = 'You • Gagal terkirim';
			timeEl.classList.remove('text-slate-400');
			timeEl.classList.add('text-rose-500');
		}

		pendingMessagesByLocalId.delete(localId);
	};

	const replacePendingMessage = (localId, serverMessage) => {
		const pending = pendingMessagesByLocalId.get(localId);
		if (!pending?.node || !pending.node.isConnected) {
			pendingMessagesByLocalId.delete(localId);
			return;
		}

		const finalNode = buildMessageNode(serverMessage);
		finalNode.setAttribute('data-message-id', String(serverMessage.id));
		finalNode.id = `message-${serverMessage.id}`;
		pending.node.replaceWith(finalNode);
		initVoicePlayers(finalNode);
		renderMermaidInNode(finalNode);

		if (typeof pending.blobUrl === 'string') {
			URL.revokeObjectURL(pending.blobUrl);
		}

		pendingMessagesByLocalId.delete(localId);
		latestKnownMessageId = Math.max(latestKnownMessageId, Number(serverMessage?.id || 0));
		scrollChatToBottom('smooth');
		markGroupRead(Number(serverMessage?.id || 0));
	};

	chatForm.addEventListener('submit', async (event) => {
		event.preventDefault();

		if (chatSubmitInFlight) {
			return;
		}

		const actionUrl = chatForm.getAttribute('action') || '';
		if (!actionUrl) {
			return;
		}

		const contentText = mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement
			? mentionInput.value.trim()
			: '';
		const selectedFile = attachmentInput.files?.[0] instanceof File ? attachmentInput.files?.[0] : null;

		if (editingMessageId > 0) {
			if (!contentText) {
				showActionToast('Isi pesan tidak boleh kosong saat edit');
				return;
			}

			if (selectedFile) {
				showActionToast('Edit teks tidak menerima lampiran baru');
				return;
			}

			const updateUrl = buildMessageApiUrl(editingMessageId);
			if (!updateUrl) {
				showActionToast('URL edit pesan tidak tersedia');
				return;
			}

			setSendingUi(true);
			chatSubmitInFlight = true;
			try {
				const result = await chatApiRequest(updateUrl, {
					method: 'PATCH',
					body: {
						content: contentText,
					},
				});

				if (result?.message) {
					const updatedNode = replaceRenderedMessageByPayload(result.message);
					if (result.message.is_edited) {
						const flashTone = resolveEditedFlashTone(result.message);
						flashEditedMessage(updatedNode, flashTone);
					}
				}

				clearEditingState(true);
				showActionToast('Pesan berhasil diperbarui');
			} catch (_error) {
				showActionToast('Gagal memperbarui pesan');
			} finally {
				setSendingUi(false);
				chatSubmitInFlight = false;
			}

			return;
		}

		if (!contentText && !selectedFile) {
			return;
		}

		markGroupVisited();

		const formData = new FormData(chatForm);
		const localId = appendPendingMessageNode(contentText, selectedFile);

		if (mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement) {
			mentionInput.value = '';
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
		}
		attachmentInput.value = '';
		if (cameraInput instanceof HTMLInputElement) {
			cameraInput.value = '';
		}
		attachmentLabel?.classList.add('hidden');
		emojiPanel?.classList.add('hidden');
		clearReplyTarget();
		whisperTyping(false);

		setSendingUi(true);
		chatSubmitInFlight = true;
		try {
			const response = await fetch(actionUrl, {
				method: 'POST',
				body: formData,
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				credentials: 'same-origin',
			});

			if (!response.ok) {
				throw new Error('send_failed');
			}

			const result = await response.json();
			if (localId && result?.message) {
				replacePendingMessage(localId, result.message);
			}
		} catch (_error) {
			if (localId) {
				markPendingMessageFailed(localId);
			}
		} finally {
			setSendingUi(false);
			chatSubmitInFlight = false;
			whisperTyping(false);
		}
	});

	window.addEventListener('beforeunload', () => {
		whisperTyping(false);
	});
}

const mentionMenu = chatForm?.querySelector('[data-mention-menu]');

if ((mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement) && mentionMenu instanceof HTMLDivElement) {
	let activeSuggestions = [];
	let allSuggestions = [];
	try {
		const parsed = JSON.parse(mentionMenu.dataset.mentionItems || '[]');
		allSuggestions = Array.isArray(parsed) ? parsed : [];
	} catch (_error) {
		allSuggestions = [];
	}

	const hideMentionMenu = () => {
		mentionMenu.innerHTML = '';
		mentionMenu.classList.add('hidden');
		activeSuggestions = [];
	};

	const getMentionQuery = () => {
		const value = mentionInput.value;
		const caret = mentionInput.selectionStart ?? value.length;
		const beforeCaret = value.slice(0, caret);
		const atIndex = beforeCaret.lastIndexOf('@');
		if (atIndex < 0) {
			return null;
		}

		const token = beforeCaret.slice(atIndex + 1);
		if (/\s/.test(token)) {
			return null;
		}

		return {
			query: token.toLowerCase(),
			start: atIndex,
			end: caret,
		};
	};

	const renderMentionMenu = (items) => {
		activeSuggestions = items;
		if (!items.length) {
			hideMentionMenu();
			return;
		}

		mentionMenu.innerHTML = items
			.map((item, index) => {
				const tagTypeClass = item.type === 'ai' ? 'text-emerald-600' : 'text-blue-600';
				return `<button type="button" data-mention-index="${index}" class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-slate-50"><span class="font-medium text-slate-700">${escapeHtml(item.label)}</span><span class="text-xs ${tagTypeClass}">${escapeHtml(item.insert)}</span></button>`;
			})
			.join('');

		mentionMenu.classList.remove('hidden');
	};

	const applyMention = (item) => {
		const mention = getMentionQuery();
		if (!mention) {
			return;
		}

		const value = mentionInput.value;
		const next = `${value.slice(0, mention.start)}${item.insert} ${value.slice(mention.end)}`;
		mentionInput.value = next;
		mentionInput.focus();
		hideMentionMenu();
	};

	if (mentionInput instanceof HTMLTextAreaElement) {
		mentionInput.addEventListener('keydown', (event) => {
			if (event.key !== 'Enter') {
				return;
			}

			if (event.shiftKey) {
				return;
			}

			if (!isDesktopKeyboard) {
				return;
			}

			event.preventDefault();
			chatForm?.requestSubmit();
		});
	}

	mentionInput.addEventListener('input', () => {
		const mention = getMentionQuery();
		if (!mention) {
			hideMentionMenu();
			return;
		}

		const filtered = allSuggestions.filter((item) => {
			if (!mention.query) {
				return true;
			}
			return String(item.label || '').toLowerCase().includes(mention.query)
				|| String(item.insert || '').toLowerCase().includes(mention.query);
		});

		renderMentionMenu(filtered.slice(0, 8));
	});

	mentionMenu.addEventListener('click', (event) => {
		const button = event.target.closest('[data-mention-index]');
		if (!button) {
			return;
		}

		const index = Number(button.getAttribute('data-mention-index'));
		const selected = activeSuggestions[index];
		if (selected) {
			applyMention(selected);
		}
	});

	document.addEventListener('click', (event) => {
		if (event.target === mentionInput || mentionMenu.contains(event.target)) {
			return;
		}

		hideMentionMenu();
	});
}

const settingsDrawer = document.querySelector('[data-settings-drawer]');
const settingsOverlay = document.querySelector('[data-settings-overlay]');
const openSettingsButtons = Array.from(document.querySelectorAll('[data-open-settings]'));
const closeSettingsButton = document.querySelector('[data-close-settings]');

if (settingsDrawer && settingsOverlay && openSettingsButtons.length > 0 && closeSettingsButton) {
	let drawerOpen = false;

	const openDrawer = () => {
		drawerOpen = true;
		settingsDrawer.classList.remove('translate-x-full');
		settingsOverlay.classList.remove('pointer-events-none', 'opacity-0', 'bg-slate-900/0');
		settingsOverlay.classList.add('bg-slate-900/35', 'opacity-100');
		document.body.classList.add('overflow-hidden');
	};

	const closeDrawer = () => {
		drawerOpen = false;
		settingsDrawer.classList.add('translate-x-full');
		settingsOverlay.classList.add('pointer-events-none', 'opacity-0', 'bg-slate-900/0');
		settingsOverlay.classList.remove('bg-slate-900/35', 'opacity-100');
		document.body.classList.remove('overflow-hidden');
	};

	openSettingsButtons.forEach((openSettingsButton) => {
		openSettingsButton.addEventListener('click', (event) => {
			event.preventDefault();
			if (drawerOpen) {
				closeDrawer();
				return;
			}
			openDrawer();
		});
	});
	closeSettingsButton.addEventListener('click', closeDrawer);
	settingsOverlay.addEventListener('click', closeDrawer);
}

document.querySelectorAll('[data-copy-share-id]').forEach((button) => {
	button.addEventListener('click', async () => {
		const shareId = button.getAttribute('data-copy-share-id') || '';
		if (!shareId || !navigator.clipboard?.writeText) {
			return;
		}

		try {
			await navigator.clipboard.writeText(shareId);
			const prev = button.textContent;
			button.textContent = 'Tersalin';
			window.setTimeout(() => {
				button.textContent = prev;
			}, 1200);
		} catch (_error) {
			// Ignore clipboard failures silently on restricted browser contexts.
		}
	});
});

document.querySelectorAll('[data-share-group-url]').forEach((button) => {
	button.addEventListener('click', async () => {
		const url = button.getAttribute('data-share-group-url') || '';
		const groupName = button.getAttribute('data-share-group-name') || 'Normchat Group';
		if (!url) {
			return;
		}

		const sharePayload = {
			title: `Join #${groupName}`,
			text: `Gabung ke group #${groupName} di Normchat`,
			url,
		};

		if (navigator.share) {
			try {
				await navigator.share(sharePayload);
				return;
			} catch (_error) {
				// Fall back to clipboard when share sheet is cancelled/unavailable.
			}
		}

		if (navigator.clipboard?.writeText) {
			try {
				await navigator.clipboard.writeText(url);
				const prev = button.textContent;
				button.textContent = 'Link tersalin';
				window.setTimeout(() => {
					button.textContent = prev;
				}, 1300);
			} catch (_error) {
				// Ignore clipboard failures silently on restricted browser contexts.
			}
		}
	});
});
