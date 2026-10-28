// Bagikan = teruskan pesan ke grup lain milik user (bisa beberapa grup sekaligus).
// Daftar grup tujuan dikirim server di halaman chat (<script data-forward-targets>).

import { chatMessagesBaseUrl } from '../composer/elements.js';
import { showActionToast } from '../composer/toast.js';
import { chatApiRequest, escapeHtml } from '../utils.js';
import { icon } from './icons.js';

const readTargets = () => {
	try {
		const targets = JSON.parse(document.querySelector('[data-forward-targets]')?.textContent || '[]');
		return Array.isArray(targets) ? targets : [];
	} catch {
		return [];
	}
};

export const openForwardSheet = (node) => {
	const messageId = Number(node.getAttribute('data-message-id') || 0);
	const targets = readTargets();
	const preview = String(node.dataset.messageContent || '').trim();

	const overlay = document.createElement('div');
	overlay.className = 'nc-dialog-overlay';
	overlay.innerHTML = `
		<div class="nc-dialog nc-forward" role="dialog" aria-modal="true" aria-labelledby="nc-forward-title">
			<div class="flex items-center justify-between gap-2">
				<h2 id="nc-forward-title" class="nc-dialog-title">Bagikan ke grup</h2>
				<button type="button" class="nc-icon-button" data-forward-close aria-label="Tutup">${icon('close')}</button>
			</div>
			<p class="nc-forward-preview">${escapeHtml(preview.slice(0, 140))}</p>
			${targets.length > 6 ? '<input type="search" class="nc-forward-search" placeholder="Cari grup" aria-label="Cari grup" data-forward-search>' : ''}
			<div class="nc-forward-list" role="group" aria-label="Grup tujuan">
				${targets.length
					? targets.map((target) => `
						<label class="nc-forward-item" data-forward-name="${escapeHtml(String(target.name).toLowerCase())}">
							<input type="checkbox" value="${Number(target.id)}">
							<span class="nc-forward-initial" aria-hidden="true">${escapeHtml(String(target.name).slice(0, 1).toUpperCase())}</span>
							<span class="min-w-0 flex-1 truncate">${escapeHtml(target.name)}</span>
						</label>`).join('')
					: '<p class="nc-forward-empty">Kamu belum punya grup lain.</p>'}
			</div>
			<button type="button" class="btn-primary w-full py-2.5 disabled:opacity-50" data-forward-send disabled>Pilih grup tujuan</button>
		</div>
	`;

	const sendButton = overlay.querySelector('[data-forward-send]');
	const checked = () => Array.from(overlay.querySelectorAll('input[type="checkbox"]:checked')).map((input) => Number(input.value));
	const close = () => {
		document.removeEventListener('keydown', onKey);
		overlay.remove();
	};
	const onKey = (event) => {
		if (event.key === 'Escape') {
			close();
		}
	};

	overlay.addEventListener('change', () => {
		const count = checked().length;
		sendButton.disabled = count === 0;
		sendButton.textContent = count === 0 ? 'Pilih grup tujuan' : `Kirim ke ${count} grup`;
	});
	overlay.querySelector('[data-forward-search]')?.addEventListener('input', (event) => {
		const query = event.target.value.trim().toLowerCase();
		overlay.querySelectorAll('[data-forward-name]').forEach((item) => {
			item.classList.toggle('hidden', !item.getAttribute('data-forward-name').includes(query));
		});
	});
	overlay.addEventListener('click', (event) => {
		if (event.target === overlay || event.target.closest('[data-forward-close]')) {
			close();
		}
	});
	sendButton.addEventListener('click', async () => {
		const groupIds = checked();
		sendButton.disabled = true;
		try {
			const result = await chatApiRequest(`${chatMessagesBaseUrl}/${messageId}/forward`, { method: 'POST', body: { group_ids: groupIds } });
			close();
			showActionToast(`Diteruskan ke ${result?.forwarded || groupIds.length} grup`);
		} catch {
			sendButton.disabled = false;
			showActionToast('Gagal meneruskan pesan');
		}
	});

	document.addEventListener('keydown', onKey);
	document.body.appendChild(overlay);
	overlay.querySelector('input')?.focus();
};
