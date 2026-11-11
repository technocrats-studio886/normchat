// Gabungkan coretan, teks, dan stiker ke foto beresolusi asli sebelum dikirim. Tanpa ini hasil edit hanya
// tampil di pratinjau dan foto yang terkirim tetap aslinya (laporan user 11 Nov 2026).

const MAX_SIDE = 4096;
const PAD_X = 0.35;
const PAD_Y = 0.15;
const LINE_HEIGHT = 1.2;

const wrapLines = (ctx, text, maxWidth) => {
	const lines = [];
	String(text).split('\n').forEach((paragraph) => {
		let line = '';
		paragraph.split(/(\s+)/).forEach((word) => {
			const next = line + word;
			if (line.trim() !== '' && ctx.measureText(next).width > maxWidth) {
				lines.push(line.trimEnd());
				line = word.trimStart();
			} else {
				line = next;
			}
		});
		lines.push(line);
	});
	return lines;
};

const roundRect = (ctx, x, y, width, height, radius) => {
	ctx.beginPath();
	ctx.moveTo(x + radius, y);
	ctx.arcTo(x + width, y, x + width, y + height, radius);
	ctx.arcTo(x + width, y + height, x, y + height, radius);
	ctx.arcTo(x, y + height, x, y, radius);
	ctx.arcTo(x, y, x + width, y, radius);
	ctx.closePath();
};

/**
 * @param {{ img: HTMLImageElement, stage: HTMLElement, file: File, paintDoodle: (ctx, w, h) => void, items: HTMLElement[] }} options
 * @returns {Promise<File>}
 */
export const flattenImage = async ({ img, stage, file, paintDoodle, items }) => {
	const naturalWidth = img.naturalWidth || stage.clientWidth;
	const naturalHeight = img.naturalHeight || stage.clientHeight;
	const shrink = Math.min(1, MAX_SIDE / Math.max(naturalWidth, naturalHeight));
	const width = Math.round(naturalWidth * shrink);
	const height = Math.round(naturalHeight * shrink);
	const stageRect = stage.getBoundingClientRect();
	const scale = width / Math.max(1, stageRect.width);

	const canvas = document.createElement('canvas');
	canvas.width = width;
	canvas.height = height;
	const ctx = canvas.getContext('2d');
	ctx.drawImage(img, 0, 0, width, height);
	paintDoodle(ctx, width, height);

	items.forEach((node) => {
		const rect = node.getBoundingClientRect();
		const cx = (rect.left + rect.width / 2 - stageRect.left) * scale;
		const cy = (rect.top + rect.height / 2 - stageRect.top) * scale;
		const style = getComputedStyle(node);
		const fontSize = parseFloat(style.fontSize) * scale;
		ctx.save();
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		if (node.dataset.type === 'sticker') {
			ctx.font = `${fontSize}px ${style.fontFamily}`;
			ctx.shadowColor = 'rgba(0,0,0,0.35)';
			ctx.shadowBlur = 6 * scale;
			ctx.fillText(node.textContent || '', cx, cy);
			ctx.restore();
			return;
		}

		ctx.font = `${style.fontWeight} ${fontSize}px ${style.fontFamily}`;
		const padX = fontSize * PAD_X;
		const padY = fontSize * PAD_Y;
		const lines = wrapLines(ctx, node.innerText.replace(/\n$/, ''), rect.width * scale - padX * 2 + 2);
		const lineHeight = fontSize * LINE_HEIGHT;
		const boxWidth = Math.max(...lines.map((line) => ctx.measureText(line).width)) + padX * 2;
		const boxHeight = lines.length * lineHeight + padY * 2;
		if (node.dataset.bg === '1') {
			ctx.fillStyle = 'rgba(0,0,0,0.45)';
			roundRect(ctx, cx - boxWidth / 2, cy - boxHeight / 2, boxWidth, boxHeight, fontSize * 0.3);
			ctx.fill();
		} else {
			ctx.shadowColor = 'rgba(0,0,0,0.6)';
			ctx.shadowBlur = 4 * scale;
			ctx.shadowOffsetY = scale;
		}
		ctx.fillStyle = node.dataset.color || '#ffffff';
		lines.forEach((line, index) => {
			ctx.fillText(line, cx, cy - boxHeight / 2 + padY + lineHeight * (index + 0.5));
		});
		ctx.restore();
	});

	const type = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
	const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.92));
	if (!blob) return file;
	const name = type === 'image/jpeg' ? file.name.replace(/\.[^.]+$/, '') + '.jpg' : file.name;
	return new File([blob], name, { type, lastModified: Date.now() });
};
