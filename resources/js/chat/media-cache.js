// Gambar di feed: skeleton berdenyut lalu cross-fade ke gambar saat selesai dimuat (transisi
// "Skeleton loader and reveal" dari transitions.dev, CSS-nya di app.css), dan cache URL gambar yang
// sudah pernah tampil. Gambar yang sama tidak di-skeleton atau dimuat/didekode ulang saat pesannya
// dirender ulang (edit, jawab ulang, pesan sementara diganti pesan asli, kembali ke halaman ini).
// File gambarnya sendiri disimpan cache browser selamanya (URL berversi, ChatController::attachment).

const STORAGE_KEY = 'nc-media-loaded';
const STORAGE_LIMIT = 400;

const loaded = new Set();
try {
	JSON.parse(window.sessionStorage.getItem(STORAGE_KEY) || '[]').forEach((url) => loaded.add(String(url)));
} catch {
	// sessionStorage tidak tersedia (mode privat): cache hanya untuk halaman ini.
}

let persistTimer = 0;
const persist = () => {
	window.clearTimeout(persistTimer);
	persistTimer = window.setTimeout(() => {
		try {
			window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(loaded).slice(-STORAGE_LIMIT)));
		} catch {
			// Penyimpanan penuh atau diblokir: abaikan.
		}
	}, 300);
};

const isLocalUrl = (url) => String(url || '').startsWith('blob:') || String(url || '').startsWith('data:');

/** Gambar ini sudah pernah tampil (ada di cache browser): render langsung tanpa skeleton. */
export const isMediaLoaded = (url) => isLocalUrl(url) || loaded.has(String(url || ''));

const markMediaLoaded = (url) => {
	if (!url || isLocalUrl(url) || loaded.has(url)) {
		return;
	}
	loaded.add(url);
	persist();
};

const frameOf = (image) => image.closest('[data-media-reveal]');

const reveal = (image) => {
	frameOf(image)?.classList.add('is-revealed');
	markMediaLoaded(image.getAttribute('src') || '');
};

/** Gambar yang selesai dimuat sebelum listener terpasang (mis. dari cache). */
export const settleMedia = (root = document) => {
	root.querySelectorAll?.('[data-media-reveal]:not(.is-revealed) img[data-media-img]').forEach((image) => {
		if (image.complete && image.naturalWidth > 0) {
			reveal(image);
		}
	});
};

/** URL blob pesan sementara dilepas belakangan: gambarnya masih tampil sampai versi server selesai dimuat. */
export const revokeLater = (url) => {
	if (typeof url === 'string' && url.startsWith('blob:')) {
		window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
	}
};

/**
 * Pesan dirender ulang: gambar yang sudah tampil dipindah ke node baru, jadi tidak dimuat dan
 * didekode ulang (tanpa kedip). Pesan sementara (blob) → pesan asli: gambar lokal tetap tampil
 * sampai gambar dari server selesai dimuat di latar.
 */
export const carryOverMedia = (fromNode, toNode) => {
	if (!(fromNode instanceof Element) || !(toNode instanceof Element)) {
		return;
	}

	const previous = Array.from(fromNode.querySelectorAll('img[data-media-img]')).filter((image) => image.complete && image.naturalWidth > 0);
	toNode.querySelectorAll('img[data-media-img]').forEach((image, index) => {
		const src = image.getAttribute('src') || '';
		const same = previous.find((old) => old.getAttribute('src') === src);
		if (same) {
			image.replaceWith(same);
			frameOf(same)?.classList.add('is-revealed');
			return;
		}

		const local = previous[index];
		if (!local || !isLocalUrl(local.getAttribute('src'))) {
			return;
		}
		frameOf(image)?.classList.add('is-revealed');
		image.setAttribute('src', local.getAttribute('src'));
		const preload = new Image();
		preload.decoding = 'async';
		preload.onload = () => {
			image.setAttribute('src', src);
			markMediaLoaded(src);
		};
		preload.src = src;
	});
};

export const initMediaReveal = () => {
	document.addEventListener('load', (event) => {
		const image = event.target;
		if (image instanceof HTMLImageElement && image.hasAttribute('data-media-img')) {
			reveal(image);
		}
	}, true);

	// Gagal dimuat: skeleton berhenti, area tetap bisa diketuk untuk membuka/mengunduh.
	document.addEventListener('error', (event) => {
		const image = event.target;
		if (image instanceof HTMLImageElement && image.hasAttribute('data-media-img')) {
			frameOf(image)?.classList.add('is-revealed', 'is-broken');
		}
	}, true);
};
