// Laci menu superadmin di HP (layouts/admin): dibuka tombol ☰, tertutup lewat tombol ×, Esc, atau ketuk di luar.

export const initAdminMenu = () => {
	const drawer = document.querySelector('[data-admin-menu]');
	if (!(drawer instanceof HTMLDialogElement)) {
		return;
	}

	document.querySelector('[data-admin-menu-open]')?.addEventListener('click', () => drawer.showModal());
	drawer.querySelector('[data-admin-menu-close]')?.addEventListener('click', () => drawer.close());
	drawer.addEventListener('click', (event) => {
		if (event.target === drawer) {
			drawer.close();
		}
	});
};
