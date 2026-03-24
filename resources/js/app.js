import './bootstrap';

const chatFeed = document.querySelector('[data-chat-feed]');
const groupId = chatFeed?.getAttribute('data-chat-group-id');
const authUserId = Number(chatFeed?.getAttribute('data-auth-user-id') || 0);
const pendingMessagesByLocalId = new Map();
let pendingLocalMessageCounter = 0;

const escapeHtml = (text) => String(text)
	.replace(/&/g, '&amp;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#039;');

const escapeAttr = (text) => String(text)
	.replace(/&/g, '&amp;')
	.replace(/"/g, '&quot;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;');

const formatAudioTime = (seconds) => {
	if (!Number.isFinite(seconds) || seconds < 0) {
		return '0:00';
	}

	const whole = Math.floor(seconds);
	const min = Math.floor(whole / 60);
	const sec = String(whole % 60).padStart(2, '0');
	return `${min}:${sec}`;
};

const playIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.5 4.75a.75.75 0 0 1 1.165-.623l7 4.75a.75.75 0 0 1 0 1.246l-7 4.75A.75.75 0 0 1 6.5 14.25v-9.5Z" /></svg>';
const pauseIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6 4.75A.75.75 0 0 1 6.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Zm5 0A.75.75 0 0 1 11.75 4h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75Z" /></svg>';
const voiceWaveHeights = ['h-2', 'h-3', 'h-2', 'h-4', 'h-2', 'h-3', 'h-4', 'h-2', 'h-3', 'h-2', 'h-4', 'h-2'];

const buildVoicePlayerMarkup = (safeUrl, sourceType, palette) => {
	const isMine = palette === 'blue';
	const playerClass = isMine
		? 'border-[#0b4a3d] bg-[#0f5f4e] text-emerald-50'
		: (palette === 'emerald' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-100 text-slate-800');
	const buttonClass = isMine ? 'bg-emerald-300 text-emerald-950' : 'bg-emerald-500 text-white';
	const sliderClass = isMine ? 'accent-emerald-300' : 'accent-emerald-500';
	const timerClass = isMine ? 'text-emerald-100' : 'text-slate-500';
	const waveClass = isMine ? 'bg-emerald-200/85' : 'bg-emerald-500/70';
	const waveHtml = voiceWaveHeights
		.map((heightClass) => `<span class="${heightClass} w-0.5 rounded-full ${waveClass} opacity-45" data-voice-bar></span>`)
		.join('');

	return `
		<div class="mb-2 inline-block w-55 max-w-full rounded-2xl border px-3 py-2 transition ${playerClass}" data-voice-player="1">
			<audio preload="metadata" class="hidden" data-voice-audio>
				<source src="${safeUrl}" type="${sourceType}">
			</audio>
			<div class="flex items-center gap-2">
				<button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${buttonClass}" data-voice-toggle aria-label="Play voice note" aria-pressed="false">
					${playIconSvg}
				</button>
				<div class="min-w-0 flex-1">
					<div class="mb-1 flex h-4 items-end gap-0.5" data-voice-wave>${waveHtml}</div>
					<input type="range" min="0" max="1000" value="0" class="h-1 w-full cursor-pointer ${sliderClass}" data-voice-progress>
					<div class="mt-1 flex items-center justify-between text-[11px] ${timerClass}">
						<span data-voice-current>0:00</span>
						<span data-voice-duration>0:00</span>
					</div>
				</div>
			</div>
			<p class="mt-1 hidden text-[11px] ${timerClass}" data-voice-fallback-note>Audio mode kompatibilitas aktif. Tap player native di bawah.</p>
		</div>
	`;
};

const renderAttachment = (message, palette = 'slate') => {
	const url = message?.attachment_url || '';
	if (!url) {
		return '';
	}

	const mime = String(message?.attachment_mime || '').toLowerCase();
	const type = String(message?.message_type || 'text').toLowerCase();
	const safeUrl = escapeAttr(url);

	if (type === 'image' || mime.startsWith('image/')) {
		const border = palette === 'blue' ? 'border-blue-200 bg-blue-50' : (palette === 'emerald' ? 'border-emerald-100 bg-emerald-50' : 'border-slate-200 bg-white');
		return `
			<a href="${safeUrl}" target="_blank" rel="noopener" class="mb-2 block overflow-hidden rounded-2xl border ${border}">
				<img src="${safeUrl}" alt="Gambar" class="h-auto max-h-64 w-full object-cover" />
			</a>
		`;
	}

	if (type === 'voice' || mime.startsWith('audio/') || mime === 'video/webm') {
		const sourceType = escapeAttr(mime === 'video/webm' ? 'audio/webm' : (message?.attachment_mime || 'audio/webm'));
		return buildVoicePlayerMarkup(safeUrl, sourceType, palette);
	}

	const attachmentName = escapeHtml(message?.attachment_original_name || 'Lampiran');
	return `
		<a href="${safeUrl}" target="_blank" rel="noopener" class="mb-2 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-blue-600 underline">${attachmentName}</a>
	`;
};

const buildMessageNode = (message) => {
	const createdAt = message?.created_at ? new Date(message.created_at) : null;
	const timeText = createdAt && !Number.isNaN(createdAt.valueOf())
		? createdAt.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
		: '';

	const isAi = message?.sender_type === 'ai';
	const isMine = message?.sender_type === 'user' && Number(message?.sender_id || 0) === authUserId;
	const content = escapeHtml(message?.content || '');

	const wrapper = document.createElement('div');
	const isPending = message?.is_pending === true;

	if (isMine) {
		const attachmentBlock = renderAttachment(message, 'blue');
		const textBlock = content
			? `<div class="rounded-2xl rounded-tr-sm bg-[#2563EB] px-4 py-2.5 text-sm text-white">${content}</div>`
			: '';
		const labelText = isPending
			? `You • <span class="inline-flex items-center gap-1"><span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span>Mengirim...</span>`
			: `You${timeText ? ` • ${timeText}` : ''}`;

		wrapper.className = 'flex justify-end';
		wrapper.innerHTML = `
			<div class="max-w-[75%]">
				${attachmentBlock}
				${textBlock}
				<p class="mt-1 text-right text-[11px] text-slate-400">${labelText}</p>
			</div>
		`;
		return wrapper;
	}

	if (isAi) {
		const aiName = escapeHtml(message?.sender_name || 'NormAI');
		const attachmentBlock = renderAttachment(message, 'emerald');
		const textBlock = content
			? `<div class="rounded-2xl rounded-tl-sm border border-emerald-100 bg-emerald-50 px-4 py-2.5 text-sm text-slate-800">${content}</div>`
			: '';

		wrapper.className = 'max-w-[80%]';
		wrapper.innerHTML = `
			${attachmentBlock}
			${textBlock}
			<p class="mt-1 text-[11px] font-medium text-emerald-700">${aiName}${timeText ? ` • ${timeText}` : ''}</p>
		`;
		return wrapper;
	}

	const senderName = escapeHtml(message?.sender_name || 'User');
	const attachmentBlock = renderAttachment(message, 'slate');
	const textBlock = content
		? `<div class="rounded-2xl rounded-tl-sm border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800">${content}</div>`
		: '';

	wrapper.className = 'max-w-[75%]';
	wrapper.innerHTML = `
		<p class="mb-1 text-[11px] text-slate-500">${senderName}</p>
		${attachmentBlock}
		${textBlock}
		<p class="mt-1 text-[11px] text-slate-400">${senderName}${timeText ? ` • ${timeText}` : ''}</p>
	`;

	return wrapper;
};

const localizeExistingMessageTimes = () => {
	document.querySelectorAll('[data-message-time]').forEach((el) => {
		const iso = el.getAttribute('data-message-time') || '';
		const label = el.getAttribute('data-time-label') || '';
		if (!iso) {
			return;
		}

		const date = new Date(iso);
		if (Number.isNaN(date.valueOf())) {
			return;
		}

		const timeText = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
		el.textContent = label ? `${label} • ${timeText}` : timeText;
	});
};

const initVoicePlayers = (root = document) => {
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
				} catch (_error) {
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

localizeExistingMessageTimes();
initVoicePlayers(document);

if (window.Echo && groupId) {
	window.Echo.private(`group.${groupId}`).listen('.message.sent', (payload) => {
		if (!chatFeed || !payload?.message) {
			return;
		}

		if (
			payload.message.sender_type === 'user'
			&& Number(payload.message.sender_id || 0) === authUserId
			&& pendingMessagesByLocalId.size > 0
		) {
			const normalizedContent = String(payload.message.content || '').trim();
			const normalizedAttachmentName = String(payload.message.attachment_original_name || '');
			for (const [localId, pending] of pendingMessagesByLocalId.entries()) {
				if (!pending?.node?.isConnected) {
					pendingMessagesByLocalId.delete(localId);
					continue;
				}

				if (pending.messageType !== String(payload.message.message_type || '').toLowerCase()) {
					continue;
				}

				if (String(pending.content || '').trim() !== normalizedContent) {
					continue;
				}

				if (String(pending.attachmentOriginalName || '') !== normalizedAttachmentName) {
					continue;
				}

				const finalNode = buildMessageNode(payload.message);
				finalNode.setAttribute('data-message-id', String(payload.message.id));
				pending.node.replaceWith(finalNode);
				initVoicePlayers(finalNode);

				if (typeof pending.blobUrl === 'string') {
					URL.revokeObjectURL(pending.blobUrl);
				}

				pendingMessagesByLocalId.delete(localId);
				chatFeed.scrollTop = chatFeed.scrollHeight;
				return;
			}
		}

		const duplicate = chatFeed.querySelector(`[data-message-id="${payload.message.id}"]`);
		if (duplicate) {
			return;
		}

		const node = buildMessageNode(payload.message);
		node.setAttribute('data-message-id', String(payload.message.id));
		chatFeed.appendChild(node);
		initVoicePlayers(node);
		chatFeed.scrollTop = chatFeed.scrollHeight;
	});
}

const chatForm = document.querySelector('[data-chat-form]');
const attachmentInput = chatForm?.querySelector('[data-chat-attachment]');
const cameraInput = chatForm?.querySelector('[data-chat-camera-input]');
const attachmentLabel = chatForm?.querySelector('[data-attachment-label]');
const cameraButton = chatForm?.querySelector('[data-open-camera]');
const voiceButton = chatForm?.querySelector('[data-pick-voice]');
const recordVoiceButton = chatForm?.querySelector('[data-record-voice]');
const voiceRecorderPanel = chatForm?.querySelector('[data-voice-recorder-panel]');
const voiceCancelButton = chatForm?.querySelector('[data-voice-cancel]');
const voiceStopSendButton = chatForm?.querySelector('[data-voice-stop-send]');
const voiceTimer = chatForm?.querySelector('[data-voice-timer]');
const composerMain = chatForm?.querySelector('[data-composer-main]');
const emojiButton = chatForm?.querySelector('[data-open-emoji]');
const emojiPanel = chatForm?.querySelector('[data-emoji-panel]');
const recordingIndicator = chatForm?.querySelector('[data-recording-indicator]');
const mentionInput = chatForm?.querySelector('[data-mention-input]');
const sendButton = chatForm?.querySelector('[data-chat-send]');
const isDesktopKeyboard = (() => {
	const hasCoarsePointer = window.matchMedia?.('(pointer: coarse)').matches;
	const hasTouchPoints = (navigator.maxTouchPoints || 0) > 0;
	return !hasCoarsePointer && !hasTouchPoints;
})();

const insertAtCursor = (input, text) => {
	const start = input.selectionStart ?? input.value.length;
	const end = input.selectionEnd ?? input.value.length;
	const next = `${input.value.slice(0, start)}${text}${input.value.slice(end)}`;
	input.value = next;
	const cursor = start + text.length;
	input.setSelectionRange(cursor, cursor);
	input.focus();
};

const detectAttachmentMessageType = (file) => {
	if (!(file instanceof File)) {
		return 'text';
	}

	const mime = String(file.type || '').toLowerCase();
	const extension = String(file.name || '').toLowerCase().split('.').pop() || '';

	if (mime.startsWith('image/')) {
		return 'image';
	}

	if (mime.startsWith('audio/')) {
		return 'voice';
	}

	if (extension === 'webm' && mime.startsWith('video/')) {
		return 'voice';
	}

	if (['mp3', 'wav', 'ogg', 'aac', 'm4a'].includes(extension)) {
		return 'voice';
	}

	return 'file';
};

if (chatForm && attachmentInput instanceof HTMLInputElement) {
	const syncAttachmentFromInput = (sourceInput) => {
		const file = sourceInput?.files?.[0];
		if (!file) {
			return;
		}

		const transfer = new DataTransfer();
		transfer.items.add(file);
		attachmentInput.files = transfer.files;
	};

	const refreshAttachmentLabel = () => {
		const file = attachmentInput.files?.[0];
		if (!file || !attachmentLabel) {
			attachmentLabel?.classList.add('hidden');
			return;
		}

		attachmentLabel.textContent = file.name;
		attachmentLabel.classList.remove('hidden');
	};

	cameraButton?.addEventListener('click', () => {
		if (!(cameraInput instanceof HTMLInputElement)) {
			attachmentInput.accept = 'image/*';
			attachmentInput.setAttribute('capture', 'environment');
			attachmentInput.click();
			return;
		}

		cameraInput.click();
	});

	if (cameraInput instanceof HTMLInputElement) {
		cameraInput.addEventListener('change', () => {
			syncAttachmentFromInput(cameraInput);
			refreshAttachmentLabel();
		});
	}

	voiceButton?.addEventListener('click', () => {
		attachmentInput.accept = 'image/*,audio/*';
		attachmentInput.removeAttribute('capture');
		attachmentInput.click();
	});

	emojiButton?.addEventListener('click', (event) => {
		event.preventDefault();
		emojiPanel?.classList.toggle('hidden');
	});

	emojiPanel?.addEventListener('click', (event) => {
		const button = event.target.closest('[data-emoji-item]');
		if (!button || !(mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement)) {
			return;
		}

		const emoji = button.getAttribute('data-emoji-item') || '';
		if (!emoji) {
			return;
		}

		insertAtCursor(mentionInput, emoji);
		emojiPanel.classList.add('hidden');
	});

	document.addEventListener('click', (event) => {
		if (!emojiPanel || !emojiButton) {
			return;
		}

		if (emojiPanel.contains(event.target) || emojiButton.contains(event.target)) {
			return;
		}

		emojiPanel.classList.add('hidden');
	});

	const supportsRecorder = typeof window.MediaRecorder !== 'undefined' && navigator.mediaDevices?.getUserMedia;
	let recorder = null;
	let activeStream = null;
	let recordChunks = [];
	let timerInterval = null;
	let startedAt = 0;
	let sendOnStop = false;

	const updateTimer = () => {
		if (!voiceTimer || !startedAt) {
			return;
		}

		const sec = Math.floor((Date.now() - startedAt) / 1000);
		const minPart = Math.floor(sec / 60);
		const secPart = String(sec % 60).padStart(2, '0');
		voiceTimer.textContent = `${minPart}:${secPart}`;
	};

	const resetRecordingUi = () => {
		recordVoiceButton?.classList.remove('bg-rose-500');
		recordVoiceButton?.classList.add('bg-emerald-500');
		recordingIndicator?.classList.add('hidden');
		voiceRecorderPanel?.classList.add('hidden');
		voiceRecorderPanel?.classList.remove('flex');
		composerMain?.classList.remove('hidden');
		if (voiceTimer) {
			voiceTimer.textContent = '0:00';
		}
		if (timerInterval) {
			window.clearInterval(timerInterval);
			timerInterval = null;
		}
		startedAt = 0;
	};

	const showRecordingUi = () => {
		recordVoiceButton?.classList.remove('bg-emerald-500');
		recordVoiceButton?.classList.add('bg-rose-500');
		recordingIndicator?.classList.remove('hidden');
		composerMain?.classList.add('hidden');
		voiceRecorderPanel?.classList.remove('hidden');
		voiceRecorderPanel?.classList.add('flex');
		startedAt = Date.now();
		updateTimer();
		timerInterval = window.setInterval(updateTimer, 500);
	};

	const applyRecordedBlob = (blob) => {
		const file = new File([blob], `voice-${Date.now()}.webm`, { type: 'audio/webm' });
		const transfer = new DataTransfer();
		transfer.items.add(file);
		attachmentInput.files = transfer.files;
		refreshAttachmentLabel();
		chatForm.requestSubmit();
	};

	const stopRecording = (shouldSend) => {
		sendOnStop = shouldSend;
		if (recorder && recorder.state === 'recording') {
			recorder.stop();
			return;
		}

		resetRecordingUi();
	};

	recordVoiceButton?.addEventListener('click', async () => {
		if (!supportsRecorder) {
			attachmentInput.accept = 'audio/*';
			attachmentInput.removeAttribute('capture');
			attachmentInput.click();
			return;
		}

		try {
			activeStream = await navigator.mediaDevices.getUserMedia({ audio: true });
			recordChunks = [];
			recorder = new MediaRecorder(activeStream);

			recorder.ondataavailable = (event) => {
				if (event.data && event.data.size > 0) {
					recordChunks.push(event.data);
				}
			};

			recorder.onstop = () => {
				const blob = new Blob(recordChunks, { type: recorder.mimeType || 'audio/webm' });
				activeStream?.getTracks().forEach((track) => track.stop());
				activeStream = null;
				resetRecordingUi();
				if (sendOnStop && blob.size > 0) {
					applyRecordedBlob(blob);
				}
				sendOnStop = false;
			};

			recorder.start();
			showRecordingUi();
		} catch (_error) {
			resetRecordingUi();
			attachmentInput.accept = 'audio/*';
			attachmentInput.removeAttribute('capture');
			attachmentInput.click();
		}
	});

	voiceCancelButton?.addEventListener('click', () => {
		stopRecording(false);
	});

	voiceStopSendButton?.addEventListener('click', () => {
		stopRecording(true);
	});

	attachmentInput.addEventListener('change', refreshAttachmentLabel);

	if (mentionInput instanceof HTMLTextAreaElement) {
		const resizeComposerInput = () => {
			mentionInput.style.height = 'auto';
			mentionInput.style.height = `${Math.min(mentionInput.scrollHeight, 96)}px`;
		};

		mentionInput.addEventListener('input', resizeComposerInput);
		resizeComposerInput();
	}

	chatForm.addEventListener('submit', () => {
		emojiPanel?.classList.add('hidden');
		recordingIndicator?.classList.add('hidden');
		if (attachmentLabel) {
			attachmentLabel.classList.add('hidden');
		}
	});

	const setSendingUi = (isSending) => {
		if (!(sendButton instanceof HTMLButtonElement)) {
			return;
		}

		sendButton.disabled = isSending;
		sendButton.classList.toggle('opacity-70', isSending);
		sendButton.classList.toggle('cursor-wait', isSending);
	};

	const appendPendingMessageNode = (content, file) => {
		if (!chatFeed) {
			return null;
		}

		const localId = `local-${Date.now()}-${++pendingLocalMessageCounter}`;
		const blobUrl = file instanceof File ? URL.createObjectURL(file) : null;
		const messageType = detectAttachmentMessageType(file);
		const pendingMessage = {
			id: localId,
			message_type: messageType,
			sender_type: 'user',
			sender_id: authUserId,
			sender_name: 'You',
			content: content || '',
			attachment_url: blobUrl,
			attachment_mime: file instanceof File ? file.type : null,
			attachment_original_name: file instanceof File ? file.name : null,
			created_at: new Date().toISOString(),
			is_pending: true,
		};

		const node = buildMessageNode(pendingMessage);
		node.setAttribute('data-local-message-id', localId);
		node.setAttribute('data-pending-message', '1');
		chatFeed.appendChild(node);
		initVoicePlayers(node);
		chatFeed.scrollTop = chatFeed.scrollHeight;

		pendingMessagesByLocalId.set(localId, {
			node,
			blobUrl,
			content: content || '',
			messageType,
			attachmentOriginalName: file instanceof File ? file.name : '',
		});
		return localId;
	};

	const markPendingMessageFailed = (localId) => {
		const pending = pendingMessagesByLocalId.get(localId);
		if (!pending?.node) {
			return;
		}

		pending.node.setAttribute('data-pending-message', 'failed');
		const timeEl = pending.node.querySelector('p:last-child');
		if (timeEl instanceof HTMLElement) {
			timeEl.textContent = 'You • Gagal terkirim';
			timeEl.classList.remove('text-slate-400');
			timeEl.classList.add('text-rose-500');
		}

		pendingMessagesByLocalId.delete(localId);
	};

	const replacePendingMessage = (localId, serverMessage) => {
		const pending = pendingMessagesByLocalId.get(localId);
		if (!pending?.node) {
			return;
		}

		const finalNode = buildMessageNode(serverMessage);
		finalNode.setAttribute('data-message-id', String(serverMessage.id));
		pending.node.replaceWith(finalNode);
		initVoicePlayers(finalNode);

		if (typeof pending.blobUrl === 'string') {
			URL.revokeObjectURL(pending.blobUrl);
		}

		pendingMessagesByLocalId.delete(localId);
	};

	chatForm.addEventListener('submit', async (event) => {
		event.preventDefault();

		const actionUrl = chatForm.getAttribute('action') || '';
		if (!actionUrl) {
			return;
		}

		const contentText = mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement
			? mentionInput.value.trim()
			: '';
		const selectedFile = attachmentInput.files?.[0] instanceof File ? attachmentInput.files?.[0] : null;

		if (!contentText && !selectedFile) {
			return;
		}

		const formData = new FormData(chatForm);
		const localId = appendPendingMessageNode(contentText, selectedFile);

		if (mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement) {
			mentionInput.value = '';
			mentionInput.dispatchEvent(new Event('input', { bubbles: true }));
		}
		attachmentInput.value = '';
		if (cameraInput instanceof HTMLInputElement) {
			cameraInput.value = '';
		}
		attachmentLabel?.classList.add('hidden');
		emojiPanel?.classList.add('hidden');

		setSendingUi(true);
		try {
			const response = await fetch(actionUrl, {
				method: 'POST',
				body: formData,
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				credentials: 'same-origin',
			});

			if (!response.ok) {
				throw new Error('send_failed');
			}

			const result = await response.json();
			if (localId && result?.message) {
				replacePendingMessage(localId, result.message);
			}
		} catch (_error) {
			if (localId) {
				markPendingMessageFailed(localId);
			}
		} finally {
			setSendingUi(false);
		}
	});
}

const mentionMenu = chatForm?.querySelector('[data-mention-menu]');

if ((mentionInput instanceof HTMLTextAreaElement || mentionInput instanceof HTMLInputElement) && mentionMenu instanceof HTMLDivElement) {
	let activeSuggestions = [];
	let allSuggestions = [];
	try {
		const parsed = JSON.parse(mentionMenu.dataset.mentionItems || '[]');
		allSuggestions = Array.isArray(parsed) ? parsed : [];
	} catch (_error) {
		allSuggestions = [];
	}

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
				const tagTypeClass = item.type === 'ai' ? 'text-emerald-600' : 'text-blue-600';
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

const settingsDrawer = document.querySelector('[data-settings-drawer]');
const settingsOverlay = document.querySelector('[data-settings-overlay]');
const openSettingsButtons = Array.from(document.querySelectorAll('[data-open-settings]'));
const closeSettingsButton = document.querySelector('[data-close-settings]');

if (settingsDrawer && settingsOverlay && openSettingsButtons.length > 0 && closeSettingsButton) {
	let drawerOpen = false;

	const openDrawer = () => {
		drawerOpen = true;
		settingsDrawer.classList.remove('translate-x-full');
		settingsOverlay.classList.remove('pointer-events-none', 'opacity-0', 'bg-slate-900/0');
		settingsOverlay.classList.add('bg-slate-900/35', 'opacity-100');
		document.body.classList.add('overflow-hidden');
	};

	const closeDrawer = () => {
		drawerOpen = false;
		settingsDrawer.classList.add('translate-x-full');
		settingsOverlay.classList.add('pointer-events-none', 'opacity-0', 'bg-slate-900/0');
		settingsOverlay.classList.remove('bg-slate-900/35', 'opacity-100');
		document.body.classList.remove('overflow-hidden');
	};

	openSettingsButtons.forEach((openSettingsButton) => {
		openSettingsButton.addEventListener('click', (event) => {
			event.preventDefault();
			if (drawerOpen) {
				closeDrawer();
				return;
			}
			openDrawer();
		});
	});
	closeSettingsButton.addEventListener('click', closeDrawer);
	settingsOverlay.addEventListener('click', closeDrawer);
}

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
		} catch (_error) {
			// Ignore clipboard failures silently on restricted browser contexts.
		}
	});
});

document.querySelectorAll('[data-share-group-url]').forEach((button) => {
	button.addEventListener('click', async () => {
		const url = button.getAttribute('data-share-group-url') || '';
		const groupName = button.getAttribute('data-share-group-name') || 'Normchat Group';
		if (!url) {
			return;
		}

		const sharePayload = {
			title: `Join #${groupName}`,
			text: `Gabung ke group #${groupName} di Normchat`,
			url,
		};

		if (navigator.share) {
			try {
				await navigator.share(sharePayload);
				return;
			} catch (_error) {
				// Fall back to clipboard when share sheet is cancelled/unavailable.
			}
		}

		if (navigator.clipboard?.writeText) {
			try {
				await navigator.clipboard.writeText(url);
				const prev = button.textContent;
				button.textContent = 'Link tersalin';
				window.setTimeout(() => {
					button.textContent = prev;
				}, 1300);
			} catch (_error) {
				// Ignore clipboard failures silently on restricted browser contexts.
			}
		}
	});
});
