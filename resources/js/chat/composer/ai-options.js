// Pilihan AI di composer: model per pesan (menu model) dan mode jawaban (bagian bawah menu plus).
// Tiap fitur punya tag sendiri: chat biasa @normi, fitur @normi-image, @normi-canvas, @normi-research,
// @normi-learn (App\Enums\AiMode::tag()). Memilih fitur dari menu menulis tagnya ke kolom ketik, dan
// mengetik tag fitur langsung mengaktifkan fiturnya: teks di kolom ketik selalu cocok dengan chip mode.

import { chatFeed } from '../context.js';
import { refreshSendVoiceToggle } from './attachment.js';
import { isEditing } from './edit-mode.js';
import { attachMenu, attachMenuButton, attachmentInput, chatForm, mentionInput, replyToInput } from './elements.js';

const CHAT_TAG = '@normi';
// Tag Normi pertama di teks: @normi atau @normi-<fitur>. Grup 2 = nama fitur (tanpa "@normi-").
const NORMI_TAG = /(^|\s)@normi(?:-([a-z]+))?(?![\w-])/i;

/** Pertanyaan saat membalas jawaban sebuah fitur (laporan user 9 Nov 2026). */
const CONFIRM_QUESTIONS = {
	deep_research: 'Ingin melakukan Deep Research lagi?',
	image: 'Ingin membuat gambar lagi?',
	canvas: 'Ingin memakai Canvas lagi?',
	guided_learning: 'Ingin lanjut dengan Guided Learning?',
};

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

const modeOptions = () => Array.from(attachMenu?.querySelectorAll('[data-ai-mode-option]') || []);
const modeOptionFor = (value) => modeOptions().find((option) => option.getAttribute('data-ai-mode-option') === value) || null;

/** Mode dari tag fitur di teks ("@normi-image …" → "image"); '' untuk @normi biasa atau tanpa tag. */
const modeFromText = (text) => {
	const match = String(text || '').match(NORMI_TAG);
	if (!match || !match[2]) {
		return '';
	}
	const tag = `${CHAT_TAG}-${match[2]}`.toLowerCase();
	const option = modeOptions().find((item) => String(item.getAttribute('data-ai-mode-tag') || '').toLowerCase() === tag);
	return option?.getAttribute('data-ai-mode-option') || '';
};

/** Tulis $tag menggantikan tag Normi pertama di kolom ketik, atau sisipkan di depan bila belum ada. */
const applyTag = (tag) => {
	if (!(mentionInput instanceof HTMLTextAreaElement) || !tag) {
		return;
	}

	const value = mentionInput.value;
	const match = value.match(NORMI_TAG);
	if (match) {
		const start = match.index + match[1].length;
		const end = match.index + match[0].length;
		if (value.slice(start, end) === tag) {
			return;
		}
		mentionInput.value = `${value.slice(0, start)}${tag}${value.slice(end)}`;
	} else {
		mentionInput.value = `${tag} ${value.replace(/^\s+/, '')}`;
	}
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

/**
 * Aktifkan mode $option (null = chat biasa). $syncText: tulis tag yang sesuai ke kolom ketik (dari menu);
 * false bila perubahan justru berasal dari teks yang diketik.
 */
const setMode = (option, { syncText = true, focus = true } = {}) => {
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
		// Fitur dilepas: tag fitur di teks kembali menjadi @normi biasa.
		if (syncText && modeFromText(mentionInput?.value)) {
			applyTag(CHAT_TAG);
		}
		return;
	}

	if (chipLabel) {
		chipLabel.textContent = option.getAttribute('data-ai-mode-label') || '';
	}
	const icon = option.querySelector('[data-ai-mode-icon]');
	if (chipIcon && icon) {
		chipIcon.innerHTML = icon.innerHTML;
	}
	if (syncText) {
		applyTag(option.getAttribute('data-ai-mode-tag') || CHAT_TAG);
	}
	if (focus) {
		focusAtEnd();
	}
};

/** Chip mode mengikuti tag yang diketik (mis. menulis/menghapus "@normi-image"). */
const syncModeFromText = () => {
	if (!(modeInput instanceof HTMLInputElement) || isEditing()) {
		return;
	}
	const fromText = modeFromText(mentionInput?.value);
	if (fromText !== modeInput.value) {
		setMode(fromText ? modeOptionFor(fromText) : null, { syncText: false, focus: false });
	}
};

const confirmCard = chatForm?.querySelector('[data-ai-mode-confirm]');
const confirmText = confirmCard?.querySelector('[data-ai-mode-confirm-text]');
let confirmOption = null;
let confirmBypass = false;

const closeConfirm = () => {
	confirmOption = null;
	confirmCard?.classList.add('hidden');
};

/**
 * Membalas jawaban sebuah fitur Normi (mis. hasil Deep Research) tanpa memilih fitur: fitur yang sama
 * tidak langsung dipakai lagi; tanya dulu. Mengembalikan opsi mode yang perlu ditanyakan, atau null.
 */
const featureToConfirm = () => {
	if (!(modeInput instanceof HTMLInputElement) || !(replyToInput instanceof HTMLInputElement) || isEditing()) {
		return null;
	}
	if (modeInput.value || modeFromText(mentionInput?.value)) {
		return null;
	}
	const hasContent = String(mentionInput?.value || '').trim() !== '' || Boolean(attachmentInput?.files?.length);
	const replyId = Number(replyToInput.value || 0);
	if (!hasContent || replyId <= 0) {
		return null;
	}

	const target = chatFeed?.querySelector(`[data-message-id="${replyId}"]`);
	if (!(target instanceof HTMLElement) || target.dataset.senderKey !== 'ai') {
		return null;
	}

	return modeOptionFor(target.dataset.messageAiMode || '');
};

const openConfirm = (option) => {
	if (!(confirmCard instanceof HTMLElement)) {
		return false;
	}
	confirmOption = option;
	const value = option.getAttribute('data-ai-mode-option') || '';
	if (confirmText) {
		confirmText.textContent = CONFIRM_QUESTIONS[value] || `Ingin memakai ${option.getAttribute('data-ai-mode-label') || 'fitur ini'} lagi?`;
	}
	confirmCard.classList.remove('hidden');
	confirmCard.querySelector('[data-ai-mode-confirm-yes]')?.focus({ preventScroll: true });
	return true;
};

/** Kirim setelah dijawab: Ya = pakai fiturnya lagi (tag fitur ditulis), Tidak = jawab dengan model biasa. */
const answerConfirm = (useFeature) => {
	const option = confirmOption;
	closeConfirm();
	if (useFeature && option) {
		setMode(option, { focus: false });
	}
	confirmBypass = true;
	chatForm?.requestSubmit();
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

	mentionInput?.addEventListener('input', syncModeFromText);
	// Draf yang dipulihkan (composer/draft.js, diinisialisasi setelah ini) bisa berisi tag fitur.
	window.setTimeout(syncModeFromText, 0);

	confirmCard?.querySelector('[data-ai-mode-confirm-yes]')?.addEventListener('click', () => answerConfirm(true));
	confirmCard?.querySelector('[data-ai-mode-confirm-no]')?.addEventListener('click', () => answerConfirm(false));
	// Batal membalas: pertanyaannya tidak berlaku lagi.
	chatForm?.querySelector('[data-reply-clear]')?.addEventListener('click', closeConfirm);

	// Didaftarkan sebelum send.js (lihat composer.js), jadi bisa menahan pengiriman sampai pertanyaan dijawab.
	chatForm?.addEventListener('submit', (event) => {
		if (!confirmBypass) {
			const option = featureToConfirm();
			if (option && openConfirm(option)) {
				event.preventDefault();
				event.stopImmediatePropagation();
				return;
			}
		}
		confirmBypass = false;
		closeConfirm();

		// Mode hanya berlaku untuk satu pesan: setelah terkirim, kolom ketik kembali ke chat biasa
		// (laporan user 9 Nov 2026: chip Deep Research/Gambar masih "nyangkut" setelah pesan terkirim).
		// setTimeout: listener send.js (didaftarkan setelah ini) membaca FormData lebih dulu secara sinkron,
		// lalu mengosongkan kolom ketik; kolom kosong = pesan benar-benar terkirim.
		window.setTimeout(() => {
			if (modeInput instanceof HTMLInputElement && modeInput.value && mentionInput?.value === '') {
				setMode(null, { syncText: false, focus: false });
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
			closeConfirm();
			attachMenu?.classList.add('hidden');
		}
	});
};
