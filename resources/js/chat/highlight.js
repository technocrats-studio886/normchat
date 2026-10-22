// Highlight kutipan/pencarian dan lompat ke pesan.

import { chatFeed } from './context.js';
import { escapeHtml } from './utils.js';

export const ensureMentionToastStack = () => {
	let stack = document.querySelector('[data-mention-toast-stack]');
	if (stack) {
		return stack;
	}

	stack = document.createElement('div');
	stack.setAttribute('data-mention-toast-stack', '1');
	stack.className = 'pointer-events-none fixed right-3 top-3 z-[70] flex w-[calc(100%-1.5rem)] max-w-sm flex-col gap-2';
	document.body.appendChild(stack);
	return stack;
};

const clearHighlightMarks = (root, markerClass) => {
	if (!(root instanceof HTMLElement || root instanceof Document)) {
		return;
	}

	root.querySelectorAll(`mark.${markerClass}`).forEach((markNode) => {
		const parent = markNode.parentNode;
		if (!parent) {
			return;
		}
		parent.replaceChild(document.createTextNode(markNode.textContent || ''), markNode);
		parent.normalize();
	});
};

const getMessageHighlightTargets = (messageNode, includeSender = false) => {
	if (!(messageNode instanceof HTMLElement)) {
		return [];
	}

	const targets = Array.from(messageNode.querySelectorAll('.whitespace-pre-wrap'));
	if (targets.length === 0) {
		targets.push(...Array.from(messageNode.querySelectorAll('.ai-markdown p, .ai-markdown li, .ai-markdown td, .ai-markdown h1, .ai-markdown h2, .ai-markdown h3, .ai-markdown h4')));
	}
	if (includeSender) {
		const senderTarget = messageNode.querySelector('.nc-chat-sender');
		if (senderTarget instanceof HTMLElement) {
			targets.push(senderTarget);
		}
	}

	return targets;
};

const escapeRegex = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const applyInlineHighlight = (targetEl, phrase, { mode = 'quote', highlightAll = false } = {}) => {
	if (!(targetEl instanceof HTMLElement)) {
		return false;
	}

	const cleanedPhrase = String(phrase || '').trim();
	if (cleanedPhrase === '') {
		return false;
	}

	const sourceText = targetEl.textContent || '';
	if (sourceText === '') {
		return false;
	}

	const className = mode === 'search' ? 'nc-inline-highlight-search' : 'nc-inline-highlight-quote';
	const escapedPhrase = escapeRegex(cleanedPhrase).replace(/\s+/g, '\\s+');
	const regex = new RegExp(escapedPhrase, highlightAll ? 'gi' : 'i');
	let resultHtml = '';
	let lastIndex = 0;
	let found = false;
	let match = regex.exec(sourceText);

	while (match) {
		found = true;
		const matchText = match[0] || '';
		const start = Number(match.index || 0);
		const end = start + matchText.length;
		resultHtml += `${escapeHtml(sourceText.slice(lastIndex, start))}<mark class="${className}">${escapeHtml(matchText)}</mark>`;
		lastIndex = end;

		if (!highlightAll) {
			break;
		}

		match = regex.exec(sourceText);
	}

	if (!found) {
		return false;
	}

	resultHtml += escapeHtml(sourceText.slice(lastIndex));
	targetEl.innerHTML = resultHtml;
	return true;
};

const clearQuoteHighlightsInFeed = () => {
	clearHighlightMarks(document, 'nc-inline-highlight-quote');
};

const REPLY_JUMP_VISUAL_MS = 1400;
const REPLY_JUMP_SCROLL_SETTLE_MS = 220;
let clearQuoteHighlightTimer = null;
let pendingReplyVisualTimer = null;

const cancelPendingReplyVisual = () => {
	if (pendingReplyVisualTimer) {
		window.clearTimeout(pendingReplyVisualTimer);
		pendingReplyVisualTimer = null;
	}
};

const cancelScheduledQuoteHighlightClear = () => {
	if (clearQuoteHighlightTimer) {
		window.clearTimeout(clearQuoteHighlightTimer);
		clearQuoteHighlightTimer = null;
	}
};

const scheduleQuoteHighlightClear = (delayMs = REPLY_JUMP_VISUAL_MS) => {
	cancelScheduledQuoteHighlightClear();
	clearQuoteHighlightTimer = window.setTimeout(() => {
		clearQuoteHighlightsInFeed();
		clearQuoteHighlightTimer = null;
	}, delayMs);
};

export const clearSearchHighlightsInFeed = (feed) => {
	if (!(feed instanceof HTMLElement)) {
		return;
	}

	clearHighlightMarks(feed, 'nc-inline-highlight-search');
};

let activeJumpFocusNode = null;
let clearJumpFocusTimer = null;

const animateJumpFocus = (target) => {
	if (!(target instanceof HTMLElement)) {
		return;
	}

	const visualTarget = target.matches('[data-message-body="1"]')
		? target
		: (target.querySelector('[data-message-body="1"], .bubble-mine, .bubble-ai, .bubble-other, .nc-media-caption-card, [data-poll-card]')
		|| target);

	if (activeJumpFocusNode instanceof HTMLElement && activeJumpFocusNode !== visualTarget) {
		activeJumpFocusNode.classList.remove('nc-jump-focus');
	}

	if (clearJumpFocusTimer) {
		window.clearTimeout(clearJumpFocusTimer);
		clearJumpFocusTimer = null;
	}

	visualTarget.style.animation = 'none';
	visualTarget.classList.remove('nc-jump-focus');
	void visualTarget.offsetWidth;
	visualTarget.style.animation = '';
	visualTarget.classList.add('nc-jump-focus');
	activeJumpFocusNode = visualTarget;

	clearJumpFocusTimer = window.setTimeout(() => {
		visualTarget.classList.remove('nc-jump-focus');
		if (activeJumpFocusNode === visualTarget) {
			activeJumpFocusNode = null;
		}
		clearJumpFocusTimer = null;
	}, REPLY_JUMP_VISUAL_MS);
};

const highlightQuoteInMessage = (messageNode, quoteText) => {
	const cleanedQuote = String(quoteText || '').trim();
	if (cleanedQuote === '' || !(messageNode instanceof HTMLElement)) {
		return null;
	}

	const targets = getMessageHighlightTargets(messageNode, false);
	for (const target of targets) {
		if (applyInlineHighlight(target, cleanedQuote, { mode: 'quote', highlightAll: false })) {
			const markNode = target.querySelector('mark.nc-inline-highlight-quote');
			return markNode instanceof HTMLElement ? markNode : target;
		}
	}

	return null;
};

export const highlightSearchInMessage = (messageNode, queryText) => {
	const cleanedQuery = String(queryText || '').trim();
	if (cleanedQuery === '' || !(messageNode instanceof HTMLElement)) {
		return false;
	}

	let hasHighlight = false;
	const targets = getMessageHighlightTargets(messageNode, true);
	targets.forEach((target) => {
		if (applyInlineHighlight(target, cleanedQuery, { mode: 'search', highlightAll: true })) {
			hasHighlight = true;
		}
	});

	return hasHighlight;
};

export const highlightMessageInFeed = (messageId, replyQuote = '') => {
	if (!messageId) {
		return false;
	}

	const target = document.getElementById(`message-${messageId}`)
		|| chatFeed?.querySelector(`[data-message-id="${messageId}"]`);
	if (!(target instanceof HTMLElement)) {
		return false;
	}

	cancelPendingReplyVisual();
	cancelScheduledQuoteHighlightClear();
	target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
	pendingReplyVisualTimer = window.setTimeout(() => {
		clearQuoteHighlightsInFeed();
		const highlightedTextTarget = highlightQuoteInMessage(target, replyQuote);

		if (highlightedTextTarget instanceof HTMLElement) {
			scheduleQuoteHighlightClear(REPLY_JUMP_VISUAL_MS);
		} else {
			animateJumpFocus(target);
		}

		pendingReplyVisualTimer = null;
	}, REPLY_JUMP_SCROLL_SETTLE_MS);

	return true;
};
