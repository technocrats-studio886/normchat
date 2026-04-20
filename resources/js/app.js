import './bootstrap';

const chatShell = document.querySelector('[data-chat-shell]');
const chatFeed = document.querySelector('[data-chat-feed]');
const groupId = chatFeed?.getAttribute('data-chat-group-id');
const chatThemeStorageKey = `nc:chatTheme:${groupId || 'global'}`;
const hiddenMessagesStorageKey = `nc:hiddenMessages:${groupId || 'global'}`;
const authUserIdFromFeed = Number(chatFeed?.getAttribute('data-auth-user-id') || 0);
const authUserIdFromMeta = Number(document.querySelector('meta[name="auth-user-id"]')?.getAttribute('content') || 0);
const authUserId = authUserIdFromFeed || authUserIdFromMeta;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const lastReadMessageIdFromServer = Number(chatFeed?.getAttribute('data-last-read-message-id') || 0);
const latestMessageIdFromServer = Number(chatFeed?.getAttribute('data-latest-message-id') || 0);
const unreadCountFromServer = Number(chatFeed?.getAttribute('data-unread-count') || 0);
const hasReadBeforeFromServer = String(chatFeed?.getAttribute('data-has-read-before') || '0') === '1';
const chatReadUrlFromServer = String(chatFeed?.getAttribute('data-chat-read-url') || '');
const pollStatsUrlFromServer = String(chatFeed?.getAttribute('data-poll-stats-url') || '');
const pollVoteUrlTemplateFromServer = String(chatFeed?.getAttribute('data-poll-vote-url-template') || '');
const pendingMessagesByLocalId = new Map();
const pollStatsById = new Map();
let pendingLocalMessageCounter = 0;
let chatSubmitInFlight = false;
let pollStatsRequestInFlight = false;
let openPollComposerFromAny = null;

const chatThemePalettes = [
	{ id: 'default', label: 'Default', color: '' },
	{
		id: 'midnight',
		label: 'Midnight',
		color: '#041126',
		primary: '#38bdf8',
		primaryHover: '#0ea5e9',
		mine: '#2563eb',
		headerBg: 'rgba(2, 6, 23, 0.8)',
		headerBorder: 'rgba(148, 163, 184, 0.3)',
		headerTitle: '#e2e8f0',
		headerSubtitle: '#94a3b8',
		otherBg: 'rgba(15, 23, 42, 0.86)',
		otherText: '#e2e8f0',
		otherBorder: 'rgba(148, 163, 184, 0.34)',
		aiBg: 'rgba(6, 95, 70, 0.86)',
		aiText: '#ecfdf5',
		aiBorder: 'rgba(110, 231, 183, 0.45)',
		composerBg: 'rgba(2, 6, 23, 0.88)',
		composerInputBg: 'rgba(15, 23, 42, 0.86)',
		composerText: '#e2e8f0',
		composerSoft: '#94a3b8',
		composerLine: 'rgba(148, 163, 184, 0.25)',
	},
	{
		id: 'ocean',
		label: 'Ocean',
		color: '#0a4f76',
		primary: '#22d3ee',
		primaryHover: '#06b6d4',
		mine: '#0284c7',
		headerBg: 'rgba(7, 33, 50, 0.78)',
		headerBorder: 'rgba(125, 211, 252, 0.28)',
		headerTitle: '#e0f2fe',
		headerSubtitle: '#bae6fd',
		otherBg: 'rgba(8, 47, 73, 0.84)',
		otherText: '#e0f2fe',
		otherBorder: 'rgba(125, 211, 252, 0.32)',
		aiBg: 'rgba(6, 78, 59, 0.82)',
		aiText: '#ecfdf5',
		aiBorder: 'rgba(110, 231, 183, 0.4)',
		composerBg: 'rgba(7, 33, 50, 0.86)',
		composerInputBg: 'rgba(8, 47, 73, 0.84)',
		composerText: '#e0f2fe',
		composerSoft: '#bae6fd',
		composerLine: 'rgba(125, 211, 252, 0.25)',
	},
	{
		id: 'forest',
		label: 'Forest',
		color: '#0f5132',
		primary: '#34d399',
		primaryHover: '#10b981',
		mine: '#059669',
		headerBg: 'rgba(6, 38, 24, 0.78)',
		headerBorder: 'rgba(110, 231, 183, 0.28)',
		headerTitle: '#d1fae5',
		headerSubtitle: '#a7f3d0',
		otherBg: 'rgba(6, 78, 59, 0.82)',
		otherText: '#ecfdf5',
		otherBorder: 'rgba(110, 231, 183, 0.35)',
		aiBg: 'rgba(5, 150, 105, 0.84)',
		aiText: '#ecfdf5',
		aiBorder: 'rgba(167, 243, 208, 0.4)',
		composerBg: 'rgba(6, 38, 24, 0.86)',
		composerInputBg: 'rgba(6, 78, 59, 0.84)',
		composerText: '#ecfdf5',
		composerSoft: '#a7f3d0',
		composerLine: 'rgba(110, 231, 183, 0.24)',
	},
	{
		id: 'sunset',
		label: 'Sunset',
		color: '#7c2d12',
		primary: '#fb923c',
		primaryHover: '#f97316',
		mine: '#ea580c',
		headerBg: 'rgba(67, 20, 7, 0.78)',
		headerBorder: 'rgba(251, 191, 36, 0.3)',
		headerTitle: '#ffedd5',
		headerSubtitle: '#fed7aa',
		otherBg: 'rgba(124, 45, 18, 0.82)',
		otherText: '#ffedd5',
		otherBorder: 'rgba(251, 191, 36, 0.32)',
		aiBg: 'rgba(120, 53, 15, 0.84)',
		aiText: '#ffedd5',
		aiBorder: 'rgba(253, 186, 116, 0.36)',
		composerBg: 'rgba(67, 20, 7, 0.86)',
		composerInputBg: 'rgba(124, 45, 18, 0.84)',
		composerText: '#ffedd5',
		composerSoft: '#fdba74',
		composerLine: 'rgba(251, 191, 36, 0.25)',
	},
	{
		id: 'slate',
		label: 'Slate',
		color: '#1e293b',
		primary: '#a78bfa',
		primaryHover: '#8b5cf6',
		mine: '#7c3aed',
		headerBg: 'rgba(15, 23, 42, 0.8)',
		headerBorder: 'rgba(148, 163, 184, 0.32)',
		headerTitle: '#e2e8f0',
		headerSubtitle: '#cbd5e1',
		otherBg: 'rgba(30, 41, 59, 0.85)',
		otherText: '#e2e8f0',
		otherBorder: 'rgba(148, 163, 184, 0.35)',
		aiBg: 'rgba(30, 64, 55, 0.84)',
		aiText: '#ecfdf5',
		aiBorder: 'rgba(110, 231, 183, 0.35)',
		composerBg: 'rgba(15, 23, 42, 0.88)',
		composerInputBg: 'rgba(30, 41, 59, 0.86)',
		composerText: '#e2e8f0',
		composerSoft: '#94a3b8',
		composerLine: 'rgba(148, 163, 184, 0.25)',
	},
	{
		id: 'charcoal',
		label: 'Charcoal',
		color: '#111827',
		primary: '#22d3ee',
		primaryHover: '#06b6d4',
		mine: '#0891b2',
		headerBg: 'rgba(2, 6, 23, 0.82)',
		headerBorder: 'rgba(71, 85, 105, 0.4)',
		headerTitle: '#e2e8f0',
		headerSubtitle: '#94a3b8',
		otherBg: 'rgba(15, 23, 42, 0.88)',
		otherText: '#e2e8f0',
		otherBorder: 'rgba(71, 85, 105, 0.45)',
		aiBg: 'rgba(6, 78, 59, 0.84)',
		aiText: '#ecfdf5',
		aiBorder: 'rgba(16, 185, 129, 0.45)',
		composerBg: 'rgba(2, 6, 23, 0.9)',
		composerInputBg: 'rgba(15, 23, 42, 0.88)',
		composerText: '#e2e8f0',
		composerSoft: '#94a3b8',
		composerLine: 'rgba(71, 85, 105, 0.4)',
	},
];

const resolveChatThemePalette = (themeId) => {
	const selectedId = String(themeId || '').trim().toLowerCase();
	return chatThemePalettes.find((item) => item.id === selectedId) || chatThemePalettes[0];
};

const applyChatTheme = (themeId) => {
	if (!(chatShell instanceof HTMLElement)) {
		return;
	}

	const theme = resolveChatThemePalette(themeId);
	chatShell.dataset.chatTheme = theme.id;
	const clearThemeVars = () => {
		[
			'--nc-primary',
			'--nc-primary-hover',
			'--nc-mine',
			'--nc-chat-meta',
			'--nc-chat-meta-strong',
			'--nc-chat-header-bg',
			'--nc-chat-header-border',
			'--nc-chat-header-title',
			'--nc-chat-header-subtitle',
			'--nc-chat-reply-mine-bg',
			'--nc-chat-reply-mine-border',
			'--nc-chat-reply-mine-text',
			'--nc-chat-reply-ai-bg',
			'--nc-chat-reply-ai-border',
			'--nc-chat-reply-ai-text',
			'--nc-chat-reply-other-bg',
			'--nc-chat-reply-other-border',
			'--nc-chat-reply-other-text',
			'--nc-chat-other-bg',
			'--nc-chat-other-text',
			'--nc-chat-other-border',
			'--nc-chat-ai-bg',
			'--nc-chat-ai-text',
			'--nc-chat-ai-border',
			'--nc-chat-composer-bg',
			'--nc-chat-composer-input-bg',
			'--nc-chat-composer-text',
			'--nc-chat-composer-soft',
			'--nc-chat-composer-line',
		].forEach((name) => {
			chatShell.style.removeProperty(name);
		});
	};

	if (!theme.color) {
		chatShell.style.background = '';
		chatShell.style.backgroundImage = '';
		chatShell.style.backgroundColor = '';
		clearThemeVars();
		return;
	}

	clearThemeVars();
	chatShell.style.setProperty('--nc-primary', String(theme.primary || '#0f766e'));
	chatShell.style.setProperty('--nc-primary-hover', String(theme.primaryHover || theme.primary || '#0d5f59'));
	chatShell.style.setProperty('--nc-mine', String(theme.mine || theme.primary || '#0f766e'));
	chatShell.style.setProperty('--nc-chat-meta', String(theme.headerSubtitle || '#94a3b8'));
	chatShell.style.setProperty('--nc-chat-meta-strong', String(theme.headerTitle || '#e2e8f0'));
	chatShell.style.setProperty('--nc-chat-header-bg', String(theme.headerBg || 'rgba(255,255,255,0.95)'));
	chatShell.style.setProperty('--nc-chat-header-border', String(theme.headerBorder || 'rgba(226, 232, 240, 0.95)'));
	chatShell.style.setProperty('--nc-chat-header-title', String(theme.headerTitle || '#1f2937'));
	chatShell.style.setProperty('--nc-chat-header-subtitle', String(theme.headerSubtitle || '#6b7280'));
	chatShell.style.setProperty('--nc-chat-reply-mine-bg', String(theme.replyMineBg || 'rgba(15, 23, 42, 0.52)'));
	chatShell.style.setProperty('--nc-chat-reply-mine-border', String(theme.primary || '#f472b6'));
	chatShell.style.setProperty('--nc-chat-reply-mine-text', String(theme.headerTitle || '#e2e8f0'));
	chatShell.style.setProperty('--nc-chat-reply-ai-bg', String(theme.replyAiBg || 'rgba(6, 95, 70, 0.5)'));
	chatShell.style.setProperty('--nc-chat-reply-ai-border', String(theme.aiBorder || '#34d399'));
	chatShell.style.setProperty('--nc-chat-reply-ai-text', String(theme.aiText || '#ecfdf5'));
	chatShell.style.setProperty('--nc-chat-reply-other-bg', String(theme.replyOtherBg || 'rgba(15, 23, 42, 0.5)'));
	chatShell.style.setProperty('--nc-chat-reply-other-border', String(theme.otherBorder || '#64748b'));
	chatShell.style.setProperty('--nc-chat-reply-other-text', String(theme.otherText || '#e2e8f0'));
	chatShell.style.setProperty('--nc-chat-other-bg', String(theme.otherBg || '#ffffff'));
	chatShell.style.setProperty('--nc-chat-other-text', String(theme.otherText || '#111827'));
	chatShell.style.setProperty('--nc-chat-other-border', String(theme.otherBorder || '#e5e7eb'));
	chatShell.style.setProperty('--nc-chat-ai-bg', String(theme.aiBg || '#ecfdf5'));
	chatShell.style.setProperty('--nc-chat-ai-text', String(theme.aiText || '#064e3b'));
	chatShell.style.setProperty('--nc-chat-ai-border', String(theme.aiBorder || '#a7f3d0'));
	chatShell.style.setProperty('--nc-chat-composer-bg', String(theme.composerBg || '#ffffff'));
	chatShell.style.setProperty('--nc-chat-composer-input-bg', String(theme.composerInputBg || '#ffffff'));
	chatShell.style.setProperty('--nc-chat-composer-text', String(theme.composerText || '#111827'));
	chatShell.style.setProperty('--nc-chat-composer-soft', String(theme.composerSoft || '#64748b'));
	chatShell.style.setProperty('--nc-chat-composer-line', String(theme.composerLine || '#e5e7eb'));

	chatShell.style.backgroundImage = 'none';
	chatShell.style.background = theme.color;
	chatShell.style.backgroundColor = theme.color;
};

const loadSavedChatTheme = () => {
	try {
		const storedTheme = String(window.localStorage.getItem(chatThemeStorageKey) || '').trim();
		applyChatTheme(storedTheme || 'default');
	} catch (_error) {
		applyChatTheme('default');
	}
};

loadSavedChatTheme();

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

const formatFileSize = (bytes) => {
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

const playIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>';
const pauseIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6 4.75A.75.75 0 0 1 6.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Zm5 0A.75.75 0 0 1 11.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Z" /></svg>';
const voiceWaveHeights = ['h-2', 'h-3', 'h-2', 'h-4', 'h-2', 'h-3', 'h-4', 'h-2', 'h-3', 'h-2', 'h-4', 'h-2'];

const buildVoicePlayerMarkup = (safeUrl, sourceType, palette) => {
	const isMine = palette === 'blue';
	const playerClass = isMine
		? 'border-white/15 text-white'
		: (palette === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-100 text-slate-800');
	const playerStyle = isMine ? 'style="background: var(--nc-mine);"' : '';
	const buttonClass = isMine ? 'bg-white/20 text-white' : 'bg-emerald-500 text-white';
	const sliderClass = isMine ? '' : 'accent-emerald-500';
	const sliderStyle = isMine ? 'style="accent-color: rgba(255,255,255,0.85);"' : '';
	const timerClass = isMine ? 'text-white/80' : 'text-slate-500';
	const waveClass = isMine ? 'bg-white/70' : 'bg-emerald-500/70';
	const waveHtml = voiceWaveHeights
		.map((heightClass) => `<span class="${heightClass} w-0.5 rounded-full ${waveClass} opacity-45" data-voice-bar></span>`)
		.join('');

	return `
		<div class="mb-2 inline-block w-55 max-w-full rounded-2xl border px-3 py-2 transition ${playerClass}" ${playerStyle} data-voice-player="1">
			<audio preload="metadata" class="hidden" data-voice-audio>
				<source src="${safeUrl}" type="${sourceType}">
			</audio>
			<div class="flex items-center gap-2">
				<button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${buttonClass}" data-voice-toggle aria-label="Play voice note" aria-pressed="false">
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
			<p class="mt-1 hidden text-[11px] ${timerClass}" data-voice-fallback-note>Audio mode kompatibilitas aktif. Tap player native di bawah.</p>
		</div>
	`;
};

const renderAttachment = (message, palette = 'slate', options = {}) => {
	const url = message?.attachment_url || '';
	if (!url) {
		return '';
	}

	const mergedCaption = options?.mergedCaption === true;

	const mime = String(message?.attachment_mime || '').toLowerCase();
	const type = String(message?.message_type || 'text').toLowerCase();
	const attachmentName = escapeHtml(message?.attachment_original_name || 'Lampiran');
	const attachmentSizeLabel = escapeHtml(formatFileSize(message?.attachment_size));
	const safeUrl = escapeAttr(url);
	const safeNameAttr = escapeAttr(message?.attachment_original_name || 'lampiran');
	const isVideo = type === 'video' || mime.startsWith('video/');

	if (type === 'image' || mime.startsWith('image/')) {
		const border = palette === 'blue' ? 'border-blue-200 bg-blue-50' : (palette === 'emerald' ? 'border-emerald-100 bg-emerald-50' : 'border-slate-200 bg-white');
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
		const frameClass = palette === 'blue'
			? 'border-blue-200 bg-blue-50'
			: (palette === 'emerald' ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white');
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

	const fileToneClass = palette === 'blue'
		? 'border-blue-200 bg-blue-50 text-blue-800'
		: (palette === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-700');
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

const renderReplyPreview = (message, palette = 'slate') => {
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
	const toneClass = palette === 'blue'
		? 'nc-reply-chip--mine'
		: (palette === 'emerald' ? 'nc-reply-chip--ai' : 'nc-reply-chip--other');

	return `
		<button type="button" data-reply-jump="${replyTo.id}" data-reply-quote="${safeQuoteAttr}" class="nc-reply-chip ${toneClass} mb-1 block w-full rounded-xl px-3 py-1.5 text-left text-xs">
			<p class="nc-reply-chip-sender font-semibold">Membalas ${sender}</p>
			<p class="truncate">${safeSummary}</p>
		</button>
	`;
};

const parsePollContent = (rawContent) => {
	const normalized = String(rawContent || '').replace(/\r\n/g, '\n').trim();
	if (!normalized) {
		return null;
	}

	if (!/^📊\s*poll:/i.test(normalized)) {
		return null;
	}

	const lines = normalized
		.split('\n')
		.map((line) => line.trim())
		.filter((line) => line !== '');

	if (!lines.length) {
		return null;
	}

	const question = lines[0].replace(/^📊\s*poll:\s*/i, '').trim();
	if (!question) {
		return null;
	}

	const options = [];
	for (let i = 1; i < lines.length; i++) {
		const match = lines[i].match(/^(\d+)[.)]\s+(.+)$/);
		if (!match) {
			continue;
		}

		const optionNumber = Number(match[1]);
		const optionLabel = String(match[2] || '').trim();
		if (!Number.isFinite(optionNumber) || optionNumber <= 0 || !optionLabel) {
			continue;
		}

		options.push({
			number: optionNumber,
			label: optionLabel,
		});
	}

	if (options.length < 2) {
		return null;
	}

	return {
		question,
		options: options.slice(0, 8),
	};
};

const renderPollCard = ({ poll, tone = 'other', messageId = 0, inlineTimeHtml = '' }) => {
	if (!poll || !Array.isArray(poll.options) || poll.options.length < 2) {
		return '';
	}

	const colorMap = {
		mine: {
			card: 'bg-indigo-500 text-white border-indigo-400',
			question: 'text-indigo-50',
			meta: 'text-indigo-100/90',
			option: 'bg-indigo-400/45 border-indigo-300/70 text-white hover:bg-indigo-300/55',
		},
		ai: {
			card: 'bg-emerald-50 text-emerald-900 border-emerald-200',
			question: 'text-emerald-900',
			meta: 'text-emerald-700',
			option: 'bg-white border-emerald-200 text-emerald-900 hover:bg-emerald-100',
		},
		other: {
			card: 'bg-white text-slate-800 border-slate-200',
			question: 'text-slate-900',
			meta: 'text-slate-500',
			option: 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100',
		},
	};

	const palette = colorMap[tone] || colorMap.other;
	const safeQuestion = escapeHtml(poll.question);
	const numericMessageId = Number(messageId || 0);
	const canVote = Number.isFinite(numericMessageId) && numericMessageId > 0;
	const optionsHtml = poll.options
		.map((option) => {
			const safeLabel = escapeHtml(option.label);
			const safeNumber = Number(option.number || 0);
			if (!canVote) {
				return `<div class="rounded-xl border px-2.5 py-2 text-xs font-medium ${palette.option}" data-poll-option-row="1" data-poll-option-number="${safeNumber}"><div class="flex items-center gap-2"><span class="font-bold">${safeNumber}.</span><span class="min-w-0 flex-1 truncate">${safeLabel}</span><span class="text-[10px] font-semibold opacity-80" data-poll-option-count>0</span></div></div>`;
			}

			return `
				<button
					type="button"
					class="w-full rounded-xl border px-2.5 py-2 text-left text-xs font-medium transition ${palette.option}"
					data-poll-vote="1"
					data-poll-id="${numericMessageId}"
					data-poll-option="${safeNumber}"
					data-poll-label="${escapeAttr(option.label)}"
					data-poll-option-row="1"
					data-poll-option-number="${safeNumber}"
				>
					<span class="flex items-center gap-2"><span class="font-bold">${safeNumber}.</span><span class="min-w-0 flex-1 truncate">${safeLabel}</span><span class="text-[10px] font-semibold opacity-80" data-poll-option-count>0</span></span>
				</button>
			`;
		})
		.join('');

	return `
		<div class="rounded-2xl border px-3 py-2.5 ${palette.card}" data-message-body="1" data-poll-card="1" data-poll-id="${numericMessageId}" data-poll-tone="${escapeAttr(tone)}">
			<p class="text-[11px] font-semibold uppercase tracking-wide ${palette.meta}">Polling</p>
			<p class="mt-1 text-sm font-semibold ${palette.question}">${safeQuestion}</p>
			<div class="mt-2 space-y-1.5">${optionsHtml}</div>
			<p class="mt-2 text-[11px] ${palette.meta}" data-poll-summary>Belum ada vote</p>
			${inlineTimeHtml}
		</div>
	`;
};

const parseVoteContent = (rawContent) => {
	const normalized = String(rawContent || '').replace(/\r\n/g, '\n').trim();
	if (!normalized) {
		return null;
	}

	const match = normalized.match(/^🗳️\s*vote\s+poll\s+#(\d+)\s*:\s*(\d+)/i);
	if (!match) {
		return null;
	}

	const pollId = Number(match[1] || 0);
	const optionNumber = Number(match[2] || 0);
	if (!Number.isFinite(pollId) || pollId <= 0 || !Number.isFinite(optionNumber) || optionNumber <= 0) {
		return null;
	}

	return {
		pollId,
		optionNumber,
	};
};

const buildPollVoteUrl = (pollId) => {
	const numericPollId = Number(pollId || 0);
	if (!Number.isFinite(numericPollId) || numericPollId <= 0) {
		return '';
	}

	if (pollVoteUrlTemplateFromServer.includes('__MESSAGE__')) {
		return pollVoteUrlTemplateFromServer.replace('__MESSAGE__', String(numericPollId));
	}

	if (groupId) {
		return `/groups/${groupId}/polls/${numericPollId}/vote`;
	}

	return '';
};

const collectPollCardIds = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return [];
	}

	return Array.from(chatFeed.querySelectorAll('[data-poll-card]'))
		.map((card) => Number(card.getAttribute('data-poll-id') || 0))
		.filter((pollId) => Number.isFinite(pollId) && pollId > 0)
		.filter((pollId, index, list) => list.indexOf(pollId) === index);
};

const mergePollStatsIntoCache = (pollStatsPayload) => {
	const pollId = Number(pollStatsPayload?.poll_id || 0);
	if (!Number.isFinite(pollId) || pollId <= 0) {
		return;
	}

	const optionCountsRaw = pollStatsPayload?.option_counts;
	const optionCounts = {};
	if (optionCountsRaw && typeof optionCountsRaw === 'object') {
		Object.entries(optionCountsRaw).forEach(([key, value]) => {
			const optionNumber = Number(key || 0);
			const count = Number(value || 0);
			if (!Number.isFinite(optionNumber) || optionNumber <= 0) {
				return;
			}

			optionCounts[optionNumber] = Number.isFinite(count) && count > 0 ? count : 0;
		});
	}

	pollStatsById.set(pollId, {
		totalVotes: Math.max(0, Number(pollStatsPayload?.total_votes || 0)),
		myVoteOption: Math.max(0, Number(pollStatsPayload?.my_vote_option || 0)),
		optionCounts,
	});
};

const requestPollStatsForVisibleCards = async () => {
	if (!pollStatsUrlFromServer || pollStatsRequestInFlight) {
		return;
	}

	const pollIds = collectPollCardIds();
	if (!pollIds.length) {
		return;
	}

	const missingIds = pollIds.filter((pollId) => !pollStatsById.has(pollId));
	if (!missingIds.length) {
		return;
	}

	pollStatsRequestInFlight = true;
	try {
		let didUpdate = false;
		const result = await chatApiRequest(`${pollStatsUrlFromServer}?ids=${missingIds.join(',')}`);
		if (result?.polls && typeof result.polls === 'object') {
			Object.values(result.polls).forEach((pollStatsPayload) => {
				mergePollStatsIntoCache(pollStatsPayload);
				didUpdate = true;
			});
		}

		if (didUpdate) {
			refreshPollCardsFromFeed();
		}
	} catch (_error) {
		// Ignore polling stat fetch errors and keep feed-based fallback active.
	} finally {
		pollStatsRequestInFlight = false;
	}
};

const collectPollVotesFromFeed = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return new Map();
	}

	const votesByPoll = new Map();
	const nodes = Array.from(chatFeed.querySelectorAll('[data-message-id]'));
	nodes.forEach((node) => {
		if (!(node instanceof HTMLElement) || node.classList.contains('hidden')) {
			return;
		}

		const vote = parseVoteContent(node.dataset.messageContent || '');
		if (!vote) {
			return;
		}

		const senderId = Number(node.dataset.messageSenderId || 0);
		const messageId = Number(node.getAttribute('data-message-id') || 0);
		if (!Number.isFinite(senderId) || senderId <= 0 || !Number.isFinite(messageId) || messageId <= 0) {
			return;
		}

		if (!votesByPoll.has(vote.pollId)) {
			votesByPoll.set(vote.pollId, new Map());
		}

		const senderVotes = votesByPoll.get(vote.pollId);
		const existing = senderVotes.get(senderId);
		if (!existing || messageId > existing.messageId) {
			senderVotes.set(senderId, {
				messageId,
				optionNumber: vote.optionNumber,
			});
		}
	});

	return votesByPoll;
};

const refreshPollCardsFromFeed = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return;
	}

	const votesByPoll = collectPollVotesFromFeed();
	requestPollStatsForVisibleCards();
	chatFeed.querySelectorAll('[data-poll-card]').forEach((card) => {
		if (!(card instanceof HTMLElement)) {
			return;
		}

		const pollId = Number(card.getAttribute('data-poll-id') || 0);
		const serverStats = pollStatsById.get(pollId);

		const optionCounts = new Map();
		let totalVotes = 0;
		let myVoteOption = 0;

		if (serverStats) {
			Object.entries(serverStats.optionCounts || {}).forEach(([optionNumber, count]) => {
				const parsedOption = Number(optionNumber || 0);
				const parsedCount = Number(count || 0);
				if (Number.isFinite(parsedOption) && parsedOption > 0) {
					optionCounts.set(parsedOption, Math.max(0, parsedCount));
				}
			});

			totalVotes = Math.max(0, Number(serverStats.totalVotes || 0));
			myVoteOption = Math.max(0, Number(serverStats.myVoteOption || 0));
		} else {
			const senderVotes = votesByPoll.get(pollId) || new Map();
			senderVotes.forEach((vote) => {
				optionCounts.set(vote.optionNumber, (optionCounts.get(vote.optionNumber) || 0) + 1);
			});
			totalVotes = senderVotes.size;

			const mine = senderVotes.get(authUserId);
			if (mine && Number.isFinite(mine.optionNumber)) {
				myVoteOption = mine.optionNumber;
			}
		}

		card.querySelectorAll('[data-poll-option-row="1"]').forEach((row) => {
			if (!(row instanceof HTMLElement)) {
				return;
			}

			const optionNumber = Number(row.getAttribute('data-poll-option-number') || 0);
			const count = optionCounts.get(optionNumber) || 0;
			const countEl = row.querySelector('[data-poll-option-count]');
			if (countEl instanceof HTMLElement) {
				countEl.textContent = String(count);
			}

			const ratio = totalVotes > 0 ? Math.max(0, Math.min(100, Math.round((count / totalVotes) * 100))) : 0;
			row.style.backgroundImage = ratio > 0
				? `linear-gradient(90deg, rgba(14, 165, 233, 0.16) ${ratio}%, rgba(0,0,0,0) ${ratio}%)`
				: '';

			const isMine = optionNumber > 0 && optionNumber === myVoteOption;
			row.classList.toggle('ring-2', isMine);
			row.classList.toggle('ring-cyan-400', isMine);
			row.classList.toggle('ring-offset-1', isMine);
		});

		const summary = card.querySelector('[data-poll-summary]');
		if (summary instanceof HTMLElement) {
			if (totalVotes <= 0) {
				summary.textContent = 'Belum ada vote';
			} else if (myVoteOption > 0) {
				summary.textContent = `${totalVotes} vote • Kamu pilih opsi ${myVoteOption}`;
			} else {
				summary.textContent = `${totalVotes} vote`;
			}
		}
	});
};

const hydrateExistingPollMessages = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return;
	}

	chatFeed.querySelectorAll('[data-message-id]').forEach((messageNode) => {
		if (!(messageNode instanceof HTMLElement)) {
			return;
		}

		if (messageNode.querySelector('[data-poll-card]')) {
			return;
		}

		const pollData = parsePollContent(messageNode.dataset.messageContent || '');
		if (!pollData) {
			return;
		}

		const bubble = messageNode.querySelector('.bubble-mine, .bubble-ai, .bubble-other');
		if (!(bubble instanceof HTMLElement)) {
			return;
		}

		const inlineTimeEl = bubble.querySelector('.nc-inline-time');
		const inlineTimeHtml = inlineTimeEl instanceof HTMLElement ? inlineTimeEl.outerHTML : '';
		const messageId = Number(messageNode.getAttribute('data-message-id') || 0);
		const tone = messageNode.classList.contains('justify-end')
			? 'mine'
			: (bubble.classList.contains('bubble-ai') ? 'ai' : 'other');

		bubble.outerHTML = renderPollCard({
			poll: pollData,
			tone,
			messageId,
			inlineTimeHtml,
		});
	});
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
	const rawContent = String(message?.content || '');
	const content = escapeHtml(rawContent);
	const pollData = parsePollContent(rawContent);

	const wrapper = document.createElement('div');
	wrapper.setAttribute('data-message-id', String(message?.id ?? ''));
	wrapper.dataset.messageSenderId = String(Number(message?.sender_id || 0));
	wrapper.style.touchAction = 'pan-y';
	wrapper.style.userSelect = 'none';
	wrapper.style.webkitUserSelect = 'none';
	const isPending = message?.is_pending === true;

	if (isMine) {
		const replyBlock = renderReplyPreview(message, 'blue');
		const inlineTimeHtml = timeText
			? `<span class="nc-inline-time">${timeText}${editedMarkForMine}</span>`
			: '';
		const hasAttachment = Boolean(message?.attachment_url);
		const shouldMergeAttachmentCaption = hasAttachment && Boolean(content) && !pollData;
		const attachmentBlock = renderAttachment(message, 'blue', {
			mergedCaption: shouldMergeAttachmentCaption,
		});
		const attachmentWithCaptionBlock = shouldMergeAttachmentCaption
			? `
				<div class="nc-media-caption-card nc-media-caption-card--mine" data-message-body="1">
					${attachmentBlock}
					<div class="px-3 pb-2 pt-2 text-sm text-white/95">
						<span class="whitespace-pre-wrap">${content}</span>
						${inlineTimeHtml}
					</div>
				</div>
			`
			: '';
		const textBlock = content
			? (pollData
				? renderPollCard({ poll: pollData, tone: 'mine', messageId: message?.id, inlineTimeHtml })
				: (shouldMergeAttachmentCaption
					? ''
					: `<div class="bubble-mine" data-message-body="1"><span class="whitespace-pre-wrap">${content}</span>${inlineTimeHtml}</div>`))
			: '';
		const pendingTail = isPending
			? `<p class="nc-chat-meta mt-1 text-right text-[11px]"><span class="inline-flex items-center gap-1"><span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span>Mengirim...</span></p>`
			: (content ? '' : `<p class="nc-chat-meta mt-1 text-right text-[11px]">${timeText}${editedMarkForMine}</p>`);

		wrapper.className = 'flex justify-end';
		wrapper.innerHTML = `
			<div class="max-w-[75%]">
				${replyBlock}
				${attachmentWithCaptionBlock || attachmentBlock}
				${textBlock}
				${pendingTail}
			</div>
		`;
		wrapper.dataset.messageSenderName = String(message?.sender_name || 'You');
		wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
		wrapper.dataset.messageType = String(message?.message_type || 'text');
		wrapper.dataset.messageAttachmentMime = String(message?.attachment_mime || '');
		wrapper.dataset.messageAttachmentName = String(message?.attachment_original_name || '');
		return wrapper;
	}

	if (isAi) {
		const replyBlock = renderReplyPreview(message, 'emerald');
		const aiName = escapeHtml(message?.sender_name || 'NormAI');
		const attachmentBlock = renderAttachment(message, 'emerald');
		const renderedContent = pollData ? '' : (content ? renderMarkdown(content) : '');
		const hasRichContent = renderedContent && (renderedContent.includes('<table') || renderedContent.includes('mermaid'));
		const inlineTimeHtml = timeText
			? `<span class="nc-inline-time">${timeText}${editedMarkForAi}</span>`
			: '';
		const aiTimeHtml = timeText
			? `<p class="nc-chat-meta nc-chat-meta--ai mt-1 text-right text-[11px] font-medium">${timeText}${editedMarkForAi}</p>`
			: '';
		const textBlock = pollData
			? renderPollCard({ poll: pollData, tone: 'ai', messageId: message?.id, inlineTimeHtml: '' })
			: (renderedContent
				? `<div class="bubble-ai ai-markdown overflow-hidden" data-message-body="1">${renderedContent}</div>`
				: '');

		wrapper.className = hasRichContent ? 'max-w-[95%]' : 'max-w-[80%]';
		wrapper.innerHTML = `
			<p class="nc-chat-sender nc-chat-sender--ai mb-1 text-[11px] font-semibold">${aiName}</p>
			${replyBlock}
			${attachmentBlock}
			${textBlock}
			${aiTimeHtml}
		`;
		wrapper.dataset.messageSenderName = String(message?.sender_name || 'NormAI');
		wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
		wrapper.dataset.messageType = String(message?.message_type || 'text');
		wrapper.dataset.messageAttachmentMime = String(message?.attachment_mime || '');
		wrapper.dataset.messageAttachmentName = String(message?.attachment_original_name || '');
		return wrapper;
	}

	const replyBlock = renderReplyPreview(message, 'slate');
	const senderName = escapeHtml(message?.sender_name || 'User');
	const attachmentBlock = renderAttachment(message, 'slate');
	const inlineTimeHtml = timeText
		? `<span class="nc-inline-time">${timeText}${editedMarkForOther}</span>`
		: '';
	const textBlock = content
		? (pollData
			? renderPollCard({ poll: pollData, tone: 'other', messageId: message?.id, inlineTimeHtml })
			: `<div class="bubble-other" data-message-body="1"><span class="whitespace-pre-wrap">${content}</span>${inlineTimeHtml}</div>`)
		: '';

	wrapper.className = 'max-w-[75%]';
	wrapper.innerHTML = `
		<p class="nc-chat-sender mb-1 text-[11px]">${senderName}</p>
		${replyBlock}
		${attachmentBlock}
		${textBlock}
		${content ? '' : `<p class="nc-chat-meta mt-1 text-[11px]">${timeText}${editedMarkForOther}</p>`}
	`;
	wrapper.dataset.messageSenderName = String(message?.sender_name || 'User');
	wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
	wrapper.dataset.messageType = String(message?.message_type || 'text');
	wrapper.dataset.messageAttachmentMime = String(message?.attachment_mime || '');
	wrapper.dataset.messageAttachmentName = String(message?.attachment_original_name || '');

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
	refreshPollCardsFromFeed();

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
	refreshPollCardsFromFeed();
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
hydrateExistingPollMessages();
refreshPollCardsFromFeed();

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

const setPendingSkipReplySourceMessageId = (messageId) => {
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

const focusComposerInput = (delay = 0) => {
	window.setTimeout(() => {
		const input = document.querySelector('[data-mention-input]');
		if (input instanceof HTMLTextAreaElement || input instanceof HTMLInputElement) {
			input.focus();
		}
	}, delay);
};

// Don't auto-open keyboard on group open — user opens it when they tap input.

let refreshScrollBottomButton = () => {};
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

const clearHighlightMarks = (root, markerClass) => {
	if (!(root instanceof HTMLElement || root instanceof Document)) {
		return;
	}

	root.querySelectorAll(`mark.${markerClass}`).forEach((markNode) => {
		const parent = markNode.parentNode;
		if (!parent) {
			return;
		}
		parent.replaceChild(document.createTextNode(markNode.textContent || ''), markNode);
		parent.normalize();
	});
};

const getMessageHighlightTargets = (messageNode, includeSender = false) => {
	if (!(messageNode instanceof HTMLElement)) {
		return [];
	}

	const targets = Array.from(messageNode.querySelectorAll('.whitespace-pre-wrap'));
	if (targets.length === 0) {
		targets.push(...Array.from(messageNode.querySelectorAll('.ai-markdown p, .ai-markdown li, .ai-markdown td, .ai-markdown h1, .ai-markdown h2, .ai-markdown h3, .ai-markdown h4')));
	}
	if (includeSender) {
		const senderTarget = messageNode.querySelector('.nc-chat-sender');
		if (senderTarget instanceof HTMLElement) {
			targets.push(senderTarget);
		}
	}

	return targets;
};

const escapeRegex = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const applyInlineHighlight = (targetEl, phrase, { mode = 'quote', highlightAll = false } = {}) => {
	if (!(targetEl instanceof HTMLElement)) {
		return false;
	}

	const cleanedPhrase = String(phrase || '').trim();
	if (cleanedPhrase === '') {
		return false;
	}

	const sourceText = targetEl.textContent || '';
	if (sourceText === '') {
		return false;
	}

	const className = mode === 'search' ? 'nc-inline-highlight-search' : 'nc-inline-highlight-quote';
	const escapedPhrase = escapeRegex(cleanedPhrase).replace(/\s+/g, '\\s+');
	const regex = new RegExp(escapedPhrase, highlightAll ? 'gi' : 'i');
	let resultHtml = '';
	let lastIndex = 0;
	let found = false;
	let match = regex.exec(sourceText);

	while (match) {
		found = true;
		const matchText = match[0] || '';
		const start = Number(match.index || 0);
		const end = start + matchText.length;
		resultHtml += `${escapeHtml(sourceText.slice(lastIndex, start))}<mark class="${className}">${escapeHtml(matchText)}</mark>`;
		lastIndex = end;

		if (!highlightAll) {
			break;
		}

		match = regex.exec(sourceText);
	}

	if (!found) {
		return false;
	}

	resultHtml += escapeHtml(sourceText.slice(lastIndex));
	targetEl.innerHTML = resultHtml;
	return true;
};

const clearQuoteHighlightsInFeed = () => {
	clearHighlightMarks(document, 'nc-inline-highlight-quote');
};

const REPLY_JUMP_VISUAL_MS = 1400;
const REPLY_JUMP_SCROLL_SETTLE_MS = 220;

let clearQuoteHighlightTimer = null;
let pendingReplyVisualTimer = null;

const cancelPendingReplyVisual = () => {
	if (pendingReplyVisualTimer) {
		window.clearTimeout(pendingReplyVisualTimer);
		pendingReplyVisualTimer = null;
	}
};

const cancelScheduledQuoteHighlightClear = () => {
	if (clearQuoteHighlightTimer) {
		window.clearTimeout(clearQuoteHighlightTimer);
		clearQuoteHighlightTimer = null;
	}
};

const scheduleQuoteHighlightClear = (delayMs = REPLY_JUMP_VISUAL_MS) => {
	cancelScheduledQuoteHighlightClear();
	clearQuoteHighlightTimer = window.setTimeout(() => {
		clearQuoteHighlightsInFeed();
		clearQuoteHighlightTimer = null;
	}, delayMs);
};

const clearSearchHighlightsInFeed = (feed) => {
	if (!(feed instanceof HTMLElement)) {
		return;
	}

	clearHighlightMarks(feed, 'nc-inline-highlight-search');
};

let activeJumpFocusNode = null;
let clearJumpFocusTimer = null;

const animateJumpFocus = (target) => {
	if (!(target instanceof HTMLElement)) {
		return;
	}

	const visualTarget = target.matches('[data-message-body="1"]')
		? target
		: (target.querySelector('[data-message-body="1"], .bubble-mine, .bubble-ai, .bubble-other, .nc-media-caption-card, [data-poll-card]')
		|| target);

	if (activeJumpFocusNode instanceof HTMLElement && activeJumpFocusNode !== visualTarget) {
		activeJumpFocusNode.classList.remove('nc-jump-focus');
	}

	if (clearJumpFocusTimer) {
		window.clearTimeout(clearJumpFocusTimer);
		clearJumpFocusTimer = null;
	}

	visualTarget.style.animation = 'none';
	visualTarget.classList.remove('nc-jump-focus');
	void visualTarget.offsetWidth;
	visualTarget.style.animation = '';
	visualTarget.classList.add('nc-jump-focus');
	activeJumpFocusNode = visualTarget;

	clearJumpFocusTimer = window.setTimeout(() => {
		visualTarget.classList.remove('nc-jump-focus');
		if (activeJumpFocusNode === visualTarget) {
			activeJumpFocusNode = null;
		}
		clearJumpFocusTimer = null;
	}, REPLY_JUMP_VISUAL_MS);
};

const highlightQuoteInMessage = (messageNode, quoteText) => {
	const cleanedQuote = String(quoteText || '').trim();
	if (cleanedQuote === '' || !(messageNode instanceof HTMLElement)) {
		return null;
	}

	const targets = getMessageHighlightTargets(messageNode, false);
	for (const target of targets) {
		if (applyInlineHighlight(target, cleanedQuote, { mode: 'quote', highlightAll: false })) {
			const markNode = target.querySelector('mark.nc-inline-highlight-quote');
			return markNode instanceof HTMLElement ? markNode : target;
		}
	}

	return null;
};

const highlightSearchInMessage = (messageNode, queryText) => {
	const cleanedQuery = String(queryText || '').trim();
	if (cleanedQuery === '' || !(messageNode instanceof HTMLElement)) {
		return false;
	}

	let hasHighlight = false;
	const targets = getMessageHighlightTargets(messageNode, true);
	targets.forEach((target) => {
		if (applyInlineHighlight(target, cleanedQuery, { mode: 'search', highlightAll: true })) {
			hasHighlight = true;
		}
	});

	return hasHighlight;
};

const highlightMessageInFeed = (messageId, replyQuote = '') => {
	if (!messageId) {
		return false;
	}

	const target = document.getElementById(`message-${messageId}`)
		|| chatFeed?.querySelector(`[data-message-id="${messageId}"]`);
	if (!(target instanceof HTMLElement)) {
		return false;
	}

	cancelPendingReplyVisual();
	cancelScheduledQuoteHighlightClear();
	target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
	pendingReplyVisualTimer = window.setTimeout(() => {
		clearQuoteHighlightsInFeed();
		const highlightedTextTarget = highlightQuoteInMessage(target, replyQuote);

		if (highlightedTextTarget instanceof HTMLElement) {
			scheduleQuoteHighlightClear(REPLY_JUMP_VISUAL_MS);
		} else {
			animateJumpFocus(target);
		}

		pendingReplyVisualTimer = null;
	}, REPLY_JUMP_SCROLL_SETTLE_MS);

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
				scrollChatToBottom('auto');
				markGroupRead(Number(payload.message.id || 0));
				refreshPollCardsFromFeed();
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
			scrollChatToBottom(isMine ? 'auto' : 'smooth');
			markGroupRead(Number(payload.message.id || 0));
		} else {
			if (!isMine) {
				shouldShowFirstOpenSkip = true;
				firstOpenPendingCount += 1;
				syncScrollBottomCount();
			}
			refreshScrollBottomButton();
		}
		refreshPollCardsFromFeed();
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
		refreshPollCardsFromFeed();
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

		if (String(payload?.action || '') === 'removed') {
			window.location.href = '/groups';
		}
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
const replyQuoteInput = chatForm?.querySelector('[data-reply-quote-input]');
const replyPreview = chatForm?.querySelector('[data-reply-preview]');
const replyPreviewSender = chatForm?.querySelector('[data-reply-preview-sender]');
const replyPreviewContent = chatForm?.querySelector('[data-reply-preview-content]');
const replyClearButton = chatForm?.querySelector('[data-reply-clear]');
const isDesktopKeyboard = (() => {
	const hasCoarsePointer = window.matchMedia?.('(pointer: coarse)').matches;
	const hasTouchPoints = (navigator.maxTouchPoints || 0) > 0;
	return !hasCoarsePointer && !hasTouchPoints;
})();
let refreshSendVoiceToggle = () => {};

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

	if (mime.startsWith('video/') || ['mp4', 'mov', 'm4v', 'webm', '3gp', 'mkv'].includes(extension)) {
		return 'video';
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
	const revokeAttachmentPreviewUrl = () => {
		if (!attachmentPreviewUrl) {
			return;
		}
		URL.revokeObjectURL(attachmentPreviewUrl);
		attachmentPreviewUrl = null;
	};

	const clearSelectedAttachment = () => {
		attachmentInput.value = '';
		if (cameraInput instanceof HTMLInputElement) {
			cameraInput.value = '';
		}
		revokeAttachmentPreviewUrl();
		refreshAttachmentLabel();
	};

	const ensureFileAttachmentPreviewEl = () => {
		let el = chatForm.querySelector('[data-attachment-preview-file]');
		if (el instanceof HTMLElement) {
			return el;
		}

		el = document.createElement('div');
		el.setAttribute('data-attachment-preview-file', '1');
		el.className = 'mb-2 hidden items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5';
		el.innerHTML = `
			<span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-200 text-base">📄</span>
			<div class="min-w-0 flex-1">
				<p class="truncate text-sm font-semibold text-slate-700" data-attachment-preview-file-name>Dokumen</p>
				<p class="text-[11px] text-slate-500" data-attachment-preview-file-size>Siap dikirim</p>
			</div>
			<button type="button" class="rounded-full p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600" data-attachment-preview-file-clear aria-label="Batal lampiran">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
			</button>
		`;

		const composer = chatForm.querySelector('[data-composer-main]');
		if (composer && composer.parentNode) {
			composer.parentNode.insertBefore(el, composer);
		} else {
			chatForm.appendChild(el);
		}

		el.querySelector('[data-attachment-preview-file-clear]')?.addEventListener('click', clearSelectedAttachment);
		return el;
	};

	const ensureMediaAttachmentPreviewEl = () => {
		let el = document.querySelector('[data-attachment-preview-media]');
		if (el instanceof HTMLElement) {
			return el;
		}

		el = document.createElement('div');
		el.setAttribute('data-attachment-preview-media', '1');
		el.className = 'fixed inset-0 left-1/2 z-[55] hidden w-full max-w-md -translate-x-1/2 bg-slate-950/95';
		el.innerHTML = `
			<div class="flex h-full flex-col pt-4">
				<div class="flex items-center justify-between px-3 text-white">
					<button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/14 text-white hover:bg-white/20" data-attachment-preview-media-clear aria-label="Batal lampiran">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
					</button>
					<p class="truncate px-2 text-center text-sm font-semibold text-white/90" data-attachment-preview-media-name></p>
					<div class="flex items-center gap-1.5" data-attachment-preview-tools>
						<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/14 text-lg font-semibold text-white hover:bg-white/20" data-preview-tool="sticker" data-preview-tool-button="1" aria-label="Tambah stiker">😀</button>
						<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/14 text-sm font-semibold text-white hover:bg-white/20" data-preview-tool="text" data-preview-tool-button="1" aria-label="Tambah teks">Aa</button>
						<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/14 text-sm font-semibold text-white hover:bg-white/20" data-preview-tool="doodle" data-preview-tool-button="1" aria-label="Coretan">✎</button>
					</div>
				</div>
				<div class="mt-2 flex flex-col gap-2 px-3">
					<div class="hidden items-center justify-end gap-1 rounded-full bg-white/14 px-2 py-1" data-preview-pen-sizes>
						<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-pen-size="2">S</button>
						<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-pen-size="4">M</button>
						<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-pen-size="7">L</button>
						<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-pen-size="11">XL</button>
					</div>
					<div class="hidden rounded-2xl bg-white/10 px-2 py-2" data-preview-sticker-controls>
						<div class="flex gap-1 overflow-x-auto pb-1" data-preview-sticker-list>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😀">😀</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😂">😂</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😍">😍</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😎">😎</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="👍">👍</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🙏">🙏</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🎉">🎉</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🔥">🔥</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="❤️">❤️</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😭">😭</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😡">😡</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="✨">✨</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🤩">🤩</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🥳">🥳</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🤯">🤯</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😴">😴</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🤖">🤖</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="💯">💯</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="✅">✅</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="❌">❌</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="💡">💡</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="💥">💥</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="⭐">⭐</button>
							<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🌈">🌈</button>
						</div>
						<div class="mt-1 flex items-center justify-end gap-1" data-preview-sticker-sizes>
							<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-sticker-size="30">S</button>
							<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-sticker-size="42">M</button>
							<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-sticker-size="56">L</button>
							<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-sticker-size="72">XL</button>
						</div>
					</div>
				</div>
				<div class="mt-3 flex min-h-0 flex-1 items-center justify-center px-3">
					<div class="relative w-full overflow-hidden rounded-2xl bg-black/50" data-attachment-preview-stage>
						<img data-attachment-preview-media-img class="hidden max-h-[60vh] w-full rounded-2xl object-contain" alt="Preview media" />
						<video data-attachment-preview-media-video class="hidden max-h-[60vh] w-full rounded-2xl bg-black object-contain" controls playsinline preload="metadata"></video>
						<div data-attachment-preview-annotations class="pointer-events-none absolute inset-0 z-[4]"></div>
						<canvas data-attachment-preview-doodle class="pointer-events-none absolute inset-0 z-[5] hidden h-full w-full rounded-2xl touch-none"></canvas>
					</div>
				</div>
				<p class="mt-2 hidden px-3 text-center text-xs text-white/80" data-attachment-preview-media-caption></p>
				<div class="px-3 pb-[max(env(safe-area-inset-bottom),12px)] pt-2">
					<div class="flex items-center gap-2 rounded-2xl bg-white/12 px-3 py-2.5 backdrop-blur">
						<input type="text" class="min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-white/60" data-attachment-preview-caption-input placeholder="Tambah keterangan..." />
						<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-emerald-950 hover:bg-emerald-400" data-attachment-preview-caption-send aria-label="Kirim lampiran">
							<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 3.105a.75.75 0 0 1 .818-.164l12 5.25a.75.75 0 0 1 0 1.374l-12 5.25A.75.75 0 0 1 2.9 14.18l1.2-4.18a.75.75 0 0 0 0-.4l-1.2-4.18a.75.75 0 0 1 .205-.815Z" /></svg>
						</button>
					</div>
				</div>
			</div>
			<div class="absolute inset-0 z-[75] hidden bg-slate-950/90 px-4 pb-6 pt-14" data-preview-text-editor>
				<div class="flex items-center justify-between text-white">
					<p class="text-sm font-semibold">Tambah teks</p>
					<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/12 text-white hover:bg-white/20" data-preview-text-close aria-label="Tutup editor teks">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
					</button>
				</div>
				<div class="mt-4 rounded-2xl bg-white/12 p-3">
					<input type="text" class="w-full rounded-xl border border-white/25 bg-black/20 px-3 py-3 text-base text-white outline-none placeholder:text-white/60" data-preview-text-input placeholder="Ketik teks" autocomplete="off" />
				</div>
				<div class="mt-3 flex items-center justify-between gap-3">
					<div class="flex items-center gap-1" data-preview-text-size-controls>
						<button type="button" class="h-8 w-8 rounded-full bg-white/20 text-[11px] font-semibold text-white" data-preview-text-size="24">S</button>
						<button type="button" class="h-8 w-8 rounded-full bg-white/20 text-[11px] font-semibold text-white" data-preview-text-size="32">M</button>
						<button type="button" class="h-8 w-8 rounded-full bg-white/20 text-[11px] font-semibold text-white" data-preview-text-size="42">L</button>
					</div>
					<div class="flex items-center gap-1" data-preview-text-color-controls>
						<button type="button" class="h-7 w-7 rounded-full border border-white/40 bg-white" data-preview-text-color="#ffffff" aria-label="Warna putih"></button>
						<button type="button" class="h-7 w-7 rounded-full border border-white/40 bg-yellow-300" data-preview-text-color="#fde047" aria-label="Warna kuning"></button>
						<button type="button" class="h-7 w-7 rounded-full border border-white/40 bg-cyan-300" data-preview-text-color="#67e8f9" aria-label="Warna cyan"></button>
						<button type="button" class="h-7 w-7 rounded-full border border-white/40 bg-rose-400" data-preview-text-color="#fb7185" aria-label="Warna rose"></button>
					</div>
				</div>
				<div class="mt-4 flex items-center justify-end gap-2">
					<button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-white/80 hover:bg-white/10" data-preview-text-cancel>Batal</button>
					<button type="button" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold text-emerald-950 hover:bg-emerald-400" data-preview-text-apply>Terapkan</button>
				</div>
			</div>
		`;

		el.querySelector('[data-attachment-preview-media-clear]')?.addEventListener('click', clearSelectedAttachment);

		const previewCaptionInput = el.querySelector('[data-attachment-preview-caption-input]');
		const previewCaptionSend = el.querySelector('[data-attachment-preview-caption-send]');
		const previewMediaCaption = el.querySelector('[data-attachment-preview-media-caption]');
		const previewDoodleCanvas = el.querySelector('[data-attachment-preview-doodle]');
		const previewStage = el.querySelector('[data-attachment-preview-stage]');
		const previewAnnotationLayer = el.querySelector('[data-attachment-preview-annotations]');
		const penSizeGroup = el.querySelector('[data-preview-pen-sizes]');
		const penSizeButtons = Array.from(el.querySelectorAll('[data-preview-pen-size]'));
		const stickerControls = el.querySelector('[data-preview-sticker-controls]');
		const stickerButtons = Array.from(el.querySelectorAll('[data-preview-sticker]'));
		const stickerSizeButtons = Array.from(el.querySelectorAll('[data-preview-sticker-size]'));
		const toolButtons = Array.from(el.querySelectorAll('[data-preview-tool-button="1"]'));
		const previewMediaImg = el.querySelector('[data-attachment-preview-media-img]');
		const previewMediaVideo = el.querySelector('[data-attachment-preview-media-video]');
		const textEditor = el.querySelector('[data-preview-text-editor]');
		const textInput = el.querySelector('[data-preview-text-input]');
		const textApplyButton = el.querySelector('[data-preview-text-apply]');
		const textCancelButton = el.querySelector('[data-preview-text-cancel]');
		const textCloseButton = el.querySelector('[data-preview-text-close]');
		const textSizeButtons = Array.from(el.querySelectorAll('[data-preview-text-size]'));
		const textColorButtons = Array.from(el.querySelectorAll('[data-preview-text-color]'));

		const syncComposerCaption = (text) => {
			if (!(mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
				return;
			}
			if (mentionInput.value === text) {
				return;
			}
			mentionInput.value = text;
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
		};

		if (previewCaptionInput instanceof HTMLInputElement) {
			previewCaptionInput.addEventListener('input', () => {
				syncComposerCaption(previewCaptionInput.value);
			});
		}

		if (previewCaptionSend instanceof HTMLButtonElement) {
			previewCaptionSend.addEventListener('click', () => {
				if (previewCaptionInput instanceof HTMLInputElement) {
					syncComposerCaption(previewCaptionInput.value.trim());
				}
				chatForm.requestSubmit();
			});
		}

		let doodleEnabled = false;
		let drawing = false;
		let placementMode = '';
		let penSize = 4;
		let selectedSticker = '😀';
		let stickerSize = 42;
		let textSize = 32;
		let textColor = '#ffffff';
		let selectedAnnotation = null;
		let pendingTextPlacement = null;
		let lastX = 0;
		let lastY = 0;

		const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

		const selectAnnotation = (node) => {
			if (selectedAnnotation && selectedAnnotation !== node) {
				selectedAnnotation.classList.remove('ring-2', 'ring-emerald-300', 'ring-offset-1', 'ring-offset-black/40');
			}

			selectedAnnotation = node instanceof HTMLElement ? node : null;
			if (selectedAnnotation) {
				selectedAnnotation.classList.add('ring-2', 'ring-emerald-300', 'ring-offset-1', 'ring-offset-black/40');
			}
		};

		const applyAnnotationSize = (node, nextSize) => {
			if (!(node instanceof HTMLElement) || !Number.isFinite(nextSize) || nextSize <= 0) {
				return;
			}

			node.style.fontSize = `${nextSize}px`;
			node.dataset.annotationSize = String(nextSize);
		};

		const makeAnnotationDraggable = (node) => {
			if (!(node instanceof HTMLElement) || !(previewStage instanceof HTMLElement)) {
				return;
			}

			node.classList.add('pointer-events-auto', 'cursor-move', 'touch-none', 'select-none');
			let dragOffsetX = 0;
			let dragOffsetY = 0;
			let draggingAnnotation = false;

			node.addEventListener('pointerdown', (event) => {
				if (doodleEnabled) {
					return;
				}

				event.preventDefault();
				event.stopPropagation();
				selectAnnotation(node);
				draggingAnnotation = true;
				const rect = node.getBoundingClientRect();
				dragOffsetX = event.clientX - (rect.left + (rect.width / 2));
				dragOffsetY = event.clientY - (rect.top + (rect.height / 2));
				node.setPointerCapture(event.pointerId);
			});

			node.addEventListener('pointermove', (event) => {
				if (!draggingAnnotation) {
					return;
				}

				event.preventDefault();
				const stageRect = previewStage.getBoundingClientRect();
				const x = clamp(event.clientX - stageRect.left - dragOffsetX, 0, stageRect.width);
				const y = clamp(event.clientY - stageRect.top - dragOffsetY, 0, stageRect.height);
				node.style.left = `${x}px`;
				node.style.top = `${y}px`;
			});

			const stopDragging = () => {
				draggingAnnotation = false;
			};

			node.addEventListener('pointerup', stopDragging);
			node.addEventListener('pointercancel', stopDragging);
		};

		const createStickerAnnotation = (x, y) => {
			if (!(previewAnnotationLayer instanceof HTMLElement)) {
				return;
			}

			const stickerNode = document.createElement('span');
			stickerNode.className = 'absolute -translate-x-1/2 -translate-y-1/2 drop-shadow';
			stickerNode.style.left = `${x}px`;
			stickerNode.style.top = `${y}px`;
			stickerNode.style.lineHeight = '1';
			stickerNode.textContent = selectedSticker;
			stickerNode.dataset.annotationType = 'sticker';
			applyAnnotationSize(stickerNode, stickerSize);
			previewAnnotationLayer.appendChild(stickerNode);
			makeAnnotationDraggable(stickerNode);
			selectAnnotation(stickerNode);
		};

		const createTextAnnotation = (x, y, textValue) => {
			if (!(previewAnnotationLayer instanceof HTMLElement)) {
				return;
			}

			const textNode = document.createElement('span');
			textNode.className = 'absolute max-w-[74%] -translate-x-1/2 -translate-y-1/2 rounded-md bg-black/45 px-2 py-1 text-center font-semibold shadow';
			textNode.style.left = `${x}px`;
			textNode.style.top = `${y}px`;
			textNode.style.wordBreak = 'break-word';
			textNode.style.color = textColor;
			textNode.textContent = textValue;
			textNode.dataset.annotationType = 'text';
			applyAnnotationSize(textNode, textSize);
			previewAnnotationLayer.appendChild(textNode);
			makeAnnotationDraggable(textNode);
			selectAnnotation(textNode);
		};

		const openTextEditor = (x, y) => {
			if (!(textEditor instanceof HTMLElement) || !(textInput instanceof HTMLInputElement)) {
				return;
			}

			pendingTextPlacement = { x, y };
			textEditor.classList.remove('hidden');
			textEditor.classList.add('flex', 'flex-col');
			textInput.value = '';
			window.setTimeout(() => textInput.focus(), 30);
		};

		const closeTextEditor = () => {
			if (!(textEditor instanceof HTMLElement)) {
				return;
			}

			textEditor.classList.add('hidden');
			textEditor.classList.remove('flex', 'flex-col');
			pendingTextPlacement = null;
		};

		const updateToolButtonState = () => {
			toolButtons.forEach((button) => {
				const tool = String(button.getAttribute('data-preview-tool') || '');
				const isActive = (tool === 'doodle' && doodleEnabled) || (tool !== 'doodle' && placementMode === tool);
				button.classList.toggle('bg-emerald-500/80', isActive);
				button.classList.toggle('text-emerald-950', isActive);
				button.classList.toggle('bg-white/14', !isActive);
				button.classList.toggle('text-white', !isActive);
			});

			if (penSizeGroup instanceof HTMLElement) {
				const showPenControls = doodleEnabled === true;
				penSizeGroup.classList.toggle('hidden', !showPenControls);
				penSizeGroup.classList.toggle('flex', showPenControls);
			}

			if (stickerControls instanceof HTMLElement) {
				const showStickerControls = !doodleEnabled && placementMode === 'sticker';
				stickerControls.classList.toggle('hidden', !showStickerControls);
			}
		};

		const updatePenSizeUi = () => {
			penSizeButtons.forEach((button) => {
				const size = Number(button.getAttribute('data-preview-pen-size') || 0);
				const active = Math.abs(size - penSize) < 0.1;
				button.classList.toggle('bg-emerald-500/80', active);
				button.classList.toggle('text-emerald-950', active);
				button.classList.toggle('bg-white/25', !active);
				button.classList.toggle('text-white', !active);
			});
		};

		const updateStickerUi = () => {
			stickerButtons.forEach((button) => {
				const isActive = button.getAttribute('data-preview-sticker') === selectedSticker;
				button.classList.toggle('bg-emerald-500/85', isActive);
				button.classList.toggle('bg-white/18', !isActive);
			});

			stickerSizeButtons.forEach((button) => {
				const size = Number(button.getAttribute('data-preview-sticker-size') || 0);
				const active = Math.abs(size - stickerSize) < 0.1;
				button.classList.toggle('bg-emerald-500/85', active);
				button.classList.toggle('text-emerald-950', active);
				button.classList.toggle('bg-white/25', !active);
				button.classList.toggle('text-white', !active);
			});
		};

		const updateTextEditorUi = () => {
			textSizeButtons.forEach((button) => {
				const size = Number(button.getAttribute('data-preview-text-size') || 0);
				const active = Math.abs(size - textSize) < 0.1;
				button.classList.toggle('bg-emerald-500/85', active);
				button.classList.toggle('text-emerald-950', active);
				button.classList.toggle('bg-white/20', !active);
				button.classList.toggle('text-white', !active);
			});

			textColorButtons.forEach((button) => {
				const color = String(button.getAttribute('data-preview-text-color') || '#ffffff').toLowerCase();
				const active = color === String(textColor).toLowerCase();
				button.classList.toggle('ring-2', active);
				button.classList.toggle('ring-emerald-300', active);
				button.classList.toggle('ring-offset-1', active);
				button.classList.toggle('ring-offset-black/70', active);
			});
		};

		const clearPreviewAnnotations = () => {
			if (previewAnnotationLayer instanceof HTMLElement) {
				previewAnnotationLayer.innerHTML = '';
			}
			if (previewDoodleCanvas instanceof HTMLCanvasElement) {
				const ctx = previewDoodleCanvas.getContext('2d');
				if (ctx) {
					ctx.clearRect(0, 0, previewDoodleCanvas.width, previewDoodleCanvas.height);
				}
			}
			doodleEnabled = false;
			placementMode = '';
			selectedAnnotation = null;
			closeTextEditor();
			if (previewMediaCaption instanceof HTMLElement) {
				previewMediaCaption.textContent = '';
				previewMediaCaption.classList.add('hidden');
			}
			if (previewDoodleCanvas instanceof HTMLCanvasElement) {
				previewDoodleCanvas.classList.add('hidden', 'pointer-events-none');
				previewDoodleCanvas.classList.remove('pointer-events-auto');
				previewDoodleCanvas.style.touchAction = '';
			}
			if (previewStage instanceof HTMLElement) {
				previewStage.style.touchAction = '';
			}
			updateToolButtonState();
		};

		const resizeDoodleCanvas = () => {
			if (!(previewDoodleCanvas instanceof HTMLCanvasElement) || !(previewStage instanceof HTMLElement)) {
				return;
			}
			const pixelRatio = Math.max(1, window.devicePixelRatio || 1);
			const width = Math.max(1, Math.floor(previewStage.clientWidth));
			const height = Math.max(1, Math.floor(previewStage.clientHeight));
			previewDoodleCanvas.width = Math.floor(width * pixelRatio);
			previewDoodleCanvas.height = Math.floor(height * pixelRatio);
			previewDoodleCanvas.style.width = `${width}px`;
			previewDoodleCanvas.style.height = `${height}px`;
			const ctx = previewDoodleCanvas.getContext('2d');
			if (!ctx) {
				return;
			}
			ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
			ctx.imageSmoothingEnabled = true;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			ctx.lineWidth = penSize;
			ctx.strokeStyle = '#f8fafc';
			ctx.globalCompositeOperation = 'source-over';
		};

		const getCanvasPoint = (event) => {
			if (!(previewDoodleCanvas instanceof HTMLCanvasElement)) {
				return null;
			}
			const rect = previewDoodleCanvas.getBoundingClientRect();
			return {
				x: event.clientX - rect.left,
				y: event.clientY - rect.top,
			};
		};

		const drawLine = (x1, y1, x2, y2) => {
			if (!(previewDoodleCanvas instanceof HTMLCanvasElement)) {
				return;
			}
			const ctx = previewDoodleCanvas.getContext('2d');
			if (!ctx) {
				return;
			}
			const distance = Math.hypot(x2 - x1, y2 - y1);
			const steps = Math.max(1, Math.ceil(distance / 0.45));
			ctx.beginPath();
			ctx.moveTo(x1, y1);
			for (let i = 1; i <= steps; i++) {
				const t = i / steps;
				ctx.lineTo(x1 + ((x2 - x1) * t), y1 + ((y2 - y1) * t));
			}
			ctx.stroke();
		};

		if (previewDoodleCanvas instanceof HTMLCanvasElement) {
			previewDoodleCanvas.addEventListener('pointerdown', (event) => {
				if (!doodleEnabled) {
					return;
				}
				event.preventDefault();
				const point = getCanvasPoint(event);
				if (!point) {
					return;
				}
				drawing = true;
				lastX = point.x;
				lastY = point.y;
				const ctx = previewDoodleCanvas.getContext('2d');
				if (ctx) {
					ctx.beginPath();
					ctx.fillStyle = '#f8fafc';
					ctx.arc(point.x, point.y, penSize / 2, 0, Math.PI * 2);
					ctx.fill();
				}
				previewDoodleCanvas.setPointerCapture(event.pointerId);
			});

			const handleDoodlePointerMove = (event) => {
				if (!doodleEnabled || !drawing) {
					return;
				}
				event.preventDefault();
				const coalesced = typeof event.getCoalescedEvents === 'function' ? event.getCoalescedEvents() : [event];
				coalesced.forEach((pointerEvent) => {
					const point = getCanvasPoint(pointerEvent);
					if (!point) {
						return;
					}
					drawLine(lastX, lastY, point.x, point.y);
					lastX = point.x;
					lastY = point.y;
				});
			};

			previewDoodleCanvas.addEventListener('pointermove', handleDoodlePointerMove);
			previewDoodleCanvas.addEventListener('pointerrawupdate', handleDoodlePointerMove);

			const stopDrawing = () => {
				drawing = false;
			};
			previewDoodleCanvas.addEventListener('pointerup', stopDrawing);
			previewDoodleCanvas.addEventListener('pointercancel', stopDrawing);
		}

		if (previewStage instanceof HTMLElement) {
			previewStage.addEventListener('click', (event) => {
				if (!placementMode || doodleEnabled || !(previewAnnotationLayer instanceof HTMLElement)) {
					return;
				}

				const rect = previewStage.getBoundingClientRect();
				const x = event.clientX - rect.left;
				const y = event.clientY - rect.top;

				if (!Number.isFinite(x) || !Number.isFinite(y)) {
					return;
				}

				if (placementMode === 'text') {
					openTextEditor(x, y);
					return;
				}

				if (placementMode === 'sticker') {
					createStickerAnnotation(x, y);
				}
			});
		}

		if (previewAnnotationLayer instanceof HTMLElement) {
			previewAnnotationLayer.addEventListener('click', (event) => {
				const target = event.target.closest('[data-annotation-type]');
				if (target instanceof HTMLElement) {
					event.stopPropagation();
					selectAnnotation(target);
					return;
				}

				selectAnnotation(null);
			}, true);
		}

		el.querySelector('[data-attachment-preview-tools]')?.addEventListener('click', (event) => {
			const toolButton = event.target.closest('[data-preview-tool]');
			if (!(toolButton instanceof HTMLButtonElement)) {
				return;
			}

			const tool = String(toolButton.getAttribute('data-preview-tool') || '');
			if (tool === 'sticker') {
				doodleEnabled = false;
				placementMode = placementMode === 'sticker' ? '' : 'sticker';
				if (previewDoodleCanvas instanceof HTMLCanvasElement) {
					previewDoodleCanvas.classList.add('hidden', 'pointer-events-none');
					previewDoodleCanvas.classList.remove('pointer-events-auto');
					previewDoodleCanvas.style.touchAction = '';
				}
				if (previewStage instanceof HTMLElement) {
					previewStage.style.touchAction = '';
				}
				updateToolButtonState();
				if (placementMode === 'sticker') {
					showActionToast('Tap media untuk menaruh emoji, lalu drag untuk geser');
				}
				return;
			}

			if (tool === 'text') {
				doodleEnabled = false;
				placementMode = placementMode === 'text' ? '' : 'text';
				if (previewDoodleCanvas instanceof HTMLCanvasElement) {
					previewDoodleCanvas.classList.add('hidden', 'pointer-events-none');
					previewDoodleCanvas.classList.remove('pointer-events-auto');
					previewDoodleCanvas.style.touchAction = '';
				}
				if (previewStage instanceof HTMLElement) {
					previewStage.style.touchAction = '';
				}
				updateToolButtonState();
				if (placementMode === 'text') {
					showActionToast('Tap media untuk menaruh teks');
				}
				return;
			}

			if (tool === 'doodle') {
				if (!(previewDoodleCanvas instanceof HTMLCanvasElement)) {
					return;
				}
				doodleEnabled = !doodleEnabled;
				placementMode = '';
				previewDoodleCanvas.classList.toggle('hidden', !doodleEnabled);
				previewDoodleCanvas.classList.toggle('pointer-events-none', !doodleEnabled);
				previewDoodleCanvas.classList.toggle('pointer-events-auto', doodleEnabled);
				previewDoodleCanvas.style.touchAction = doodleEnabled ? 'none' : '';
				if (previewStage instanceof HTMLElement) {
					previewStage.style.touchAction = doodleEnabled ? 'none' : '';
				}
				updateToolButtonState();
				if (doodleEnabled) {
					resizeDoodleCanvas();
					showActionToast('Mode coretan aktif');
				} else {
					showActionToast('Mode coretan dimatikan');
				}
			}
		});

		if (penSizeButtons.length > 0) {
			penSizeButtons.forEach((button) => {
				button.addEventListener('click', () => {
					const nextSize = Number(button.getAttribute('data-preview-pen-size') || penSize);
					if (!Number.isFinite(nextSize) || nextSize <= 0) {
						return;
					}
					penSize = nextSize;
					updatePenSizeUi();
					if (previewDoodleCanvas instanceof HTMLCanvasElement) {
						const ctx = previewDoodleCanvas.getContext('2d');
						if (ctx) {
							ctx.lineWidth = penSize;
						}
					}
					showActionToast(`Ketebalan pena: ${penSize}px`);
				});
			});
			updatePenSizeUi();
		}

		if (stickerButtons.length > 0) {
			stickerButtons.forEach((button) => {
				button.addEventListener('click', (event) => {
					event.stopPropagation();
					doodleEnabled = false;
					selectedSticker = String(button.getAttribute('data-preview-sticker') || '😀');
					updateStickerUi();
					updateToolButtonState();
					if (selectedAnnotation && selectedAnnotation.dataset.annotationType === 'sticker') {
						selectedAnnotation.textContent = selectedSticker;
					}
				});
			});
		}

		if (stickerSizeButtons.length > 0) {
			stickerSizeButtons.forEach((button) => {
				button.addEventListener('click', (event) => {
					event.stopPropagation();
					doodleEnabled = false;
					const nextSize = Number(button.getAttribute('data-preview-sticker-size') || stickerSize);
					if (!Number.isFinite(nextSize) || nextSize <= 0) {
						return;
					}

					stickerSize = nextSize;
					updateStickerUi();
					updateToolButtonState();
					if (selectedAnnotation && selectedAnnotation.dataset.annotationType === 'sticker') {
						applyAnnotationSize(selectedAnnotation, stickerSize);
					}
				});
			});
		}

		if (textSizeButtons.length > 0) {
			textSizeButtons.forEach((button) => {
				button.addEventListener('click', () => {
					const nextSize = Number(button.getAttribute('data-preview-text-size') || textSize);
					if (!Number.isFinite(nextSize) || nextSize <= 0) {
						return;
					}

					textSize = nextSize;
					updateTextEditorUi();
					if (selectedAnnotation && selectedAnnotation.dataset.annotationType === 'text') {
						applyAnnotationSize(selectedAnnotation, textSize);
					}
				});
			});
		}

		if (textColorButtons.length > 0) {
			textColorButtons.forEach((button) => {
				button.addEventListener('click', () => {
					textColor = String(button.getAttribute('data-preview-text-color') || '#ffffff');
					updateTextEditorUi();
					if (selectedAnnotation && selectedAnnotation.dataset.annotationType === 'text') {
						selectedAnnotation.style.color = textColor;
					}
				});
			});
		}

		const applyTextPlacement = () => {
			if (!(textInput instanceof HTMLInputElement) || !pendingTextPlacement) {
				closeTextEditor();
				return;
			}

			const value = String(textInput.value || '').trim();
			if (!value) {
				showActionToast('Teks belum diisi');
				textInput.focus();
				return;
			}

			createTextAnnotation(pendingTextPlacement.x, pendingTextPlacement.y, value);
			closeTextEditor();
			showActionToast('Teks ditambahkan, drag untuk atur posisi');
		};

		textApplyButton?.addEventListener('click', applyTextPlacement);
		textCancelButton?.addEventListener('click', closeTextEditor);
		textCloseButton?.addEventListener('click', closeTextEditor);
		textInput?.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
				applyTextPlacement();
			}
		});

		updateStickerUi();
		updateTextEditorUi();

		const syncCaptionFromComposer = () => {
			if (!(previewCaptionInput instanceof HTMLInputElement)) {
				return;
			}
			const composerValue = mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement
				? mentionInput.value
				: '';
			previewCaptionInput.value = composerValue;
		};

		window.addEventListener('resize', resizeDoodleCanvas);
		previewMediaImg?.addEventListener('load', resizeDoodleCanvas);
		previewMediaVideo?.addEventListener('loadedmetadata', resizeDoodleCanvas);
		el.addEventListener('preview:sync-caption', syncCaptionFromComposer);
		el.addEventListener('preview:resize-doodle', resizeDoodleCanvas);
		el.addEventListener('preview:reset-annotations', clearPreviewAnnotations);
		updateToolButtonState();

		document.body.appendChild(el);
		return el;
	};

	const refreshAttachmentLabel = () => {
		const file = attachmentInput.files?.[0];
		const filePreviewEl = ensureFileAttachmentPreviewEl();
		const mediaPreviewEl = ensureMediaAttachmentPreviewEl();
		const previewImg = mediaPreviewEl.querySelector('[data-attachment-preview-media-img]');
		const previewVideo = mediaPreviewEl.querySelector('[data-attachment-preview-media-video]');
		const previewMediaName = mediaPreviewEl.querySelector('[data-attachment-preview-media-name]');
		const previewMediaCaption = mediaPreviewEl.querySelector('[data-attachment-preview-media-caption]');
		const previewMediaCaptionInput = mediaPreviewEl.querySelector('[data-attachment-preview-caption-input]');
		const previewFileName = filePreviewEl.querySelector('[data-attachment-preview-file-name]');
		const previewFileSize = filePreviewEl.querySelector('[data-attachment-preview-file-size]');

		revokeAttachmentPreviewUrl();

		if (previewImg instanceof HTMLImageElement) {
			previewImg.src = '';
			previewImg.classList.add('hidden');
		}
		if (previewVideo instanceof HTMLVideoElement) {
			previewVideo.pause();
			previewVideo.removeAttribute('src');
			previewVideo.load();
			previewVideo.classList.add('hidden');
		}
		mediaPreviewEl.dispatchEvent(new Event('preview:reset-annotations'));
		if (previewMediaCaptionInput instanceof HTMLInputElement) {
			previewMediaCaptionInput.value = mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement
				? mentionInput.value
				: '';
		}
		mediaPreviewEl.dispatchEvent(new Event('preview:resize-doodle'));

		if (!file) {
			mediaPreviewEl.classList.add('hidden');
			filePreviewEl.classList.add('hidden');
			filePreviewEl.classList.remove('flex');
			refreshSendVoiceToggle();
			return;
		}

		const lowerMime = String(file.type || '').toLowerCase();
		const isImage = lowerMime.startsWith('image/');
		const isVideo = lowerMime.startsWith('video/');

		if (isImage || isVideo) {
			attachmentPreviewUrl = URL.createObjectURL(file);
			if (previewMediaName instanceof HTMLElement) {
				previewMediaName.textContent = file.name;
			}
			if (previewMediaCaption instanceof HTMLElement) {
				previewMediaCaption.textContent = '';
				previewMediaCaption.classList.add('hidden');
			}

			if (isImage && previewImg instanceof HTMLImageElement) {
				previewImg.src = attachmentPreviewUrl;
				previewImg.classList.remove('hidden');
			}

			if (isVideo && previewVideo instanceof HTMLVideoElement) {
				previewVideo.src = attachmentPreviewUrl;
				previewVideo.classList.remove('hidden');
			}

			filePreviewEl.classList.add('hidden');
			filePreviewEl.classList.remove('flex');
			mediaPreviewEl.classList.remove('hidden');
			mediaPreviewEl.dispatchEvent(new Event('preview:sync-caption'));
			mediaPreviewEl.dispatchEvent(new Event('preview:resize-doodle'));
			refreshSendVoiceToggle();
			return;
		}

		if (previewFileName instanceof HTMLElement) {
			previewFileName.textContent = file.name;
		}
		if (previewFileSize instanceof HTMLElement) {
			previewFileSize.textContent = formatFileSize(file.size);
		}

		mediaPreviewEl.classList.add('hidden');
		filePreviewEl.classList.remove('hidden');
		filePreviewEl.classList.add('flex');
		refreshSendVoiceToggle();
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

		if (replyQuoteInput instanceof HTMLInputElement) {
			replyQuoteInput.value = '';
		}

		if (replyPreview instanceof HTMLElement) {
			replyPreview.classList.add('hidden');
			replyPreview.classList.remove('flex');
		}

		if (editingMessageId > 0) {
			clearEditingState();
		}
	};

	const normalizeReplyQuote = (value) => String(value || '')
		.replace(/\s+/g, ' ')
		.trim()
		.slice(0, 500);

	const getSelectedReplyQuoteFromMessage = (messageNode) => {
		if (!(messageNode instanceof HTMLElement)) {
			return '';
		}

		const selection = window.getSelection();
		if (!selection || selection.rangeCount === 0) {
			return '';
		}

		const selectedText = normalizeReplyQuote(selection.toString() || '');
		if (selectedText === '') {
			return '';
		}

		const selectionRange = selection.getRangeAt(0);
		const commonNode = selectionRange.commonAncestorContainer;
		if (!messageNode.contains(commonNode)) {
			return '';
		}

		return selectedText;
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
		const selectedQuote = getSelectedReplyQuoteFromMessage(messageNode);

		replyToInput.value = messageId;
		if (replyQuoteInput instanceof HTMLInputElement) {
			replyQuoteInput.value = selectedQuote;
		}
		clearEditingState();
		if (replyPreviewSender instanceof HTMLElement) {
			replyPreviewSender.textContent = `Membalas ${sender}`;
		}
		if (replyPreviewContent instanceof HTMLElement) {
			replyPreviewContent.textContent = selectedQuote || content;
		}
		if (replyPreview instanceof HTMLElement) {
			replyPreview.classList.remove('hidden');
			replyPreview.classList.add('flex');
		}

		const selection = window.getSelection();
		if (selection && selectedQuote !== '') {
			selection.removeAllRanges();
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
		const normalizedSelectedText = normalizeReplyQuote(selectedText);
		const content = normalizedSelectedText || (rawContent !== '' ? rawContent : fallback);

		replyToInput.value = messageId;
		if (replyQuoteInput instanceof HTMLInputElement) {
			replyQuoteInput.value = normalizedSelectedText;
		}
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
				<p class="pointer-events-none absolute inset-x-0 bottom-6 px-4 text-center text-xs font-medium text-white/70">Geser video ke arah mana saja untuk menutup</p>
			`
			: `
				<div class="mx-auto flex h-full w-full max-w-md touch-none items-center justify-center overflow-hidden p-3" data-media-swipe-stage="1">
					<img src="${safeUrl}" alt="${safeName}" class="max-h-full w-full select-none rounded-2xl object-contain" draggable="false" data-media-preview-el="1" />
				</div>
				<p class="pointer-events-none absolute inset-x-0 bottom-6 px-4 text-center text-xs font-medium text-white/70">Geser gambar ke arah mana saja untuk menutup</p>
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
			showActionToast('Lampiran diunduh');
		} catch (_error) {
			showActionToast('Gagal mengunduh lampiran');
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
					showActionToast('Pesan yang direply tidak ditemukan');
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
				showActionToast('Vote poll belum tersedia');
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
				showActionToast('Vote tersimpan');
			} catch (_error) {
				showActionToast('Gagal mengirim vote');
			} finally {
				delete voteButton.dataset.pollSubmitting;
				voteButton.disabled = false;
			}
		});
	}

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
	let activePopupCleanup = null;
	const dismissPopup = () => {
		if (typeof activePopupCleanup === 'function') {
			activePopupCleanup();
		}
		activePopupCleanup = null;
		if (activePopup) {
			activePopup.remove();
			activePopup = null;
		}
	};

	const showSelectTextPopup = (messageNode, sender, messageText) => {
		dismissPopup();
		const safeMessageText = String(messageText || '').trim();
		const selectOverlay = document.createElement('div');
		selectOverlay.className = 'nc-popup-overlay';
		selectOverlay.innerHTML = `
			<div class="nc-popup-card" onclick="event.stopPropagation()">
				<div class="nc-popup-header">
					<span class="nc-popup-sender">${escapeHtml(sender)}</span>
					<button type="button" class="nc-popup-close" data-popup-close>&times;</button>
				</div>
				<div class="nc-popup-body nc-popup-body--quote" data-quote-picker-body>
					<p class="nc-quote-picker" data-quote-picker-words>${escapeHtml(safeMessageText)}</p>
					<p class="nc-quote-result" data-quote-picker-result>Pilih teks langsung seperti biasa, lalu tekan tombol di bawah.</p>
				</div>
				<div class="nc-popup-actions">
					<button type="button" class="nc-popup-btn" data-popup-reply-sel disabled>Balas teks terpilih</button>
				</div>
			</div>
		`;
		document.body.appendChild(selectOverlay);
		activePopup = selectOverlay;

		const wordsContainer = selectOverlay.querySelector('[data-quote-picker-words]');
		const resultEl = selectOverlay.querySelector('[data-quote-picker-result]');
		const btnReplySel = selectOverlay.querySelector('[data-popup-reply-sel]');
		const getSelectedQuote = () => {
			const selection = window.getSelection();
			if (!selection || selection.rangeCount === 0) {
				return '';
			}

			const selectedText = String(selection.toString() || '').trim();
			if (selectedText === '' || !(wordsContainer instanceof HTMLElement)) {
				return '';
			}

			const selectionRange = selection.getRangeAt(0);
			const commonNode = selectionRange.commonAncestorContainer;
			if (!wordsContainer.contains(commonNode)) {
				return '';
			}

			return selectedText;
		};

		const renderSelectionState = () => {
			const quote = getSelectedQuote();
			if (resultEl instanceof HTMLElement) {
				resultEl.textContent = quote !== ''
					? `"${quote}"`
					: 'Pilih teks langsung seperti biasa, lalu tekan tombol di bawah.';
			}

			if (btnReplySel instanceof HTMLButtonElement) {
				btnReplySel.disabled = quote === '';
			}
		};

		renderSelectionState();
		document.addEventListener('selectionchange', renderSelectionState);
		activePopupCleanup = () => {
			document.removeEventListener('selectionchange', renderSelectionState);
		};

		const cleanup = () => {
			activePopupCleanup = null;
			document.removeEventListener('selectionchange', renderSelectionState);
			dismissPopup();
		};

		selectOverlay.addEventListener('click', (e) => {
			if (e.target === selectOverlay) {
				cleanup();
			}
		});
		selectOverlay.querySelector('[data-popup-close]')?.addEventListener('click', cleanup);
		btnReplySel?.addEventListener('click', () => {
			const text = getSelectedQuote();
			cleanup();
			if (text) {
				setReplyTargetWithText(messageNode, text);
			}
		});
	};

	const viewerIsModerator = String(chatFeed?.getAttribute('data-viewer-is-moderator') || '0') === '1';

	const showConfirmDialog = ({ title, message, confirmLabel = 'Hapus', cancelLabel = 'Batal', danger = true, checkboxLabel = '', checkboxDefault = false, checkboxDisabled = false } = {}) => new Promise((resolve) => {
		const overlay = document.createElement('div');
		overlay.className = 'fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/45 px-5';
		overlay.innerHTML = `
			<div class="w-full max-w-xs rounded-2xl bg-white p-5 shadow-2xl">
				<p class="text-sm font-semibold text-slate-900">${escapeHtml(title || 'Konfirmasi')}</p>
				${message ? `<p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">${escapeHtml(message)}</p>` : ''}
				${checkboxLabel ? `
					<label class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-[13px] text-slate-700 ${checkboxDisabled ? 'opacity-50' : 'cursor-pointer'}">
						<input type="checkbox" data-confirm-checkbox class="h-4 w-4 rounded border-slate-300" ${checkboxDefault ? 'checked' : ''} ${checkboxDisabled ? 'disabled' : ''}>
						<span>${escapeHtml(checkboxLabel)}</span>
					</label>
				` : ''}
				<div class="mt-4 flex items-center justify-end gap-2">
					<button type="button" data-confirm-cancel class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">${escapeHtml(cancelLabel)}</button>
					<button type="button" data-confirm-ok class="rounded-xl px-3 py-2 text-xs font-bold text-white ${danger ? 'bg-rose-500 hover:bg-rose-600' : 'bg-indigo-600 hover:bg-indigo-700'}">${escapeHtml(confirmLabel)}</button>
				</div>
			</div>
		`;
		const checkbox = overlay.querySelector('[data-confirm-checkbox]');
		const cleanup = (confirmed) => {
			const checked = checkbox instanceof HTMLInputElement ? checkbox.checked : false;
			overlay.remove();
			resolve(confirmed ? { confirmed: true, checked } : { confirmed: false, checked: false });
		};
		overlay.addEventListener('click', (e) => { if (e.target === overlay) cleanup(false); });
		overlay.querySelector('[data-confirm-cancel]').addEventListener('click', () => cleanup(false));
		overlay.querySelector('[data-confirm-ok]').addEventListener('click', () => cleanup(true));
		document.body.appendChild(overlay);
	});

	const loadHiddenMessageIds = () => {
		try {
			const raw = window.localStorage.getItem(hiddenMessagesStorageKey);
			const arr = raw ? JSON.parse(raw) : [];
			return new Set(Array.isArray(arr) ? arr.map(String) : []);
		} catch (_e) { return new Set(); }
	};
	const persistHiddenMessageIds = (set) => {
		try { window.localStorage.setItem(hiddenMessagesStorageKey, JSON.stringify(Array.from(set))); } catch (_e) {}
	};
	const hiddenMessageIds = loadHiddenMessageIds();
	const hideMessageLocally = (messageId) => {
		const id = String(messageId);
		hiddenMessageIds.add(id);
		persistHiddenMessageIds(hiddenMessageIds);
		const node = document.querySelector(`[data-message-id="${id}"]`);
		if (node instanceof HTMLElement) node.classList.add('hidden');
	};
	if (chatFeed instanceof HTMLElement && hiddenMessageIds.size > 0) {
		hiddenMessageIds.forEach((id) => {
			const node = chatFeed.querySelector(`[data-message-id="${id}"]`);
			if (node instanceof HTMLElement) node.classList.add('hidden');
		});
	}

	const showMessagePopup = (messageNode) => {
		dismissPopup();
		const rawContent = String(messageNode.dataset.messageContent || '').trim();
		const type = String(messageNode.dataset.messageType || 'text').toLowerCase();
		const fallback = type === 'image' ? '[Gambar]' : (type === 'voice' ? '[Voice note]' : '[Lampiran]');
		const messageText = rawContent !== '' ? rawContent : fallback;
		const sender = String(messageNode.dataset.messageSenderName || 'User');
		const isMine = messageNode.closest('.flex.justify-end') !== null;
		const canEdit = isMine && type === 'text' && rawContent !== '';
		const canDelete = viewerIsModerator;

		const overlay = document.createElement('div');
		overlay.className = 'nc-popup-overlay';
		overlay.innerHTML = `
			<div class="nc-popup-card" onclick="event.stopPropagation()">
				<div class="nc-popup-preview">${escapeHtml(messageText.length > 200 ? messageText.slice(0, 200) + '...' : messageText)}</div>
				<div class="nc-popup-menu">
					<button type="button" class="nc-popup-menu-item" data-popup-reply>
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 0 1 0 10H9m-6-10 4-4m-4 4 4 4"/></svg>
						Balas
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
					${canDelete ? `<button type="button" class="nc-popup-menu-item nc-popup-menu-danger" data-popup-delete>
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
			const result = await showConfirmDialog({
				title: 'Hapus pesan?',
				message: 'Pesan ini akan dihapus untuk semua anggota grup.',
				confirmLabel: 'Hapus',
				cancelLabel: 'Batal',
				danger: true,
			});
			if (!result.confirmed) {
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
				showActionToast('Pesan dihapus untuk semua');
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
		let swipeReplyReady = false;
		let swipeReplyHapticSent = false;

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
			swipeReplyReady = false;
			swipeReplyHapticSent = false;
			clearLongPress();
		};

		const commitGestureOnRelease = () => {
			if (!(activeNode instanceof HTMLElement)) {
				return;
			}

			const targetNode = activeNode;
			const shouldReply = swipeReplyReady;
			clearActive();

			if (shouldReply) {
				setReplyTarget(targetNode);
			}
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
			swipeReplyReady = false;
			swipeReplyHapticSent = false;
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
				const passesThreshold = deltaX > SWIPE_THRESHOLD && deltaX > Math.abs(deltaY) + 8;
				swipeReplyReady = passesThreshold;
				if (passesThreshold && !swipeReplyHapticSent) {
					try { navigator.vibrate?.(10); } catch (_) {}
					swipeReplyHapticSent = true;
				}
				if (event.cancelable) event.preventDefault();
			} else if (deltaX < -6) {
				// block left swipe — just cancel
				clearActive();
				return;
			} else {
				swipeReplyReady = false;
			}
		}, { passive: false });

		chatFeed.addEventListener('pointerup', commitGestureOnRelease);
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
		attachmentInput.accept = 'image/*,video/*,audio/*';
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
		attachmentInput.accept = 'image/*,video/*';
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

	attachMenu?.querySelector('[data-attach-poll]')?.addEventListener('click', () => {
		attachMenu.classList.add('hidden');
		if (typeof openPollComposerFromAny === 'function') {
			openPollComposerFromAny();
			return;
		}

		showActionToast('Menu polling belum tersedia');
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

		mentionInput.addEventListener('focus', () => {
			window.setTimeout(() => scrollChatToBottom('auto'), 70);
			window.setTimeout(() => scrollChatToBottom('auto'), 220);
		});

		mentionInput.addEventListener('keydown', (event) => {
			if (event.key !== 'Escape' || editingMessageId <= 0) {
				return;
			}

			event.preventDefault();
			clearEditingState(true);
			showActionToast('Mode edit dibatalkan');
		});

		mentionInput.addEventListener('input', resizeComposerInput);
		const composerVoiceBtn = chatForm.querySelector('[data-composer-voice-btn]');
		refreshSendVoiceToggle = () => {
			const hasText = mentionInput.value.trim().length > 0;
			const hasAttachment = attachmentInput instanceof HTMLInputElement && (attachmentInput.files?.length || 0) > 0;
			const canSend = hasText || hasAttachment;
			if (sendButton instanceof HTMLElement) {
				sendButton.classList.toggle('hidden', !canSend);
			}
			if (composerVoiceBtn instanceof HTMLElement) {
				composerVoiceBtn.classList.toggle('hidden', canSend);
			}
		};
		mentionInput.addEventListener('input', refreshSendVoiceToggle);
		refreshSendVoiceToggle();
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
	});

	const setSendingUi = (isSending) => {
		if (!(sendButton instanceof HTMLButtonElement)) {
			return;
		}

		sendButton.disabled = isSending;
		sendButton.classList.toggle('opacity-70', isSending);
		sendButton.classList.toggle('cursor-wait', isSending);
	};

	const appendPendingMessageNode = (content, file, replyContext = null) => {
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
			reply_to: replyContext && replyContext.id ? replyContext : null,
			attachment_url: blobUrl,
			attachment_mime: file instanceof File ? file.type : null,
			attachment_original_name: file instanceof File ? file.name : null,
			attachment_size: file instanceof File ? file.size : null,
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
		scrollChatToBottom('auto');
		markGroupRead(Number(serverMessage?.id || 0));
		refreshPollCardsFromFeed();
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

		const replyToId = replyToInput instanceof HTMLInputElement
			? Number(replyToInput.value || 0)
			: 0;
		const replyQuoteText = replyQuoteInput instanceof HTMLInputElement
			? normalizeReplyQuote(replyQuoteInput.value)
			: '';
		let pendingReplyContext = null;
		if (replyToId > 0 && chatFeed instanceof HTMLElement) {
			const replySource = chatFeed.querySelector(`[data-message-id="${replyToId}"]`);
			if (replySource instanceof HTMLElement) {
				const sourceType = String(replySource.dataset.messageType || 'text').toLowerCase();
				const sourceRawContent = String(replySource.dataset.messageContent || '').trim();
				const sourceFallback = sourceType === 'image'
					? '[Gambar]'
					: (sourceType === 'voice' ? '[Voice note]' : '[Lampiran]');
				pendingReplyContext = {
					id: replyToId,
					sender_name: String(replySource.dataset.messageSenderName || 'User'),
					message_type: sourceType,
					content: sourceRawContent !== '' ? sourceRawContent : sourceFallback,
					quote_text: replyQuoteText,
				};
			}
		}

		const formData = new FormData(chatForm);
		const localId = appendPendingMessageNode(contentText, selectedFile, pendingReplyContext);

		if (mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement) {
			mentionInput.value = '';
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
		}
		attachmentInput.value = '';
		if (cameraInput instanceof HTMLInputElement) {
			cameraInput.value = '';
		}
		refreshAttachmentLabel();
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

const settingsMenu = document.querySelector('[data-settings-menu]');
const settingsOverlay = document.querySelector('[data-settings-overlay]');
const openSettingsButtons = Array.from(document.querySelectorAll('[data-open-settings]'));
const quickDeleteOverlay = document.querySelector('[data-delete-group-menu-overlay]');
const quickDeleteClose = document.querySelector('[data-close-delete-group-menu]');
const quickDeleteInput = document.querySelector('[data-delete-group-menu-confirm-input]');
const quickDeleteSubmit = document.querySelector('[data-delete-group-menu-submit]');

if (settingsMenu && settingsOverlay && openSettingsButtons.length > 0) {
	let menuOpen = false;
	const applySettingsMenuTheme = () => {
		if (!(settingsMenu instanceof HTMLElement)) {
			return;
		}

		const styleSource = chatShell instanceof HTMLElement ? chatShell : document.documentElement;
		const primary = getComputedStyle(styleSource).getPropertyValue('--nc-primary').trim() || '#0f766e';
		settingsMenu.style.background = primary;
		settingsMenu.style.boxShadow = '0 16px 40px rgba(2, 6, 23, 0.35)';
	};

	const formatPollMessage = (question, options) => {
		const lines = [`📊 POLL: ${question}`, ''];
		options.forEach((option, index) => {
			lines.push(`${index + 1}. ${option}`);
		});
		lines.push('', 'Balas dengan angka pilihan kamu.');
		return lines.join('\n');
	};

	const openPollComposer = () => {
		if (!chatMessagesBaseUrl) {
			showMenuToast('Form polling hanya tersedia di halaman chat');
			return;
		}

		const overlay = document.createElement('div');
		overlay.className = 'fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/45 px-5';
		overlay.innerHTML = `
			<div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
				<p class="text-sm font-semibold text-slate-900">Create Poll</p>
				<p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">Buat polling cepat dan kirim ke grup.</p>
				<div class="mt-3 space-y-2">
					<input type="text" class="input-field" data-poll-question placeholder="Pertanyaan polling" autocomplete="off" />
					<input type="text" class="input-field" data-poll-option="0" placeholder="Opsi 1" autocomplete="off" />
					<input type="text" class="input-field" data-poll-option="1" placeholder="Opsi 2" autocomplete="off" />
					<input type="text" class="input-field" data-poll-option="2" placeholder="Opsi 3 (opsional)" autocomplete="off" />
					<input type="text" class="input-field" data-poll-option="3" placeholder="Opsi 4 (opsional)" autocomplete="off" />
				</div>
				<div class="mt-4 flex items-center justify-end gap-2">
					<button type="button" data-poll-cancel class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
					<button type="button" data-poll-send class="rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700">Kirim Poll</button>
				</div>
			</div>
		`;

		const questionInput = overlay.querySelector('[data-poll-question]');
		const optionInputs = Array.from(overlay.querySelectorAll('[data-poll-option]'));
		const cancelBtn = overlay.querySelector('[data-poll-cancel]');
		const sendBtn = overlay.querySelector('[data-poll-send]');

		const close = () => {
			overlay.remove();
		};

		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				close();
			}
		});
		cancelBtn?.addEventListener('click', close);

		sendBtn?.addEventListener('click', async () => {
			const question = String(questionInput instanceof HTMLInputElement ? questionInput.value : '').trim();
			const options = optionInputs
				.map((input) => String(input instanceof HTMLInputElement ? input.value : '').trim())
				.filter((item) => item !== '');

			if (!question) {
				showMenuToast('Pertanyaan polling belum diisi');
				questionInput?.focus();
				return;
			}

			if (options.length < 2) {
				showMenuToast('Minimal 2 opsi polling');
				optionInputs[0]?.focus();
				return;
			}

			sendBtn.disabled = true;
			sendBtn.classList.add('opacity-70', 'cursor-wait');
			try {
				const result = await chatApiRequest(chatMessagesBaseUrl, {
					method: 'POST',
					body: {
						content: formatPollMessage(question, options),
					},
				});

				if (result?.message && chatFeed) {
					const duplicate = chatFeed.querySelector(`[data-message-id="${result.message.id}"]`);
					if (!duplicate) {
						const node = buildMessageNode(result.message);
						node.setAttribute('data-message-id', String(result.message.id));
						node.id = `message-${result.message.id}`;
						if (typingIndicator && typingIndicator.parentNode === chatFeed) {
							chatFeed.insertBefore(node, typingIndicator);
						} else {
							chatFeed.appendChild(node);
						}
						initVoicePlayers(node);
						renderMermaidInNode(node);
						latestKnownMessageId = Math.max(latestKnownMessageId, Number(result.message.id || 0));
						scrollChatToBottom('auto');
						markGroupRead(Number(result.message.id || 0));
						refreshPollCardsFromFeed();
					}
				}

				showMenuToast('Poll berhasil dikirim');
				close();
			} catch (_error) {
				showMenuToast('Gagal mengirim poll');
				sendBtn.disabled = false;
				sendBtn.classList.remove('opacity-70', 'cursor-wait');
			}
		});

		document.body.appendChild(overlay);
		if (questionInput instanceof HTMLInputElement) {
			questionInput.focus();
		}
	};

	openPollComposerFromAny = openPollComposer;

	const showMenuToast = (message) => {
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

	const openMenu = () => {
		menuOpen = true;
		applySettingsMenuTheme();
		document.body.style.overflow = '';
		settingsMenu.classList.remove('hidden');
	};

	const closeMenu = () => {
		menuOpen = false;
		document.body.style.overflow = '';
		settingsMenu.classList.add('hidden');
	};

	document.addEventListener('click', (event) => {
		if (!menuOpen) return;
		if (settingsMenu.contains(event.target)) return;
		if (event.target.closest('[data-open-settings]')) return;
		closeMenu();
	}, true);

	openSettingsButtons.forEach((openSettingsButton) => {
		openSettingsButton.addEventListener('click', (event) => {
			event.preventDefault();
			if (menuOpen) {
				closeMenu();
				return;
			}
			openMenu();
		});
	});

	settingsOverlay.addEventListener('click', closeMenu);
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && menuOpen) {
			closeMenu();
		}
	});

	settingsMenu.addEventListener('click', (event) => {
		const target = event.target.closest('button,a');
		if (!target) {
			return;
		}

		if (target.matches('[data-menu-clear-history]')) {
			event.preventDefault();
			const confirmClear = window.confirm('Clear history lokal di perangkat ini?');
			if (confirmClear && chatFeed) {
				const hiddenIds = [];
				chatFeed.querySelectorAll('[data-message-id]').forEach((node) => {
					const messageId = String(node.getAttribute('data-message-id') || '').trim();
					if (messageId) {
						hiddenIds.push(messageId);
					}
					node.classList.add('hidden');
				});
				try {
					window.localStorage.setItem(hiddenMessagesStorageKey, JSON.stringify(Array.from(new Set(hiddenIds))));
				} catch (_error) {
					// Ignore storage write failures silently.
				}
				showMenuToast('History lokal dibersihkan');
			}
			closeMenu();
			return;
		}

		if (target.matches('[data-open-search]')) {
			closeMenu();
			return;
		}

		if (target.matches('[data-open-delete-group-menu]')) {
			event.preventDefault();
			closeMenu();
			if (quickDeleteOverlay instanceof HTMLElement) {
				quickDeleteOverlay.classList.remove('hidden');
				quickDeleteOverlay.classList.add('flex');
				if (quickDeleteInput instanceof HTMLInputElement) {
					quickDeleteInput.value = '';
					quickDeleteInput.focus();
				}
				if (quickDeleteSubmit instanceof HTMLButtonElement) {
					quickDeleteSubmit.disabled = true;
				}
			}
			return;
		}

		closeMenu();
	});

	if (
		quickDeleteOverlay instanceof HTMLElement
		&& quickDeleteClose instanceof HTMLElement
		&& quickDeleteInput instanceof HTMLInputElement
		&& quickDeleteSubmit instanceof HTMLButtonElement
	) {
		const expected = String(document.querySelector('[data-chat-shell]')?.getAttribute('data-group-name') || '').trim();
		const hideDeleteOverlay = () => {
			quickDeleteOverlay.classList.add('hidden');
			quickDeleteOverlay.classList.remove('flex');
		};
		quickDeleteClose.addEventListener('click', hideDeleteOverlay);
		quickDeleteOverlay.addEventListener('click', (event) => {
			if (event.target === quickDeleteOverlay) {
				hideDeleteOverlay();
			}
		});
		quickDeleteInput.addEventListener('input', () => {
			quickDeleteSubmit.disabled = quickDeleteInput.value.trim() !== expected;
		});
	}
}

// Message search inside a group
(() => {
	const bar = document.querySelector('[data-message-search-bar]');
	const input = document.querySelector('[data-message-search-input]');
	const countEl = document.querySelector('[data-message-search-count]');
	const openBtn = document.querySelector('[data-open-search]');
	const closeBtn = document.querySelector('[data-close-search]');
	const feed = document.querySelector('[data-chat-feed]');
	if (!bar || !input || !openBtn || !closeBtn || !feed) return;

	const show = () => {
		bar.classList.remove('hidden');
		bar.classList.add('flex');
		input.focus();
	};
	const hide = () => {
		bar.classList.add('hidden');
		bar.classList.remove('flex');
		input.value = '';
		applyFilter('');
	};
	const applyFilter = (raw) => {
		const q = raw.trim().toLowerCase();
		const nodes = feed.querySelectorAll('[data-message-id]');
		let matches = 0;
		clearSearchHighlightsInFeed(feed);
		nodes.forEach((node) => {
			if (!q) {
				node.classList.remove('hidden');
				return;
			}
			const content = String(node.dataset.messageContent || '').toLowerCase();
			const sender = String(node.dataset.messageSenderName || '').toLowerCase();
			const match = content.includes(q) || sender.includes(q);
			node.classList.toggle('hidden', !match);
			if (match) {
				highlightSearchInMessage(node, raw.trim());
				matches++;
			}
		});
		if (countEl) countEl.textContent = q ? `${matches} hasil` : '';
	};

	openBtn.addEventListener('click', show);
	closeBtn.addEventListener('click', hide);
	input.addEventListener('input', () => applyFilter(input.value));
	input.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });
})();

// Group list search on home
(() => {
	const searchInput = document.querySelector('[data-group-search-input]');
	const emptyEl = document.querySelector('[data-group-search-empty]');
	if (!searchInput) return;
	const cards = Array.from(document.querySelectorAll('[data-group-card]'));
	searchInput.addEventListener('input', () => {
		const q = searchInput.value.trim().toLowerCase();
		let matches = 0;
		cards.forEach((card) => {
			const hay = String(card.getAttribute('data-group-search') || '').toLowerCase();
			const show = !q || hay.includes(q);
			card.classList.toggle('hidden', !show);
			if (show) matches++;
		});
		if (emptyEl) emptyEl.classList.toggle('hidden', matches > 0 || !q);
	});
})();

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

		const setButtonLabelTemporarily = (label, timeout = 1300) => {
			const prev = button.textContent;
			button.textContent = label;
			window.setTimeout(() => {
				button.textContent = prev;
			}, timeout);
		};

		const sharePayload = {
			title: `Join #${groupName}`,
			text: `Gabung ke group #${groupName} di Normchat`,
			url,
		};

		const openFallbackShareMenu = () => {
			const message = `Gabung ke group #${groupName} di Normchat`;
			const encodedMessage = encodeURIComponent(`${message}\n${url}`);
			const encodedTextOnly = encodeURIComponent(message);
			const encodedUrl = encodeURIComponent(url);

			const backdrop = document.createElement('div');
			backdrop.className = 'fixed inset-0 z-[90] bg-slate-900/40 p-4';

			const panel = document.createElement('div');
			panel.className = 'absolute bottom-4 left-4 right-4 rounded-2xl bg-white p-3 shadow-2xl';
			panel.innerHTML = `
				<p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Bagikan Grup</p>
				<div class="grid grid-cols-2 gap-2 text-sm">
					<a class="rounded-xl border border-slate-200 px-3 py-2 font-semibold text-slate-700" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=${encodedMessage}">WhatsApp</a>
					<a class="rounded-xl border border-slate-200 px-3 py-2 font-semibold text-slate-700" target="_blank" rel="noopener noreferrer" href="https://t.me/share/url?url=${encodedUrl}&text=${encodedTextOnly}">Telegram</a>
					<a class="rounded-xl border border-slate-200 px-3 py-2 font-semibold text-slate-700" target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?text=${encodedMessage}">X</a>
					<a class="rounded-xl border border-slate-200 px-3 py-2 font-semibold text-slate-700" target="_blank" rel="noopener noreferrer" href="mailto:?subject=${encodeURIComponent(`Join #${groupName}`)}&body=${encodedMessage}">Email</a>
				</div>
				<div class="mt-2 grid grid-cols-2 gap-2 text-sm">
					<button type="button" data-share-copy-link="1" class="rounded-xl bg-emerald-600 px-3 py-2 font-semibold text-white">Copy Link</button>
					<button type="button" data-share-close-menu="1" class="rounded-xl bg-slate-100 px-3 py-2 font-semibold text-slate-700">Tutup</button>
				</div>
			`;

			backdrop.appendChild(panel);
			document.body.appendChild(backdrop);

			const closeMenu = () => {
				if (backdrop.isConnected) {
					backdrop.remove();
				}
			};

			backdrop.addEventListener('click', (event) => {
				if (event.target === backdrop) {
					closeMenu();
				}
			});

			const closeButton = panel.querySelector('[data-share-close-menu]');
			if (closeButton) {
				closeButton.addEventListener('click', closeMenu);
			}

			const copyButton = panel.querySelector('[data-share-copy-link]');
			if (copyButton) {
				copyButton.addEventListener('click', async () => {
					if (navigator.clipboard?.writeText) {
						try {
							await navigator.clipboard.writeText(url);
							setButtonLabelTemporarily('Link tersalin', 1600);
						} catch (_error) {
							setButtonLabelTemporarily('Gagal salin link', 1600);
						}
					} else {
						window.prompt('Salin link grup ini:', url);
					}
					closeMenu();
				});
			}
		};

		if (navigator.share) {
			try {
				await navigator.share(sharePayload);
				return;
			} catch (error) {
				// User cancelled share sheet.
				if (error && error.name === 'AbortError') {
					return;
				}
			}
		}

		openFallbackShareMenu();
	});
});
