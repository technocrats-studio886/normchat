{{--
    Gerak halaman pemasaran dan halaman masuk (docs/rancangan-copy-marketing.md, bagian gerak).
    - [data-live-demo]: demo chat yang diputar langsung di browser. Semua pesan dirender server, jadi tanpa JS
      atau dengan prefers-reduced-motion percakapan tampil lengkap. Langkah: [data-demo-step] tampil berurutan,
      [data-demo-mention] disorot, [data-demo-typing] tampil sebentar, lalu teks [data-demo-answer] mengalir
      kata demi kata. Berhenti selama demo tidak terlihat atau tab disembunyikan, lalu berulang.
    - [data-reveal-scope]: judul, paragraf, dan kartu di section selain hero muncul (fade up) saat digulir.
    - [data-cta]: tombol menampilkan status memuat saat diklik.
--}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
(() => {
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// ── Tombol: status memuat sampai halaman berikutnya terbuka (dikembalikan bila kembali lewat riwayat).
	document.querySelectorAll('[data-cta]').forEach((link) => {
		link.addEventListener('click', () => {
			link.classList.add('is-loading');
			link.setAttribute('aria-busy', 'true');
		});
	});
	window.addEventListener('pageshow', () => {
		document.querySelectorAll('[data-cta].is-loading').forEach((link) => {
			link.classList.remove('is-loading');
			link.removeAttribute('aria-busy');
		});
	});

	if (reduce || !('IntersectionObserver' in window)) {
		return;
	}

	// ── Scroll reveal: bertahap di antara saudara, satu kali per elemen.
	const scope = document.querySelector('[data-reveal-scope]');
	if (scope) {
		const selector = 'h2, .prose, .grid > *, .facts > *, .levels > *, .table-wrap, .table-note, .faq, .btn-row, .split > *';
		const found = [...scope.querySelectorAll(`section:not(.hero) :is(${selector})`)];
		// Elemen di dalam target lain ikut bergerak bersama induknya.
		const targets = found.filter((el) => !found.some((other) => other !== el && other.contains(el)));
		document.documentElement.classList.add('reveal-on');
		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
					observer.unobserve(entry.target);
				}
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
		targets.forEach((el) => {
			const siblings = [...el.parentElement.children].filter((child) => targets.includes(child));
			el.style.setProperty('--reveal-delay', `${Math.min(siblings.indexOf(el), 5) * 80}ms`);
			el.classList.add('reveal');
			if (el.matches('.table-wrap, .levels > *')) {
				el.classList.add('reveal-scale');
			}
			// Setelah muncul, transisi milik elemen (mis. hover kartu) tidak lagi tertunda.
			const done = (event) => {
				if (event.target === el) {
					el.classList.remove('reveal', 'reveal-scale', 'is-visible');
					el.removeEventListener('transitionend', done);
				}
			};
			el.addEventListener('transitionend', done);
			observer.observe(el);
		});
	}

	// ── Demo chat hidup.
	document.querySelectorAll('[data-live-demo]').forEach((demo) => {
		const steps = [...demo.querySelectorAll('[data-demo-step]')];
		const typing = demo.querySelector('[data-demo-typing]');
		const answer = demo.querySelector('[data-demo-answer]');
		if (!steps.length || !answer) {
			return;
		}
		const words = answer.textContent.trim().split(/\s+/);
		const body = demo.querySelector('[data-demo-body]') || demo;

		// Tinggi tetap seukuran percakapan lengkap, supaya halaman tidak bergeser saat pesan muncul.
		body.style.minHeight = `${body.offsetHeight}px`;
		demo.classList.add('is-live');

		let inView = false;
		new IntersectionObserver(([entry]) => { inView = entry.isIntersecting; }, { threshold: 0.2 }).observe(demo);
		const active = () => inView && !document.hidden && demo.offsetParent !== null;
		const wait = (ms) => new Promise((resolve) => {
			const tick = () => (active() ? setTimeout(resolve, ms) : setTimeout(tick, 400));
			tick();
		});
		const show = (el) => {
			el.classList.remove('is-shown');
			void el.offsetWidth;
			el.classList.add('is-shown');
		};

		const play = async () => {
			for (;;) {
				steps.forEach((step) => step.classList.remove('is-shown', 'is-streaming'));
				typing?.classList.remove('is-shown');
				answer.textContent = '';
				await wait(600);

				for (const step of steps) {
					if (step.contains(answer)) {
						if (typing) {
							show(typing);
							await wait(1400);
							typing.classList.remove('is-shown');
						}
						step.classList.add('is-streaming');
						show(step);
						for (let i = 1; i <= words.length; i++) {
							answer.textContent = words.slice(0, i).join(' ');
							await wait(70);
						}
						step.classList.remove('is-streaming');
						continue;
					}
					show(step);
					const mention = step.querySelector('[data-demo-mention]');
					if (mention) {
						mention.classList.remove('is-flash');
						void mention.offsetWidth;
						mention.classList.add('is-flash');
					}
					await wait(Number(step.dataset.demoPause || 1100));
				}

				await wait(3500);
			}
		};
		play();
	});
})();
</script>
