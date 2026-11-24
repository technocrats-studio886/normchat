import { chatFeed } from './context.js';
import { chatMessagesBaseUrl } from './composer/elements.js';
import { chatApiRequest } from './utils.js';

const activityLabels = {
	file: 'Normi sedang menyiapkan dokumen…',
	research: 'Normi sedang melakukan riset…',
	canvas: 'Normi sedang menyiapkan proyek…',
	image: 'Normi sedang membuat gambar…',
	typing: 'Normi sedang menyusun jawaban…',
	learning: 'Normi sedang menyusun jawaban…',
};

export function initRequestStatus(catchUp) {
	if (!chatFeed || !chatMessagesBaseUrl) return;
	let timer;
	let polling = false;

	function schedule(delay = 0) {
		clearTimeout(timer);
		if (!document.hidden && chatFeed.querySelector('[data-request-status="watch"]')) {
			timer = setTimeout(poll, delay);
		}
	}

	function display(box, state) {
		const node = box.closest('[data-message-id]');
		if (!node || !state || state.status === 'processed') {
			node?.removeAttribute('data-message-awaiting-ai');
			box.remove();
			return;
		}
		const active = ['queued', 'processing'].includes(state.status);
		if (active) node.dataset.messageAwaitingAi = '1';
		else node.removeAttribute('data-message-awaiting-ai');
		box.dataset.requestStatus = state.status === 'failed' ? 'stopped' : 'watch';
		const text = {
			queued: 'Menunggu giliran Normi…',
			processing: activityLabels[state.activity] || 'Normi sedang membaca permintaanmu…',
			checking: 'Proses terhenti. Menunggu pemeriksaan saldo…',
			failed: state.can_retry ? 'Proses terhenti sebelum jawaban terkirim.' : ({
				resend: 'Proses terhenti. Edit pesan ini atau kirim ulang permintaannya.',
				group_inactive: 'Proses terhenti karena grup sedang dibekukan.',
			}[state.hint] || 'Proses terhenti. Hubungi admin untuk memeriksa permintaan ini.'),
		}[state.status];
		if (box.dataset.label === text && box.dataset.attempt === String(state.attempt)) return;
		box.dataset.label = text;
		box.dataset.attempt = String(state.attempt);
		box.textContent = text;
		if (!state.can_retry) return;
		const button = document.createElement('button');
		button.type = 'button';
		button.textContent = 'Coba lagi';
		button.className = 'underline font-semibold ml-2';
		box.append(button);
		button.addEventListener('click', async () => {
			if (button.disabled) return;
			button.disabled = true;
			try {
				const data = await chatApiRequest(chatMessagesBaseUrl + '/' + state.message_id + '/retry', {
					method: 'POST', body: { attempt: state.attempt, queue_id: state.queue_id },
				});
				display(box, data.request);
				if (data.request.status === 'processed') await catchUp();
			} catch {
				box.firstChild.textContent = 'Status belum terkonfirmasi. Periksa koneksi lalu coba lagi.';
				delete box.dataset.label;
				box.dataset.requestStatus = 'watch';
			} finally {
				button.disabled = false;
				schedule(1000);
			}
		});
	}

	async function poll() {
		if (polling || document.hidden) return;
		polling = true;
		let delay = 4500;
		try {
			const boxes = Array.from(chatFeed.querySelectorAll('[data-request-status="watch"]'));
			let missedAnswer = false;
			for (let start = 0; start < boxes.length; start += 80) {
				const chunk = boxes.slice(start, start + 80);
				const query = new URLSearchParams();
				chunk.forEach(box => query.append('ids[]', box.closest('[data-message-id]').dataset.messageId));
				const data = await chatApiRequest(chatMessagesBaseUrl + '/requests?' + query);
				const states = new Map(data.requests.map(state => [String(state.message_id), state]));
				chunk.forEach(box => {
					if (!box.isConnected) return;
					const id = box.closest('[data-message-id]').dataset.messageId;
					const state = states.get(id);
					if (state?.status === 'processed') missedAnswer = true;
					display(box, state);
				});
			}
			if (missedAnswer) await catchUp();
		} catch {
			delay = 12000;
		} finally {
			polling = false;
			schedule(delay);
		}
	}

	new MutationObserver(changes => {
		if (changes.some(change => Array.from(change.addedNodes).some(node => node.querySelector?.('[data-request-status]')))) schedule(150);
	}).observe(chatFeed, { childList: true });
	const resume = () => {
		chatFeed.querySelectorAll('[data-request-status="stopped"]').forEach(box => { box.dataset.requestStatus = 'watch'; });
		schedule();
	};
	document.addEventListener('visibilitychange', resume);
	window.addEventListener('online', resume);
	schedule();
}
