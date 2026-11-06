// Tur pengenalan di ruang chat (docs/rancangan-tur-dan-blok-kode.md), memakai driver.js (MIT).
// Lima langkah: cara chat, tombol +, pilihan model, pengaturan grup, membuat grup baru.
// Tampil bila halaman chat membawa data-tour-due: pengguna baru, pengguna lama yang belum pernah melihatnya,
// atau yang kembali setelah 3 minggu. Ditutup di langkah mana pun = sudah dilihat (POST /tour/seen).

import { csrfToken } from '../chat/context.js';

const desktop = () => window.matchMedia('(min-width: 1024px)').matches;
const visible = (element) => element instanceof HTMLElement && element.offsetParent !== null && element.getBoundingClientRect().width > 0;
const first = (...selectors) => selectors.map((selector) => document.querySelector(selector)).find(visible) || null;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const list = (items) => `<ul class="nc-tour-list">${items.map(([name, use]) => `<li><b>${escapeHtml(name)}</b> ${escapeHtml(use)}</li>`).join('')}</ul>`;

/** Pilihan model dari menu model yang aktif (label + keterangan dari superadmin), supaya penjelasannya tidak basi. */
const modelItems = () => {
	const options = [...document.querySelectorAll('[data-model-menu] [data-ai-model-option]')].map((option) => [
		option.dataset.aiModelName || option.querySelector('.font-semibold')?.textContent?.trim() || '',
		option.querySelector('[data-ai-model-description]')?.textContent?.trim() || '',
	]).filter(([name]) => name);
	return options.length ? options : [['Cepat', 'untuk pertanyaan ringan.'], ['Smart', 'untuk tugas sehari-hari.'], ['Advanced', 'untuk soal yang berat.']];
};

/**
 * Langkah tur (urutan dan isi sesuai permintaan user 6 Nov 2026): cara chat, isi tombol +, pilihan model,
 * melihat pengaturan grup, dan membuat grup baru. Langkah yang elemennya tidak tampil di layar dilewati.
 */
const steps = () => [
	{
		element: first('[data-mention-input]'),
		popover: {
			title: 'Cara chat',
			description: 'Ketik pesan di sini lalu kirim; semua anggota grup langsung membacanya. Untuk bertanya ke Normi, tulis <b>@normi</b> di pesanmu, dan Normi menjawab di grup untuk semua anggota.',
		},
	},
	{
		element: first('[data-open-attach-menu]'),
		popover: {
			title: 'Isi tombol +',
			description: list([
				['Unggah file', 'mengirim gambar, dokumen, atau berkas lain ke grup.'],
				['Buat polling', 'mengajak anggota memilih, hasilnya terlihat bersama.'],
				['Guided Learning', 'Normi membimbing belajar langkah demi langkah dengan contoh dan latihan.'],
				['Buat gambar', 'Normi membuat gambar dari deskripsimu, atau mengubah gambar yang kamu balas.'],
				['Canvas', 'Normi membuat dokumen atau halaman web utuh yang bisa dilihat dan direvisi.'],
				['Deep Research', 'Normi mencari di web dan menulis laporan lengkap dengan sumbernya.'],
			]),
		},
	},
	{
		element: first('[data-open-model-menu]'),
		popover: {
			title: 'Pilihan model',
			description: `Pilih kemampuan Normi untuk pertanyaanmu.${list(modelItems().map(([name, use]) => [name, use]))}Model yang lebih kuat memakai Normkredit lebih banyak.`,
		},
	},
	{
		element: first('[data-chat-shell] [data-open-group-info]'),
		popover: {
			title: 'Pengaturan grup',
			description: 'Ketuk bagian atas ini untuk membuka info grup: saldo Normkredit, anggota, media, tautan, dan pengaturan.',
		},
	},
	{
		element: desktop() ? first('[data-sidebar] a[href$="/groups/create"]') : first('.nc-chat-header-card a[aria-label="Kembali"]'),
		popover: desktop()
			? { title: 'Buat grup baru', description: 'Ketuk tombol + ini untuk membuat grup baru, lalu ajak teman dengan kode atau tautan grupnya.' }
			: { title: 'Buat grup baru', description: 'Kembali ke daftar grup lewat tombol ini, lalu ketuk tombol <b>+</b> di kanan bawah untuk membuat grup baru.' },
	},
].filter((step) => step.element);

const markSeen = (url) => {
	try {
		fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, credentials: 'same-origin', keepalive: true });
	} catch {
		// Gagal tersimpan: tur muncul lagi di kunjungan berikutnya, tidak lebih buruk dari itu.
	}
};

export const initTour = () => {
	const shell = document.querySelector('[data-chat-shell][data-tour-due]');
	if (!shell) return;
	const seenUrl = shell.dataset.tourSeenUrl || '';

	// Tunggu feed dan gulir awal selesai supaya posisi elemen sudah tetap.
	window.setTimeout(async () => {
		const tourSteps = steps();
		if (tourSteps.length === 0) return;
		const [{ driver }] = await Promise.all([import('driver.js'), import('driver.js/dist/driver.css')]);
		let saved = false;
		const tour = driver({
			steps: tourSteps,
			showProgress: true,
			progressText: '{{current}} dari {{total}}',
			nextBtnText: 'Lanjut',
			prevBtnText: 'Kembali',
			doneBtnText: 'Selesai',
			popoverClass: 'nc-tour',
			overlayOpacity: 0.55,
			stagePadding: 6,
			stageRadius: 14,
			smoothScroll: true,
			allowClose: true,
			onDestroyed: () => {
				if (!saved && seenUrl) {
					saved = true;
					markSeen(seenUrl);
				}
				delete shell.dataset.tourDue;
			},
		});
		window.ncTour = tour;
		tour.drive();
	}, 900);
};
