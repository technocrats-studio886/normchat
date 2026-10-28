// Menu saran @mention di composer. Saran teratas selalu terpilih; ↑/↓ memindah pilihan,
// Enter/Tab memasukkan mention, Esc menutup. "@a" hanya menampilkan nama depan berawalan "a".

import { chatForm, isDesktopKeyboard, mentionInput } from './composer.js';
import { authUserId } from './context.js';
import { escapeHtml } from './utils.js';

const mentionMenu = chatForm?.querySelector('[data-mention-menu]');

export const initMentionMenu = () => {
	if ((mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement) && mentionMenu instanceof HTMLDivElement) {
		let activeSuggestions = [];
		let activeIndex = 0;
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

		const isMenuOpen = () => activeSuggestions.length > 0;

		const hideMentionMenu = () => {
			mentionMenu.innerHTML = '';
			mentionMenu.classList.add('hidden');
			activeSuggestions = [];
		};

		const highlight = (index) => {
			activeIndex = (index + activeSuggestions.length) % activeSuggestions.length;
			mentionMenu.querySelectorAll('[data-mention-index]').forEach((button) => {
				const active = Number(button.getAttribute('data-mention-index')) === activeIndex;
				button.classList.toggle('is-active', active);
				button.setAttribute('aria-selected', active ? 'true' : 'false');
				if (active) {
					button.scrollIntoView({ block: 'nearest' });
				}
			});
		};

		// Nama depan (kata pertama) berawalan query; Normi AI juga lewat handle-nya (@normi).
		const matches = (item, query) => String(item.label || '').toLowerCase().split(/\s+/)[0].startsWith(query)
			|| (item.type === 'ai' && String(item.insert || '').toLowerCase().startsWith(`@${query}`));

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
				.map((item, index) => `<button type="button" role="option" data-mention-index="${index}" class="nc-mention-option flex w-full items-center justify-between border-b border-slate-100 px-3 py-2 text-left text-sm last:border-0"><span class="font-medium text-slate-700">${escapeHtml(item.label)}</span><span class="text-xs text-primary-ink">${escapeHtml(item.insert)}</span></button>`)
				.join('');

			mentionMenu.classList.remove('hidden');
			highlight(0);
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
				if (isMenuOpen()) {
					if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
						event.preventDefault();
						highlight(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
						return;
					}
					if ((event.key === 'Enter' && !event.shiftKey) || event.key === 'Tab') {
						event.preventDefault();
						applyMention(activeSuggestions[activeIndex]);
						return;
					}
					if (event.key === 'Escape') {
						event.preventDefault();
						hideMentionMenu();
						return;
					}
				}

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

			const filtered = mention.query ? allSuggestions.filter((item) => matches(item, mention.query)) : allSuggestions;

			renderMentionMenu(filtered.slice(0, 8));
		});

		mentionMenu.addEventListener('mousemove', (event) => {
			const button = event.target.closest('[data-mention-index]');
			if (button) {
				highlight(Number(button.getAttribute('data-mention-index')));
			}
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
