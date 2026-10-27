// Canvas: kartu di feed dan panel Code/Preview yang terbuka saat tombol Open diklik.
// Preview web dimuat dari route canvas (ChatController::canvas) di iframe sandbox tanpa
// allow-same-origin, jadi skrip canvas tidak bisa menyentuh sesi atau halaman normi.chat.
// Bukan srcdoc: dokumen srcdoc mewarisi CSP normi.chat sehingga skrip canvas terblokir.

import { chatShell } from './context.js';
import { renderMarkdown, renderMermaidInNode } from './markdown.js';
import { escapeAttr, escapeHtml } from './utils.js';

const WEB_ICON = '<path stroke-linecap="round" stroke-linejoin="round" d="m8 9-3 3 3 3m8-6 3 3-3 3M13.5 6l-3 12"/>';
const DOC_ICON = '<path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Zm0 0v5h5M9 13h6m-6 4h4"/>';

const titleFromFilename = (filename) => {
	const base = String(filename || '').replace(/\.[^.]+$/, '').replace(/-/g, ' ').trim();
	return base ? base.replace(/\b\w/g, (char) => char.toUpperCase()) : 'Canvas';
};

/** Kartu Canvas untuk pesan realtime; padanan resources/views/chat/_canvas-card.blade.php. */
export const renderCanvasCard = (message) => {
	const isWeb = String(message?.attachment_mime || '').toLowerCase() === 'text/html';
	const title = titleFromFilename(message?.attachment_original_name);
	const createdAt = message?.created_at ? new Date(message.created_at) : null;
	const dateText = createdAt && !Number.isNaN(createdAt.valueOf())
		? createdAt.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
		: '';

	return `
		<div class="nc-canvas-card mb-2" data-message-body="1">
			<div class="flex items-start gap-3">
				<span class="nc-canvas-card-icon">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">${isWeb ? WEB_ICON : DOC_ICON}</svg>
				</span>
				<div class="min-w-0 flex-1">
					<p class="truncate text-sm font-semibold">${escapeHtml(title)}</p>
					<p class="text-[11px] opacity-70">${isWeb ? 'Halaman web' : 'Dokumen'}${dateText ? ` · ${escapeHtml(dateText)}` : ''}</p>
				</div>
			</div>
			<div class="mt-3 flex justify-end">
				<button type="button" class="nc-canvas-open" data-canvas-open="1" data-canvas-url="${escapeAttr(message?.attachment_url || '')}" data-canvas-kind="${isWeb ? 'web' : 'document'}" data-canvas-title="${escapeAttr(title)}" data-canvas-filename="${escapeAttr(message?.attachment_original_name || 'canvas')}">Buka</button>
			</div>
		</div>
	`;
};

let panel = null;
let current = null;

const buildPanel = () => {
	const overlay = document.createElement('div');
	overlay.className = 'nc-canvas-overlay hidden';
	overlay.setAttribute('role', 'dialog');
	overlay.setAttribute('aria-modal', 'true');
	overlay.innerHTML = `
		<div class="nc-canvas-panel" data-canvas-frame>
			<div class="nc-canvas-toolbar">
				<p class="min-w-0 flex-1 truncate text-sm font-semibold" data-canvas-heading></p>
				<div class="nc-canvas-tabs" role="tablist">
					<button type="button" role="tab" data-canvas-tab="code">Kode</button>
					<button type="button" role="tab" data-canvas-tab="preview">Pratinjau</button>
				</div>
				<button type="button" class="nc-canvas-tool" data-canvas-download title="Unduh" aria-label="Unduh">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 20h16"/></svg>
				</button>
				<button type="button" class="nc-canvas-tool hidden sm:inline-flex" data-canvas-fullscreen title="Layar penuh" aria-label="Layar penuh">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
				</button>
				<button type="button" class="nc-canvas-tool" data-canvas-close title="Tutup" aria-label="Tutup">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
				</button>
			</div>
			<div class="nc-canvas-body">
				<p class="p-6 text-sm opacity-70" data-canvas-status>Memuat...</p>
				<div class="hidden h-full" data-canvas-preview></div>
				<pre class="nc-canvas-code hidden" data-canvas-code><code></code></pre>
			</div>
		</div>
	`;

	(chatShell instanceof HTMLElement ? chatShell : document.body).appendChild(overlay);

	overlay.addEventListener('click', (event) => {
		if (event.target === overlay) {
			closePanel();
		}
	});
	overlay.querySelector('[data-canvas-close]').addEventListener('click', closePanel);
	overlay.querySelectorAll('[data-canvas-tab]').forEach((tab) => {
		tab.addEventListener('click', () => showTab(tab.getAttribute('data-canvas-tab')));
	});
	overlay.querySelector('[data-canvas-download]').addEventListener('click', download);
	overlay.querySelector('[data-canvas-fullscreen]').addEventListener('click', () => {
		const frame = overlay.querySelector('[data-canvas-frame]');
		if (document.fullscreenElement) {
			document.exitFullscreen?.();
		} else {
			frame.requestFullscreen?.().catch(() => {});
		}
	});

	return overlay;
};

const showTab = (tab) => {
	if (!panel || !current) {
		return;
	}

	panel.querySelectorAll('[data-canvas-tab]').forEach((button) => {
		const active = button.getAttribute('data-canvas-tab') === tab;
		button.classList.toggle('is-active', active);
		button.setAttribute('aria-selected', active ? 'true' : 'false');
	});
	panel.querySelector('[data-canvas-preview]').classList.toggle('hidden', tab !== 'preview');
	panel.querySelector('[data-canvas-code]').classList.toggle('hidden', tab !== 'code');
};

const renderContent = () => {
	const preview = panel.querySelector('[data-canvas-preview]');
	panel.querySelector('[data-canvas-code] code').textContent = current.content;

	if (current.kind === 'web') {
		preview.innerHTML = '';
		const iframe = document.createElement('iframe');
		iframe.className = 'h-full w-full border-0 bg-white';
		iframe.title = current.title;
		iframe.setAttribute('sandbox', 'allow-scripts allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-downloads');
		iframe.src = current.url.replace(/\/attachment$/, '/canvas');
		preview.appendChild(iframe);
		return;
	}

	preview.innerHTML = `<article class="nc-canvas-document ai-markdown">${renderMarkdown(escapeHtml(current.content))}</article>`;
	renderMermaidInNode(preview);
};

const openPanel = async (trigger) => {
	panel ??= buildPanel();
	current = {
		url: trigger.getAttribute('data-canvas-url') || '',
		kind: trigger.getAttribute('data-canvas-kind') === 'web' ? 'web' : 'document',
		title: trigger.getAttribute('data-canvas-title') || 'Canvas',
		filename: trigger.getAttribute('data-canvas-filename') || 'canvas',
		content: '',
	};

	const status = panel.querySelector('[data-canvas-status]');
	panel.querySelector('[data-canvas-heading]').textContent = current.title;
	panel.querySelector('[data-canvas-preview]').classList.add('hidden');
	panel.querySelector('[data-canvas-code]').classList.add('hidden');
	status.textContent = 'Memuat...';
	status.classList.remove('hidden');
	panel.classList.remove('hidden');
	document.addEventListener('keydown', onEscape);

	const opened = current;
	try {
		const response = await fetch(opened.url, { credentials: 'same-origin' });
		if (!response.ok) {
			throw new Error(String(response.status));
		}
		opened.content = await response.text();
	} catch {
		if (current === opened) {
			status.textContent = 'Gagal memuat. Coba lagi.';
		}
		return;
	}

	if (current !== opened) {
		return;
	}
	status.classList.add('hidden');
	renderContent();
	showTab('preview');
};

const closePanel = () => {
	if (document.fullscreenElement) {
		document.exitFullscreen?.();
	}
	panel?.classList.add('hidden');
	const preview = panel?.querySelector('[data-canvas-preview]');
	if (preview) {
		preview.innerHTML = ''; // hentikan skrip canvas yang masih berjalan
	}
	current = null;
	document.removeEventListener('keydown', onEscape);
};

const onEscape = (event) => {
	if (event.key === 'Escape' && !document.fullscreenElement) {
		closePanel();
	}
};

const download = () => {
	if (!current?.content) {
		return;
	}

	const blob = new Blob([current.content], { type: current.kind === 'web' ? 'text/html' : 'text/markdown' });
	const link = document.createElement('a');
	link.href = URL.createObjectURL(blob);
	link.download = current.filename;
	document.body.appendChild(link);
	link.click();
	link.remove();
	window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
};

export const initCanvas = () => {
	document.addEventListener('click', (event) => {
		const trigger = event.target.closest('[data-canvas-open]');
		if (trigger) {
			event.preventDefault();
			openPanel(trigger);
		}
	});
};
