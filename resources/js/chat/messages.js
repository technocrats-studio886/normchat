// Render, ganti, dan hapus node pesan di feed.

import { renderAttachment, renderReplyPreview } from './attachments.js';
import { authUserId, chatFeed } from './context.js';
import { renderMarkdown, renderMermaidInNode } from './markdown.js';
import { parsePollContent, refreshPollCardsFromFeed, renderPollCard } from './polls.js';
import { escapeAttr, escapeHtml } from './utils.js';
import { initVoicePlayers } from './voice-player.js';

export const buildMessageNode = (message) => {
	const createdAt = message?.created_at ? new Date(message.created_at) : null;
	const timeText = createdAt && !Number.isNaN(createdAt.valueOf())
		? createdAt.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
		: '';

	const isAi = message?.sender_type === 'ai';
	const isMine = message?.sender_type === 'user' && Number(message?.sender_id || 0) === authUserId;
	const buildEditedMarkHtml = (tone) => {
		if (!message?.is_edited) {
			return '';
		}

		const editedAt = message?.edited_at ? new Date(message.edited_at) : null;
		const editedTitle = editedAt && !Number.isNaN(editedAt.valueOf())
			? `Diedit ${editedAt.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}`
			: 'Pesan telah diedit';

		return ` <span class="nc-edited-mark nc-edited-mark--${tone}" title="${escapeAttr(editedTitle)}" aria-label="${escapeAttr(editedTitle)}">diedit</span>`;
	};
	const editedMarkForMine = buildEditedMarkHtml('mine');
	const editedMarkForAi = buildEditedMarkHtml('ai');
	const editedMarkForOther = buildEditedMarkHtml('other');
	const rawContent = String(message?.content || '');
	const content = escapeHtml(rawContent);
	const pollData = parsePollContent(rawContent);

	const wrapper = document.createElement('div');
	wrapper.setAttribute('data-message-id', String(message?.id ?? ''));
	wrapper.dataset.messageSenderId = String(Number(message?.sender_id || 0));
	wrapper.style.touchAction = 'pan-y';
	wrapper.style.userSelect = 'none';
	wrapper.style.webkitUserSelect = 'none';
	const isPending = message?.is_pending === true;

	if (isMine) {
		const replyBlock = renderReplyPreview(message, 'mine');
		const inlineTimeHtml = timeText
			? `<span class="nc-inline-time">${timeText}${editedMarkForMine}</span>`
			: '';
		const hasAttachment = Boolean(message?.attachment_url);
		const shouldMergeAttachmentCaption = hasAttachment && Boolean(content) && !pollData;
		const attachmentBlock = renderAttachment(message, 'mine', {
			mergedCaption: shouldMergeAttachmentCaption,
		});
		const attachmentWithCaptionBlock = shouldMergeAttachmentCaption
			? `
				<div class="nc-media-caption-card nc-media-caption-card--mine" data-message-body="1">
					${attachmentBlock}
					<div class="px-3 pb-2 pt-2 text-sm text-white/95">
						<span class="whitespace-pre-wrap">${content}</span>
						${inlineTimeHtml}
					</div>
				</div>
			`
			: '';
		const textBlock = content
			? (pollData
				? renderPollCard({ poll: pollData, tone: 'mine', messageId: message?.id, inlineTimeHtml })
				: (shouldMergeAttachmentCaption
					? ''
					: `<div class="bubble-mine" data-message-body="1"><span class="whitespace-pre-wrap">${content}</span>${inlineTimeHtml}</div>`))
			: '';
		const pendingTail = isPending
			? `<p class="nc-chat-meta mt-1 text-right text-[11px]"><span class="inline-flex items-center gap-1"><span class="h-2 w-2 animate-pulse rounded-full bg-coral-400"></span>Mengirim...</span></p>`
			: (content ? '' : `<p class="nc-chat-meta mt-1 text-right text-[11px]">${timeText}${editedMarkForMine}</p>`);

		wrapper.className = 'flex justify-end';
		wrapper.innerHTML = `
			<div class="max-w-[75%]">
				${replyBlock}
				${attachmentWithCaptionBlock || attachmentBlock}
				${textBlock}
				${pendingTail}
			</div>
		`;
		wrapper.dataset.messageSenderName = String(message?.sender_name || 'Kamu');
		wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
		wrapper.dataset.messageType = String(message?.message_type || 'text');
		wrapper.dataset.messageAttachmentMime = String(message?.attachment_mime || '');
		wrapper.dataset.messageAttachmentName = String(message?.attachment_original_name || '');
		return wrapper;
	}

	if (isAi) {
		const replyBlock = renderReplyPreview(message, 'ai');
		const aiName = escapeHtml(message?.sender_name || 'Normi AI');
		const attachmentBlock = renderAttachment(message, 'ai');
		const renderedContent = pollData ? '' : (content ? renderMarkdown(content) : '');
		const hasRichContent = renderedContent && (renderedContent.includes('<table') || renderedContent.includes('mermaid'));
		const aiTimeHtml = timeText
			? `<p class="nc-chat-meta nc-chat-meta--ai mt-1 text-right text-[11px] font-medium">${timeText}${editedMarkForAi}</p>`
			: '';
		const textBlock = pollData
			? renderPollCard({ poll: pollData, tone: 'ai', messageId: message?.id, inlineTimeHtml: '' })
			: (renderedContent
				? `<div class="bubble-ai ai-markdown overflow-hidden" data-message-body="1">${renderedContent}</div>`
				: '');

		wrapper.className = hasRichContent ? 'max-w-[95%]' : 'max-w-[80%]';
		wrapper.innerHTML = `
			<p class="nc-chat-sender nc-chat-sender--ai mb-1 text-[11px] font-semibold">${aiName}</p>
			${replyBlock}
			${attachmentBlock}
			${textBlock}
			${aiTimeHtml}
		`;
		wrapper.dataset.messageSenderName = String(message?.sender_name || 'Normi AI');
		wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
		wrapper.dataset.messageType = String(message?.message_type || 'text');
		wrapper.dataset.messageAttachmentMime = String(message?.attachment_mime || '');
		wrapper.dataset.messageAttachmentName = String(message?.attachment_original_name || '');
		return wrapper;
	}

	const replyBlock = renderReplyPreview(message, 'slate');
	const senderName = escapeHtml(message?.sender_name || 'User');
	const attachmentBlock = renderAttachment(message, 'slate');
	const inlineTimeHtml = timeText
		? `<span class="nc-inline-time">${timeText}${editedMarkForOther}</span>`
		: '';
	const textBlock = content
		? (pollData
			? renderPollCard({ poll: pollData, tone: 'other', messageId: message?.id, inlineTimeHtml })
			: `<div class="bubble-other" data-message-body="1"><span class="whitespace-pre-wrap">${content}</span>${inlineTimeHtml}</div>`)
		: '';

	wrapper.className = 'max-w-[75%]';
	wrapper.innerHTML = `
		<p class="nc-chat-sender mb-1 text-[11px]">${senderName}</p>
		${replyBlock}
		${attachmentBlock}
		${textBlock}
		${content ? '' : `<p class="nc-chat-meta mt-1 text-[11px]">${timeText}${editedMarkForOther}</p>`}
	`;
	wrapper.dataset.messageSenderName = String(message?.sender_name || 'User');
	wrapper.dataset.messageContent = String(message?.content || '').trim() || (message?.message_type === 'image' ? '[Gambar]' : (message?.message_type === 'voice' ? '[Voice note]' : '[Lampiran]'));
	wrapper.dataset.messageType = String(message?.message_type || 'text');
	wrapper.dataset.messageAttachmentMime = String(message?.attachment_mime || '');
	wrapper.dataset.messageAttachmentName = String(message?.attachment_original_name || '');

	return wrapper;
};

export const replaceRenderedMessageByPayload = (message) => {
	if (!chatFeed || !message?.id) {
		return null;
	}

	const messageId = Number(message.id || 0);
	if (messageId <= 0) {
		return null;
	}

	const currentNode = chatFeed.querySelector(`[data-message-id="${messageId}"]`);
	if (!(currentNode instanceof HTMLElement)) {
		return null;
	}

	const nextNode = buildMessageNode(message);
	nextNode.setAttribute('data-message-id', String(messageId));
	nextNode.id = `message-${messageId}`;
	currentNode.replaceWith(nextNode);
	initVoicePlayers(nextNode);
	renderMermaidInNode(nextNode);
	refreshPollCardsFromFeed();

	return nextNode;
};

export const resolveEditedFlashTone = (message) => {
	if (message?.sender_type === 'ai') {
		return 'ai';
	}

	if (message?.sender_type === 'user' && Number(message?.sender_id || 0) === authUserId) {
		return 'mine';
	}

	return 'other';
};

export const flashEditedMessage = (node, tone = 'other') => {
	if (!(node instanceof HTMLElement)) {
		return;
	}
	const bodyCandidates = node.querySelectorAll('[data-message-body]');
	const flashTarget = bodyCandidates.length > 0
		? bodyCandidates[bodyCandidates.length - 1]
		: node;
	const normalizedTone = tone === 'mine' || tone === 'ai' || tone === 'other'
		? tone
		: 'other';

	flashTarget.classList.remove(
		'nc-message-edited-flash',
		'nc-message-edited-flash--mine',
		'nc-message-edited-flash--ai',
		'nc-message-edited-flash--other',
	);
	void flashTarget.offsetWidth;
	flashTarget.classList.add('nc-message-edited-flash', `nc-message-edited-flash--${normalizedTone}`);
	window.setTimeout(() => {
		flashTarget.classList.remove(
			'nc-message-edited-flash',
			'nc-message-edited-flash--mine',
			'nc-message-edited-flash--ai',
			'nc-message-edited-flash--other',
		);
	}, 950);
};

export const removeRenderedMessageById = (messageId) => {
	if (!chatFeed || !messageId) {
		return false;
	}

	const target = chatFeed.querySelector(`[data-message-id="${messageId}"]`);
	if (!(target instanceof HTMLElement)) {
		return false;
	}

	target.remove();
	refreshPollCardsFromFeed();
	return true;
};

export const localizeExistingMessageTimes = () => {
	document.querySelectorAll('[data-message-time]').forEach((el) => {
		const iso = el.getAttribute('data-message-time') || '';
		const label = el.getAttribute('data-time-label') || '';
		const edited = el.getAttribute('data-time-edited') === '1';
		const editedAtIso = el.getAttribute('data-time-edited-at') || '';
		const editedTone = el.getAttribute('data-time-tone') || 'other';
		if (!iso) {
			return;
		}

		const date = new Date(iso);
		if (Number.isNaN(date.valueOf())) {
			return;
		}

		const timeText = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
		const editedAt = editedAtIso ? new Date(editedAtIso) : null;
		const editedTitle = editedAt && !Number.isNaN(editedAt.valueOf())
			? `Diedit ${editedAt.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}`
			: 'Pesan telah diedit';
		const editedMarkHtml = edited
			? ` <span class="nc-edited-mark nc-edited-mark--${escapeAttr(editedTone)}" title="${escapeAttr(editedTitle)}" aria-label="${escapeAttr(editedTitle)}">diedit</span>`
			: '';
		const safeLabel = escapeHtml(label);
		const showLabel = label && editedTone !== 'other';
		el.innerHTML = showLabel
			? `${safeLabel} • ${timeText}${editedMarkHtml}`
			: `${timeText}${editedMarkHtml}`;
	});
};
