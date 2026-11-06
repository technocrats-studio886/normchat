// Blok kode di bubble chat (docs/rancangan-tur-dan-blok-kode.md): pewarnaan sintaks (highlight.js, dimuat
// hanya bila ada blok kode), ikon salin, dan ikon <> yang memasukkan kode ke kolom ketik untuk diubah.

import { chatFeed } from './context.js';
import { showActionToast } from './composer/toast.js';

const CHECK_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';

let highlighter = null;
const loadHighlighter = () => {
	highlighter ??= import('highlight.js/lib/common').then((module) => module.default ?? module);
	return highlighter;
};

/** Warnai blok kode yang belum diwarnai di dalam root. Bahasa yang tidak dikenal dibiarkan polos. */
export const highlightCodeIn = async (root) => {
	const blocks = [...(root?.querySelectorAll?.('[data-code-block] code:not([data-highlighted])') || [])];
	if (blocks.length === 0) return;
	const hljs = await loadHighlighter();
	blocks.forEach((code) => {
		code.setAttribute('data-highlighted', '');
		const lang = code.dataset.lang || '';
		if (!lang || !hljs.getLanguage(lang)) return;
		// highlight.js meng-escape sendiri isi kodenya; hasilnya hanya <span class="hljs-…">.
		code.innerHTML = hljs.highlight(code.textContent || '', { language: lang, ignoreIllegals: true }).value;
		code.classList.add('hljs');
	});
};

const copyText = async (text) => {
	try {
		await navigator.clipboard.writeText(text);
		return true;
	} catch {
		const area = Object.assign(document.createElement('textarea'), { value: text });
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

const flash = (button) => {
	const original = button.innerHTML;
	button.innerHTML = CHECK_ICON;
	button.classList.add('is-done');
	window.setTimeout(() => {
		button.innerHTML = original;
		button.classList.remove('is-done');
	}, 1500);
};

/** Masukkan kode ke kolom ketik sebagai ```bahasa … ```; teks yang sudah ada tidak ditimpa. */
const toComposer = (block) => {
	const input = document.querySelector('[data-chat-form] [data-mention-input]');
	if (!(input instanceof HTMLTextAreaElement || input instanceof HTMLInputElement)) {
		showActionToast('Kolom ketik tidak tersedia di grup ini');
		return false;
	}
	const code = block.querySelector('code');
	const lang = code?.dataset.lang || '';
	const fenced = `\`\`\`${lang}\n${(code?.textContent || '').replace(/\n+$/, '')}\n\`\`\``;
	const current = input.value.replace(/\s+$/, '');
	input.value = current ? `${current}\n\n${fenced}` : fenced;
	input.focus();
	input.setSelectionRange(input.value.length, input.value.length);
	// Tinggi kolom, tombol kirim, dan draf mengikuti event input.
	input.dispatchEvent(new Event('input', { bubbles: true }));
	input.scrollTop = input.scrollHeight;
	return true;
};

export const initCodeBlocks = () => {
	document.addEventListener('click', async (event) => {
		const target = event.target instanceof Element ? event.target : null;
		const copyButton = target?.closest('[data-code-copy]');
		const editButton = target?.closest('[data-code-to-composer]');
		const block = (copyButton || editButton)?.closest('[data-code-block]');
		if (!block) return;
		event.preventDefault();
		event.stopPropagation();
		if (copyButton) {
			const copied = await copyText(block.querySelector('code')?.textContent || '');
			if (copied) flash(copyButton);
			showActionToast(copied ? 'Kode disalin' : 'Kode tidak bisa disalin di browser ini');
			return;
		}
		if (toComposer(block)) {
			flash(editButton);
			showActionToast('Kode masuk ke kolom ketik');
		}
	});

	if (!(chatFeed instanceof HTMLElement)) return;
	highlightCodeIn(chatFeed);
	let scheduled = false;
	new MutationObserver(() => {
		if (scheduled) return;
		scheduled = true;
		window.requestAnimationFrame(() => {
			scheduled = false;
			highlightCodeIn(chatFeed);
		});
	}).observe(chatFeed, { childList: true, subtree: true });
};
