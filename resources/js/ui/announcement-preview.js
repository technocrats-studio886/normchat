// Pratinjau langsung di form pengumuman superadmin (superadmin/announcements/form).

export const initAnnouncementPreview = () => {
	const form = document.querySelector('[data-announcement-form]');
	const card = document.querySelector('[data-announcement-preview] [data-announcement-card]');
	if (!form || !card) {
		return;
	}

	const field = (name) => form.querySelector(`[data-preview="${name}"]`);
	const target = (name) => card.querySelector(`[data-preview-target="${name}"]`);

	const update = () => {
		target('title').textContent = field('title').value.trim() || 'Judul pengumuman';
		// Teks apa adanya; baris baru ditampilkan lewat white-space: pre-line.
		target('body').textContent = field('body').value.trim() || 'Isi pengumuman.';
		const cta = field('cta').value.trim();
		target('cta').textContent = cta;
		target('cta').classList.toggle('hidden', cta === '');
		card.classList.toggle('nc-announce--popup', field('display').value === 'popup');
		card.classList.toggle('nc-announce--banner', field('display').value === 'banner');
		card.classList.toggle('is-important', field('tone').value === 'penting');
	};

	form.addEventListener('input', update);
	form.addEventListener('change', update);
	update();
};
