// Mode edit gaya Telegram: header berganti "Edit pesan", pesan lain diredupkan, tombol kirim jadi
// centang. Pesan yang memanggil Normi diberi pilihan "Simpan dan jawab ulang" (memakai Normkredit)
// atau "Simpan tanpa jawab ulang". Isi kolom ketik sebelum mengedit dikembalikan saat selesai.

import { chatFeed, chatShell } from '../context.js';
import { forgetToolbarFor } from '../actions/menu.js';
import { icon } from '../actions/icons.js';
import { flashEditedMessage, replaceRenderedMessageByPayload } from '../messages.js';
import { chatApiRequest } from '../utils.js';
import { clearSelectedAttachment, refreshSendVoiceToggle } from './attachment.js';
import { buildMessageApiUrl, chatForm, mentionInput } from './elements.js';
import { clearReplyTarget } from './reply.js';
import { showActionToast } from './toast.js';

const AI_MENTION = /(^|\s)@normi(?![\w])/i;
const root = document.documentElement;

let editing = null; // { id, node, original, draft }
let header = null;
let strip = null;

export const isEditing = () => editing !== null;

const modelLabel = () => chatForm?.querySelector('[data-ai-model-label]')?.textContent.trim() || 'model pilihanmu';
const groupCredits = () => Number(document.querySelector('[data-model-menu]')?.dataset.groupCredits ?? 1);

/** Jawaban Normi untuk pesan ini yang sedang tampil di feed. */
const answerNode = (id) => chatFeed?.querySelector(`.nc-msg[data-message-answer="1"][data-message-reply-to="${id}"]`);

/** Pesan ditujukan ke Normi: menyebut @normi, atau membalas pesan Normi. */
const addressesNormi = (content) => {
	const replyTo = Number(editing?.node.dataset.messageReplyTo || 0);
	const target = replyTo ? chatFeed?.querySelector(`[data-message-id="${replyTo}"]`) : null;
	return AI_MENTION.test(content) || target?.dataset.senderKey === 'ai';
};

const refreshStrip = () => {
	if (!strip || !editing || !(mentionInput instanceof HTMLTextAreaElement)) {
		return;
	}
	const show = addressesNormi(mentionInput.value);
	strip.classList.toggle('hidden', !show);
	if (show) {
		strip.querySelector('[data-edit-strip-text]').textContent = answerNode(editing.id)
			? `Bila dijawab ulang, Normi memakai ${modelLabel()} dan Normkredit grup terpakai lagi.`
			: `Bila dijawab, Normi memakai ${modelLabel()} dan Normkredit grup terpakai.`;
	}
};

const mountChrome = () => {
	header = document.createElement('div');
	header.className = 'nc-edit-header';
	header.innerHTML = `
		<button type="button" class="nc-icon-button" data-edit-cancel aria-label="Batal mengedit">${icon('back')}</button>
		<p class="text-base font-semibold">Edit pesan</p>
	`;
	header.querySelector('[data-edit-cancel]').addEventListener('click', () => cancelEditMode());
	(chatShell || document.body).prepend(header);

	strip = document.createElement('div');
	strip.className = 'nc-edit-strip hidden';
	strip.innerHTML = `${icon('info', 'h-4 w-4 shrink-0')}<span data-edit-strip-text></span>`;
	chatForm?.querySelector('[data-composer-main]')?.before(strip);
	mentionInput?.addEventListener('input', refreshStrip);
};

const unmountChrome = () => {
	header?.remove();
	strip?.remove();
	header = null;
	strip = null;
	mentionInput?.removeEventListener('input', refreshStrip);
};

export const startEditMode = (node) => {
	if (!(mentionInput instanceof HTMLTextAreaElement)) {
		return;
	}
	if (node.dataset.messageAwaitingAi === '1') {
		showActionToast('Tunggu Normi selesai menjawab');
		return;
	}
	if (editing) {
		cancelEditMode();
	}

	const id = Number(node.getAttribute('data-message-id') || 0);
	editing = { id, node, original: String(node.dataset.messageText || ''), draft: mentionInput.value };
	clearReplyTarget();
	clearSelectedAttachment();
	forgetToolbarFor(node);
	root.classList.add('nc-editing');
	node.classList.add('is-edit-target');
	mountChrome();

	mentionInput.value = editing.original;
	mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
	refreshSendVoiceToggle();
	mentionInput.focus();
	mentionInput.setSelectionRange(mentionInput.value.length, mentionInput.value.length);
	node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
};

export const cancelEditMode = () => {
	if (!editing) {
		return;
	}
	const { node, draft } = editing;
	editing = null;
	root.classList.remove('nc-editing');
	chatFeed?.querySelector('.is-edit-target')?.classList.remove('is-edit-target');
	node.classList.remove('is-edit-target');
	unmountChrome();
	if (mentionInput instanceof HTMLTextAreaElement) {
		mentionInput.value = draft;
		mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
	}
	refreshSendVoiceToggle();
};

/** Pesan yang sedang diedit dihapus orang lain: keluar dari mode edit. */
export const cancelEditModeFor = (id) => {
	if (editing?.id === Number(id)) {
		cancelEditMode();
	}
};

/** Pilihan jawab ulang, di atas tombol centang. @returns {Promise<'regenerate'|'save'|null>} */
const askRegenerate = (hasAnswer) => new Promise((resolve) => {
	const noCredits = groupCredits() <= 0;
	const popover = document.createElement('div');
	popover.className = 'nc-edit-choice';
	popover.setAttribute('role', 'menu');
	popover.innerHTML = `
		<button type="button" role="menuitem" class="is-main" data-edit-choice="regenerate" ${noCredits ? 'disabled' : ''}>
			${hasAnswer ? 'Simpan dan jawab ulang' : 'Simpan dan tanya Normi'}
			<small>${noCredits ? 'Saldo grup habis' : (hasAnswer ? 'Jawaban lama diganti · Normkredit terpakai' : 'Normkredit terpakai')}</small>
		</button>
		<button type="button" role="menuitem" data-edit-choice="save">${hasAnswer ? 'Simpan tanpa jawab ulang' : 'Simpan saja'}</button>
	`;
	const done = (value) => {
		document.removeEventListener('pointerdown', onOutside, true);
		document.removeEventListener('keydown', onKey);
		popover.remove();
		resolve(value);
	};
	const onOutside = (event) => {
		if (!popover.contains(event.target)) {
			done(null);
		}
	};
	const onKey = (event) => {
		if (event.key === 'Escape') {
			event.stopPropagation();
			done(null);
		}
	};
	popover.addEventListener('click', (event) => {
		const choice = event.target.closest('[data-edit-choice]');
		if (choice && !choice.disabled) {
			done(choice.getAttribute('data-edit-choice'));
		}
	});
	chatForm?.querySelector('[data-composer-main]')?.appendChild(popover);
	document.addEventListener('pointerdown', onOutside, true);
	document.addEventListener('keydown', onKey);
	popover.querySelector('button:not([disabled])')?.focus();
});

/** Simpan edit (dipanggil send.js saat form dikirim dalam mode edit). */
export const submitEdit = async (content) => {
	if (!editing) {
		return;
	}
	if (content === '') {
		showActionToast('Pesan tidak boleh kosong');
		return;
	}
	if (content === editing.original.trim()) {
		cancelEditMode();
		return;
	}

	let regenerate = false;
	if (addressesNormi(content)) {
		const choice = await askRegenerate(Boolean(answerNode(editing.id)));
		if (!choice) {
			return;
		}
		regenerate = choice === 'regenerate';
	}

	const id = editing.id;
	try {
		const result = await chatApiRequest(buildMessageApiUrl(id), {
			method: 'PATCH',
			body: {
				content,
				regenerate,
				ai_model: chatForm?.querySelector('[data-ai-model-input]')?.value || null,
			},
		});
		editing.draft = '';
		cancelEditMode();
		if (result?.message) {
			flashEditedMessage(replaceRenderedMessageByPayload(result.message));
		}
		showActionToast(regenerate ? 'Pesan disimpan, Normi menjawab ulang' : 'Pesan disimpan');
	} catch (error) {
		showActionToast(error?.message && error.message !== 'request_failed' ? error.message : 'Gagal menyimpan pesan');
	}
};

export const initEditMode = () => {
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && editing && !document.querySelector('.nc-edit-choice')) {
			cancelEditMode();
		}
	});
};
