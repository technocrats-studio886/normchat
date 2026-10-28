// Render markdown balasan AI (tabel, list, blok kode, kutipan, mermaid).

export const renderMarkdown = (escaped) => {
	const lines = escaped.split('\n');
	const out = [];
	let inTable = false;
	let fenceLang = null;
	let fenceLines = [];
	let inList = false;
	let listType = '';

	const closeList = () => {
		if (inList) {
			out.push(listType === 'ol' ? '</ol>' : '</ul>');
			inList = false;
		}
	};

	for (let i = 0; i < lines.length; i++) {
		const line = lines[i];

		// Blok kode berpagar (```bahasa ... ```); ```mermaid menjadi diagram.
		const fence = line.trim().match(/^```(\w*)$/);
		if (fence && fenceLang === null) {
			closeList();
			fenceLang = fence[1].toLowerCase();
			fenceLines = [];
			continue;
		}
		if (fenceLang !== null) {
			if (line.trim() === '```') {
				if (fenceLang === 'mermaid') {
					const id = 'mermaid-' + Math.random().toString(36).slice(2, 10);
					out.push(`<pre class="mermaid" id="${id}">${fenceLines.join('\n')}</pre>`);
				} else {
					out.push(`<pre class="nc-code"><code>${fenceLines.join('\n')}</code></pre>`);
				}
				fenceLang = null;
			} else {
				fenceLines.push(line);
			}
			continue;
		}

		// Kutipan (teks sudah di-escape, jadi ">" menjadi "&gt;")
		const quoteMatch = line.match(/^&gt;\s?(.*)/);
		if (quoteMatch) {
			closeList();
			out.push(`<blockquote class="nc-md-quote">${inlineFormat(quoteMatch[1])}</blockquote>`);
			continue;
		}

		// Table rows
		if (line.trim().match(/^\|(.+)\|$/)) {
			closeList();
			if (line.trim().match(/^\|[\s\-:|]+\|$/)) {
				continue; // separator row
			}
			const cells = line.trim().slice(1, -1).split('|').map(c => c.trim());
			if (!inTable) {
				inTable = true;
				out.push('<div class="overflow-x-auto my-2"><table class="w-full text-xs border-collapse">');
				out.push('<thead><tr>' + cells.map(c => `<th class="border border-slate-300 bg-slate-100 px-2 py-1 text-left font-semibold">${inlineFormat(c)}</th>`).join('') + '</tr></thead><tbody>');
			} else {
				out.push('<tr>' + cells.map(c => `<td class="border border-slate-200 px-2 py-1">${inlineFormat(c)}</td>`).join('') + '</tr>');
			}
			continue;
		}
		if (inTable) {
			inTable = false;
			out.push('</tbody></table></div>');
		}

		// Headings
		const headingMatch = line.match(/^(#{1,3})\s+(.+)/);
		if (headingMatch) {
			closeList();
			const level = headingMatch[1].length;
			const sizes = { 1: 'text-base font-bold', 2: 'text-sm font-bold', 3: 'text-sm font-semibold' };
			out.push(`<p class="${sizes[level] || 'font-bold'} mt-1">${inlineFormat(headingMatch[2])}</p>`);
			continue;
		}

		// Ordered list
		const olMatch = line.match(/^(\d+)\.\s+(.+)/);
		if (olMatch) {
			if (!inList || listType !== 'ol') {
				closeList();
				inList = true;
				listType = 'ol';
				out.push('<ol class="list-decimal pl-5 my-1 space-y-0.5 text-sm">');
			}
			out.push(`<li>${inlineFormat(olMatch[2])}</li>`);
			continue;
		}

		// Unordered list
		const ulMatch = line.match(/^[-*]\s+(.+)/);
		if (ulMatch) {
			if (!inList || listType !== 'ul') {
				closeList();
				inList = true;
				listType = 'ul';
				out.push('<ul class="list-disc pl-5 my-1 space-y-0.5 text-sm">');
			}
			out.push(`<li>${inlineFormat(ulMatch[1])}</li>`);
			continue;
		}

		closeList();

		// Horizontal rule
		if (line.trim() === '---' || line.trim() === '***') {
			out.push('<hr class="my-2 border-slate-200" />');
			continue;
		}

		// Empty line
		if (line.trim() === '') {
			out.push('<br/>');
			continue;
		}

		// Normal paragraph
		out.push(`<p>${inlineFormat(line)}</p>`);
	}

	closeList();
	if (inTable) out.push('</tbody></table></div>');
	// Blok kode yang tidak ditutup tetap ditampilkan sebagai kode.
	if (fenceLang !== null) out.push(`<pre class="nc-code"><code>${fenceLines.join('\n')}</code></pre>`);

	return out.join('\n');
};

// Teks sudah di-escape sebelum masuk sini, jadi URL tidak bisa keluar dari atribut href.
const inlineFormat = (text) => {
	return text
		.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer nofollow" class="font-medium underline underline-offset-2">$1</a>')
		.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
		.replace(/\*(.+?)\*/g, '<em>$1</em>')
		.replace(/`([^`]+)`/g, '<code class="rounded bg-slate-200 px-1 py-0.5 text-xs">$1</code>');
};

// Mermaid (±2,5 MB) hanya dimuat bila ada diagram, dengan versi pasti dan hash integritas.
const MERMAID_SRC = 'https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.min.js';
const MERMAID_SRI = 'sha384-rbtjAdnIQE/aQJGEgXrVUlMibdfTSa4PQju4HDhN3sR2PmaKFzhEafuePsl9H/9I';
let mermaidReady = null;

const loadMermaid = () => {
	mermaidReady ??= new Promise((resolve, reject) => {
		const script = document.createElement('script');
		script.src = MERMAID_SRC;
		script.integrity = MERMAID_SRI;
		script.crossOrigin = 'anonymous';
		// Content-Security-Policy hanya mengizinkan skrip bernonce (lihat AddContentSecurityPolicy).
		script.nonce = document.querySelector('script[nonce]')?.nonce || '';
		script.onload = () => {
			window.mermaid.initialize({ startOnLoad: false, theme: 'neutral', securityLevel: 'strict' });
			resolve(window.mermaid);
		};
		script.onerror = () => {
			mermaidReady = null;
			reject(new Error('mermaid_unavailable'));
		};
		document.head.appendChild(script);
	});

	return mermaidReady;
};

export const renderMermaidInNode = async (node) => {
	const blocks = Array.from(node.querySelectorAll('pre.mermaid:not([data-processed])'));
	if (blocks.length === 0) {
		return;
	}

	let mermaid;
	try {
		mermaid = await loadMermaid();
	} catch {
		return;
	}
	blocks.forEach(async (block) => {
		if (block.dataset.processed) return;
		block.dataset.processed = '1';
		try {
			const id = block.id || ('m-' + Math.random().toString(36).slice(2, 8));
			const { svg } = await mermaid.render(id + '-svg', block.textContent);
			block.innerHTML = svg;
		} catch {
			block.classList.add('text-xs', 'text-danger-ink');
		}
	});
};
