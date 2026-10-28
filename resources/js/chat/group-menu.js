// Menu pengaturan cepat grup di halaman chat (polling, hapus grup).

import { chatMessagesBaseUrl, setPollComposerOpener } from './composer.js';
import { chatFeed, chatShell } from './context.js';
import { renderMermaidInNode } from './markdown.js';
import { buildMessageNode } from './messages.js';
import { refreshPollCardsFromFeed } from './polls.js';
import { markGroupRead, rememberLatestMessageId } from './read-state.js';
import { typingIndicator } from './realtime.js';
import { chatApiRequest, scrollChatToBottom } from './utils.js';
import { openDialog } from '../ui/dialog.js';
import { initVoicePlayers } from './voice-player.js';

const settingsMenu = document.querySelector('[data-settings-menu]');
const settingsOverlay = document.querySelector('[data-settings-overlay]');
const openSettingsButtons = Array.from(document.querySelectorAll('[data-open-settings]'));
const quickDeleteOverlay = document.querySelector('[data-delete-group-menu-overlay]');
const quickDeleteClose = document.querySelector('[data-close-delete-group-menu]');
const quickDeleteInput = document.querySelector('[data-delete-group-menu-confirm-input]');
const quickDeleteSubmit = document.querySelector('[data-delete-group-menu-submit]');

export const initGroupMenu = () => {
	if (settingsMenu && settingsOverlay && openSettingsButtons.length > 0) {
		let menuOpen = false;
		const applySettingsMenuTheme = () => {
			if (!(settingsMenu instanceof HTMLElement)) {
				return;
			}

			const styleSource = chatShell instanceof HTMLElement ? chatShell : document.documentElement;
			const primary = getComputedStyle(styleSource).getPropertyValue('--nc-primary').trim() || '#344458';
			settingsMenu.style.background = primary;
			settingsMenu.style.boxShadow = '0 16px 40px rgba(18, 28, 41, 0.35)';
		};

		const formatPollMessage = (question, options) => {
			const lines = [`📊 POLL: ${question}`, ''];
			options.forEach((option, index) => {
				lines.push(`${index + 1}. ${option}`);
			});
			lines.push('', 'Balas dengan angka pilihan kamu.');
			return lines.join('\n');
		};

		const openPollComposer = () => {
			if (!chatMessagesBaseUrl) {
				showMenuToast('Form polling hanya tersedia di halaman chat');
				return;
			}

			const overlay = document.createElement('div');
			overlay.className = 'fixed inset-0 z-[90] flex items-center justify-center bg-navy-950/45 px-5';
			overlay.innerHTML = `
			<div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
				<p class="text-sm font-semibold text-slate-900">Buat Polling</p>
				<p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">Ajak anggota memilih.</p>
				<div class="mt-3 space-y-2">
					<input type="text" class="input-field" data-poll-question placeholder="Pertanyaan" autocomplete="off" />
					<input type="text" class="input-field" data-poll-option="0" placeholder="Pilihan 1" autocomplete="off" />
					<input type="text" class="input-field" data-poll-option="1" placeholder="Pilihan 2" autocomplete="off" />
					<input type="text" class="input-field" data-poll-option="2" placeholder="Pilihan 3 (opsional)" autocomplete="off" />
					<input type="text" class="input-field" data-poll-option="3" placeholder="Pilihan 4 (opsional)" autocomplete="off" />
				</div>
				<div class="mt-4 flex items-center justify-end gap-2">
					<button type="button" data-poll-cancel class="rounded-xl px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
					<button type="button" data-poll-send class="rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white hover:bg-primary-hover">Kirim</button>
				</div>
			</div>
		`;

			const questionInput = overlay.querySelector('[data-poll-question]');
			const optionInputs = Array.from(overlay.querySelectorAll('[data-poll-option]'));
			const cancelBtn = overlay.querySelector('[data-poll-cancel]');
			const sendBtn = overlay.querySelector('[data-poll-send]');

			const close = () => {
				overlay.remove();
			};

			overlay.addEventListener('click', (event) => {
				if (event.target === overlay) {
					close();
				}
			});
			cancelBtn?.addEventListener('click', close);

			sendBtn?.addEventListener('click', async () => {
				const question = String(questionInput instanceof HTMLInputElement ? questionInput.value : '').trim();
				const options = optionInputs
					.map((input) => String(input instanceof HTMLInputElement ? input.value : '').trim())
					.filter((item) => item !== '');

				if (!question) {
					showMenuToast('Pertanyaan polling belum diisi');
					questionInput?.focus();
					return;
				}

				if (options.length < 2) {
					showMenuToast('Minimal 2 opsi polling');
					optionInputs[0]?.focus();
					return;
				}

				sendBtn.disabled = true;
				sendBtn.classList.add('opacity-70', 'cursor-wait');
				try {
					const result = await chatApiRequest(chatMessagesBaseUrl, {
						method: 'POST',
						body: {
							content: formatPollMessage(question, options),
						},
					});

					if (result?.message && chatFeed) {
						const duplicate = chatFeed.querySelector(`[data-message-id="${result.message.id}"]`);
						if (!duplicate) {
							const node = buildMessageNode(result.message);
							node.setAttribute('data-message-id', String(result.message.id));
							node.id = `message-${result.message.id}`;
							if (typingIndicator && typingIndicator.parentNode === chatFeed) {
								chatFeed.insertBefore(node, typingIndicator);
							} else {
								chatFeed.appendChild(node);
							}
							initVoicePlayers(node);
							renderMermaidInNode(node);
							rememberLatestMessageId(result.message.id);
							scrollChatToBottom('auto');
							markGroupRead(Number(result.message.id || 0));
							refreshPollCardsFromFeed();
						}
					}

					showMenuToast('Poll berhasil dikirim');
					close();
				} catch {
					showMenuToast('Gagal mengirim poll');
					sendBtn.disabled = false;
					sendBtn.classList.remove('opacity-70', 'cursor-wait');
				}
			});

			document.body.appendChild(overlay);
			if (questionInput instanceof HTMLInputElement) {
				questionInput.focus();
			}
		};

		setPollComposerOpener(openPollComposer);

		const showMenuToast = (message) => {
			if (!message) {
				return;
			}

			const toast = document.createElement('div');
			toast.className = 'fixed left-1/2 bottom-24 z-[75] -translate-x-1/2 rounded-full bg-navy-950/95 px-3 py-1.5 text-xs font-medium text-white shadow-lg';
			toast.textContent = message;
			document.body.appendChild(toast);
			window.setTimeout(() => {
				if (toast.isConnected) {
					toast.remove();
				}
			}, 1800);
		};

		const openMenu = () => {
			menuOpen = true;
			applySettingsMenuTheme();
			document.body.style.overflow = '';
			settingsMenu.classList.remove('hidden');
		};

		const closeMenu = () => {
			menuOpen = false;
			document.body.style.overflow = '';
			settingsMenu.classList.add('hidden');
		};

		document.addEventListener('click', (event) => {
			if (!menuOpen) return;
			if (settingsMenu.contains(event.target)) return;
			if (event.target.closest('[data-open-settings]')) return;
			closeMenu();
		}, true);

		openSettingsButtons.forEach((openSettingsButton) => {
			openSettingsButton.addEventListener('click', (event) => {
				event.preventDefault();
				if (menuOpen) {
					closeMenu();
					return;
				}
				openMenu();
			});
		});

		settingsOverlay.addEventListener('click', closeMenu);
		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && menuOpen) {
				closeMenu();
			}
		});

		settingsMenu.addEventListener('click', async (event) => {
			const target = event.target.closest('button,a');
			if (!target) {
				return;
			}

			if (target.matches('[data-menu-clear-history]')) {
				event.preventDefault();
				closeMenu();
				const choice = await openDialog({
					title: 'Bersihkan chat?',
					message: 'Semua pesan di grup ini dihapus dari tampilanmu, di semua perangkatmu. Anggota lain tetap melihatnya.',
					choices: [{ label: 'Bersihkan', value: 'clear', tone: 'danger' }],
				});
				if (choice) {
					try {
						await chatApiRequest(`${chatMessagesBaseUrl}/hide`, { method: 'POST', body: { all: true } });
						chatFeed?.querySelectorAll('.nc-msg').forEach((node) => node.remove());
						showMenuToast('Chat dibersihkan');
					} catch {
						showMenuToast('Gagal membersihkan chat');
					}
				}
				return;
			}

			if (target.matches('[data-open-search]')) {
				closeMenu();
				return;
			}

			if (target.matches('[data-open-delete-group-menu]')) {
				event.preventDefault();
				closeMenu();
				if (quickDeleteOverlay instanceof HTMLElement) {
					quickDeleteOverlay.classList.remove('hidden');
					quickDeleteOverlay.classList.add('flex');
					if (quickDeleteInput instanceof HTMLInputElement) {
						quickDeleteInput.value = '';
						quickDeleteInput.focus();
					}
					if (quickDeleteSubmit instanceof HTMLButtonElement) {
						quickDeleteSubmit.disabled = true;
					}
				}
				return;
			}

			closeMenu();
		});

		if (
			quickDeleteOverlay instanceof HTMLElement
			&& quickDeleteClose instanceof HTMLElement
			&& quickDeleteInput instanceof HTMLInputElement
			&& quickDeleteSubmit instanceof HTMLButtonElement
		) {
			const expected = String(document.querySelector('[data-chat-shell]')?.getAttribute('data-group-name') || '').trim();
			const hideDeleteOverlay = () => {
				quickDeleteOverlay.classList.add('hidden');
				quickDeleteOverlay.classList.remove('flex');
			};
			quickDeleteClose.addEventListener('click', hideDeleteOverlay);
			quickDeleteOverlay.addEventListener('click', (event) => {
				if (event.target === quickDeleteOverlay) {
					hideDeleteOverlay();
				}
			});
			quickDeleteInput.addEventListener('input', () => {
				quickDeleteSubmit.disabled = quickDeleteInput.value.trim() !== expected;
			});
		}
	}
};
