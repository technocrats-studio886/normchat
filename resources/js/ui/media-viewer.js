// Penampil gambar layar penuh untuk tab Media di Info grup: buka dari deretan gambar atau grid
// "Media, tautan, dan dok", lalu geser kiri/kanan (sentuh/seret, tombol ‹ ›, panah keyboard).

const SWIPE_PX = 50;

export const initMediaViewer = (root) => {
	const viewer = root.querySelector('[data-media-viewer]');
	if (!(viewer instanceof HTMLElement)) return;
	const image = viewer.querySelector('[data-media-viewer-image]');
	const count = viewer.querySelector('[data-media-viewer-count]');
	const original = viewer.querySelector('[data-media-viewer-open]');
	const prev = viewer.querySelector('[data-media-viewer-prev]');
	const next = viewer.querySelector('[data-media-viewer-next]');
	const stage = viewer.querySelector('[data-media-viewer-stage]');
	// Urutan gambar sama dengan deretan di tab Media (terbaru dulu).
	const urls = [...root.querySelectorAll('[data-info-media-strip] [data-info-media-item]')].map((item) => item.getAttribute('href'));
	if (urls.length === 0) return;

	// Di luar jendela Info grup, supaya layar penuh tidak terpotong wadah yang bergulir.
	document.body.appendChild(viewer);
	let index = 0;
	let previousFocus = null;

	const show = (i) => {
		index = Math.max(0, Math.min(urls.length - 1, i));
		image.src = urls[index];
		original.href = urls[index];
		count.textContent = `${index + 1} / ${urls.length}`;
		prev.disabled = index === 0;
		next.disabled = index === urls.length - 1;
	};
	const open = (i) => {
		previousFocus = document.activeElement;
		show(i);
		viewer.hidden = false;
		document.documentElement.classList.add('nc-media-viewer-on');
		viewer.querySelector('[data-media-viewer-close]').focus();
	};
	const close = () => {
		viewer.hidden = true;
		document.documentElement.classList.remove('nc-media-viewer-on');
		image.removeAttribute('src');
		if (previousFocus instanceof HTMLElement) previousFocus.focus();
	};

	root.addEventListener('click', (event) => {
		const item = event.target instanceof Element ? event.target.closest('[data-info-media-item]') : null;
		if (!item) return;
		event.preventDefault();
		open(Number(item.getAttribute('data-info-media-item')) || 0);
	});
	prev.addEventListener('click', () => show(index - 1));
	next.addEventListener('click', () => show(index + 1));
	viewer.querySelector('[data-media-viewer-close]').addEventListener('click', close);
	stage.addEventListener('click', (event) => {
		if (event.target === stage) close();
	});

	// Geser: catat titik awal, pindah gambar bila jarak horizontal cukup.
	let startX = null;
	let startY = 0;
	stage.addEventListener('pointerdown', (event) => {
		startX = event.clientX;
		startY = event.clientY;
	});
	stage.addEventListener('pointerup', (event) => {
		if (startX === null) return;
		const dx = event.clientX - startX;
		const dy = event.clientY - startY;
		startX = null;
		if (Math.abs(dx) >= SWIPE_PX && Math.abs(dx) > Math.abs(dy)) show(index + (dx < 0 ? 1 : -1));
	});
	stage.addEventListener('pointercancel', () => { startX = null; });

	// Fase capture: penampil menangani Esc dan panah sebelum lapisan Info grup di bawahnya.
	document.addEventListener('keydown', (event) => {
		if (viewer.hidden) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			event.stopPropagation();
			close();
		} else if (event.key === 'ArrowLeft') {
			event.preventDefault();
			show(index - 1);
		} else if (event.key === 'ArrowRight') {
			event.preventDefault();
			show(index + 1);
		}
	}, true);
};
