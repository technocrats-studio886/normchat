// Info grup (docs/rancangan-info-grup.md revisi 2, resources/views/groups/info/panel.blade.php).
// - Tab Anggota | Media | Pengaturan, tampilan lengkap "Media, tautan, dan dok", edit profil di bagian atas.
// - Ruang chat desktop: satu isi panel dipindah ke jendela melayang (ketuk header) atau panel kanan
//   (ikon buka/tutup di header, statusnya diingat per perangkat seperti sidebar kiri).

import { initMediaViewer } from './media-viewer.js';

const SIDE_CLASS = 'nc-info-open';
const SIDE_KEY = 'nc-info-side';
const desktop = window.matchMedia('(min-width: 1024px)');

const openMenuIn = (root) => root.querySelector('[data-info-menu]:not([hidden]), [data-info-more-menu]:not([hidden])');

const closeMenus = (root) => {
	root.querySelectorAll('[data-info-menu], [data-info-more-menu]').forEach((menu) => { menu.hidden = true; });
	root.querySelectorAll('[data-info-menu-toggle], [data-info-more]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
};

const initPanel = (root, { onClose, onSearch }) => {
	const scroller = () => root.closest('[data-info-scroll]') || root.parentElement;
	const setTab = (tab) => {
		root.dataset.infoTab = tab;
		root.querySelectorAll('[data-info-tab-button]').forEach((button) => {
			button.setAttribute('aria-selected', button.getAttribute('data-info-tab-button') === tab ? 'true' : 'false');
		});
		closeMenus(root);
	};
	const setView = (view) => {
		root.dataset.infoView = view;
		closeMenus(root);
		scroller()?.scrollTo?.({ top: 0 });
	};
	// Edit per bagian profil atas: hanya bagian yang diketuk (nama atau deskripsi) yang jadi kolom isian.
	const editingField = () => root.querySelector('[data-info-field][data-editing]');
	const closeField = (field, { focusView = true } = {}) => {
		if (!field) return;
		const input = field.querySelector('[data-info-field-input]');
		if (input) input.value = input.dataset.original ?? '';
		field.removeAttribute('data-editing');
		if (focusView) field.querySelector('[data-info-field-edit]')?.focus();
	};
	const openField = (field) => {
		const current = editingField();
		if (current && current !== field) closeField(current, { focusView: false });
		closeMenus(root);
		field.setAttribute('data-editing', '');
		const input = field.querySelector('[data-info-field-input]');
		input?.focus();
		if (input instanceof HTMLInputElement) input.select();
	};
	/** Kembali ke keadaan awal saat jendela dibuka dari header. */
	const reset = () => {
		closeField(editingField(), { focusView: false });
		setView('main');
		setTab('members');
	};

	root.addEventListener('click', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		if (!target) return;

		const go = target.closest('[data-info-go]');
		if (go) {
			event.preventDefault();
			setView(go.getAttribute('data-info-go') || 'main');
			return;
		}
		const tab = target.closest('[data-info-tab-button]');
		if (tab) {
			setTab(tab.getAttribute('data-info-tab-button') || 'members');
			return;
		}
		const fieldEdit = target.closest('[data-info-field-edit]');
		if (fieldEdit) {
			openField(fieldEdit.closest('[data-info-field]'));
			return;
		}
		if (target.closest('[data-info-field-cancel]')) {
			closeField(target.closest('[data-info-field]'));
			return;
		}
		if (target.closest('[data-info-close]')) {
			onClose?.();
			return;
		}
		const search = target.closest('[data-info-search]');
		if (search && onSearch) {
			event.preventDefault();
			onSearch();
			return;
		}
		const more = target.closest('[data-info-more]');
		if (more) {
			event.preventDefault();
			const menu = root.querySelector('[data-info-more-menu]');
			if (!menu) return;
			const willOpen = menu.hidden;
			closeMenus(root);
			if (willOpen) {
				const rootBox = root.getBoundingClientRect();
				const box = more.getBoundingClientRect();
				menu.style.top = `${box.bottom - rootBox.top + 6}px`;
				menu.style.right = `${Math.max(8, rootBox.right - box.right)}px`;
				menu.hidden = false;
				more.setAttribute('aria-expanded', 'true');
				menu.querySelector('a,button')?.focus();
			}
			return;
		}
		const toggle = target.closest('[data-info-menu-toggle]');
		if (toggle) {
			const menu = toggle.parentElement?.querySelector('[data-info-menu]');
			if (!menu) return;
			const willOpen = menu.hidden;
			closeMenus(root);
			menu.hidden = !willOpen;
			toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			return;
		}
		if (target.closest('[data-info-clear-history]')) {
			closeMenus(root);
			// Memakai alur "Bersihkan chat" yang sudah ada di menu titik tiga ruang chat.
			document.querySelector('[data-settings-menu] [data-menu-clear-history]')?.click();
			return;
		}
		const expand = target.closest('[data-info-expand]');
		if (expand) {
			const body = expand.closest('[data-info-expand-row]')?.querySelector('[data-info-expand-body]');
			if (body instanceof HTMLElement) {
				body.hidden = !body.hidden;
				expand.setAttribute('aria-expanded', body.hidden ? 'false' : 'true');
				if (!body.hidden) body.querySelector('input:not([type="hidden"])')?.focus();
			}
			return;
		}
		if (target.closest('[data-info-photo-pick]')) {
			// Label membuka pemilih berkas; menu ditutup supaya tidak tertinggal di atas pratinjau.
			window.setTimeout(() => closeMenus(root), 0);
			return;
		}
		if (!target.closest('[data-info-menu], [data-info-more-menu]')) closeMenus(root);
	});

	// Foto: setelah gambar dipilih, langsung diunggah (seperti aplikasi chat umumnya).
	root.addEventListener('change', (event) => {
		const input = event.target;
		if (!(input instanceof HTMLInputElement) || !input.matches('[data-info-photo-input]')) return;
		const file = input.files?.[0];
		if (!file) return;
		const button = root.querySelector('[data-info-photo-current]');
		const preview = document.createElement('img');
		preview.className = 'nc-group-photo';
		preview.alt = '';
		preview.src = URL.createObjectURL(file);
		button?.querySelector('img.nc-group-photo')?.remove();
		button?.prepend(preview);
		root.querySelector('[data-info-photo-busy]')?.removeAttribute('hidden');
		button?.setAttribute('aria-busy', 'true');
		input.form?.submit();
	});

	// Enter di kolom nama menyimpan (bawaan form); Esc di kolom mana pun membatalkan bagian itu saja.
	root.querySelector('[data-info-member-filter]')?.addEventListener('input', (event) => {
		const query = event.target.value.trim().toLowerCase();
		let shown = 0;
		root.querySelectorAll('[data-info-member]').forEach((row) => {
			const match = !query || row.getAttribute('data-info-member').includes(query);
			row.hidden = !match;
			if (match) shown++;
		});
		root.querySelector('[data-info-member-empty]')?.classList.toggle('hidden', shown > 0);
	});

	document.addEventListener('click', (event) => {
		if (!root.contains(event.target)) closeMenus(root);
	});
	// Esc berlapis: menu dulu, lalu bagian yang sedang diedit / tampilan lengkap, baru jendela Info grup.
	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape' || event.defaultPrevented || !root.isConnected || !root.offsetParent) return;
		if (openMenuIn(root)) {
			closeMenus(root);
			event.preventDefault();
		} else if (editingField()) {
			closeField(editingField());
			event.preventDefault();
		} else if (root.dataset.infoView === 'archive') {
			setView('main');
			event.preventDefault();
		}
	});

	initMediaViewer(root);

	return { setView, setTab, reset };
};

/** Ruang chat desktop: jendela melayang + panel kanan. */
const initEmbedded = (root) => {
	const modal = document.querySelector('[data-info-modal]');
	const modalSlot = modal?.querySelector('[data-info-slot="modal"]');
	const side = document.querySelector('[data-info-side]');
	const sideSlot = side?.querySelector('[data-info-slot="side"]');
	const sideToggle = document.querySelector('[data-info-side-toggle]');
	if (!modal || !modalSlot || !side || !sideSlot) return;

	const html = document.documentElement;
	const closeButton = root.querySelector('[data-info-close]');
	const reopen = modal.hasAttribute('data-info-reopen');
	let previousFocus = null;

	const sideOpen = () => html.classList.contains(SIDE_CLASS) && desktop.matches;
	const remember = (open) => {
		try {
			window.localStorage.setItem(SIDE_KEY, open ? 'open' : 'closed');
		} catch {
			// Penyimpanan diblokir: status berlaku sampai halaman berganti.
		}
	};
	const syncToggle = () => {
		sideToggle?.setAttribute('aria-expanded', sideOpen() ? 'true' : 'false');
		sideToggle?.setAttribute('aria-label', sideOpen() ? 'Tutup panel info grup' : 'Buka panel info grup');
	};

	const closeModal = () => {
		if (modal.hidden) return;
		modal.hidden = true;
		document.body.style.overflow = '';
		if (previousFocus instanceof HTMLElement) previousFocus.focus();
	};
	const openModal = () => {
		previousFocus = document.activeElement;
		modalSlot.appendChild(root);
		if (closeButton) closeButton.hidden = false;
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
		root.querySelector('[data-info-close]')?.focus();
	};
	const setSide = (open, { save = true } = {}) => {
		html.classList.toggle(SIDE_CLASS, open);
		if (open) {
			closeModal();
			sideSlot.appendChild(root);
			if (closeButton) closeButton.hidden = false;
		}
		if (save) remember(open);
		syncToggle();
	};

	const panel = initPanel(root, {
		onClose: () => (root.parentElement === sideSlot ? setSide(false) : closeModal()),
		onSearch: () => {
			closeModal();
			openSearch();
		},
	});

	document.addEventListener('click', (event) => {
		const opener = event.target instanceof Element ? event.target.closest('[data-open-group-info]') : null;
		if (!opener || !desktop.matches) return;
		event.preventDefault();
		panel.reset();
		if (sideOpen()) {
			side.classList.remove('is-flash');
			void side.offsetWidth;
			side.classList.add('is-flash');
			return;
		}
		openModal();
	});
	sideToggle?.addEventListener('click', () => setSide(!sideOpen()));
	modal.addEventListener('click', (event) => {
		if (event.target === modal) closeModal();
	});
	document.addEventListener('keydown', (event) => {
		// Lapisan di atasnya (menu, mode edit, lembar Tautan grup, penampil gambar, dialog) menandai Esc dengan preventDefault.
		if (event.key === 'Escape' && !event.defaultPrevented && !modal.hidden && !document.querySelector('.nc-dialog-overlay')) closeModal();
	});
	desktop.addEventListener('change', () => {
		if (!desktop.matches) closeModal();
		syncToggle();
	});

	if (sideOpen()) {
		setSide(true, { save: false });
	} else if (reopen && desktop.matches) {
		openModal();
	}
	syncToggle();
};

const openSearch = () => {
	const opener = document.querySelector('[data-settings-menu] [data-open-search]');
	if (opener instanceof HTMLElement) opener.click();
};

export const initGroupInfo = () => {
	const root = document.querySelector('[data-group-info]');
	// Tombol Cari di header chat desktop dan ?search=1 dari tombol Cari di halaman Info grup HP.
	document.querySelector('[data-header-search]')?.addEventListener('click', openSearch);
	if (new URLSearchParams(window.location.search).has('search') && document.querySelector('[data-chat-feed]')) {
		const url = new URL(window.location.href);
		url.searchParams.delete('search');
		window.history.replaceState(window.history.state, '', url);
		window.setTimeout(openSearch, 0);
	}
	if (!root) return;

	if (root.classList.contains('nc-info-embed')) {
		initEmbedded(root);
	} else {
		initPanel(root, {});
	}
};
