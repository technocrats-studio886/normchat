// Panel pesan penuh untuk memilih bagian yang dibalas: blok teks, lalu "Balas bagian ini".
// Dibuka dari menu "Pilih teks" (tekan lama) dan dari bar balasan di atas kolom ketik.

import { chatFeed } from '../context.js';
import { renderMarkdown, renderMermaidInNode } from '../markdown.js';
import { escapeHtml } from '../utils.js';
import { replyClearButton, replyPreview, replyQuoteInput, replyToInput } from './elements.js';
import { normalizeReplyQuote, setReplyTarget } from './reply.js';
import { showActionToast } from './toast.js';

const HINT = 'Blok teks yang mau dibalas.';

let activePicker = null;

export const closeQuotePicker = () => {
	activePicker?.();
	activePicker = null;
};

/**
 * @param {HTMLElement} messageNode node [data-message-id] di feed
 * @param {{ currentQuote?: string, onPick: (quote: string) => void }} options
 *        onPick menerima kutipan terpilih, atau '' untuk membalas seluruh pesan.
 */
export const openQuotePicker = (messageNode, { currentQuote = '', onPick }) => {
	closeQuotePicker();

	const sender = String(messageNode.dataset.messageSenderName || 'User');
	const content = String(messageNode.dataset.messageContent || '').trim();
	const isAi = messageNode.dataset.senderKey === 'ai';
	const bodyHtml = isAi
		? `<div class="ai-markdown">${renderMarkdown(escapeHtml(content))}</div>`
		: `<p class="whitespace-pre-wrap">${escapeHtml(content)}</p>`;

	const overlay = document.createElement('div');
	overlay.className = 'nc-popup-overlay';
	overlay.innerHTML = `
		<div class="nc-popup-card nc-popup-card--wide" role="dialog" aria-modal="true" aria-label="Pilih bagian pesan">
			<div class="nc-popup-header">
				<span class="nc-popup-sender">${escapeHtml(sender)}</span>
				<button type="button" class="nc-popup-close" data-quote-close aria-label="Tutup">&times;</button>
			</div>
			<div class="nc-popup-body nc-popup-body--quote">
				<div class="nc-quote-picker" data-quote-source>${bodyHtml}</div>
				<p class="nc-quote-result" data-quote-result></p>
			</div>
			<div class="nc-popup-actions">
				<button type="button" class="nc-popup-btn nc-popup-btn-select" data-quote-whole>Seluruh pesan</button>
				<button type="button" class="nc-popup-btn" data-quote-pick disabled>Balas bagian ini</button>
			</div>
		</div>
	`;
	document.body.appendChild(overlay);
	if (isAi) {
		renderMermaidInNode(overlay);
	}

	const source = overlay.querySelector('[data-quote-source]');
	const result = overlay.querySelector('[data-quote-result]');
	const pickButton = overlay.querySelector('[data-quote-pick]');
	let quote = '';
	// Di layar sentuh, menekan tombol bisa menghapus blok sebelum event click datang;
	// selama tombol ditekan, kutipan terakhir dipertahankan.
	let pressingAction = false;

	const selectedQuote = () => {
		const selection = window.getSelection();
		if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
			return '';
		}

		return source.contains(selection.getRangeAt(0).commonAncestorContainer)
			? normalizeReplyQuote(selection.toString())
			: '';
	};

	const render = () => {
		result.textContent = quote !== '' ? `"${quote}"` : (currentQuote !== '' ? `Sekarang membalas: "${currentQuote}"` : HINT);
		pickButton.disabled = quote === '';
	};

	const onSelectionChange = () => {
		const next = selectedQuote();
		if (next === '' && pressingAction) {
			return;
		}
		quote = next;
		render();
	};

	const onEscape = (event) => {
		if (event.key === 'Escape') {
			closeQuotePicker();
		}
	};

	const pick = (value) => {
		closeQuotePicker();
		window.getSelection()?.removeAllRanges();
		onPick(value);
	};

	overlay.querySelectorAll('.nc-popup-actions button').forEach((button) => {
		button.addEventListener('pointerdown', () => { pressingAction = true; });
		button.addEventListener('pointerup', () => { window.setTimeout(() => { pressingAction = false; }, 0); });
	});
	pickButton.addEventListener('click', () => {
		if (quote !== '') {
			pick(quote);
		}
	});
	overlay.querySelector('[data-quote-whole]').addEventListener('click', () => pick(''));
	overlay.querySelector('[data-quote-close]').addEventListener('click', closeQuotePicker);
	overlay.addEventListener('click', (event) => {
		if (event.target === overlay) {
			closeQuotePicker();
		}
	});
	document.addEventListener('selectionchange', onSelectionChange);
	document.addEventListener('keydown', onEscape);

	activePicker = () => {
		document.removeEventListener('selectionchange', onSelectionChange);
		document.removeEventListener('keydown', onEscape);
		overlay.remove();
	};

	render();
};

/** Bar balasan di atas kolom ketik: ketuk untuk melihat pesan penuh dan memilih bagian yang dibalas. */
export const initReplyPreviewPicker = () => {
	if (!(replyPreview instanceof HTMLElement)) {
		return;
	}

	const open = () => {
		const messageId = String(replyToInput instanceof HTMLInputElement ? replyToInput.value : '').trim();
		if (!messageId) {
			return;
		}

		const messageNode = chatFeed?.querySelector(`[data-message-id="${messageId}"]`);
		if (!(messageNode instanceof HTMLElement)) {
			showActionToast('Pesan tidak ditemukan');
			return;
		}

		openQuotePicker(messageNode, {
			currentQuote: replyQuoteInput instanceof HTMLInputElement ? replyQuoteInput.value : '',
			onPick: (quote) => setReplyTarget(messageNode, quote),
		});
	};

	replyPreview.addEventListener('click', (event) => {
		if (!replyClearButton?.contains(event.target)) {
			open();
		}
	});
	replyPreview.querySelector('[data-reply-preview-open]')?.addEventListener('keydown', (event) => {
		if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			open();
		}
	});
};
