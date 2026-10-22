// Sidebar desktop: buka/tutup lewat tombol, atau geser tepi kanannya (ke kiri = tutup, ke kanan = buka).
// Status disimpan per browser; kelas awalnya dipasang skrip kecil di <head> agar tidak berkedip.

const COLLAPSED_CLASS = 'nc-sidebar-collapsed';
const DRAGGING_CLASS = 'nc-sidebar-dragging';
const STORAGE_KEY = 'nc-sidebar';

const root = document.documentElement;

const cssPx = (name, fallback) => {
	const value = parseFloat(getComputedStyle(root).getPropertyValue(name));
	return Number.isFinite(value) ? value : fallback;
};

const setCollapsed = (collapsed) => {
	root.classList.toggle(COLLAPSED_CLASS, collapsed);
	try {
		window.localStorage.setItem(STORAGE_KEY, collapsed ? 'collapsed' : 'open');
	} catch {
		// Penyimpanan diblokir; status tetap berlaku sampai halaman berganti.
	}
	document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
		button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
		button.setAttribute('aria-label', collapsed ? 'Buka panel' : 'Tutup panel');
	});
};

const initResizer = (resizer) => {
	resizer.addEventListener('pointerdown', (event) => {
		if (event.button !== 0) {
			return;
		}
		event.preventDefault();

		const openWidth = cssPx('--nc-sidebar-open-w', 360);
		const railWidth = cssPx('--nc-sidebar-rail-w', 76);
		const startX = event.clientX;
		const startWidth = root.classList.contains(COLLAPSED_CLASS) ? railWidth : openWidth;
		let width = startWidth;

		resizer.setPointerCapture(event.pointerId);
		root.classList.add(DRAGGING_CLASS);

		const onMove = (moveEvent) => {
			width = Math.min(openWidth, Math.max(railWidth, startWidth + moveEvent.clientX - startX));
			root.style.setProperty('--nc-sidebar-w', `${width}px`);
			// Label disembunyikan begitu lebarnya sudah lebih dekat ke mode ramping.
			root.classList.toggle(COLLAPSED_CLASS, width < (openWidth + railWidth) / 2);
		};

		const onUp = () => {
			resizer.removeEventListener('pointermove', onMove);
			resizer.removeEventListener('pointerup', onUp);
			resizer.removeEventListener('pointercancel', onUp);
			root.classList.remove(DRAGGING_CLASS);
			root.style.removeProperty('--nc-sidebar-w');
			setCollapsed(width < (openWidth + railWidth) / 2);
		};

		resizer.addEventListener('pointermove', onMove);
		resizer.addEventListener('pointerup', onUp);
		resizer.addEventListener('pointercancel', onUp);
	});

	resizer.addEventListener('dblclick', () => setCollapsed(!root.classList.contains(COLLAPSED_CLASS)));
};

export const initSidebar = () => {
	const sidebar = document.querySelector('[data-sidebar]');
	if (!sidebar) {
		return;
	}

	sidebar.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
		button.addEventListener('click', () => setCollapsed(!root.classList.contains(COLLAPSED_CLASS)));
	});

	const resizer = sidebar.querySelector('[data-sidebar-resizer]');
	if (resizer) {
		initResizer(resizer);
	}

	setCollapsed(root.classList.contains(COLLAPSED_CLASS));
};
