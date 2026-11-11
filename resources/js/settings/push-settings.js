// Akun > Notifikasi: status dan tombol notifikasi push perangkat ini (resources/js/push.js).

import { currentSubscription, disablePush, enablePush, pushStatus, sendTestPush } from '../push.js';

const TEXT = {
	unsupported: 'Browser ini belum mendukung notifikasi push.',
	'ios-install': 'Di iPhone/iPad, buka Normi Chat dari Layar Utama dulu (Safari › Bagikan › Tambahkan ke Layar Utama).',
	denied: 'Diblokir browser. Izinkan notifikasi lewat pengaturan situs, lalu muat ulang halaman.',
	default: 'Mati. Nyalakan untuk menerima pesan baru saat Normi Chat tertutup.',
	off: 'Mati di perangkat ini.',
	on: 'Menyala di perangkat ini.',
};

export const initPushSettings = () => {
	const box = document.querySelector('[data-push-settings]');
	if (!box) return;

	const statusText = box.querySelector('[data-push-status]');
	const onButton = box.querySelector('[data-push-on]');
	const offButton = box.querySelector('[data-push-off]');
	const testRow = box.querySelector('[data-push-test-row]');
	const testStatus = box.querySelector('[data-push-test-status]');

	const render = async () => {
		const status = pushStatus();
		const subscribed = status === 'granted' && Boolean(await currentSubscription().catch(() => null));
		const key = subscribed ? 'on' : (status === 'granted' ? 'off' : status);
		statusText.textContent = TEXT[key] || TEXT.unsupported;
		onButton.classList.toggle('hidden', !['default', 'off'].includes(key));
		offButton.classList.toggle('hidden', key !== 'on');
		testRow.classList.toggle('hidden', key !== 'on');
	};

	onButton.addEventListener('click', async () => {
		onButton.disabled = true;
		statusText.textContent = 'Menyalakan…';
		await enablePush().catch(() => undefined);
		onButton.disabled = false;
		render();
	});
	offButton.addEventListener('click', async () => {
		offButton.disabled = true;
		await disablePush().catch(() => undefined);
		offButton.disabled = false;
		render();
	});
	box.querySelector('[data-push-test]')?.addEventListener('click', async () => {
		testStatus.textContent = 'Mengirim…';
		const ok = await sendTestPush().catch(() => false);
		testStatus.textContent = ok ? 'Terkirim. Notifikasinya akan muncul sebentar lagi.' : 'Gagal mengirim. Coba matikan lalu nyalakan lagi.';
	});

	render();
};
