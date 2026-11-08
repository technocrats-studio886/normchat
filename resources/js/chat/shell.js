// Kerangka halaman chat: tombol kembali perangkat menuju daftar grup, dan tinggi shell
// mengikuti visual viewport saat keyboard terbuka.

import { chatShell } from './context.js';

/** Chat dibuka dari daftar grup: tombol kembali bawaan sudah menuju ke sana (dan instan dari Back-Forward Cache). */
const cameFromGroupList = (groupsUrl) => {
	try {
		const referrer = new URL(document.referrer);
		const groups = new URL(groupsUrl, window.location.href);
		return referrer.origin === window.location.origin && referrer.pathname === groups.pathname;
	} catch {
		return false;
	}
};

const routeBackButtonToGroups = () => {
	const groupsUrl = chatShell.getAttribute('data-groups-url') || '/groups';
	if (cameFromGroupList(groupsUrl)) {
		return;
	}
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

	const root = document.documentElement;
	root.style.overscrollBehavior = 'none';
	root.style.overflow = 'hidden';
	document.body.style.overscrollBehavior = 'none';
	document.body.style.overflow = 'hidden';
	root.classList.add('nc-chat-locked');

	let rafId = null;
	const applyHeight = () => {
		const viewport = window.visualViewport;
		const height = Math.max(320, Math.round(viewport?.height || window.innerHeight));
		// Safari iOS tidak mengecilkan layout viewport saat keyboard muncul; ia menggeser visual viewport
		// (offsetTop > 0) sehingga header terdorong ke atas dan kolom ketik tertutup keyboard
		// (laporan user 9 Nov 2026). Shell diikutkan ke posisi visual viewport, bukan ke atas layout.
		const top = Math.max(0, Math.round(viewport?.offsetTop || 0));
		chatShell.style.height = `${height}px`;
		chatShell.style.top = `${top}px`;
		chatShell.style.bottom = 'auto';
		// Composer tetap menempel di bawah agar kompensasi keyboard tidak terjadi dua kali.
		root.style.setProperty('--nc-keyboard-offset', '0px');
		root.style.setProperty('--nc-shell-h', `${height}px`);
		// Keyboard terbuka: jarak safe-area bawah (home indicator) tidak perlu lagi di atas keyboard.
		root.classList.toggle('nc-keyboard-open', window.innerHeight - height > 120);
	};
	const schedule = () => {
		if (rafId) {
			cancelAnimationFrame(rafId);
		}
		rafId = requestAnimationFrame(applyHeight);
	};

	applyHeight();
	window.visualViewport.addEventListener('resize', schedule);
	// iOS menggeser visual viewport (event scroll, bukan resize) saat keyboard terbuka/kolom ketik difokus.
	window.visualViewport.addEventListener('scroll', schedule);
	window.addEventListener('orientationchange', schedule);
	window.addEventListener('pageshow', schedule);

	// Halaman itu sendiri tidak boleh ikut tergulir (iOS kadang menggulir dokumen agar input terlihat):
	// kembalikan ke atas begitu kolom ketik difokus atau keyboard ditutup.
	const resetWindowScroll = () => {
		if (window.scrollY !== 0 || window.scrollX !== 0) {
			window.scrollTo(0, 0);
		}
		schedule();
	};
	chatShell.addEventListener('focusin', () => window.setTimeout(resetWindowScroll, 50));
	chatShell.addEventListener('focusout', () => window.setTimeout(resetWindowScroll, 50));
};

export const initChatShell = () => {
	if (!(chatShell instanceof HTMLElement)) {
		return;
	}

	routeBackButtonToGroups();
	fitShellToVisualViewport();
};
