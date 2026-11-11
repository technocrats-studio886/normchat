// Pesan terjadwal (App\Services\Chat\ScheduledMessages): lembar jadwalkan + daftar milikku, dan pil "N pesan terjadwal".

import { chatFeed } from '../context.js';
import { replyToInput, mentionInput } from '../composer/elements.js';
import { clearReplyTarget } from '../composer/reply.js';
import { showActionToast } from '../composer/toast.js';
import { escapeAttr, escapeHtml } from '../utils.js';
import { toolsRequest } from './api.js';
import { isoFromLocalInput, localInputValue, openSheet, openSheetOf, quickTimes } from './sheet.js';

const pill = document.querySelector('[data-scheduled-pill]');
let count = Number(chatFeed?.getAttribute('data-scheduled-count') || 0);

const renderPill = () => {
	if (!(pill instanceof HTMLElement)) return;
	pill.classList.toggle('hidden', count === 0);
	pill.querySelector('[data-scheduled-pill-text]').textContent = `${count} pesan terjadwal`;
};

const listHtml = (items) => items.length === 0 ? '' : `
	<p class="nc-tool-subtitle">Terjadwal</p>
	<ul class="nc-sheet-list">${items.map((item) => `
		<li class="nc-sheet-item">
			<div class="min-w-0 flex-1">
				<span class="nc-sheet-item-text">${escapeHtml(item.content)}</span>
				<span class="nc-sheet-item-meta">${escapeHtml(item.when)}</span>
			</div>
			<div class="flex shrink-0 flex-col items-end gap-1">
				<button type="button" class="nc-sheet-link" data-scheduled-send="${item.id}">Kirim sekarang</button>
				<button type="button" class="nc-sheet-link is-danger" data-scheduled-cancel="${item.id}">Batalkan</button>
			</div>
		</li>`).join('')}</ul>`;

const applyList = (items) => {
	count = items.length;
	renderPill();
	const sheet = openSheetOf('scheduled');
	const holder = sheet?.body.querySelector('[data-scheduled-list]');
	if (holder) holder.innerHTML = listHtml(items);
};

export const refreshScheduled = async () => {
	try {
		applyList((await toolsRequest('/scheduled'))?.scheduled || []);
	} catch {
		// Tetap daftar lama.
	}
};

/** Lembar "Jadwalkan pesan": isi diambil dari kolom ketik, termasuk balasan yang sedang dipilih. */
export const openScheduleSheet = () => {
	const draft = mentionInput instanceof HTMLTextAreaElement ? mentionInput.value : '';
	const replyTo = replyToInput instanceof HTMLInputElement ? Number(replyToInput.value || 0) : 0;
	const quick = quickTimes();
	const sheet = openSheet({
		title: 'Jadwalkan pesan',
		html: `<div data-sheet-kind="scheduled">
			<form class="nc-tool-form" data-scheduled-form>
				<label class="nc-tool-label">Pesan
					<textarea name="content" rows="3" maxlength="3000" class="nc-input w-full" placeholder="Tulis pesan yang akan dikirim nanti" required>${escapeHtml(draft)}</textarea>
				</label>
				${replyTo ? '<p class="nc-tool-hint">Dikirim sebagai balasan ke pesan yang sedang kamu balas.</p>' : ''}
				<div class="nc-chip-row">${quick.map((item) => `<button type="button" class="nc-chip" data-quick-time="${escapeAttr(localInputValue(item.date))}">${escapeHtml(item.label)}</button>`).join('')}</div>
				<label class="nc-tool-label">Kirim pada
					<input type="datetime-local" name="send_at" class="nc-input w-full" value="${localInputValue(quick[0]?.date || new Date(Date.now() + 3600_000))}" min="${localInputValue(new Date())}" required>
				</label>
				<button type="submit" class="btn-primary w-full py-2">Jadwalkan</button>
			</form>
			<div data-scheduled-list></div>
		</div>`,
	});
	const form = sheet.body.querySelector('[data-scheduled-form]');
	sheet.body.addEventListener('click', async (event) => {
		const chip = event.target.closest('[data-quick-time]');
		const send = event.target.closest('[data-scheduled-send]');
		const cancel = event.target.closest('[data-scheduled-cancel]');
		if (chip) {
			form.elements.send_at.value = chip.dataset.quickTime;
		} else if (send || cancel) {
			const id = Number((send || cancel).dataset[send ? 'scheduledSend' : 'scheduledCancel']);
			try {
				const payload = await toolsRequest(send ? `/scheduled/${id}/send` : `/scheduled/${id}`, send ? 'POST' : 'DELETE');
				applyList(payload?.scheduled || []);
				showActionToast(send ? 'Pesan terkirim' : 'Jadwal dibatalkan');
			} catch (error) {
				showActionToast(error?.message || 'Gagal');
			}
		}
	});
	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const button = form.querySelector('button[type="submit"]');
		button.disabled = true;
		try {
			const payload = await toolsRequest('/scheduled', 'POST', {
				content: form.elements.content.value.trim(),
				send_at: isoFromLocalInput(form.elements.send_at.value),
				reply_to_message_id: replyTo || null,
				ai_mode: document.querySelector('[data-ai-mode-input]')?.value || null,
				ai_model: document.querySelector('[data-ai-model-input]')?.value || null,
			});
			applyList(payload?.scheduled || []);
			form.elements.content.value = '';
			// Isi kolom ketik sudah dipindah ke jadwal.
			if (mentionInput instanceof HTMLTextAreaElement && draft && mentionInput.value === draft) {
				mentionInput.value = '';
				mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
			if (replyTo) clearReplyTarget();
			showActionToast('Pesan dijadwalkan');
			button.disabled = false;
		} catch (error) {
			showActionToast(error?.message || 'Gagal menjadwalkan pesan');
			button.disabled = false;
		}
	});
	refreshScheduled();
	window.setTimeout(() => form.elements.content.focus(), 50);
};

export const initScheduled = () => {
	renderPill();
	pill?.addEventListener('click', openScheduleSheet);
};
