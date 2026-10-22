// Kerangka halaman chat: tombol kembali perangkat menuju daftar grup, dan tinggi shell
// mengikuti visual viewport saat keyboard terbuka.

import { chatShell } from './context.js';

const routeBackButtonToGroups = () => {
	const groupsUrl = chatShell.getAttribute('data-groups-url') || '/groups';
	try {
		history.pushState({ nc: 'chat' }, '', location.href);
	} catch {
		// Riwayat tidak bisa diubah (mis. sandbox); tombol kembali memakai perilaku bawaan.
	}
	window.addEventListener('popstate', () => {
		window.location.replace(groupsUrl);
	});
};

const fitShellToVisualViewport = () => {
	if (!window.visualViewport) {
		return;
	}

	document.documentElement.style.overscrollBehavior = 'none';
	document.body.style.overscrollBehavior = 'none';
	document.body.style.overflow = 'hidden';

	let rafId = null;
	const applyHeight = () => {
		const height = Math.max(320, Math.round(window.visualViewport?.height || window.innerHeight));
		chatShell.style.height = `${height}px`;
		chatShell.style.top = '0px';
		chatShell.style.bottom = 'auto';
		// Composer tetap menempel di bawah agar kompensasi keyboard tidak terjadi dua kali.
		document.documentElement.style.setProperty('--nc-keyboard-offset', '0px');
	};
	const schedule = () => {
		if (rafId) {
			cancelAnimationFrame(rafId);
		}
		rafId = requestAnimationFrame(applyHeight);
	};

	applyHeight();
	window.visualViewport.addEventListener('resize', schedule);
	window.addEventListener('orientationchange', schedule);
};

export const initChatShell = () => {
	if (!(chatShell instanceof HTMLElement)) {
		return;
	}

	routeBackButtonToGroups();
	fitShellToVisualViewport();
};
