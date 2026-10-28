// Gestur pada pesan: geser ke kanan untuk membalas, tahan lama untuk menu aksi (HP), klik ganda
// untuk membalas. Menu dan aksinya ada di chat/actions/.

import { openPressMenu } from '../actions/menu.js';
import { isSelecting } from '../actions/select-mode.js';
import { chatFeed } from '../context.js';
import { setReplyTarget } from './reply.js';

export const initMessageActions = () => {
	if (chatFeed instanceof HTMLElement) {
		const SWIPE_THRESHOLD = 56;
		const SWIPE_MAX = 90;
		const AXIS_LOCK_PX = 10;
		const LONG_PRESS_MS = 520;
		let startX = 0;
		let startY = 0;
		let activeNode = null;
		let pointerId = null;
		// null = arah belum jelas, 'x' = sedang swipe balas, 'y' = user menggulir.
		let axis = null;
		let longPressTimer = null;
		let swipeReplyReady = false;
		let swipeIcon = null;
		let suppressClickUntil = 0;

		const clearLongPress = () => {
			if (longPressTimer) {
				window.clearTimeout(longPressTimer);
				longPressTimer = null;
			}
		};

		const resetTransform = (node) => {
			if (node instanceof HTMLElement) {
				node.style.transform = '';
				node.style.transition = 'transform 180ms ease';
				window.setTimeout(() => { node.style.transition = ''; }, 200);
			}
		};

		/** Ikon balas di sisi kiri bubble; ikut bergeser bersama pesan dan makin jelas mendekati ambang. */
		const showSwipeIcon = (node) => {
			const bubble = node.querySelector('.nc-msg-stack') || node;
			const offset = (bubble instanceof HTMLElement ? bubble.getBoundingClientRect().left : 0) - node.getBoundingClientRect().left;
			swipeIcon = document.createElement('span');
			swipeIcon.className = 'nc-swipe-reply-icon';
			swipeIcon.setAttribute('aria-hidden', 'true');
			swipeIcon.style.left = `${offset - 36}px`;
			swipeIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 0 1 0 10H9m-6-10 4-4m-4 4 4 4"/></svg>';
			node.style.position = 'relative';
			node.appendChild(swipeIcon);
		};

		const removeSwipeIcon = (node) => {
			swipeIcon?.remove();
			swipeIcon = null;
			if (node instanceof HTMLElement) {
				node.style.position = '';
			}
		};

		const clearActive = () => {
			if (activeNode) {
				resetTransform(activeNode);
				removeSwipeIcon(activeNode);
				if (pointerId !== null && activeNode.hasPointerCapture?.(pointerId)) {
					activeNode.releasePointerCapture(pointerId);
				}
			}
			activeNode = null;
			pointerId = null;
			axis = null;
			swipeReplyReady = false;
			clearLongPress();
		};

		const commitGestureOnRelease = () => {
			if (!(activeNode instanceof HTMLElement)) {
				return;
			}

			const targetNode = activeNode;
			const shouldReply = swipeReplyReady;
			if (axis === 'x') {
				// Mouse: lepas setelah menggeser jangan sampai membuka gambar/canvas di bawah kursor.
				suppressClickUntil = Date.now() + 350;
			}
			clearActive();

			if (shouldReply) {
				setReplyTarget(targetNode);
			}
		};

		chatFeed.addEventListener('pointerdown', (event) => {
			if (event.button !== 0) {
				return;
			}
			clearActive();

			const node = event.target instanceof HTMLElement
				? event.target.closest('[data-message-id]')
				: null;
			if (!(node instanceof HTMLElement) || isSelecting()) {
				return;
			}

			if (event.target.closest('input, textarea, [data-voice-toggle], [data-voice-progress], .nc-msg-actions')) {
				return;
			}

			activeNode = node;
			pointerId = event.pointerId;
			axis = null;
			swipeReplyReady = false;
			startX = event.clientX;
			startY = event.clientY;
			node.style.transition = '';
			clearLongPress();
			longPressTimer = window.setTimeout(() => {
				if (activeNode && axis === null && !isSelecting()) {
					try { navigator.vibrate?.(15); } catch {}
					const pressedNode = activeNode;
					clearActive();
					suppressClickUntil = Date.now() + 400;
					openPressMenu(pressedNode);
				}
			}, LONG_PRESS_MS);
		});

		chatFeed.addEventListener('pointermove', (event) => {
			if (!activeNode || event.pointerId !== pointerId) return;

			const deltaX = event.clientX - startX;
			const deltaY = event.clientY - startY;

			if (axis === null) {
				if (Math.abs(deltaX) < AXIS_LOCK_PX && Math.abs(deltaY) < AXIS_LOCK_PX) {
					return;
				}
				clearLongPress();
				// Geser ke kanan yang lebih dominan = balas; selain itu biarkan feed menggulir.
				if (deltaX > 0 && Math.abs(deltaX) > Math.abs(deltaY)) {
					axis = 'x';
					try { activeNode.setPointerCapture(pointerId); } catch {}
					showSwipeIcon(activeNode);
				} else {
					clearActive();
					return;
				}
			}

			const dragX = Math.max(0, Math.min(SWIPE_MAX, deltaX));
			activeNode.style.transform = `translateX(${dragX}px)`;
			const progress = Math.min(1, dragX / SWIPE_THRESHOLD);
			if (swipeIcon) {
				swipeIcon.style.opacity = String(progress);
				swipeIcon.style.transform = `translateY(-50%) scale(${0.6 + 0.4 * progress})`;
			}

			const passesThreshold = dragX >= SWIPE_THRESHOLD;
			if (passesThreshold && !swipeReplyReady) {
				try { navigator.vibrate?.(10); } catch {}
			}
			swipeReplyReady = passesThreshold;
			swipeIcon?.classList.toggle('is-ready', passesThreshold);
			if (event.cancelable) event.preventDefault();
		}, { passive: false });

		chatFeed.addEventListener('pointerup', commitGestureOnRelease);
		chatFeed.addEventListener('pointercancel', clearActive);
		chatFeed.addEventListener('pointerleave', () => {
			// Setelah arah terkunci, pointer capture menjaga gestur walau kursor keluar feed.
			if (axis !== 'x') {
				clearActive();
			}
		});
		chatFeed.addEventListener('lostpointercapture', (event) => {
			// Sentuhan sudah ter-capture ke elemen anak; perpindahan capture ke node memicu event ini
			// dari anak itu, jadi hanya capture milik node sendiri yang membatalkan gestur.
			if (event.pointerId === pointerId && event.target === activeNode) {
				clearActive();
			}
		});

		// Tanpa ini, menggeser gambar/tautan dengan mouse memulai drag bawaan browser dan swipe batal.
		chatFeed.addEventListener('dragstart', (event) => {
			if (event.target instanceof Element && event.target.closest('[data-message-id]')) {
				event.preventDefault();
			}
		});

		chatFeed.addEventListener('click', (event) => {
			if (Date.now() < suppressClickUntil) {
				event.preventDefault();
				event.stopPropagation();
			}
		}, true);

		chatFeed.addEventListener('contextmenu', (event) => {
			const node = event.target instanceof HTMLElement
				? event.target.closest('[data-message-id]')
				: null;
			if (node) event.preventDefault();
		});

		chatFeed.addEventListener('dblclick', (event) => {
			const node = event.target instanceof HTMLElement
				? event.target.closest('[data-message-id]')
				: null;
			if (node instanceof HTMLElement && !isSelecting() && !event.target.closest('button')) {
				setReplyTarget(node);
			}
		});
	}
};
