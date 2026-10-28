// Menu saran @mention untuk input di pratinjau media (caption dan teks anotasi).

import { authUserId } from '../../context.js';
import { escapeHtml } from '../../utils.js';
import { chatForm } from '../elements.js';

/** Daftar mention dari composer utama, tanpa diri sendiri (AI tetap ada). */
export const loadPreviewMentionSuggestions = () => {
	let previewMentionSuggestions = [];
	try {
		const sourceMentionMenu = chatForm?.querySelector('[data-mention-menu]');
		const parsed = JSON.parse(sourceMentionMenu?.dataset.mentionItems || '[]');
		previewMentionSuggestions = Array.isArray(parsed) ? parsed : [];
	} catch {
		previewMentionSuggestions = [];
	}
	previewMentionSuggestions = previewMentionSuggestions.filter((item) => {
		if (String(item?.type || '') === 'ai') {
			return true;
		}
		const userId = Number(item?.user_id || 0);
		return userId <= 0 || userId !== authUserId;
	});

	return previewMentionSuggestions;
};

export const bindPreviewMentionMenu = (targetInput, targetMenu, suggestions, onApplied) => {
	if (!(targetInput instanceof HTMLInputElement) || !(targetMenu instanceof HTMLDivElement)) {
		return;
	}

	let activeSuggestions = [];
	const hideMenu = () => {
		targetMenu.classList.add('hidden');
		targetMenu.innerHTML = '';
		activeSuggestions = [];
	};

	const getMentionQuery = () => {
		const value = targetInput.value;
		const caret = targetInput.selectionStart ?? value.length;
		const beforeCaret = value.slice(0, caret);
		const atIndex = beforeCaret.lastIndexOf('@');
		if (atIndex < 0) {
			return null;
		}

		const token = beforeCaret.slice(atIndex + 1);
		if (/\s/.test(token)) {
			return null;
		}

		return {
			query: token.toLowerCase(),
			start: atIndex,
			end: caret,
		};
	};

	const applyMention = (item) => {
		const mention = getMentionQuery();
		if (!mention) {
			return;
		}

		const value = targetInput.value;
		targetInput.value = `${value.slice(0, mention.start)}${item.insert} ${value.slice(mention.end)}`;
		targetInput.focus();
		hideMenu();
		if (typeof onApplied === 'function') {
			onApplied(targetInput.value);
		}
	};

	targetInput.addEventListener('input', () => {
		const mention = getMentionQuery();
		if (!mention) {
			hideMenu();
			return;
		}

		const filtered = suggestions
			.filter((item) => {
				if (!mention.query) {
					return true;
				}
				return String(item?.label || '').toLowerCase().includes(mention.query)
					|| String(item?.insert || '').toLowerCase().includes(mention.query);
			})
			.slice(0, 8);

		activeSuggestions = filtered;
		if (!filtered.length) {
			hideMenu();
			return;
		}

		targetMenu.innerHTML = filtered
			.map((item, index) => {
				return `<button type="button" data-preview-mention-index="${index}" class="flex w-full items-center justify-between border-b border-white/10 px-3 py-2 text-left text-sm text-white last:border-0 hover:bg-white/10"><span class="font-medium">${escapeHtml(item.label)}</span><span class="text-xs text-navy-300">${escapeHtml(item.insert)}</span></button>`;
			})
			.join('');
		targetMenu.classList.remove('hidden');
	});

	targetMenu.addEventListener('click', (event) => {
		const button = event.target.closest('[data-preview-mention-index]');
		if (!button) {
			return;
		}

		const index = Number(button.getAttribute('data-preview-mention-index'));
		const selected = activeSuggestions[index];
		if (selected) {
			applyMention(selected);
		}
	});

	targetInput.addEventListener('blur', () => {
		window.setTimeout(hideMenu, 120);
	});
};
