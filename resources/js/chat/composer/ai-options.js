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

// Kategori yang dikunci saldo grup (App\Services\Credits\ModelAccess) tidak bisa dipilih.
const isLocked = (option) => option?.getAttribute('data-ai-model-locked') === '1';

const selectModel = (modelId, persist = true) => {
	const option = modelMenu?.querySelector(`[data-ai-model-option="${CSS.escape(modelId)}"]`);
	if (!(modelInput instanceof HTMLInputElement) || !option || isLocked(option)) {
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

/** Model bawaan grup, atau model pertama yang tidak terkunci. */
const selectFallbackModel = () => {
	if (modelInput instanceof HTMLInputElement && selectModel(modelInput.dataset.defaultModel || '', false)) {
		return;
	}
	const unlocked = modelMenu?.querySelector('[data-ai-model-option]:not([data-ai-model-locked="1"])');
	selectModel(unlocked?.getAttribute('data-ai-model-option') || '', false);
};

const setLocked = (option, locked) => {
	option.setAttribute('data-ai-model-locked', locked ? '1' : '0');
	option.querySelector('[data-ai-lock-icon]')?.classList.toggle('hidden', !locked);
	option.querySelector('[data-ai-lock-hint]')?.classList.toggle('hidden', !locked);
	option.querySelector('[data-ai-model-description]')?.classList.toggle('hidden', locked);
};

const lockSheet = document.querySelector('[data-model-lock-sheet]');
const creditFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

const closeLockSheet = () => lockSheet?.classList.add('hidden');

/** Penjelasan model terkunci: ambang buka, saldo grup sekarang, dan kekurangannya. */
const openLockSheet = (option) => {
	if (!(lockSheet instanceof HTMLElement)) {
		return;
	}

	const label = option.getAttribute('data-ai-model-name') || '';
	const openAt = Number(option.getAttribute('data-ai-model-open-at') || 0);
	const balance = Math.max(0, Number(modelMenu?.dataset.groupCredits || 0));
	const missing = Math.max(0, openAt - balance);

	lockSheet.querySelector('[data-lock-title]').textContent = `${label} belum terbuka`;
	lockSheet.querySelector('[data-lock-text]').textContent = `${label} terbuka saat saldo grup mencapai ${creditFormat.format(openAt)} NK. `
		+ `Saldo sekarang ${creditFormat.format(balance)} NK, kurang ${creditFormat.format(missing)} NK.`;
	lockSheet.querySelector('[data-lock-meter]').style.width = `${openAt > 0 ? Math.min(100, (balance / openAt) * 100) : 0}%`;
	closeModelMenu();
	lockSheet.classList.remove('hidden');
	lockSheet.querySelector('[data-lock-close]')?.focus();
};

/**
 * Perbarui kunci kategori dari saldo grup terbaru (dibawa tiap pesan realtime). Polanya sama
 * dengan server: terbuka saat saldo ≥ ambang buka, terkunci saat saldo < ambang tutup,
 * di antaranya tetap seperti sebelumnya.
 */
export const refreshModelLocks = (credits) => {
	const balance = Number(credits);
	if (!modelMenu || !Number.isFinite(balance)) {
		return;
	}
	modelMenu.dataset.groupCredits = String(balance);

	modelMenu.querySelectorAll('[data-ai-model-locked]').forEach((option) => {
		if (balance >= Number(option.getAttribute('data-ai-model-open-at') || 0)) {
			setLocked(option, false);
		} else if (balance < Number(option.getAttribute('data-ai-model-floor') || 0)) {
			setLocked(option, true);
		}
	});

	const current = modelInput instanceof HTMLInputElement
		? modelMenu.querySelector(`[data-ai-model-option="${CSS.escape(modelInput.value)}"]`)
		: null;
	if (isLocked(current)) {
		selectFallbackModel();
	}
};

export const initAiOptions = () => {
	if (modelInput instanceof HTMLInputElement) {
		const stored = readStoredModel();
		if (!stored || !selectModel(stored, false)) {
			selectFallbackModel();
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
		if (isLocked(option)) {
			openLockSheet(option);
			return;
		}

		selectModel(option.getAttribute('data-ai-model-option') || '');
		closeModelMenu();
	});

	attachMenuButton?.addEventListener('click', closeModelMenu);

	lockSheet?.addEventListener('click', (event) => {
		if (event.target === lockSheet || event.target.closest('[data-lock-close]')) {
			closeLockSheet();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			closeLockSheet();
		}
	});

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
