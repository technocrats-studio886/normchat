// Markup pratinjau lampiran foto/video layar penuh.

export const mediaPreviewTemplate = `
	<div class="flex h-full flex-col pt-4">
		<div class="flex items-center justify-between px-3 text-white">
			<button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/14 text-white hover:bg-white/20" data-attachment-preview-media-clear aria-label="Batal lampiran">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
			</button>
			<p class="truncate px-2 text-center text-sm font-semibold text-white/90" data-attachment-preview-media-name></p>
			<div class="flex items-center gap-1.5" data-attachment-preview-tools>
				<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/14 text-lg font-semibold text-white hover:bg-white/20" data-preview-tool="sticker" data-preview-tool-button="1" aria-label="Tambah stiker"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z"/></svg></button>
				<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/14 text-sm font-semibold text-white hover:bg-white/20" data-preview-tool="text" data-preview-tool-button="1" aria-label="Tambah teks">Aa</button>
				<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/14 text-sm font-semibold text-white hover:bg-white/20" data-preview-tool="doodle" data-preview-tool-button="1" aria-label="Coretan"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg></button>
			</div>
		</div>
		<div class="mt-2 flex flex-col gap-2 px-3">
			<div class="hidden items-center justify-end gap-1 rounded-full bg-white/14 px-2 py-1" data-preview-pen-sizes>
				<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-pen-size="2">S</button>
				<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-pen-size="4">M</button>
				<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-pen-size="7">L</button>
				<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-pen-size="11">XL</button>
			</div>
			<div class="hidden rounded-2xl bg-white/10 px-2 py-2" data-preview-sticker-controls>
				<div class="flex gap-1 overflow-x-auto pb-1" data-preview-sticker-list>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😀">😀</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😂">😂</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😍">😍</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😎">😎</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="👍">👍</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🙏">🙏</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🎉">🎉</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🔥">🔥</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="❤️">❤️</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😭">😭</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😡">😡</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="✨">✨</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🤩">🤩</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🥳">🥳</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🤯">🤯</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="😴">😴</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🤖">🤖</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="💯">💯</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="✅">✅</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="❌">❌</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="💡">💡</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="💥">💥</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="⭐">⭐</button>
					<button type="button" class="min-w-8 rounded-full bg-white/18 px-2 py-1 text-lg" data-preview-sticker="🌈">🌈</button>
				</div>
				<div class="mt-1 flex items-center justify-end gap-1" data-preview-sticker-sizes>
					<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-sticker-size="30">S</button>
					<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-sticker-size="42">M</button>
					<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-sticker-size="56">L</button>
					<button type="button" class="h-7 w-7 rounded-full bg-white/25 text-[10px] font-semibold text-white" data-preview-sticker-size="72">XL</button>
				</div>
			</div>
		</div>
		<div class="mt-3 flex min-h-0 flex-1 items-center justify-center px-3">
			<div class="relative w-full overflow-hidden rounded-2xl bg-black/50" data-attachment-preview-stage>
				<img data-attachment-preview-media-img class="hidden max-h-[60vh] w-full rounded-2xl object-contain" alt="Preview media" />
				<video data-attachment-preview-media-video class="hidden max-h-[60vh] w-full rounded-2xl bg-black object-contain" controls playsinline preload="metadata"></video>
				<div data-attachment-preview-annotations class="pointer-events-none absolute inset-0 z-[4]"></div>
				<canvas data-attachment-preview-doodle class="pointer-events-none absolute inset-0 z-[5] hidden h-full w-full rounded-2xl touch-none"></canvas>
			</div>
		</div>
		<p class="mt-2 hidden px-3 text-center text-xs text-white/80" data-attachment-preview-media-caption></p>
		<div class="px-3 pb-[max(env(safe-area-inset-bottom),12px)] pt-2">
			<div class="flex items-center gap-2 rounded-2xl bg-white/12 px-3 py-2.5 backdrop-blur">
				<input type="text" class="min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-white/60" data-attachment-preview-caption-input placeholder="Tambah keterangan" />
				<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-navy-50 text-navy-950 hover:bg-navy-100" data-attachment-preview-caption-send aria-label="Kirim lampiran">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 3.105a.75.75 0 0 1 .818-.164l12 5.25a.75.75 0 0 1 0 1.374l-12 5.25A.75.75 0 0 1 2.9 14.18l1.2-4.18a.75.75 0 0 0 0-.4l-1.2-4.18a.75.75 0 0 1 .205-.815Z" /></svg>
				</button>
			</div>
			<div class="mt-2 hidden rounded-xl border border-white/20 bg-navy-950/90 shadow-lg" data-preview-caption-mention-menu></div>
		</div>
	</div>
	<div class="absolute inset-0 z-[75] hidden bg-navy-950/90 px-4 pb-6 pt-14" data-preview-text-editor>
		<div class="flex items-center justify-between text-white">
			<p class="text-sm font-semibold">Tambah teks</p>
			<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/12 text-white hover:bg-white/20" data-preview-text-close aria-label="Tutup editor teks">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
			</button>
		</div>
		<div class="mt-4 rounded-2xl bg-white/12 p-3">
			<input type="text" class="w-full rounded-xl border border-white/25 bg-black/20 px-3 py-3 text-base text-white outline-none placeholder:text-white/60" data-preview-text-input placeholder="Ketik teks" autocomplete="off" />
			<div class="mt-2 hidden rounded-xl border border-white/20 bg-navy-950/90 shadow-lg" data-preview-text-mention-menu></div>
		</div>
		<div class="mt-3 flex items-center justify-between gap-3">
			<div class="flex items-center gap-1" data-preview-text-size-controls>
				<button type="button" class="h-8 w-8 rounded-full bg-white/20 text-[11px] font-semibold text-white" data-preview-text-size="24">S</button>
				<button type="button" class="h-8 w-8 rounded-full bg-white/20 text-[11px] font-semibold text-white" data-preview-text-size="32">M</button>
				<button type="button" class="h-8 w-8 rounded-full bg-white/20 text-[11px] font-semibold text-white" data-preview-text-size="42">L</button>
			</div>
			<div class="flex items-center gap-1" data-preview-text-color-controls>
				<button type="button" class="h-7 w-7 rounded-full border border-white/40 bg-white" data-preview-text-color="#ffffff" aria-label="Warna putih"></button>
				<button type="button" class="h-7 w-7 rounded-full border border-white/40 bg-navy-900" data-preview-text-color="#1E2E43" aria-label="Warna navy"></button>
				<button type="button" class="h-7 w-7 rounded-full border border-white/40 bg-coral-500" data-preview-text-color="#FB6856" aria-label="Warna coral"></button>
				<button type="button" class="h-7 w-7 rounded-full border border-white/40 bg-coral-200" data-preview-text-color="#FECCC5" aria-label="Warna coral muda"></button>
			</div>
		</div>
		<div class="mt-4 flex items-center justify-end gap-2">
			<button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-white/80 hover:bg-white/10" data-preview-text-cancel>Batal</button>
			<button type="button" class="rounded-xl bg-navy-50 px-4 py-2 text-sm font-bold text-navy-950 hover:bg-navy-100" data-preview-text-apply>Terapkan</button>
		</div>
	</div>
`;
