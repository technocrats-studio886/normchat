import './bootstrap';

const chatFeed = document.querySelector('[data-chat-feed]');
const groupId = chatFeed?.getAttribute('data-chat-group-id');
const authUserId = Number(chatFeed?.getAttribute('data-auth-user-id') || 0);

const escapeHtml = (text) => String(text)
	.replace(/&/g, '&amp;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#039;');

const buildMessageNode = (message) => {
	const createdAt = message?.created_at ? new Date(message.created_at) : null;
	const timeText = createdAt && !Number.isNaN(createdAt.valueOf())
		? createdAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
		: '';

	const isAi = message?.sender_type === 'ai';
	const isMine = message?.sender_type === 'user' && Number(message?.sender_id || 0) === authUserId;
	const content = escapeHtml(message?.content || '');

	const wrapper = document.createElement('div');

	if (isMine) {
		wrapper.className = 'flex justify-end';
		wrapper.innerHTML = `
			<div class="max-w-[75%]">
				<div class="rounded-2xl rounded-tr-sm bg-[#2563EB] px-4 py-2.5 text-sm text-white">${content}</div>
				<p class="mt-1 text-right text-[11px] text-slate-400">You${timeText ? ` • ${timeText}` : ''}</p>
			</div>
		`;
		return wrapper;
	}

	if (isAi) {
		const aiName = escapeHtml(message?.sender_name || 'NormAI');
		wrapper.className = 'max-w-[80%]';
		wrapper.innerHTML = `
			<div class="rounded-2xl rounded-tl-sm border border-emerald-100 bg-emerald-50 px-4 py-2.5 text-sm text-slate-800">${content}</div>
			<p class="mt-1 text-[11px] font-medium text-emerald-700">${aiName}${timeText ? ` • ${timeText}` : ''}</p>
		`;
		return wrapper;
	}

	const senderName = escapeHtml(message?.sender_name || 'User');

	wrapper.className = 'max-w-[75%]';
	wrapper.innerHTML = `
		<p class="mb-1 text-[11px] text-slate-500">${senderName}</p>
		<div class="rounded-2xl rounded-tl-sm border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800">${content}</div>
		<p class="mt-1 text-[11px] text-slate-400">${senderName}${timeText ? ` • ${timeText}` : ''}</p>
	`;

	return wrapper;
};

if (window.Echo && groupId) {
	window.Echo.private(`group.${groupId}`).listen('.message.sent', (payload) => {
		if (!chatFeed || !payload?.message) {
			return;
		}

		const duplicate = chatFeed.querySelector(`[data-message-id="${payload.message.id}"]`);
		if (duplicate) {
			return;
		}

		const node = buildMessageNode(payload.message);
		node.setAttribute('data-message-id', String(payload.message.id));
		chatFeed.appendChild(node);
		chatFeed.scrollTop = chatFeed.scrollHeight;
	});
}
