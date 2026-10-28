// Halaman Riwayat & Cadangan: lembar filter unduhan (hitungan pesan langsung), daftar unduhan dan
// cadangan yang statusnya berubah sendiri, buat cadangan, dan pulihkan cadangan.

import { showActionToast } from '../chat/composer/toast.js';
import { chatApiRequest, escapeHtml } from '../chat/utils.js';
import { openDialog } from '../ui/dialog.js';

const STATUS = { queued: 'Menunggu', processing: 'Sedang dibuat', pending: 'Sedang dibuat', done: 'Siap', ready: 'Siap', failed: 'Gagal' };
const POLL_MS = 3000;
const dateTime = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
const number = new Intl.NumberFormat('id-ID');

const isBusy = (status) => ['queued', 'processing', 'pending'].includes(status);
const spinner = '<span class="nc-spinner" aria-hidden="true"></span>';

const exportRow = (item) => `
	<div class="nc-list-row">
		${isBusy(item.status) ? spinner : ''}
		<div class="min-w-0 flex-1">
			<p class="truncate text-sm font-semibold">${escapeHtml(item.file_name)}</p>
			<p class="text-xs text-ink-soft">${escapeHtml(item.created_at)} · ${escapeHtml(STATUS[item.status] || item.status)}${item.status === 'done' ? ` · ${number.format(item.message_count)} pesan · tersedia sampai ${escapeHtml(item.expires_at)}` : ''}</p>
		</div>
		${item.download_url ? `<a href="${escapeHtml(item.download_url)}" class="btn-secondary shrink-0 px-3 py-1.5 text-xs">Unduh</a>` : ''}
	</div>`;

const backupRow = (item) => `
	<div class="nc-list-row">
		${isBusy(item.status) ? spinner : ''}
		<div class="min-w-0 flex-1">
			<p class="text-sm font-semibold">${isBusy(item.status) ? 'Sedang dibuat' : escapeHtml(dateTime.format(new Date(item.created_at)))}</p>
			<p class="text-xs text-ink-soft">${item.type === 'auto' ? 'Otomatis' : `Oleh ${escapeHtml(item.creator || 'anggota')}`}${item.status === 'ready' ? ` · ${number.format(item.message_count)} pesan · ${number.format(item.attachment_count)} lampiran` : ''}${item.status === 'failed' ? ' · Gagal dibuat' : ''}</p>
		</div>
		${item.status === 'ready' ? `<button type="button" class="btn-secondary shrink-0 px-3 py-1.5 text-xs" data-restore="${escapeHtml(item.preview_url)}" data-restore-url="${escapeHtml(item.restore_url)}" data-restore-date="${escapeHtml(dateTime.format(new Date(item.created_at)))}">Pulihkan</button>` : ''}
	</div>`;

/** Daftar yang memperbarui dirinya sendiri selama ada item yang masih diproses. */
const liveList = (container, url, key, rowHtml, emptyText) => {
	let items = [];
	let timer = null;
	const render = () => {
		container.innerHTML = items.length ? items.map(rowHtml).join('') : `<p class="nc-list-empty">${emptyText}</p>`;
	};
	const poll = () => {
		window.clearTimeout(timer);
		if (!items.some((item) => isBusy(item.status))) {
			return;
		}
		timer = window.setTimeout(async () => {
			try {
				items = (await chatApiRequest(url))?.[key] || items;
				render();
			} finally {
				poll();
			}
		}, POLL_MS);
	};

	return {
		set(next) {
			items = next;
			render();
			poll();
		},
		prepend(item) {
			this.set([item, ...items]);
		},
	};
};

const initExportSheet = (page, exportsList) => {
	const sheet = page.querySelector('[data-export-sheet]');
	const form = page.querySelector('[data-export-form]');
	if (!sheet || !form) {
		return;
	}

	const submit = form.querySelector('[data-export-submit]');
	const countLabel = form.querySelector('[data-export-count]');
	const allSenders = form.querySelector('[data-sender-all]');
	let countTimer = null;
	let countRequest = 0;

	const params = () => {
		const data = new FormData(form);
		const query = new URLSearchParams();
		query.set('file_type', data.get('file_type'));
		query.set('range', data.get('range'));
		if (data.get('range') === 'custom') {
			query.set('from', data.get('from') || '');
			query.set('to', data.get('to') || '');
		}
		data.getAll('senders[]').forEach((sender) => query.append('senders[]', sender));
		query.set('images', data.get('images') ? '1' : '0');
		query.set('polls', data.get('polls') ? '1' : '0');
		return query;
	};

	const refreshCount = () => {
		window.clearTimeout(countTimer);
		const custom = form.elements.range.value === 'custom';
		form.querySelector('[data-custom-range]').classList.toggle('hidden', !custom);
		form.querySelector('[data-custom-range]').classList.toggle('grid', custom);
		submit.textContent = `Buat ${form.elements.file_type.value.toUpperCase()}`;
		if (custom && (!form.elements.from.value || !form.elements.to.value)) {
			countLabel.textContent = 'Pilih tanggal mulai dan akhir.';
			submit.disabled = true;
			return;
		}

		countTimer = window.setTimeout(async () => {
			const request = ++countRequest;
			try {
				const result = await chatApiRequest(`${page.dataset.exportCountUrl}?${params()}`);
				if (request === countRequest) {
					countLabel.textContent = result.count > 0 ? `${number.format(result.count)} pesan akan diunduh` : 'Tidak ada pesan dengan filter ini';
					submit.disabled = result.count === 0;
				}
			} catch (error) {
				countLabel.textContent = error?.message && error.message !== 'request_failed' ? error.message : 'Gagal menghitung pesan';
				submit.disabled = true;
			}
		}, 250);
	};

	form.addEventListener('change', (event) => {
		const target = event.target;
		// "Semua" pengirim dan pengirim tertentu saling meniadakan.
		if (target === allSenders && target.checked) {
			form.querySelectorAll('input[name="senders[]"]').forEach((input) => { input.checked = false; });
		} else if (target.name === 'senders[]') {
			allSenders.checked = !form.querySelector('input[name="senders[]"]:checked');
		}
		refreshCount();
	});

	const close = () => sheet.classList.add('hidden');
	page.querySelector('[data-open-export]')?.addEventListener('click', () => {
		sheet.classList.remove('hidden');
		refreshCount();
		form.querySelector('input')?.focus();
	});
	sheet.addEventListener('click', (event) => {
		if (event.target === sheet || event.target.closest('[data-close-export]')) {
			close();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !sheet.classList.contains('hidden')) {
			close();
		}
	});

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		submit.disabled = true;
		try {
			const body = Object.fromEntries(params());
			body.senders = params().getAll('senders[]');
			delete body['senders[]'];
			const result = await chatApiRequest(page.dataset.exportUrl, { method: 'POST', body });
			exportsList.prepend(result.export);
			close();
			showActionToast('File sedang dibuat');
		} catch (error) {
			showActionToast(error?.message && error.message !== 'request_failed' ? error.message : 'Gagal membuat file');
		} finally {
			submit.disabled = false;
		}
	});
};

const initBackups = (page, backupsList) => {
	page.querySelector('[data-create-backup]')?.addEventListener('click', async (event) => {
		const button = event.currentTarget;
		button.disabled = true;
		try {
			backupsList.prepend((await chatApiRequest(page.dataset.backupUrl, { method: 'POST' })).backup);
		} catch {
			showActionToast('Gagal membuat cadangan');
		} finally {
			button.disabled = false;
		}
	});

	page.addEventListener('click', async (event) => {
		const button = event.target.closest('[data-restore]');
		if (!button) {
			return;
		}

		let restorable = 0;
		try {
			restorable = (await chatApiRequest(button.dataset.restore)).restorable;
		} catch (error) {
			showActionToast(error?.message && error.message !== 'request_failed' ? error.message : 'Cadangan tidak bisa dibaca');
			return;
		}
		if (restorable === 0) {
			await openDialog({ title: 'Tidak ada yang perlu dipulihkan', message: `Tidak ada pesan yang terhapus sejak cadangan ${button.dataset.restoreDate}.`, choices: [], cancelLabel: 'Tutup' });
			return;
		}

		const choice = await openDialog({
			title: `Pulihkan cadangan ${button.dataset.restoreDate}?`,
			message: `${number.format(restorable)} pesan yang terhapus sejak saat itu akan dikembalikan ke tempatnya. Pesan yang dikirim sesudahnya tetap ada.`,
			choices: [{ label: `Kembalikan ${number.format(restorable)} pesan`, value: 'restore', tone: 'primary' }],
		});
		if (!choice) {
			return;
		}
		try {
			const result = await chatApiRequest(button.dataset.restoreUrl, { method: 'POST' });
			showActionToast(`${number.format(result.restored)} pesan dikembalikan`);
		} catch {
			showActionToast('Gagal memulihkan cadangan');
		}
	});
};

export const initHistoryPage = () => {
	const page = document.querySelector('[data-history-page]');
	if (!page) {
		return;
	}

	let data = { exports: [], backups: [] };
	try {
		data = JSON.parse(page.querySelector('[data-history-data]')?.textContent || '{}');
	} catch {
		// Data rusak: daftar kosong.
	}

	const exportContainer = page.querySelector('[data-export-list]');
	const exportsList = exportContainer ? liveList(exportContainer, page.dataset.exportsUrl, 'exports', exportRow, 'Belum ada unduhan.') : null;
	exportsList?.set(data.exports || []);
	const backupContainer = page.querySelector('[data-backup-list]');
	const backupsList = backupContainer ? liveList(backupContainer, page.dataset.backupsUrl, 'backups', backupRow, 'Belum ada cadangan.') : null;
	backupsList?.set(data.backups || []);

	if (exportsList) {
		initExportSheet(page, exportsList);
	}
	if (backupsList) {
		initBackups(page, backupsList);
	}
};
