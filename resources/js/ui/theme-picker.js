// Pemilih tema di halaman Profil. Tema dipasang langsung ke <html data-theme> sebagai pratinjau
// seluruh halaman; Batal mengembalikan, Terapkan menyimpan ke akun (POST /profile/theme).

import { showActionToast } from '../chat/composer/toast.js';
import { chatApiRequest } from '../chat/utils.js';

const root = document.documentElement;
const browserColorMeta = document.querySelector('meta[name="theme-color"]');

export const initThemePicker = () => {
	const panel = document.querySelector('[data-theme-panel]');
	const openButton = document.querySelector('[data-theme-open]');
	if (!(panel instanceof HTMLElement) || !(openButton instanceof HTMLElement)) {
		return;
	}

	const saveUrl = String(panel.getAttribute('data-theme-url') || '');
	const applyButton = panel.querySelector('[data-theme-apply]');
	const options = Array.from(panel.querySelectorAll('[data-theme-option]'));
	const optionFor = (themeId) => options.find((option) => option.getAttribute('data-theme-option') === themeId);
	let savedTheme = root.dataset.theme || 'default';
	let selected = savedTheme;

	const preview = (themeId) => {
		const option = optionFor(themeId) ?? optionFor('default');
		selected = option.getAttribute('data-theme-option');
		root.dataset.theme = selected;
		browserColorMeta?.setAttribute('content', option.getAttribute('data-theme-browser') || '#ffffff');
		options.forEach((item) => item.setAttribute('aria-checked', item === option ? 'true' : 'false'));
	};

	const close = () => {
		panel.classList.add('hidden');
		document.removeEventListener('keydown', onEscape);
		openButton.focus();
	};

	const cancel = () => {
		preview(savedTheme);
		close();
	};

	const onEscape = (event) => {
		if (event.key === 'Escape') {
			cancel();
		}
	};

	openButton.addEventListener('click', () => {
		preview(savedTheme);
		panel.classList.remove('hidden');
		document.addEventListener('keydown', onEscape);
		optionFor(selected)?.focus();
	});
	options.forEach((option) => {
		option.addEventListener('click', () => preview(option.getAttribute('data-theme-option')));
	});
	panel.querySelector('[data-theme-cancel]')?.addEventListener('click', cancel);

	applyButton?.addEventListener('click', async () => {
		if (selected === savedTheme) {
			close();
			return;
		}

		applyButton.disabled = true;
		try {
			await chatApiRequest(saveUrl, { method: 'POST', body: { theme: selected } });
			savedTheme = selected;
			const option = optionFor(selected);
			document.querySelectorAll('[data-theme-current-label]').forEach((node) => {
				node.textContent = option?.getAttribute('data-theme-label') || '';
			});
			document.querySelectorAll('[data-theme-current-swatch]').forEach((node) => {
				node.style.background = option?.getAttribute('data-theme-swatch') || '';
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
