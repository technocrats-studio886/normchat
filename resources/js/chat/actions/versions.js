// Popup "Version": setiap versi pesan beserta jawaban Normi pada versi itu.
// Navigasi ‹ Version N › di tengah bawah; dibuka pada versi terbaru.

import { authUserId, chatFeed } from '../context.js';
import { renderMarkdown } from '../markdown.js';
import { chatApiRequest, escapeHtml } from '../utils.js';
import { chatMessagesBaseUrl } from '../composer/elements.js';
import { icon } from './icons.js';

const timeFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

const answerHtml = (answer) => {
	if (!answer) {
		return '';
	}
	const attachment = answer.attachment_original_name
		? `<p class="nc-version-file">Lampiran: ${escapeHtml(answer.attachment_original_name)}</p>`
		: '';

	return `
		<div class="nc-version-row">
			<p class="nc-version-name" style="color: var(--nc-ai-name)">Normi AI</p>
			<div class="nc-bubble bubble-ai"><div class="ai-markdown">${renderMarkdown(escapeHtml(answer.content || ''))}</div>${attachment}</div>
		</div>
	`;
};

export const openVersions = async (messageId) => {
	if (!messageId) {
		return;
	}

	let versions = [];
	try {
		versions = (await chatApiRequest(`${chatMessagesBaseUrl}/${messageId}/versions`))?.versions || [];
	} catch {
		return;
	}
	if (versions.length === 0) {
		return;
	}

	const source = chatFeed?.querySelector(`[data-message-id="${messageId}"]`);
	const mine = source?.dataset.messageMine === '1' || Number(source?.dataset.messageSenderId || 0) === authUserId;
	let index = versions.length - 1;

	const overlay = document.createElement('div');
	overlay.className = 'nc-dialog-overlay';
	overlay.innerHTML = `
		<div class="nc-dialog nc-versions" role="dialog" aria-modal="true" aria-labelledby="nc-versions-title">
			<div class="flex items-center justify-between gap-2">
				<h2 id="nc-versions-title" class="nc-dialog-title">Version</h2>
				<button type="button" class="nc-icon-button" data-versions-close aria-label="Tutup">${icon('close')}</button>
			</div>
			<div class="nc-versions-body" data-versions-body aria-live="polite"></div>
			<div class="nc-versions-nav">
				<button type="button" class="nc-icon-button" data-versions-prev aria-label="Versi sebelumnya">${icon('chevronLeft')}</button>
				<span data-versions-label></span>
				<button type="button" class="nc-icon-button" data-versions-next aria-label="Versi selanjutnya">${icon('chevronRight')}</button>
			</div>
		</div>
	`;

	const body = overlay.querySelector('[data-versions-body]');
	const render = () => {
		const version = versions[index];
		const at = version.created_at ? timeFormat.format(new Date(version.created_at)) : '';
		body.innerHTML = `
			<p class="nc-version-time">${escapeHtml(at)}${index === versions.length - 1 ? ' · versi sekarang' : ''}</p>
			<div class="nc-version-row${mine ? ' is-mine' : ''}">
				<div class="nc-bubble ${mine ? 'bubble-mine' : 'bubble-other'}"><span class="whitespace-pre-wrap">${escapeHtml(version.content || '')}</span></div>
			</div>
			${answerHtml(version.answer)}
		`;
		overlay.querySelector('[data-versions-label]').textContent = `Version ${version.number}`;
		overlay.querySelector('[data-versions-prev]').disabled = index === 0;
		overlay.querySelector('[data-versions-next]').disabled = index === versions.length - 1;
	};

	const close = () => {
		document.removeEventListener('keydown', onKey);
		overlay.remove();
	};
	const onKey = (event) => {
		if (event.key === 'Escape') {
			close();
		} else if (event.key === 'ArrowLeft' && index > 0) {
			index -= 1;
			render();
		} else if (event.key === 'ArrowRight' && index < versions.length - 1) {
			index += 1;
			render();
		}
	};

	overlay.addEventListener('click', (event) => {
		if (event.target === overlay || event.target.closest('[data-versions-close]')) {
			close();
		} else if (event.target.closest('[data-versions-prev]') && index > 0) {
			index -= 1;
			render();
		} else if (event.target.closest('[data-versions-next]') && index < versions.length - 1) {
			index += 1;
			render();
		}
	});

	render();
	document.addEventListener('keydown', onKey);
	document.body.appendChild(overlay);
	overlay.querySelector('[data-versions-close]')?.focus();
};
