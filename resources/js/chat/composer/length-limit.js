// Batas panjang pesan (App\Support\MessageRules::MAX_CONTENT) diperiksa sambil mengetik, bukan setelah dikirim.
// Hitungan sama dengan server: titik kode Unicode (mb_strlen), setelah trim, baris baru dihitung satu karakter.
import { chatForm, mentionInput, sendButton } from './elements.js';

const limit = Number(chatForm?.dataset.maxLength || 3000);
const format = (value) => value.toLocaleString('id-ID');

export const contentLength = (value) => Array.from(String(value ?? '').replace(/\r\n?/g, '\n').trim()).length;

/** Pesan untuk user bila teks melebihi batas; '' bila aman dikirim. */
export const lengthProblem = (value = mentionInput?.value) => {
	const length = contentLength(value);

	return length > limit
		? `Pesan terlalu panjang (${format(length)}/${format(limit)} karakter). Pendekkan ${format(length - limit)} karakter, atau kirim isinya sebagai file.`
		: '';
};

export const refreshLengthNote = () => {
	const note = chatForm?.querySelector('[data-length-note]');
	if (!note || !mentionInput) {
		return '';
	}
	const length = contentLength(mentionInput.value);
	const problem = lengthProblem();
	// Penghitung baru muncul menjelang batas, supaya kolom ketik tetap bersih untuk pesan biasa.
	note.hidden = length < limit * 0.9;
	note.textContent = problem || `${format(length)}/${format(limit)} karakter`;
	note.classList.toggle('text-danger-ink', problem !== '');
	note.classList.toggle('text-ink-soft', problem === '');
	mentionInput.setAttribute('aria-invalid', problem ? 'true' : 'false');
	if (sendButton instanceof HTMLElement) {
		sendButton.setAttribute('aria-disabled', problem ? 'true' : 'false');
		sendButton.classList.toggle('opacity-50', problem !== '');
	}

	return problem;
};

export const initLengthLimit = () => {
	if (!chatForm || !mentionInput) {
		return;
	}
	mentionInput.addEventListener('input', refreshLengthNote);
	mentionInput.addEventListener('change', refreshLengthNote);
	refreshLengthNote();
};
