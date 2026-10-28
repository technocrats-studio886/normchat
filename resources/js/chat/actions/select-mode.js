// Mode pilih pesan: ketuk pesan untuk memilih/batal; bilah atas berisi jumlah, Salin, dan Hapus.

import { chatFeed, chatShell } from '../context.js';
import { showActionToast } from '../composer/toast.js';
import { deleteMessages } from './delete.js';
import { writeClipboard } from './handlers.js';
import { icon } from './icons.js';

const root = document.documentElement;
const selected = new Set();
let bar = null;

const nodes = () => Array.from(chatFeed?.querySelectorAll('.nc-msg.is-selected') || []);

const renderBar = () => {
	if (!bar) {
		bar = document.createElement('div');
		bar.className = 'nc-select-bar';
		bar.setAttribute('role', 'toolbar');
		bar.setAttribute('aria-label', 'Pesan dipilih');
		bar.innerHTML = `
			<button type="button" class="nc-icon-button" data-select-exit aria-label="Batal memilih">${icon('close')}</button>
			<span class="flex-1 text-sm font-semibold" data-select-count aria-live="polite"></span>
			<button type="button" class="nc-icon-button" data-select-copy aria-label="Salin" title="Salin">${icon('copy')}</button>
			<button type="button" class="nc-icon-button is-danger" data-select-delete aria-label="Hapus" title="Hapus">${icon('trash')}</button>
		`;
		bar.addEventListener('click', onBarClick);
		(chatShell || document.body).prepend(bar);
	}
	bar.querySelector('[data-select-count]').textContent = `${selected.size} dipilih`;
	bar.querySelector('[data-select-copy]').disabled = !nodes().some((node) => String(node.dataset.messageText || '').trim());
};

export const exitSelectMode = () => {
	nodes().forEach((node) => node.classList.remove('is-selected'));
	selected.clear();
	root.classList.remove('nc-selecting');
	bar?.remove();
	bar = null;
};

const toggle = (node) => {
	const id = String(node.getAttribute('data-message-id') || '');
	if (!id || id.startsWith('local-')) {
		return;
	}
	const on = !selected.has(id);
	if (on) {
		selected.add(id);
	} else {
		selected.delete(id);
	}
	node.classList.toggle('is-selected', on);
	if (selected.size === 0) {
		exitSelectMode();
	} else {
		renderBar();
	}
};

async function onBarClick(event) {
	if (event.target.closest('[data-select-exit]')) {
		exitSelectMode();
	} else if (event.target.closest('[data-select-copy]')) {
		const lines = nodes()
			.filter((node) => String(node.dataset.messageText || '').trim())
			.map((node) => `${node.dataset.messageSenderName}: ${node.dataset.messageText}`);
		try {
			await writeClipboard(lines.join('\n\n'));
			showActionToast(`${lines.length} pesan disalin`);
			exitSelectMode();
		} catch {
			showActionToast('Gagal menyalin');
		}
	} else if (event.target.closest('[data-select-delete]')) {
		if (await deleteMessages(nodes())) {
			exitSelectMode();
		}
	}
}

export const isSelecting = () => root.classList.contains('nc-selecting');

/** Mulai memilih dari menu "Pilih pesan"; pesan itu langsung terpilih. */
export const enterSelectMode = (node) => {
	root.classList.add('nc-selecting');
	toggle(node);
};

export const initSelectMode = () => {
	// Saat memilih, ketukan pada pesan hanya memilih (tidak membuka gambar, kutipan, atau tautan).
	chatFeed?.addEventListener('click', (event) => {
		if (!isSelecting()) {
			return;
		}
		const node = event.target instanceof Element ? event.target.closest('.nc-msg') : null;
		event.preventDefault();
		event.stopPropagation();
		if (node instanceof HTMLElement) {
			toggle(node);
		}
	}, true);
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && isSelecting()) {
			exitSelectMode();
		}
	});
};
