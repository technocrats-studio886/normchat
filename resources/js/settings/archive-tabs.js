// Tab arsip (media / tautan / file) di pengaturan grup.

export const initArchiveTabs = () => {
	const root = document.querySelector('[data-archive-tabs]');
	if (!root) {
		return;
	}

	const tabs = root.querySelectorAll('[data-archive-tab]');
	const panels = document.querySelectorAll('[data-archive-panel]');
	tabs.forEach((tab) => {
		tab.addEventListener('click', () => {
			const target = tab.getAttribute('data-archive-tab');
			tabs.forEach((other) => {
				const active = other === tab;
				other.setAttribute('aria-selected', active ? 'true' : 'false');
				other.classList.toggle('text-slate-700', active);
				other.classList.toggle('border-primary', active);
				other.classList.toggle('bg-primary-soft', active);
				other.classList.toggle('text-slate-500', !active);
				other.classList.toggle('border-transparent', !active);
			});
			panels.forEach((panel) => {
				panel.classList.toggle('hidden', panel.getAttribute('data-archive-panel') !== target);
			});
		});
	});
};
