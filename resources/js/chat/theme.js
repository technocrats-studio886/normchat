// Tema warna chat per grup (disimpan di localStorage).

import { chatShell, chatThemeStorageKey } from './context.js';

// Semua tema turunan palet logo (navy & coral); tanpa gradasi.
const chatThemePalettes = [
	{ id: 'default', label: 'Default', color: '' },
	{
		id: 'navy',
		label: 'Navy',
		color: '#121C29',
		primary: '#FB6856',
		primaryHover: '#E94E3C',
		mine: '#40536C',
		headerBg: 'rgba(30, 46, 67, 0.92)',
		headerBorder: 'rgba(161, 176, 197, 0.25)',
		headerTitle: '#F3F5F8',
		headerSubtitle: '#A1B0C5',
		otherBg: '#1E2E43',
		otherText: '#F3F5F8',
		otherBorder: 'rgba(161, 176, 197, 0.25)',
		aiBg: 'rgba(251, 104, 86, 0.16)',
		aiText: '#FFE4E0',
		aiBorder: 'rgba(251, 104, 86, 0.4)',
		replyMineBg: 'rgba(18, 28, 41, 0.45)',
		replyAiBg: 'rgba(251, 104, 86, 0.14)',
		replyOtherBg: 'rgba(18, 28, 41, 0.45)',
		composerBg: '#121C29',
		composerInputBg: '#1E2E43',
		composerText: '#F3F5F8',
		composerSoft: '#A1B0C5',
		composerLine: 'rgba(161, 176, 197, 0.25)',
	},
	{
		id: 'coral',
		label: 'Coral',
		color: '#FFF3F1',
		primary: '#1E2E43',
		primaryHover: '#121C29',
		mine: '#1E2E43',
		headerBg: 'rgba(255, 255, 255, 0.95)',
		headerBorder: '#FECCC5',
		headerTitle: '#121C29',
		headerSubtitle: '#C43D2E',
		otherBg: '#FFFFFF',
		otherText: '#121C29',
		otherBorder: '#FECCC5',
		aiBg: '#FFE4E0',
		aiText: '#852F27',
		aiBorder: '#FDA99E',
		replyMineBg: 'rgba(255, 255, 255, 0.16)',
		replyAiBg: '#FFF3F1',
		replyOtherBg: '#FFF3F1',
		composerBg: '#FFF3F1',
		composerInputBg: '#FFFFFF',
		composerText: '#121C29',
		composerSoft: '#C43D2E',
		composerLine: '#FECCC5',
	},
];

const resolveChatThemePalette = (themeId) => {
	const selectedId = String(themeId || '').trim().toLowerCase();
	return chatThemePalettes.find((item) => item.id === selectedId) || chatThemePalettes[0];
};

/** Pasang tema ke ruang chat, atau ke elemen lain (pratinjau di pengaturan grup). */
const applyChatTheme = (themeId, target = chatShell) => {
	if (!(target instanceof HTMLElement)) {
		return;
	}

	const theme = resolveChatThemePalette(themeId);
	target.dataset.chatTheme = theme.id;
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
			target.style.removeProperty(name);
		});
	};

	if (!theme.color) {
		target.style.background = '';
		target.style.backgroundImage = '';
		target.style.backgroundColor = '';
		clearThemeVars();
		return;
	}

	clearThemeVars();
	target.style.setProperty('--nc-primary', String(theme.primary || '#344458'));
	target.style.setProperty('--nc-primary-hover', String(theme.primaryHover || theme.primary || '#29374A'));
	target.style.setProperty('--nc-mine', String(theme.mine || theme.primary || '#344458'));
	target.style.setProperty('--nc-chat-meta', String(theme.headerSubtitle || '#A1B0C5'));
	target.style.setProperty('--nc-chat-meta-strong', String(theme.headerTitle || '#E4E9F0'));
	target.style.setProperty('--nc-chat-header-bg', String(theme.headerBg || 'rgba(255,255,255,0.95)'));
	target.style.setProperty('--nc-chat-header-border', String(theme.headerBorder || 'rgba(228, 233, 240, 0.95)'));
	target.style.setProperty('--nc-chat-header-title', String(theme.headerTitle || '#1E2E43'));
	target.style.setProperty('--nc-chat-header-subtitle', String(theme.headerSubtitle || '#6b7280'));
	target.style.setProperty('--nc-chat-reply-mine-bg', String(theme.replyMineBg || 'rgba(18, 28, 41, 0.52)'));
	target.style.setProperty('--nc-chat-reply-mine-border', String(theme.primary || '#FC8474'));
	target.style.setProperty('--nc-chat-reply-mine-text', String(theme.headerTitle || '#E4E9F0'));
	target.style.setProperty('--nc-chat-reply-ai-bg', String(theme.replyAiBg || 'rgba(30, 46, 67, 0.5)'));
	target.style.setProperty('--nc-chat-reply-ai-border', String(theme.aiBorder || '#7186A3'));
	target.style.setProperty('--nc-chat-reply-ai-text', String(theme.aiText || '#F3F5F8'));
	target.style.setProperty('--nc-chat-reply-other-bg', String(theme.replyOtherBg || 'rgba(18, 28, 41, 0.5)'));
	target.style.setProperty('--nc-chat-reply-other-border', String(theme.otherBorder || '#64748b'));
	target.style.setProperty('--nc-chat-reply-other-text', String(theme.otherText || '#E4E9F0'));
	target.style.setProperty('--nc-chat-other-bg', String(theme.otherBg || '#ffffff'));
	target.style.setProperty('--nc-chat-other-text', String(theme.otherText || '#121C29'));
	target.style.setProperty('--nc-chat-other-border', String(theme.otherBorder || '#e5e7eb'));
	target.style.setProperty('--nc-chat-ai-bg', String(theme.aiBg || '#F3F5F8'));
	target.style.setProperty('--nc-chat-ai-text', String(theme.aiText || '#1E2E43'));
	target.style.setProperty('--nc-chat-ai-border', String(theme.aiBorder || '#C9D2DF'));
	target.style.setProperty('--nc-chat-composer-bg', String(theme.composerBg || '#ffffff'));
	target.style.setProperty('--nc-chat-composer-input-bg', String(theme.composerInputBg || '#ffffff'));
	target.style.setProperty('--nc-chat-composer-text', String(theme.composerText || '#121C29'));
	target.style.setProperty('--nc-chat-composer-soft', String(theme.composerSoft || '#64748b'));
	target.style.setProperty('--nc-chat-composer-line', String(theme.composerLine || '#e5e7eb'));

	target.style.backgroundImage = 'none';
	target.style.background = theme.color;
	target.style.backgroundColor = theme.color;
};

export const loadSavedChatTheme = () => {
	try {
		const storedTheme = String(window.localStorage.getItem(chatThemeStorageKey) || '').trim();
		applyChatTheme(storedTheme || 'default');
	} catch {
		applyChatTheme('default');
	}

	// Tema diganti di tab/jendela lain (mis. halaman pengaturan grup): terapkan langsung.
	window.addEventListener('storage', (event) => {
		if (event.key === chatThemeStorageKey) {
			applyChatTheme(String(event.newValue || '').trim() || 'default');
		}
	});
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

	const preview = picker.querySelector('[data-chat-theme-preview]');

	const setActive = (themeId) => {
		const selected = resolveChatThemePalette(themeId);
		if (preview instanceof HTMLElement) {
			applyChatTheme(selected.id, preview);
		}
		options.forEach((button) => {
			const isActive = button.getAttribute('data-chat-theme-option') === selected.id;
			button.classList.toggle('ring-2', isActive);
			button.classList.toggle('ring-navy-500', isActive);
			button.classList.toggle('border-navy-400', isActive);
		});
		if (status) {
			status.textContent = `Tema: ${selected.label}`;
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
