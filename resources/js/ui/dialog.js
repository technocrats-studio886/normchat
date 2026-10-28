// Dialog aplikasi pengganti window.confirm: judul, keterangan, dan beberapa pilihan tombol.
// Mengembalikan value pilihan, atau null bila ditutup (Batal, Esc, ketuk di luar).

import { escapeHtml } from '../chat/utils.js';

/**
 * @param {{ title: string, message?: string, choices: Array<{ label: string, value: string, tone?: 'danger'|'primary'|'plain' }>, cancelLabel?: string }} options
 * @returns {Promise<string|null>}
 */
export const openDialog = ({ title, message = '', choices, cancelLabel = 'Batal' }) => new Promise((resolve) => {
	const previousFocus = document.activeElement;
	const overlay = document.createElement('div');
	overlay.className = 'nc-dialog-overlay';
	overlay.innerHTML = `
		<div class="nc-dialog" role="alertdialog" aria-modal="true" aria-labelledby="nc-dialog-title" ${message ? 'aria-describedby="nc-dialog-message"' : ''}>
			<h2 id="nc-dialog-title" class="nc-dialog-title">${escapeHtml(title)}</h2>
			${message ? `<p id="nc-dialog-message" class="nc-dialog-message">${escapeHtml(message)}</p>` : ''}
			<div class="nc-dialog-choices">
				${choices.map((choice) => `<button type="button" class="nc-dialog-choice is-${choice.tone || 'plain'}" data-dialog-value="${escapeHtml(choice.value)}">${escapeHtml(choice.label)}</button>`).join('')}
				<button type="button" class="nc-dialog-choice is-plain" data-dialog-value="">${escapeHtml(cancelLabel)}</button>
			</div>
		</div>
	`;

	const close = (value) => {
		document.removeEventListener('keydown', onKey);
		overlay.remove();
		if (previousFocus instanceof HTMLElement) {
			previousFocus.focus();
		}
		resolve(value || null);
	};
	const onKey = (event) => {
		if (event.key === 'Escape') {
			close(null);
		}
	};

	overlay.addEventListener('click', (event) => {
		const choice = event.target.closest('[data-dialog-value]');
		if (choice) {
			close(choice.getAttribute('data-dialog-value'));
		} else if (event.target === overlay) {
			close(null);
		}
	});
	document.addEventListener('keydown', onKey);
	document.body.appendChild(overlay);
	overlay.querySelector('.nc-dialog-choice')?.focus();
});
