// Tombol salin ID grup dan bagikan tautan grup.

export const initShareButtons = () => {
	document.querySelectorAll('[data-copy-share-id]').forEach((button) => {
		button.addEventListener('click', async () => {
			const shareId = button.getAttribute('data-copy-share-id') || '';
			if (!shareId || !navigator.clipboard?.writeText) {
				return;
			}

			try {
				await navigator.clipboard.writeText(shareId);
				const prev = button.textContent;
				button.textContent = 'Tersalin';
				window.setTimeout(() => {
					button.textContent = prev;
				}, 1200);
			} catch {
				// Ignore clipboard failures silently on restricted browser contexts.
			}
		});
	});

	document.querySelectorAll('[data-share-group-url]').forEach((button) => {
		button.addEventListener('click', async () => {
			const url = button.getAttribute('data-share-group-url') || '';
			const groupName = button.getAttribute('data-share-group-name') || 'Normi Chat';
			if (!url) {
				return;
			}

			const setButtonLabelTemporarily = (label, timeout = 1300) => {
				const prev = button.textContent;
				button.textContent = label;
				window.setTimeout(() => {
					button.textContent = prev;
				}, timeout);
			};

			const sharePayload = {
				title: `Gabung ${groupName}`,
				text: `Yuk gabung ke grup ${groupName} di Normi Chat`,
				url,
			};

			const openFallbackShareMenu = () => {
				const message = `Yuk gabung ke grup ${groupName} di Normi Chat`;
				const encodedMessage = encodeURIComponent(`${message}\n${url}`);
				const encodedTextOnly = encodeURIComponent(message);
				const encodedUrl = encodeURIComponent(url);

				const backdrop = document.createElement('div');
				backdrop.className = 'fixed inset-0 z-[90] bg-slate-900/40 p-4';

				const panel = document.createElement('div');
				panel.className = 'absolute bottom-4 left-4 right-4 rounded-2xl bg-white p-3 shadow-2xl';
				panel.innerHTML = `
				<p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Bagikan Grup</p>
				<div class="grid grid-cols-2 gap-2 text-sm">
					<a class="rounded-xl border border-slate-200 px-3 py-2 font-semibold text-slate-700" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=${encodedMessage}">WhatsApp</a>
					<a class="rounded-xl border border-slate-200 px-3 py-2 font-semibold text-slate-700" target="_blank" rel="noopener noreferrer" href="https://t.me/share/url?url=${encodedUrl}&text=${encodedTextOnly}">Telegram</a>
					<a class="rounded-xl border border-slate-200 px-3 py-2 font-semibold text-slate-700" target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?text=${encodedMessage}">X</a>
					<a class="rounded-xl border border-slate-200 px-3 py-2 font-semibold text-slate-700" target="_blank" rel="noopener noreferrer" href="mailto:?subject=${encodeURIComponent(`Join #${groupName}`)}&body=${encodedMessage}">Email</a>
				</div>
				<div class="mt-2 grid grid-cols-2 gap-2 text-sm">
					<button type="button" data-share-copy-link="1" class="rounded-xl bg-navy-600 px-3 py-2 font-semibold text-white">Salin Link</button>
					<button type="button" data-share-close-menu="1" class="rounded-xl bg-slate-100 px-3 py-2 font-semibold text-slate-700">Tutup</button>
				</div>
			`;

				backdrop.appendChild(panel);
				document.body.appendChild(backdrop);

				const closeMenu = () => {
					if (backdrop.isConnected) {
						backdrop.remove();
					}
				};

				backdrop.addEventListener('click', (event) => {
					if (event.target === backdrop) {
						closeMenu();
					}
				});

				const closeButton = panel.querySelector('[data-share-close-menu]');
				if (closeButton) {
					closeButton.addEventListener('click', closeMenu);
				}

				const copyButton = panel.querySelector('[data-share-copy-link]');
				if (copyButton) {
					copyButton.addEventListener('click', async () => {
						if (navigator.clipboard?.writeText) {
							try {
								await navigator.clipboard.writeText(url);
								setButtonLabelTemporarily('Link tersalin', 1600);
							} catch {
								setButtonLabelTemporarily('Gagal salin link', 1600);
							}
						} else {
							window.prompt('Salin link grup ini:', url);
						}
						closeMenu();
					});
				}
			};

			if (navigator.share) {
				try {
					await navigator.share(sharePayload);
					return;
				} catch (error) {
					// User cancelled share sheet.
					if (error && error.name === 'AbortError') {
						return;
					}
				}
			}

			openFallbackShareMenu();
		});
	});
};
