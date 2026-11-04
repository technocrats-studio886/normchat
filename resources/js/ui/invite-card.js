// Kartu ajakan grup gratis di ruang chat (chat/show): tampil sampai pemilik menutupnya di perangkat ini.

const keyFor = (groupId) => `nc-invite-card-closed:${groupId}`;

export const initInviteCard = () => {
	const card = document.querySelector('[data-invite-card]');
	if (!card) {
		return;
	}

	const key = keyFor(card.getAttribute('data-invite-card'));
	let closed = false;
	try {
		closed = localStorage.getItem(key) === '1';
	} catch {
		// Penyimpanan bisa diblokir browser: kartu tetap tampil.
	}
	if (closed) {
		card.remove();
		return;
	}
	card.classList.remove('hidden');

	card.querySelector('[data-invite-card-close]')?.addEventListener('click', () => {
		try {
			localStorage.setItem(key, '1');
		} catch {
			// Tanpa penyimpanan, kartu muncul lagi saat halaman dibuka ulang.
		}
		card.remove();
	});
};
