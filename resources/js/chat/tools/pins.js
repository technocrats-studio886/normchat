// Sematan pesan (App\Services\Chat\GroupPins): bilah di bawah header dan daftar sematan.
// Ketuk bilah: lompat ke pesan sematan, lalu bilah beralih ke sematan berikutnya (seperti Telegram).

import { chatFeed } from '../context.js';
import { jumpToMessage } from '../message-search.js';
import { showActionToast } from '../composer/toast.js';
import { escapeHtml } from '../utils.js';
import { toolsRequest } from './api.js';
import { openSheet, openSheetOf } from './sheet.js';

let pins = [];
let index = 0;
const bar = document.querySelector('[data-pin-bar]');

export const isPinned = (messageId) => pins.some((pin) => Number(pin.message_id) === Number(messageId));

const render = () => {
	if (!(bar instanceof HTMLElement)) return;
	bar.classList.toggle('hidden', pins.length === 0);
	if (pins.length === 0) return;
	index = Math.min(index, pins.length - 1);
	const pin = pins[index];
	bar.querySelector('[data-pin-bar-label]').textContent = pins.length > 1 ? `Pesan disematkan ${index + 1} dari ${pins.length}` : 'Pesan disematkan';
	bar.querySelector('[data-pin-bar-text]').textContent = `${pin.sender_name}: ${pin.preview}`;
	bar.querySelector('[data-pin-bar-index]').innerHTML = pins.map((_, i) => `<span class="${i === index ? 'is-active' : ''}"></span>`).join('');
	const sheet = openSheetOf('pins');
	if (sheet) sheet.body.innerHTML = listHtml();
};

const listHtml = () => `<div data-sheet-kind="pins">${pins.length === 0
	? '<p class="nc-sheet-empty">Belum ada pesan yang disematkan.</p>'
	: `<ul class="nc-sheet-list">${pins.map((pin) => `
		<li class="nc-sheet-item">
			<button type="button" class="min-w-0 flex-1 text-left" data-pin-jump="${Number(pin.message_id)}">
				<span class="nc-sheet-item-title">${escapeHtml(pin.sender_name)}</span>
				<span class="nc-sheet-item-text">${escapeHtml(pin.preview)}</span>
				<span class="nc-sheet-item-meta">Disematkan ${escapeHtml(pin.pinned_by)}</span>
			</button>
			<button type="button" class="nc-sheet-link" data-pin-remove="${Number(pin.message_id)}">Lepas</button>
		</li>`).join('')}</ul>`}</div>`;

export const refreshPins = async () => {
	try {
		pins = (await toolsRequest('/pins'))?.pins || [];
		render();
	} catch {
		// Tetap memakai daftar terakhir.
	}
};

export const pinMessage = async (messageId) => {
	try {
		pins = (await toolsRequest(`/messages/${messageId}/pin`, 'POST'))?.pins || pins;
		index = 0;
		render();
		showActionToast('Pesan disematkan');
	} catch (error) {
		showActionToast(error?.message && error.message !== 'request_failed' ? error.message : 'Gagal menyematkan pesan');
	}
};

export const unpinMessage = async (messageId) => {
	try {
		pins = (await toolsRequest(`/messages/${messageId}/pin`, 'DELETE'))?.pins || [];
		render();
		showActionToast('Sematan dilepas');
	} catch {
		showActionToast('Gagal melepas sematan');
	}
};

export const openPinsSheet = () => {
	const sheet = openSheet({ title: 'Pesan disematkan', html: listHtml() });
	sheet.body.addEventListener('click', (event) => {
		const jump = event.target.closest('[data-pin-jump]');
		const remove = event.target.closest('[data-pin-remove]');
		if (jump) {
			sheet.close();
			jumpToMessage(Number(jump.dataset.pinJump));
		} else if (remove) {
			unpinMessage(Number(remove.dataset.pinRemove));
		}
	});
};

export const initPins = () => {
	if (!(bar instanceof HTMLElement)) return;
	try {
		pins = JSON.parse(chatFeed?.getAttribute('data-initial-pins') || '[]');
	} catch {
		pins = [];
	}
	render();
	bar.querySelector('[data-pin-bar-jump]')?.addEventListener('click', () => {
		const pin = pins[index];
		if (!pin) return;
		jumpToMessage(Number(pin.message_id));
		index = (index + 1) % pins.length;
		render();
	});
	bar.querySelector('[data-pin-bar-list]')?.addEventListener('click', openPinsSheet);
};
