// Pilihan AI di composer: model per pesan (menu model) dan mode jawaban (bagian bawah menu plus).
// Mode tidak menggantikan @normi; saat mode dipilih, "@normi " disisipkan bila belum ada.

import { refreshSendVoiceToggle } from './attachment.js';
import { attachMenu, attachMenuButton, chatForm, mentionInput } from './elements.js';

const AI_MENTION = /(^|\s)@normi(?![\w])/i;

const modeInput = chatForm?.querySelector('[data-ai-mode-input]');
const modelInput = chatForm?.querySelector('[data-ai-model-input]');
const modelMenu = chatForm?.querySelector('[data-model-menu]');
const modelMenuButton = chatForm?.querySelector('[data-open-model-menu]');
const modelLabel = chatForm?.querySelector('[data-ai-model-label]');
const chipRow = chatForm?.querySelector('[data-ai-mode-chip-row]');
const chipLabel = chatForm?.querySelector('[data-ai-mode-chip-label]');
const chipIcon = chatForm?.querySelector('[data-ai-mode-chip-icon]');

const storageKey = () => `nc-ai-model:${modelInput?.dataset.groupId || '0'}`;

const readStoredModel = () => {
	try {
		return window.localStorage.getItem(storageKey()) || '';
	} catch {
		return '';
	}
};

const storeModel = (modelId) => {
	try {
		window.localStorage.setItem(storageKey(), modelId);
	} catch {
		// Penyimpanan diblokir (mode privat); pilihan tetap berlaku untuk sesi ini.
	}
};

const markChecked = (container, attribute, value) => {
	container?.querySelectorAll(`[${attribute}]`).forEach((option) => {
		const selected = option.getAttribute(attribute) === value;
		option.setAttribute('aria-checked', selected ? 'true' : 'false');
		option.classList.toggle('is-selected', selected);
		option.querySelector('[data-ai-check]')?.classList.toggle('hidden', !selected);
	});
};

const selectModel = (modelId, persist = true) => {
	const option = modelMenu?.querySelector(`[data-ai-model-option="${CSS.escape(modelId)}"]`);
	if (!(modelInput instanceof HTMLInputElement) || !option) {
		return false;
	}

	modelInput.value = modelId;
	if (modelLabel) {
		modelLabel.textContent = option.getAttribute('data-ai-model-name') || modelId;
	}
	markChecked(modelMenu, 'data-ai-model-option', modelId);
	if (persist) {
		storeModel(modelId);
	}
	return true;
};

const ensureAiMention = () => {
	if (!(mentionInput instanceof HTMLTextAreaElement) || AI_MENTION.test(mentionInput.value)) {
		return;
	}

	mentionInput.value = `@normi ${mentionInput.value.replace(/^\s+/, '')}`;
	// Bukan event input: sisipan otomatis tidak boleh tampil sebagai "sedang mengetik" ke anggota lain.
	refreshSendVoiceToggle();
};

const focusAtEnd = () => {
	if (!(mentionInput instanceof HTMLTextAreaElement)) {
		return;
	}

	mentionInput.focus();
	const end = mentionInput.value.length;
	mentionInput.setSelectionRange(end, end);
};

const setMode = (option) => {
	if (!(modeInput instanceof HTMLInputElement)) {
		return;
	}

	const value = option?.getAttribute('data-ai-mode-option') || '';
	modeInput.value = value;
	markChecked(attachMenu, 'data-ai-mode-option', value);
	chipRow?.classList.toggle('hidden', value === '');

	if (mentionInput instanceof HTMLTextAreaElement) {
		mentionInput.placeholder = value
			? (option.getAttribute('data-ai-mode-placeholder') || '')
			: (mentionInput.dataset.defaultPlaceholder || 'Ketik pesan');
	}

	if (!value) {
		return;
	}

	if (chipLabel) {
		chipLabel.textContent = option.getAttribute('data-ai-mode-label') || '';
	}
	const icon = option.querySelector('[data-ai-mode-icon]');
	if (chipIcon && icon) {
		chipIcon.innerHTML = icon.innerHTML;
	}
	ensureAiMention();
	focusAtEnd();
};

const closeModelMenu = () => modelMenu?.classList.add('hidden');

export const initAiOptions = () => {
	if (modelInput instanceof HTMLInputElement) {
		const stored = readStoredModel();
		if (!stored || !selectModel(stored, false)) {
			selectModel(modelInput.dataset.defaultModel || modelInput.value, false);
		}
	}

	modelMenuButton?.addEventListener('click', (event) => {
		event.preventDefault();
		attachMenu?.classList.add('hidden');
		modelMenu?.classList.toggle('hidden');
	});

	modelMenu?.addEventListener('click', (event) => {
		const option = event.target.closest('[data-ai-model-option]');
		if (!option) {
			return;
		}

		selectModel(option.getAttribute('data-ai-model-option') || '');
		closeModelMenu();
	});

	attachMenuButton?.addEventListener('click', closeModelMenu);

	attachMenu?.addEventListener('click', (event) => {
		const option = event.target.closest('[data-ai-mode-option]');
		if (!option) {
			return;
		}

		const alreadyActive = modeInput instanceof HTMLInputElement
			&& modeInput.value === option.getAttribute('data-ai-mode-option');
		setMode(alreadyActive ? null : option);
		attachMenu.classList.add('hidden');
	});

	chatForm?.querySelector('[data-ai-mode-clear]')?.addEventListener('click', () => setMode(null));

	// Mode tetap aktif untuk pesan berikutnya (seperti Gemini); siapkan lagi "@normi " setelah terkirim.
	chatForm?.addEventListener('submit', () => {
		window.setTimeout(() => {
			if (modeInput instanceof HTMLInputElement && modeInput.value && mentionInput?.value === '') {
				ensureAiMention();
			}
		}, 0);
	});

	document.addEventListener('click', (event) => {
		if (!modelMenu || modelMenu.classList.contains('hidden')) {
			return;
		}
		if (!modelMenu.contains(event.target) && !modelMenuButton?.contains(event.target)) {
			closeModelMenu();
		}
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			closeModelMenu();
			attachMenu?.classList.add('hidden');
		}
	});
};
