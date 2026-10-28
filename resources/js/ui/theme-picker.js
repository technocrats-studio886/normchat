// Pemilih tema di halaman Profil: mode (terang/gelap/ikuti sistem) + warna aksen. Pilihan hanya
// mengubah pratinjau di dalam panel; halaman berubah setelah Terapkan (POST /profile/theme).

import { showActionToast } from '../chat/composer/toast.js';
import { chatApiRequest } from '../chat/utils.js';

const root = document.documentElement;
const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');

const resolveMode = (mode) => (mode === 'system' ? (darkQuery.matches ? 'dark' : 'light') : mode);

/** Pasang tema ke <html>; skrip di layouts/app.blade.php yang mengikuti perubahan setelan perangkat. */
const applyToPage = ({ mode, accent }) => {
	root.dataset.themeMode = mode;
	root.dataset.mode = resolveMode(mode);
	root.dataset.accent = accent;
	document.querySelector('meta[name="theme-color"]')?.setAttribute('content', root.dataset.mode === 'dark' ? '#121C29' : '#ffffff');
};

export const initThemePicker = () => {
	const panel = document.querySelector('[data-theme-panel]');
	const openButton = document.querySelector('[data-theme-open]');
	const preview = panel?.querySelector('[data-theme-preview]');
	if (!(panel instanceof HTMLElement) || !(openButton instanceof HTMLElement) || !(preview instanceof HTMLElement)) {
		return;
	}

	const applyButton = panel.querySelector('[data-theme-apply]');
	const modeOptions = Array.from(panel.querySelectorAll('[data-theme-mode-option]'));
	const accentOptions = Array.from(panel.querySelectorAll('[data-theme-accent-option]'));
	let saved = { mode: panel.dataset.themeMode || 'system', accent: panel.dataset.themeAccent || 'navy' };
	let selected = { ...saved };

	const render = () => {
		preview.dataset.mode = resolveMode(selected.mode);
		preview.dataset.accent = selected.accent;
		modeOptions.forEach((option) => option.setAttribute('aria-checked', String(option.dataset.themeModeOption === selected.mode)));
		accentOptions.forEach((option) => option.setAttribute('aria-checked', String(option.dataset.themeAccentOption === selected.accent)));
	};

	const onEscape = (event) => {
		if (event.key === 'Escape') {
			close();
		}
	};

	const close = () => {
		panel.classList.add('hidden');
		panel.classList.remove('flex');
		document.removeEventListener('keydown', onEscape);
		darkQuery.removeEventListener('change', render);
		openButton.focus();
	};

	openButton.addEventListener('click', () => {
		selected = { ...saved };
		render();
		panel.classList.remove('hidden');
		panel.classList.add('flex');
		document.addEventListener('keydown', onEscape);
		darkQuery.addEventListener('change', render);
		modeOptions.find((option) => option.getAttribute('aria-checked') === 'true')?.focus();
	});
	panel.addEventListener('click', (event) => {
		if (event.target === panel) {
			close();
		}
	});
	modeOptions.forEach((option) => option.addEventListener('click', () => {
		selected.mode = option.dataset.themeModeOption;
		render();
	}));
	accentOptions.forEach((option) => option.addEventListener('click', () => {
		selected.accent = option.dataset.themeAccentOption;
		render();
	}));
	panel.querySelector('[data-theme-cancel]')?.addEventListener('click', close);

	applyButton?.addEventListener('click', async () => {
		if (selected.mode === saved.mode && selected.accent === saved.accent) {
			close();
			return;
		}

		applyButton.disabled = true;
		try {
			await chatApiRequest(String(panel.dataset.themeUrl || ''), { method: 'POST', body: selected });
			saved = { ...selected };
			applyToPage(saved);
			const modeLabel = modeOptions.find((option) => option.dataset.themeModeOption === saved.mode)?.textContent.trim() || '';
			const accentOption = accentOptions.find((option) => option.dataset.themeAccentOption === saved.accent);
			document.querySelectorAll('[data-theme-current-label]').forEach((node) => {
				node.textContent = `${modeLabel} · ${accentOption?.getAttribute('aria-label') || ''}`;
			});
			document.querySelectorAll('[data-theme-current-swatch]').forEach((node) => {
				node.style.background = accentOption?.style.getPropertyValue('--swatch') || '';
			});
			close();
			showActionToast('Tema diterapkan');
		} catch {
			showActionToast('Gagal menyimpan tema. Coba lagi.');
		} finally {
			applyButton.disabled = false;
		}
	});
};
