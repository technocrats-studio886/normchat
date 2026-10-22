// Rekam pesan suara lewat MediaRecorder; fallback ke pemilih file audio.

import { refreshAttachmentLabel } from './attachment.js';
import { attachmentInput, chatForm, composerMain, recordVoiceButton, recordingIndicator, voiceCancelButton, voiceRecorderPanel, voiceStopSendButton, voiceTimer } from './elements.js';

export const initVoiceRecorder = () => {
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
		recordVoiceButton?.classList.remove('bg-coral-500');
		recordVoiceButton?.classList.add('bg-navy-500');
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
		recordVoiceButton?.classList.remove('bg-navy-500');
		recordVoiceButton?.classList.add('bg-coral-500');
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
		} catch {
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
};
