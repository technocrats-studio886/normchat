// Tugas grup (App\Services\Chat\GroupTasks): daftar bersama, tambah, centang, hapus, dan "Jadikan tugas" dari pesan.

import { jumpToMessage } from '../message-search.js';
import { showActionToast } from '../composer/toast.js';
import { escapeHtml } from '../utils.js';
import { groupFrozen, toolsRequest } from './api.js';
import { openSheet, openSheetOf } from './sheet.js';

let state = { open: [], done: [], done_total: 0, members: [] };
let showDone = false;

const itemHtml = (task) => `
	<li class="nc-task${task.done ? ' is-done' : ''}">
		<label class="nc-task-check">
			<input type="checkbox" data-task-toggle="${task.id}" ${task.done ? 'checked' : ''} ${groupFrozen() ? 'disabled' : ''} aria-label="Tandai selesai">
		</label>
		<div class="min-w-0 flex-1">
			<p class="nc-task-title">${escapeHtml(task.title)}</p>
			<p class="nc-task-meta">${[
				task.assignee_name ? escapeHtml(task.assignee_name) : '',
				task.due_label ? `<span class="${task.overdue ? 'is-overdue' : ''}">${escapeHtml(task.due_label)}</span>` : '',
				task.done && task.done_by_name ? `Diselesaikan ${escapeHtml(task.done_by_name)}` : '',
			].filter(Boolean).join(' · ')}</p>
		</div>
		${task.source_message_id ? `<button type="button" class="nc-sheet-link" data-task-source="${task.source_message_id}">Lihat pesan</button>` : ''}
		${task.can_delete && !groupFrozen() ? `<button type="button" class="nc-task-delete" data-task-delete="${task.id}" aria-label="Hapus tugas"><svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg></button>` : ''}
	</li>`;

const bodyHtml = () => `
	<div data-sheet-kind="tasks">
		${groupFrozen() ? '' : `<form class="nc-task-form" data-task-form>
			<input type="text" name="title" class="nc-input w-full" maxlength="300" placeholder="Tambah tugas, mis. Kumpulkan laporan" autocomplete="off" required>
			<div class="nc-task-form-row">
				<select name="assignee_id" class="nc-input nc-task-assignee" aria-label="Penanggung jawab">
					<option value="">Tanpa penanggung jawab</option>
					${state.members.map((member) => `<option value="${member.id}">${escapeHtml(member.name)}</option>`).join('')}
				</select>
				<label class="nc-task-due"><span>Tenggat</span><input type="date" name="due_date" class="nc-input" aria-label="Tenggat (opsional)"></label>
				<button type="submit" class="btn-primary px-4 py-2">Tambah</button>
			</div>
		</form>`}
		${state.open.length === 0
			? '<p class="nc-sheet-empty">Belum ada tugas. Tambahkan di atas, pilih "Jadikan tugas" pada pesan, atau minta "@normi buat checklist dari pembahasan tadi".</p>'
			: `<ul class="nc-task-list">${state.open.map(itemHtml).join('')}</ul>`}
		${state.done_total > 0 ? `
			<button type="button" class="nc-task-done-toggle" data-task-show-done aria-expanded="${showDone}">Selesai (${state.done_total})</button>
			${showDone ? `<ul class="nc-task-list">${state.done.map(itemHtml).join('')}</ul>` : ''}` : ''}
	</div>`;

const rerender = () => {
	const sheet = openSheetOf('tasks');
	if (!sheet) return;
	const draft = sheet.body.querySelector('[data-task-form] input[name="title"]')?.value || '';
	sheet.body.innerHTML = bodyHtml();
	const input = sheet.body.querySelector('[data-task-form] input[name="title"]');
	if (input && draft) input.value = draft;
};

const apply = (payload) => {
	if (payload?.open) {
		state = { open: payload.open, done: payload.done || [], done_total: payload.done_total || 0, members: payload.members || state.members };
		rerender();
	}
};

export const refreshTasks = async () => {
	if (!openSheetOf('tasks')) return;
	try {
		apply(await toolsRequest('/tasks'));
	} catch {
		// Daftar lama tetap tampil.
	}
};

export const openTasksSheet = async () => {
	const sheet = openSheet({ title: 'Tugas grup', html: '<div data-sheet-kind="tasks"><p class="nc-sheet-empty">Memuat…</p></div>' });
	sheet.body.addEventListener('submit', async (event) => {
		const form = event.target.closest('[data-task-form]');
		if (!form) return;
		event.preventDefault();
		const data = new FormData(form);
		const title = String(data.get('title') || '').trim();
		if (!title) return;
		form.querySelector('button[type="submit"]').disabled = true;
		try {
			const payload = await toolsRequest('/tasks', 'POST', {
				title,
				assignee_id: Number(data.get('assignee_id') || 0) || null,
				due_date: String(data.get('due_date') || '') || null,
			});
			form.reset();
			apply(payload);
			sheet.body.querySelector('[data-task-form] input[name="title"]')?.focus();
		} catch (error) {
			showActionToast(error?.message || 'Gagal menambah tugas');
			form.querySelector('button[type="submit"]').disabled = false;
		}
	});
	sheet.body.addEventListener('change', async (event) => {
		const toggle = event.target.closest('[data-task-toggle]');
		if (!toggle) return;
		try {
			apply(await toolsRequest(`/tasks/${toggle.dataset.taskToggle}`, 'PATCH', { done: toggle.checked }));
		} catch {
			toggle.checked = !toggle.checked;
			showActionToast('Gagal menyimpan');
		}
	});
	sheet.body.addEventListener('click', async (event) => {
		const remove = event.target.closest('[data-task-delete]');
		const source = event.target.closest('[data-task-source]');
		if (event.target.closest('[data-task-show-done]')) {
			showDone = !showDone;
			rerender();
		} else if (remove) {
			try {
				apply(await toolsRequest(`/tasks/${remove.dataset.taskDelete}`, 'DELETE'));
			} catch (error) {
				showActionToast(error?.message || 'Gagal menghapus tugas');
			}
		} else if (source) {
			sheet.close();
			jumpToMessage(Number(source.dataset.taskSource));
		}
	});
	try {
		apply(await toolsRequest('/tasks'));
	} catch {
		sheet.body.innerHTML = '<div data-sheet-kind="tasks"><p class="nc-sheet-empty">Daftar tugas gagal dimuat. Coba lagi.</p></div>';
	}
};

/** Aksi pesan "Jadikan tugas". */
export const taskFromMessage = async (node) => {
	const text = String(node.dataset.messageText || '').trim().split('\n').find((line) => line.trim() !== '') || '';
	if (!text) return;
	try {
		await toolsRequest('/tasks', 'POST', { title: text.slice(0, 290), source_message_id: Number(node.getAttribute('data-message-id') || 0) });
		showActionToast('Ditambahkan ke Tugas grup');
	} catch (error) {
		showActionToast(error?.message || 'Gagal menambah tugas');
	}
};

