// Menjalankan aksi pesan dari menu (menu.js) dan mode pilih pesan (select-mode.js).

import { startEditMode } from '../composer/edit-mode.js';
import { openQuotePicker } from '../composer/quote-picker.js';
import { setReplyTarget } from '../composer/reply.js';
import { showActionToast } from '../composer/toast.js';
import { highlightMessageInFeed } from '../highlight.js';
import { deleteMessages } from './delete.js';
import { openForwardSheet } from './forward.js';
import { icon } from './icons.js';
import { messageFacts } from './rules.js';
import { enterSelectMode } from './select-mode.js';
import { openVersions } from './versions.js';

/** navigator.clipboard hanya ada di HTTPS; di luar itu memakai salinan lewat textarea sementara. */
export const writeClipboard = async (text) => {
	if (navigator.clipboard?.writeText) {
		await navigator.clipboard.writeText(text);
		return;
	}
	const area = document.createElement('textarea');
	area.value = text;
	area.setAttribute('readonly', '');
	area.style.position = 'fixed';
	area.style.opacity = '0';
	document.body.appendChild(area);
	area.select();
	const copied = document.execCommand('copy');
	area.remove();
	if (!copied) {
		throw new Error('copy_failed');
	}
};

/** Salin teks; tombol ikon berganti centang sebentar, selain itu muncul toast. */
export const copyText = async (text, button = null) => {
	try {
		await writeClipboard(text);
	} catch {
		showActionToast('Gagal menyalin');
		return;
	}

	if (button instanceof HTMLElement) {
		const original = button.innerHTML;
		button.innerHTML = icon('check', 'h-[18px] w-[18px]');
		button.setAttribute('aria-label', 'Tersalin');
		window.setTimeout(() => {
			button.innerHTML = original;
			button.setAttribute('aria-label', 'Salin');
		}, 1200);
	} else {
		showActionToast('Disalin');
	}
};

export const runAction = (action, node, { button = null } = {}) => {
	const facts = messageFacts(node);

	switch (action) {
		case 'reply':
			setReplyTarget(node);
			break;
		case 'copy':
			copyText(String(node.dataset.messageText || ''), button);
			break;
		case 'forward':
			openForwardSheet(node);
			break;
		case 'edit':
			startEditMode(node);
			break;
		case 'versions':
			openVersions(facts.isAi ? facts.replyToId : facts.id);
			break;
		case 'selectText':
			openQuotePicker(node, { onPick: (quote) => setReplyTarget(node, quote) });
			break;
		case 'select':
			enterSelectMode(node);
			break;
		case 'link':
			copyText(`${window.location.origin}${window.location.pathname}#message-${facts.id}`, button);
			highlightMessageInFeed(String(facts.id));
			break;
		case 'delete':
			deleteMessages([node]);
			break;
		default:
			break;
	}
};
