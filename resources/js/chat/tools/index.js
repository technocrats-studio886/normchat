// Fitur produktivitas grup di halaman chat (docs/rancangan-fitur-produktivitas-nov.md): menu, tombol di bubble,
// tombol pemberitahuan sistem, dan pembaruan realtime (event group.data.changed).

import { chatFeed } from '../context.js';
import { jumpToMessage } from '../message-search.js';
import { insertMessagePayload } from '../realtime.js';
import { showActionToast } from '../composer/toast.js';
import { toolsAvailable, toolsRequest } from './api.js';
import { initPins, refreshPins } from './pins.js';
import { cancelReminder, openReminderForm, openRemindersSheet, refreshReminders } from './reminders.js';
import { initScheduled, openScheduleSheet, refreshScheduled } from './scheduled.js';
import { openTasksSheet, refreshTasks } from './tasks.js';

const closeMenus = () => {
	document.querySelector('[data-attach-menu]')?.classList.add('hidden');
	document.querySelector('[data-settings-menu]')?.classList.add('hidden');
	document.querySelector('[data-settings-overlay]')?.classList.add('hidden');
};

/** "Ringkas dengan Normi" di pembatas pesan belum dibaca (chat/read-state.js). */
const catchUp = async (button) => {
	button.disabled = true;
	button.textContent = 'Meminta ringkasan…';
	try {
		const { message } = await toolsRequest('/catch-up', 'POST', {
			from_id: Number(button.dataset.catchUp || 0),
			ai_model: document.querySelector('[data-ai-model-input]')?.value || null,
		});
		insertMessagePayload(message);
		button.remove();
	} catch (error) {
		button.disabled = false;
		button.textContent = 'Ringkas dengan Normi';
		showActionToast(error?.message && error.message !== 'request_failed' ? error.message : 'Gagal meminta ringkasan');
	}
};

export const initTools = () => {
	if (!(chatFeed instanceof HTMLElement) || !toolsAvailable()) return;
	initPins();
	initScheduled();

	document.addEventListener('click', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		if (!target) return;
		const open = target.closest('[data-open-tasks], [data-open-reminders], [data-new-reminder], [data-open-schedule]');
		if (open) {
			closeMenus();
			if (open.matches('[data-open-tasks]')) openTasksSheet();
			else if (open.matches('[data-open-reminders]')) openRemindersSheet();
			else if (open.matches('[data-new-reminder]')) openReminderForm();
			else openScheduleSheet();
			return;
		}
		const cancel = target.closest('[data-chat-feed] [data-reminder-cancel]');
		if (cancel) {
			cancelReminder(Number(cancel.dataset.reminderCancel));
			return;
		}
		const jump = target.closest('[data-jump-message]');
		if (jump) {
			jumpToMessage(Number(jump.dataset.jumpMessage));
			return;
		}
		const catchUpButton = target.closest('[data-catch-up]');
		if (catchUpButton instanceof HTMLButtonElement) catchUp(catchUpButton);
	});

	window.addEventListener('nc:group-data', (event) => {
		const kind = event.detail?.kind;
		if (kind === 'pins') refreshPins();
		else if (kind === 'tasks') refreshTasks();
		else if (kind === 'reminders') refreshReminders();
		else if (kind === 'scheduled') refreshScheduled();
	});
	// Kembali dari bfcache: daftar bisa sudah berubah.
	window.addEventListener('pageshow', (event) => {
		if (event.persisted) {
			refreshPins();
			refreshScheduled();
		}
	});
};
