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
	const editForm = root.querySelector('[data-info-edit-form]');

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
	const resetEdit = () => {
		if (!editForm) return;
		editForm.reset();
		const preview = editForm.querySelector('[data-info-photo-preview]');
		if (preview) preview.hidden = true;
		const current = editForm.querySelector('[data-info-photo-current]');
		if (current && current.dataset.original !== undefined) current.innerHTML = current.dataset.original;
		const remove = editForm.querySelector('[data-info-photo-remove]');
		if (remove) {
			remove.disabled = false;
			remove.textContent = 'Hapus foto';
		}
		editForm.querySelector('[data-info-photo-remove-input]').value = '0';
	};
	const setEditing = (on) => {
		root.toggleAttribute('data-info-editing', on);
		closeMenus(root);
		if (on) {
			scroller()?.scrollTo?.({ top: 0 });
			editForm?.querySelector('[data-info-edit-name]')?.focus();
		} else {
			resetEdit();
			root.querySelector('[data-info-edit-open]')?.focus();
		}
	};
	/** Kembali ke keadaan awal saat jendela dibuka dari header. */
	const reset = () => {
		if (root.hasAttribute('data-info-editing')) setEditing(false);
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
		if (target.closest('[data-info-edit-open]')) {
			setEditing(true);
			return;
		}
		if (target.closest('[data-info-edit-cancel]')) {
			setEditing(false);
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
		const removePhoto = target.closest('[data-info-photo-remove]');
		if (removePhoto && editForm) {
			editForm.querySelector('[data-info-photo-remove-input]').value = '1';
			editForm.querySelector('[data-info-photo-input]').value = '';
			editForm.querySelector('[data-info-photo-preview]').hidden = true;
			const current = editForm.querySelector('[data-info-photo-current]');
			current.dataset.original ??= current.innerHTML;
			current.textContent = (editForm.querySelector('[data-info-edit-name]')?.value || '?').trim().charAt(0).toUpperCase();
			removePhoto.textContent = 'Foto akan dihapus setelah disimpan';
			removePhoto.disabled = true;
			return;
		}
		if (!target.closest('[data-info-menu], [data-info-more-menu]')) closeMenus(root);
	});

	root.addEventListener('change', (event) => {
		const input = event.target;
		if (!(input instanceof HTMLInputElement) || !input.matches('[data-info-photo-input]') || !editForm) return;
		const file = input.files?.[0];
		const preview = editForm.querySelector('[data-info-photo-preview]');
		if (!file || !(preview instanceof HTMLImageElement)) return;
		preview.src = URL.createObjectURL(file);
		preview.hidden = false;
		editForm.querySelector('[data-info-photo-remove-input]').value = '0';
	});

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
	// Esc berlapis: menu dulu, lalu mode edit / tampilan lengkap, baru jendela Info grup.
	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape' || event.defaultPrevented || !root.isConnected || !root.offsetParent) return;
		if (openMenuIn(root)) {
			closeMenus(root);
			event.preventDefault();
		} else if (root.hasAttribute('data-info-editing')) {
			setEditing(false);
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
