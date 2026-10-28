// Menu aksi pesan.
// Desktop (kursor): baris ikon di samping bawah bubble (atau di bawahnya bila sempit) saat kursor
// diarahkan atau bubble difokus.
// HP: tahan lama → bubble ditonjolkan di atas latar redup, daftar aksi tepat di bawahnya.

import { chatFeed } from '../context.js';
import { escapeHtml } from '../utils.js';
import { runAction } from './handlers.js';
import { icon } from './icons.js';
import { actionsFor } from './rules.js';

const LABELS = {
	reply: 'Balas',
	copy: 'Salin',
	forward: 'Bagikan',
	edit: 'Edit',
	versions: 'Lihat versi',
	selectText: 'Pilih teks',
	select: 'Pilih pesan',
	link: 'Salin tautan',
	delete: 'Hapus',
};
const ICONS = { ...Object.fromEntries(Object.keys(LABELS).map((key) => [key, key])), delete: 'trash' };
const TOOLBAR_MAIN = ['reply', 'copy', 'forward', 'edit', 'versions', 'delete'];

const canHover = window.matchMedia('(hover: hover) and (pointer: fine)');

/* ── Desktop: baris ikon ───────────────────────────── */

const toolbar = document.createElement('div');
toolbar.className = 'nc-msg-actions';
toolbar.setAttribute('role', 'toolbar');
toolbar.setAttribute('aria-label', 'Aksi pesan');
let toolbarNode = null;
let moreMenu = null;

const closeMoreMenu = () => {
	moreMenu?.remove();
	moreMenu = null;
};

const unmountToolbar = () => {
	closeMoreMenu();
	toolbar.remove();
	toolbarNode = null;
};

const toolbarButton = (action) => `<button type="button" class="nc-msg-action${action === 'delete' ? ' is-danger' : ''}" data-msg-action="${action}" aria-label="${LABELS[action]}" title="${LABELS[action]}">${icon(ICONS[action], 'h-[18px] w-[18px]')}</button>`;

const mountToolbar = (node) => {
	if (node === toolbarNode) {
		return;
	}
	unmountToolbar();
	const actions = actionsFor(node);
	if (actions.length === 0 || document.documentElement.classList.contains('nc-selecting') || document.documentElement.classList.contains('nc-editing')) {
		return;
	}

	const main = actions.filter((action) => TOOLBAR_MAIN.includes(action));
	const extra = actions.filter((action) => !TOOLBAR_MAIN.includes(action));
	toolbar.innerHTML = main.map(toolbarButton).join('')
		+ (extra.length ? `<button type="button" class="nc-msg-action" data-msg-more aria-label="Lainnya" title="Lainnya" aria-haspopup="menu">${icon('more', 'h-[18px] w-[18px]')}</button>` : '');
	toolbar.dataset.extra = extra.join(',');
	const stack = node.querySelector('.nc-msg-stack');
	stack?.appendChild(toolbar);
	toolbarNode = node;

	// Di samping bubble bila ruangnya cukup (tidak menutupi pesan lain); selain itu di bawahnya.
	const bubble = node.querySelector('[data-message-bubble]');
	if (stack && bubble) {
		const mine = node.classList.contains('nc-msg--mine');
		const nodeRect = node.getBoundingClientRect();
		const bubbleRect = bubble.getBoundingClientRect();
		const free = mine ? bubbleRect.left - nodeRect.left : nodeRect.right - bubbleRect.right;
		toolbar.classList.toggle('is-side', free >= toolbar.offsetWidth + 12);
	}
};

const openMoreMenu = (button) => {
	closeMoreMenu();
	moreMenu = document.createElement('div');
	moreMenu.className = 'nc-msg-more';
	moreMenu.setAttribute('role', 'menu');
	moreMenu.innerHTML = String(toolbar.dataset.extra || '').split(',').filter(Boolean)
		.map((action) => `<button type="button" role="menuitem" data-msg-action="${action}">${icon(ICONS[action], 'h-[18px] w-[18px]')}<span>${LABELS[action]}</span></button>`)
		.join('');
	toolbar.appendChild(moreMenu);
	moreMenu.querySelector('button')?.focus();
	button.setAttribute('aria-expanded', 'true');
};

toolbar.addEventListener('click', (event) => {
	const node = toolbarNode;
	const more = event.target.closest('[data-msg-more]');
	if (more) {
		if (moreMenu) {
			closeMoreMenu();
		} else {
			openMoreMenu(more);
		}
		return;
	}

	const button = event.target.closest('[data-msg-action]');
	if (button && node) {
		const action = button.getAttribute('data-msg-action');
		closeMoreMenu();
		runAction(action, node, { button });
	}
});

/* ── HP: tahan lama ────────────────────────────────── */

let pressOverlay = null;

let pressedNode = null;

export const closePressMenu = () => {
	pressOverlay?.remove();
	pressOverlay = null;
	pressedNode?.classList.remove('is-pressed');
	pressedNode = null;
	document.removeEventListener('keydown', onPressKey);
};

const onPressKey = (event) => {
	if (event.key === 'Escape') {
		closePressMenu();
	}
};

export const openPressMenu = (node) => {
	closePressMenu();
	const bubble = node.querySelector('[data-message-bubble]');
	const actions = actionsFor(node);
	if (!(bubble instanceof HTMLElement) || actions.length === 0) {
		return;
	}

	const rect = bubble.getBoundingClientRect();
	const mine = node.classList.contains('nc-msg--mine');
	pressOverlay = document.createElement('div');
	pressOverlay.className = 'nc-press-overlay';
	pressOverlay.innerHTML = `
		<div class="nc-press-stack${mine ? ' is-mine' : ''}">
			<div class="nc-press-bubble"></div>
			<div class="nc-press-menu" role="menu" aria-label="Aksi pesan" tabindex="-1">
				${actions.map((action) => `${action === 'delete' ? '<hr>' : ''}<button type="button" role="menuitem" class="${action === 'delete' ? 'is-danger' : ''}" data-msg-action="${action}">${icon(ICONS[action])}<span>${escapeHtml(LABELS[action])}</span></button>`).join('')}
			</div>
		</div>
	`;
	const clone = bubble.cloneNode(true);
	clone.removeAttribute('data-message-bubble');
	clone.style.width = `${rect.width}px`;
	pressOverlay.querySelector('.nc-press-bubble').appendChild(clone);
	document.body.appendChild(pressOverlay);

	// Bubble tetap di posisi aslinya; bila menu tidak muat di bawah, tumpukan digeser ke atas.
	const stack = pressOverlay.querySelector('.nc-press-stack');
	const menuHeight = pressOverlay.querySelector('.nc-press-menu').getBoundingClientRect().height;
	const maxBubble = Math.max(80, window.innerHeight - menuHeight - 48);
	const bubbleHeight = Math.min(rect.height, maxBubble);
	pressOverlay.querySelector('.nc-press-bubble').style.maxHeight = `${maxBubble}px`;
	const top = Math.min(Math.max(16, rect.top), window.innerHeight - 16 - menuHeight - bubbleHeight - 8);
	stack.style.top = `${Math.max(16, top)}px`;
	stack.style[mine ? 'right' : 'left'] = `${mine ? window.innerWidth - rect.right : rect.left}px`;

	pressOverlay.addEventListener('click', (event) => {
		const button = event.target.closest('[data-msg-action]');
		if (button) {
			closePressMenu();
			runAction(button.getAttribute('data-msg-action'), node, {});
		} else if (!event.target.closest('.nc-press-menu')) {
			closePressMenu();
		}
	});
	document.addEventListener('keydown', onPressKey);
	// Bubble aslinya disembunyikan selama salinannya tampil di atas latar redup.
	pressedNode = node;
	node.classList.add('is-pressed');
	pressOverlay.querySelector('.nc-press-menu').focus();
};

export const initMessageMenu = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return;
	}

	chatFeed.addEventListener('pointerover', (event) => {
		if (!canHover.matches || event.pointerType === 'touch') {
			return;
		}
		const node = event.target instanceof Element ? event.target.closest('.nc-msg') : null;
		if (node instanceof HTMLElement) {
			mountToolbar(node);
		} else if (!moreMenu) {
			unmountToolbar();
		}
	});
	chatFeed.addEventListener('pointerleave', () => {
		if (!moreMenu) {
			unmountToolbar();
		}
	});
	// Keyboard: fokus pada bubble menampilkan baris ikon yang sama.
	chatFeed.addEventListener('focusin', (event) => {
		const node = event.target instanceof Element ? event.target.closest('.nc-msg') : null;
		if (node instanceof HTMLElement && !toolbar.contains(event.target)) {
			mountToolbar(node);
		}
	});
	document.addEventListener('click', (event) => {
		if (moreMenu && !toolbar.contains(event.target)) {
			closeMoreMenu();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			closeMoreMenu();
		}
	});
	chatFeed.addEventListener('scroll', closePressMenu, { passive: true });
};

/** Node pesan diganti (edit, hapus, realtime): baris ikon ikut dilepas. */
export const forgetToolbarFor = (node) => {
	if (node === toolbarNode) {
		unmountToolbar();
	}
};
