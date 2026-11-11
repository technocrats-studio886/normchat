// Teks dan stiker emoji di atas foto. Teks diketik langsung di titik yang diketuk (kursor muncul di situ,
// tanpa kolom isian terpisah; laporan user 11 Nov 2026). Posisi disimpan dalam persen terhadap foto, jadi
// tetap pas saat layar berubah ukuran dan saat digabung ke foto beresolusi asli (media-editor/flatten.js).

/**
 * @param {{ doodleEnabled: boolean, placementMode: string }} state status editor bersama
 * @param {() => void} refreshTools perbarui tampilan tombol alat
 */
export const bindAnnotations = (el, state, refreshTools) => {
	const stage = el.querySelector('[data-attachment-preview-stage]');
	const layer = el.querySelector('[data-attachment-preview-annotations]');
	const stickerButtons = Array.from(el.querySelectorAll('[data-preview-sticker]'));
	const stickerSizeButtons = Array.from(el.querySelectorAll('[data-preview-sticker-size]'));
	const textSizeButtons = Array.from(el.querySelectorAll('[data-preview-text-size]'));
	const textColorButtons = Array.from(el.querySelectorAll('[data-preview-text-color]'));
	const textBgButton = el.querySelector('[data-preview-text-bg]');

	let selectedSticker = '😀';
	let stickerSize = 48;
	let textSize = 28;
	let textColor = '#ffffff';
	let textBackground = true;
	let selected = null;

	const clamp = (value) => Math.min(100, Math.max(0, value));
	const percentOf = (clientX, clientY) => {
		const rect = stage.getBoundingClientRect();
		return [clamp(((clientX - rect.left) / Math.max(1, rect.width)) * 100), clamp(((clientY - rect.top) / Math.max(1, rect.height)) * 100)];
	};
	const place = (node, [x, y]) => {
		node.style.left = `${x}%`;
		node.style.top = `${y}%`;
		node.dataset.x = String(x);
		node.dataset.y = String(y);
	};

	const select = (node) => {
		selected?.classList.remove('is-selected');
		selected = node instanceof HTMLElement ? node : null;
		selected?.classList.add('is-selected');
	};

	const styleText = (node) => {
		node.style.fontSize = `${node.dataset.size}px`;
		node.style.color = node.dataset.color;
		node.classList.toggle('has-bg', node.dataset.bg === '1');
	};

	const placeCaretAtEnd = (node) => {
		const range = document.createRange();
		range.selectNodeContents(node);
		range.collapse(false);
		const selection = window.getSelection();
		selection?.removeAllRanges();
		selection?.addRange(range);
	};

	const editText = (node) => {
		select(node);
		node.contentEditable = 'true';
		node.classList.add('is-editing');
		node.focus({ preventScroll: true });
		placeCaretAtEnd(node);
	};

	const commitText = (node) => {
		node.contentEditable = 'false';
		node.classList.remove('is-editing');
		if (node.innerText.trim() === '') {
			if (selected === node) select(null);
			node.remove();
		}
	};

	const editingNode = () => layer?.querySelector('.nc-annot.is-editing') || null;

	/** Geser anotasi; ketukan tanpa geseran pada teks (alat teks aktif) membukanya untuk diedit. */
	const bindDrag = (node) => {
		let start = null;
		node.addEventListener('pointerdown', (event) => {
			if (state.doodleEnabled || node.classList.contains('is-editing')) return;
			event.preventDefault();
			event.stopPropagation();
			select(node);
			start = { x: event.clientX, y: event.clientY, moved: false };
			node.setPointerCapture?.(event.pointerId);
		});
		node.addEventListener('pointermove', (event) => {
			if (!start) return;
			if (!start.moved && Math.hypot(event.clientX - start.x, event.clientY - start.y) < 4) return;
			start.moved = true;
			place(node, percentOf(event.clientX, event.clientY));
		});
		const end = () => {
			if (start && !start.moved && node.dataset.type === 'text') editText(node);
			start = null;
		};
		node.addEventListener('pointerup', end);
		node.addEventListener('pointercancel', () => { start = null; });
	};

	const createText = (point) => {
		const node = document.createElement('span');
		node.className = 'nc-annot nc-annot--text';
		node.dataset.type = 'text';
		node.dataset.size = String(textSize);
		node.dataset.color = textColor;
		node.dataset.bg = textBackground ? '1' : '0';
		node.setAttribute('role', 'textbox');
		node.setAttribute('aria-label', 'Teks di foto');
		node.spellcheck = false;
		styleText(node);
		place(node, point);
		node.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' && !event.shiftKey) {
				event.preventDefault();
				node.blur();
			} else if (event.key === 'Escape') {
				node.blur();
			}
			event.stopPropagation();
		});
		node.addEventListener('blur', () => commitText(node));
		bindDrag(node);
		layer.appendChild(node);
		editText(node);
	};

	const createSticker = (point) => {
		const node = document.createElement('span');
		node.className = 'nc-annot nc-annot--sticker';
		node.dataset.type = 'sticker';
		node.dataset.size = String(stickerSize);
		node.textContent = selectedSticker;
		node.style.fontSize = `${stickerSize}px`;
		place(node, point);
		bindDrag(node);
		layer.appendChild(node);
		select(node);
	};

	stage?.addEventListener('click', (event) => {
		if (state.doodleEnabled || !state.placementMode || event.target.closest?.('.nc-annot')) return;
		// Mengetuk di luar teks yang sedang diketik: teks itu selesai dulu.
		const editing = editingNode();
		if (editing) {
			editing.blur();
			return;
		}
		const point = percentOf(event.clientX, event.clientY);
		if (state.placementMode === 'text') createText(point);
		else if (state.placementMode === 'sticker') createSticker(point);
	});

	const syncUi = () => {
		stickerButtons.forEach((button) => {
			const active = button.getAttribute('data-preview-sticker') === selectedSticker;
			button.classList.toggle('bg-white/45', active);
			button.classList.toggle('bg-white/18', !active);
		});
		stickerSizeButtons.forEach((button) => {
			const active = Number(button.getAttribute('data-preview-sticker-size')) === stickerSize;
			button.classList.toggle('bg-white', active);
			button.classList.toggle('text-navy-950', active);
			button.classList.toggle('bg-white/25', !active);
			button.classList.toggle('text-white', !active);
		});
		textSizeButtons.forEach((button) => {
			const active = Number(button.getAttribute('data-preview-text-size')) === textSize;
			button.classList.toggle('bg-white', active);
			button.classList.toggle('text-navy-950', active);
			button.classList.toggle('bg-white/25', !active);
			button.classList.toggle('text-white', !active);
		});
		textColorButtons.forEach((button) => {
			const active = button.getAttribute('data-preview-text-color') === textColor;
			button.classList.toggle('ring-2', active);
			button.classList.toggle('ring-white', active);
			button.classList.toggle('ring-offset-2', active);
			button.classList.toggle('ring-offset-navy-950', active);
		});
		if (textBgButton instanceof HTMLElement) {
			textBgButton.setAttribute('aria-pressed', textBackground ? 'true' : 'false');
			textBgButton.classList.toggle('bg-white', textBackground);
			textBgButton.classList.toggle('text-navy-950', textBackground);
			textBgButton.classList.toggle('bg-white/20', !textBackground);
			textBgButton.classList.toggle('text-white', !textBackground);
		}
	};

	/** Gaya teks berlaku ke teks yang sedang dipilih/diketik; fokus tetap di teks itu. */
	const restyleSelectedText = () => {
		if (selected?.dataset.type !== 'text') return;
		selected.dataset.size = String(textSize);
		selected.dataset.color = textColor;
		selected.dataset.bg = textBackground ? '1' : '0';
		styleText(selected);
		if (selected.classList.contains('is-editing')) {
			selected.focus({ preventScroll: true });
			placeCaretAtEnd(selected);
		}
	};
	// Tombol gaya tidak mengambil fokus dari teks yang sedang diketik.
	[...textSizeButtons, ...textColorButtons, textBgButton].forEach((button) => button?.addEventListener('pointerdown', (event) => event.preventDefault()));
	textSizeButtons.forEach((button) => button.addEventListener('click', () => {
		textSize = Number(button.getAttribute('data-preview-text-size')) || textSize;
		restyleSelectedText();
		syncUi();
	}));
	textColorButtons.forEach((button) => button.addEventListener('click', () => {
		textColor = button.getAttribute('data-preview-text-color') || textColor;
		restyleSelectedText();
		syncUi();
	}));
	textBgButton?.addEventListener('click', () => {
		textBackground = !textBackground;
		restyleSelectedText();
		syncUi();
	});
	stickerButtons.forEach((button) => button.addEventListener('click', (event) => {
		event.stopPropagation();
		selectedSticker = button.getAttribute('data-preview-sticker') || selectedSticker;
		if (selected?.dataset.type === 'sticker') selected.textContent = selectedSticker;
		syncUi();
		refreshTools();
	}));
	stickerSizeButtons.forEach((button) => button.addEventListener('click', (event) => {
		event.stopPropagation();
		stickerSize = Number(button.getAttribute('data-preview-sticker-size')) || stickerSize;
		if (selected?.dataset.type === 'sticker') {
			selected.dataset.size = String(stickerSize);
			selected.style.fontSize = `${stickerSize}px`;
		}
		syncUi();
	}));

	// Hapus anotasi terpilih dengan Delete/Backspace (desktop), kecuali saat sedang mengetik teks.
	document.addEventListener('keydown', (event) => {
		if (el.classList.contains('hidden') || !selected || selected.classList.contains('is-editing')) return;
		if (event.key === 'Delete' || event.key === 'Backspace') {
			if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return;
			selected.remove();
			select(null);
		}
	});

	syncUi();

	return {
		clear: () => {
			if (layer) layer.innerHTML = '';
			select(null);
		},
		/** Selesaikan teks yang masih diketik (sebelum foto digabung). */
		commit: () => editingNode()?.blur(),
		items: () => Array.from(layer?.querySelectorAll('.nc-annot') || []).filter((node) => node.innerText.trim() !== ''),
		deselect: () => select(null),
	};
};
