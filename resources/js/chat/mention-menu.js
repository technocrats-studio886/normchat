// Menu saran @mention di composer.

import { chatForm, isDesktopKeyboard, mentionInput } from './composer.js';
import { authUserId } from './context.js';
import { escapeHtml } from './utils.js';

const mentionMenu = chatForm?.querySelector('[data-mention-menu]');

export const initMentionMenu = () => {
	if ((mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement) && mentionMenu instanceof HTMLDivElement) {
		let activeSuggestions = [];
		let allSuggestions = [];
		try {
			const parsed = JSON.parse(mentionMenu.dataset.mentionItems || '[]');
			allSuggestions = Array.isArray(parsed) ? parsed : [];
		} catch {
			allSuggestions = [];
		}
		allSuggestions = allSuggestions.filter((item) => {
			if (String(item?.type || '') === 'ai') {
				return true;
			}
			const userId = Number(item?.user_id || 0);
			return userId <= 0 || userId !== authUserId;
		});

		const hideMentionMenu = () => {
			mentionMenu.innerHTML = '';
			mentionMenu.classList.add('hidden');
			activeSuggestions = [];
		};

		const getMentionQuery = () => {
			const value = mentionInput.value;
			const caret = mentionInput.selectionStart ?? value.length;
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

		const renderMentionMenu = (items) => {
			activeSuggestions = items;
			if (!items.length) {
				hideMentionMenu();
				return;
			}

			mentionMenu.innerHTML = items
				.map((item, index) => {
					const tagTypeClass = item.type === 'ai' ? 'text-navy-600' : 'text-navy-600';
					return `<button type="button" data-mention-index="${index}" class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-slate-50"><span class="font-medium text-slate-700">${escapeHtml(item.label)}</span><span class="text-xs ${tagTypeClass}">${escapeHtml(item.insert)}</span></button>`;
				})
				.join('');

			mentionMenu.classList.remove('hidden');
		};

		const applyMention = (item) => {
			const mention = getMentionQuery();
			if (!mention) {
				return;
			}

			const value = mentionInput.value;
			const next = `${value.slice(0, mention.start)}${item.insert} ${value.slice(mention.end)}`;
			mentionInput.value = next;
			mentionInput.focus();
			hideMentionMenu();
		};

		if (mentionInput instanceof HTMLTextAreaElement) {
			mentionInput.addEventListener('keydown', (event) => {
				if (event.key !== 'Enter') {
					return;
				}

				if (event.shiftKey) {
					return;
				}

				if (!isDesktopKeyboard) {
					return;
				}

				event.preventDefault();
				chatForm?.requestSubmit();
			});
		}

		mentionInput.addEventListener('input', () => {
			const mention = getMentionQuery();
			if (!mention) {
				hideMentionMenu();
				return;
			}

			const filtered = allSuggestions.filter((item) => {
				if (!mention.query) {
					return true;
				}
				return String(item.label || '').toLowerCase().includes(mention.query)
					|| String(item.insert || '').toLowerCase().includes(mention.query);
			});

			renderMentionMenu(filtered.slice(0, 8));
		});

		mentionMenu.addEventListener('click', (event) => {
			const button = event.target.closest('[data-mention-index]');
			if (!button) {
				return;
			}

			const index = Number(button.getAttribute('data-mention-index'));
			const selected = activeSuggestions[index];
			if (selected) {
				applyMention(selected);
			}
		});

		document.addEventListener('click', (event) => {
			if (event.target === mentionInput || mentionMenu.contains(event.target)) {
				return;
			}

			hideMentionMenu();
		});
	}
};
