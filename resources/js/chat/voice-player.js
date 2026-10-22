// Pemutar voice note.

import { formatAudioTime, pauseIconSvg, playIconSvg } from './attachments.js';

export const initVoicePlayers = (root = document) => {
	const container = root instanceof Element || root instanceof Document ? root : document;
	const players = container.querySelectorAll('[data-voice-player]:not([data-voice-ready="1"])');

	players.forEach((player) => {
		const audio = player.querySelector('[data-voice-audio]');
		const toggle = player.querySelector('[data-voice-toggle]');
		const progress = player.querySelector('[data-voice-progress]');
		const current = player.querySelector('[data-voice-current]');
		const duration = player.querySelector('[data-voice-duration]');
		const fallbackNote = player.querySelector('[data-voice-fallback-note]');
		const bars = Array.from(player.querySelectorAll('[data-voice-bar]'));

		if (!(audio instanceof HTMLAudioElement) || !(toggle instanceof HTMLButtonElement) || !(progress instanceof HTMLInputElement)) {
			return;
		}

		if (!audio.src) {
			const source = audio.querySelector('source');
			const sourceSrc = source?.getAttribute('src');
			if (sourceSrc) {
				audio.src = sourceSrc;
			}
		}

		const enableNativeFallback = () => {
			audio.classList.remove('hidden');
			audio.controls = true;
			audio.preload = 'auto';
			progress.disabled = true;
			progress.classList.add('opacity-50', 'cursor-not-allowed');
			toggle.disabled = true;
			toggle.classList.add('opacity-60', 'cursor-not-allowed');
			if (fallbackNote instanceof HTMLElement) {
				fallbackNote.classList.remove('hidden');
			}
		};

		const updateWave = (ratio) => {
			if (!bars.length) {
				return;
			}

			const activeIndex = Math.round(ratio * (bars.length - 1));
			bars.forEach((bar, index) => {
				bar.classList.toggle('opacity-95', index <= activeIndex);
				bar.classList.toggle('opacity-45', index > activeIndex);
				if (index > activeIndex) {
					bar.classList.remove('animate-pulse');
				}
			});
		};

		const applyDurationWidth = (seconds) => {
			const safeSeconds = Number.isFinite(seconds) && seconds > 0 ? seconds : 0;
			const normalizedSeconds = Math.min(safeSeconds, 180);
			let computedWidth = 205;

			if (normalizedSeconds > 0 && normalizedSeconds <= 3) {
				computedWidth = 170 + (normalizedSeconds * 12);
			} else if (normalizedSeconds <= 10) {
				computedWidth = 206 + ((normalizedSeconds - 3) * 7.5);
			} else if (normalizedSeconds <= 30) {
				computedWidth = 258 + ((normalizedSeconds - 10) * 2.7);
			} else {
				computedWidth = 312 + (Math.log10(normalizedSeconds - 29) * 9);
			}

			const targetWidth = Math.round(Math.max(180, Math.min(332, computedWidth)));

			player.style.width = `${targetWidth}px`;
			player.style.maxWidth = '100%';
			player.style.transition = 'width 180ms ease';
		};

		const updateUi = () => {
			const total = Number.isFinite(audio.duration) ? audio.duration : 0;
			const now = Number.isFinite(audio.currentTime) ? audio.currentTime : 0;
			const ratio = total > 0 ? Math.min(now / total, 1) : 0;
			applyDurationWidth(total);
			progress.value = String(Math.round(ratio * 1000));
			updateWave(ratio);
			if (current) {
				current.textContent = formatAudioTime(now);
			}
			if (duration) {
				duration.textContent = formatAudioTime(total);
			}
		};

		const setPlaying = (isPlaying) => {
			toggle.innerHTML = isPlaying ? pauseIconSvg : playIconSvg;
			toggle.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
			player.classList.toggle('ring-1', isPlaying);
			player.classList.toggle('ring-emerald-300/60', isPlaying);
			bars.forEach((bar, index) => {
				if (!isPlaying) {
					bar.classList.remove('animate-pulse');
					bar.style.animationDelay = '';
					return;
				}

				if (bar.classList.contains('opacity-95')) {
					bar.classList.add('animate-pulse');
					bar.style.animationDelay = `${(index % 6) * 0.08}s`;
				}
			});
		};

		audio.addEventListener('loadedmetadata', updateUi);
		audio.addEventListener('durationchange', updateUi);
		audio.addEventListener('timeupdate', updateUi);
		audio.addEventListener('error', enableNativeFallback);
		audio.addEventListener('ended', () => {
			audio.currentTime = 0;
			setPlaying(false);
			updateUi();
		});

		progress.addEventListener('input', () => {
			const total = Number.isFinite(audio.duration) ? audio.duration : 0;
			if (total <= 0) {
				return;
			}
			const seek = (Number(progress.value) / 1000) * total;
			audio.currentTime = seek;
			updateUi();
		});

		toggle.addEventListener('click', async () => {
			if (audio.paused) {
				document.querySelectorAll('[data-voice-audio]').forEach((other) => {
					if (other !== audio && other instanceof HTMLAudioElement) {
						other.pause();
					}
				});

				try {
					await audio.play();
					setPlaying(true);
				} catch {
					setPlaying(false);
					enableNativeFallback();
				}
				return;
			}

			audio.pause();
			setPlaying(false);
		});

		audio.addEventListener('pause', () => setPlaying(false));
		audio.addEventListener('play', () => setPlaying(true));

		player.setAttribute('data-voice-ready', '1');
		applyDurationWidth(audio.duration);
		updateUi();
	});
};
