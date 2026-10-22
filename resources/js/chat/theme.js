// Tema warna chat per grup (disimpan di localStorage).

import { chatShell, chatThemeStorageKey } from './context.js';

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

export const loadSavedChatTheme = () => {
	try {
		const storedTheme = String(window.localStorage.getItem(chatThemeStorageKey) || '').trim();
		applyChatTheme(storedTheme || 'default');
	} catch {
		applyChatTheme('default');
	}
};

/** Pemilih tema di pengaturan grup: menyimpan pilihan per grup ke localStorage. */
export const initChatThemePicker = () => {
	const picker = document.querySelector('[data-chat-theme-picker="1"]');
	if (!picker) {
		return;
	}

	const groupId = String(picker.getAttribute('data-chat-theme-group-id') || 'global');
	const storageKey = `nc:chatTheme:${groupId}`;
	const status = picker.querySelector('[data-chat-theme-status="1"]');
	const options = Array.from(picker.querySelectorAll('[data-chat-theme-option]'));
	const knownTheme = (themeId) => chatThemePalettes.some((theme) => theme.id === themeId);

	const setActive = (themeId) => {
		const selected = resolveChatThemePalette(themeId);
		options.forEach((button) => {
			const isActive = button.getAttribute('data-chat-theme-option') === selected.id;
			button.classList.toggle('ring-2', isActive);
			button.classList.toggle('ring-indigo-500', isActive);
			button.classList.toggle('border-indigo-400', isActive);
		});
		if (status) {
			status.textContent = `Theme aktif: ${selected.label}`;
		}
	};

	let initial = 'default';
	try {
		const stored = String(window.localStorage.getItem(storageKey) || '').trim().toLowerCase();
		if (stored && knownTheme(stored)) {
			initial = stored;
		}
	} catch {
		initial = 'default';
	}
	setActive(initial);

	options.forEach((button) => {
		button.addEventListener('click', () => {
			const next = String(button.getAttribute('data-chat-theme-option') || 'default').toLowerCase();
			try {
				if (next === 'default') {
					window.localStorage.removeItem(storageKey);
				} else {
					window.localStorage.setItem(storageKey, next);
				}
			} catch {
				// Penyimpanan diblokir browser: pilihan hanya ditandai, tidak tersimpan.
			}
			setActive(next);
		});
	});
};
