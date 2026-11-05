// Lembar "Tautan grup" (resources/views/groups/info/share-sheet.blade.php).
// Bagikan tautan memakai navigator.share (pilihan aplikasi bawaan HP); bila tidak didukung, tautan disalin.

let qrModule = null;
const loadQr = async () => {
	qrModule ??= import('qrcode').then((module) => module.default ?? module);
	return qrModule;
};

const copyText = async (text) => {
	try {
		await navigator.clipboard.writeText(text);
		return true;
	} catch {
		// Clipboard API tidak tersedia (bukan HTTPS) atau ditolak: salin lewat textarea sementara.
		const area = document.createElement('textarea');
		area.value = text;
		area.setAttribute('readonly', '');
		area.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
		document.body.appendChild(area);
		area.select();
		let copied = false;
		try {
			copied = document.execCommand('copy');
		} catch {
			copied = false;
		}
		area.remove();
		return copied;
	}
};

export const initShareSheet = () => {
	const overlay = document.querySelector('[data-share-sheet]');
	if (!(overlay instanceof HTMLElement)) {
		return;
	}
	const url = overlay.dataset.shareUrl || '';
	const code = overlay.dataset.shareCode || '';
	const name = overlay.dataset.shareName || 'Normi Chat';
	const options = overlay.querySelector('[data-share-options]');
	const qrBox = overlay.querySelector('[data-share-qr]');
	const canvas = overlay.querySelector('[data-share-qr-canvas]');
	const status = overlay.querySelector('[data-share-status]');
	let previousFocus = null;
	let statusTimer = 0;

	const say = (text) => {
		if (!status) return;
		status.textContent = text;
		window.clearTimeout(statusTimer);
		statusTimer = window.setTimeout(() => { status.textContent = ''; }, 2200);
	};
	const showQr = (visible) => {
		if (options) options.hidden = visible;
		if (qrBox) qrBox.hidden = !visible;
	};
	const open = () => {
		previousFocus = document.activeElement;
		showQr(false);
		if (status) status.textContent = '';
		overlay.hidden = false;
		overlay.querySelector('[data-share-action]')?.focus();
	};
	const close = () => {
		overlay.hidden = true;
		if (previousFocus instanceof HTMLElement) previousFocus.focus();
	};

	document.addEventListener('click', (event) => {
		const trigger = event.target instanceof Element ? event.target.closest('[data-open-share-sheet]') : null;
		if (trigger) {
			event.preventDefault();
			open();
			// "Undang via tautan atau kode QR": langsung ke Kode QR.
			if (trigger.dataset.shareStart === 'qr') overlay.querySelector('[data-share-action="qr"]')?.click();
		}
	});
	overlay.addEventListener('click', async (event) => {
		if (event.target === overlay || (event.target instanceof Element && event.target.closest('[data-share-close]'))) {
			close();
			return;
		}
		const action = event.target instanceof Element ? event.target.closest('[data-share-action]')?.getAttribute('data-share-action') : null;
		if (action === 'copy-link') {
			say(await copyText(url) ? 'Tautan disalin.' : 'Tautan tidak bisa disalin di browser ini.');
		} else if (action === 'copy-code') {
			say(await copyText(code) ? 'Kode grup disalin.' : 'Kode tidak bisa disalin di browser ini.');
		} else if (action === 'share') {
			const payload = { title: `Gabung ${name}`, text: `Gabung ke grup ${name} di Normi Chat`, url };
			if (navigator.share && (!navigator.canShare || navigator.canShare(payload))) {
				try {
					await navigator.share(payload);
					close();
				} catch (error) {
					if (error?.name !== 'AbortError') say(await copyText(url) ? 'Tautan disalin.' : 'Tautan tidak bisa dibagikan.');
				}
				return;
			}
			say(await copyText(url) ? 'Browser ini tidak punya menu bagikan, jadi tautannya disalin.' : 'Tautan tidak bisa dibagikan.');
		} else if (action === 'qr' && canvas instanceof HTMLCanvasElement) {
			try {
				const QRCode = await loadQr();
				await QRCode.toCanvas(canvas, url, { width: 240, margin: 2, color: { dark: '#111827', light: '#ffffff' } });
				showQr(true);
				overlay.querySelector('[data-share-qr-save]')?.focus();
			} catch {
				say('Kode QR gagal dibuat. Coba lagi.');
			}
		}
	});
	overlay.querySelector('[data-share-qr-back]')?.addEventListener('click', () => showQr(false));
	overlay.querySelector('[data-share-qr-save]')?.addEventListener('click', () => {
		if (!(canvas instanceof HTMLCanvasElement)) return;
		const link = document.createElement('a');
		link.href = canvas.toDataURL('image/png');
		link.download = `qr-${name.trim().replace(/\s+/g, '-').replace(/[^\p{L}\p{N}-]/gu, '') || 'grup'}.png`;
		link.click();
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !overlay.hidden) {
			event.preventDefault();
			close();
		}
	});
};
