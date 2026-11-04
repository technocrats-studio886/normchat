// Bunyi pendek untuk pesan masuk / disebut (Akun > Notifikasi > Bunyi pesan masuk). Tanpa berkas audio:
// dua nada singkat lewat Web Audio. Browser bisa menolak sebelum ada interaksi; kegagalan diabaikan.

const enabled = () => document.querySelector('meta[name="auth-user-notify-sound"]')?.getAttribute('content') === '1';
let context = null;
let lastPlayed = 0;

export const playNotifySound = () => {
	const now = Date.now();
	if (!enabled() || now - lastPlayed < 1500) {
		return;
	}
	lastPlayed = now;

	try {
		context ??= new (window.AudioContext || window.webkitAudioContext)();
		[[880, 0], [1320, 0.11]].forEach(([frequency, offset]) => {
			const oscillator = context.createOscillator();
			const gain = context.createGain();
			const start = context.currentTime + offset;
			oscillator.frequency.value = frequency;
			gain.gain.setValueAtTime(0.0001, start);
			gain.gain.exponentialRampToValueAtTime(0.08, start + 0.02);
			gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
			oscillator.connect(gain).connect(context.destination);
			oscillator.start(start);
			oscillator.stop(start + 0.18);
		});
	} catch {
		// Audio tidak tersedia di perangkat ini.
	}
};
