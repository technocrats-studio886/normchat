// Pengingat (App\Services\Chat\Reminders): daftar yang akan datang, buat pengingat (dari menu + atau dari pesan),
// dan tombol "Batalkan pengingat" di jawaban Normi.

import { authUserId } from '../context.js';
import { showActionToast } from '../composer/toast.js';
import { escapeAttr, escapeHtml } from '../utils.js';
import { groupFrozen, toolsRequest, viewerIsModerator } from './api.js';
import { isoFromLocalInput, localInputValue, openSheet, openSheetOf, quickTimes } from './sheet.js';

const REPEAT_LABEL = { none: '', daily: 'Tiap hari', weekly: 'Tiap minggu' };

const listHtml = (reminders) => `<div data-sheet-kind="reminders">
	${groupFrozen() ? '' : '<button type="button" class="btn-primary mb-3 w-full py-2" data-reminder-new>Buat pengingat</button>'}
	${reminders.length === 0
		? '<p class="nc-sheet-empty">Belum ada pengingat. Buat di sini, atau tulis "@normi ingatkan grup Jumat jam 7 bawa laptop".</p>'
		: `<ul class="nc-sheet-list">${reminders.map((reminder) => `
			<li class="nc-sheet-item">
				<div class="min-w-0 flex-1">
					<span class="nc-sheet-item-title">${escapeHtml(reminder.text)}</span>
					<span class="nc-sheet-item-meta">${escapeHtml(reminder.when)}${reminder.audience === 'me' ? ' · hanya untukmu' : ''}${reminder.creator_name && reminder.audience !== 'me' ? ` · dibuat ${escapeHtml(reminder.creator_name)}` : ''}</span>
				</div>
				${reminder.can_cancel ? `<button type="button" class="nc-sheet-link is-danger" data-reminder-cancel="${reminder.id}">Batalkan</button>` : ''}
			</li>`).join('')}</ul>`}
</div>`;

export const refreshReminders = async () => {
	const sheet = openSheetOf('reminders');
	if (!sheet) return;
	try {
		sheet.body.innerHTML = listHtml((await toolsRequest('/reminders'))?.reminders || []);
	} catch {
		// Tetap daftar lama.
	}
};

export const cancelReminder = async (id) => {
	try {
		await toolsRequest(`/reminders/${id}`, 'DELETE');
		showActionToast('Pengingat dibatalkan');
		refreshReminders();
	} catch (error) {
		showActionToast(error?.message || 'Gagal membatalkan pengingat');
	}
};

export const openRemindersSheet = async () => {
	const sheet = openSheet({ title: 'Pengingat', html: '<div data-sheet-kind="reminders"><p class="nc-sheet-empty">Memuat…</p></div>' });
	sheet.body.addEventListener('click', (event) => {
		const cancel = event.target.closest('[data-reminder-cancel]');
		if (event.target.closest('[data-reminder-new]')) {
			openReminderForm();
		} else if (cancel) {
			cancelReminder(Number(cancel.dataset.reminderCancel));
		}
	});
	refreshReminders();
};

/** Lembar "Buat pengingat"; dari aksi pesan, isinya diambil dari pesan itu. */
export const openReminderForm = ({ text = '', sourceId = 0 } = {}) => {
	const quick = quickTimes();
	const defaultTime = quick[0]?.date || new Date(Date.now() + 3600_000);
	const sheet = openSheet({
		title: 'Buat pengingat',
		html: `<form class="nc-tool-form" data-reminder-form>
			<label class="nc-tool-label">Ingatkan tentang
				<textarea name="text" rows="2" maxlength="500" class="nc-input w-full" placeholder="mis. Bawa laptop untuk presentasi" required>${escapeHtml(text)}</textarea>
			</label>
			<div class="nc-chip-row">${quick.map((item) => `<button type="button" class="nc-chip" data-quick-time="${escapeAttr(localInputValue(item.date))}">${escapeHtml(item.label)}</button>`).join('')}</div>
			<label class="nc-tool-label">Waktu
				<input type="datetime-local" name="remind_at" class="nc-input w-full" value="${localInputValue(defaultTime)}" min="${localInputValue(new Date())}" required>
			</label>
			<div class="flex gap-2">
				<label class="nc-tool-label min-w-0 flex-1">Ulangi
					<select name="repeat" class="nc-input w-full">
						<option value="none">Sekali</option>
						<option value="daily">Tiap hari</option>
						<option value="weekly">Tiap minggu</option>
					</select>
				</label>
				<label class="nc-tool-label min-w-0 flex-1">Untuk
					<select name="audience" class="nc-input w-full">
						<option value="group">Semua anggota</option>
						<option value="me">Hanya aku</option>
					</select>
				</label>
			</div>
			<button type="submit" class="btn-primary w-full py-2">Simpan pengingat</button>
		</form>`,
	});
	const form = sheet.body.querySelector('[data-reminder-form]');
	form.addEventListener('click', (event) => {
		const chip = event.target.closest('[data-quick-time]');
		if (chip) form.elements.remind_at.value = chip.dataset.quickTime;
	});
	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const button = form.querySelector('button[type="submit"]');
		button.disabled = true;
		try {
			await toolsRequest('/reminders', 'POST', {
				text: form.elements.text.value.trim(),
				remind_at: isoFromLocalInput(form.elements.remind_at.value),
				repeat: form.elements.repeat.value,
				audience: form.elements.audience.value,
				source_message_id: sourceId || null,
			});
			sheet.close();
			showActionToast(form.elements.audience.value === 'me' ? 'Pengingat untukmu tersimpan' : 'Pengingat grup tersimpan');
		} catch (error) {
			showActionToast(error?.message || 'Gagal menyimpan pengingat');
			button.disabled = false;
		}
	});
	window.setTimeout(() => form.elements.text.focus(), 50);
};

/** Tombol di jawaban Normi yang membuat pengingat (MessagePresenter::reminderOf). */
export const reminderBubbleHtml = (message) => {
	const reminder = message?.reminder;
	if (!reminder?.id) return '';
	if (reminder.status === 'cancelled') return '<p class="nc-bubble-note">Pengingat ini dibatalkan.</p>';
	if (reminder.status !== 'pending') return '';
	const canCancel = Number(reminder.created_by || 0) === authUserId || (!reminder.personal && viewerIsModerator());
	return canCancel && !groupFrozen()
		? `<div><button type="button" class="nc-suggest" data-reminder-cancel="${Number(reminder.id)}">Batalkan pengingat</button></div>`
		: '';
};
