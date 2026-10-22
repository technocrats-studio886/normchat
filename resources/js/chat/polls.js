// Polling di chat: parsing, kartu, dan statistik suara.

import { authUserId, chatFeed, groupId, pollStatsById, pollStatsUrlFromServer, pollVoteUrlTemplateFromServer } from './context.js';
import { chatApiRequest, escapeAttr, escapeHtml } from './utils.js';

let pollStatsRequestInFlight = false;

export const parsePollContent = (rawContent) => {
	const normalized = String(rawContent || '').replace(/\r\n/g, '\n').trim();
	if (!normalized) {
		return null;
	}

	if (!/^📊\s*poll:/i.test(normalized)) {
		return null;
	}

	const lines = normalized
		.split('\n')
		.map((line) => line.trim())
		.filter((line) => line !== '');

	if (!lines.length) {
		return null;
	}

	const question = lines[0].replace(/^📊\s*poll:\s*/i, '').trim();
	if (!question) {
		return null;
	}

	const options = [];
	for (let i = 1; i < lines.length; i++) {
		const match = lines[i].match(/^(\d+)[.)]\s+(.+)$/);
		if (!match) {
			continue;
		}

		const optionNumber = Number(match[1]);
		const optionLabel = String(match[2] || '').trim();
		if (!Number.isFinite(optionNumber) || optionNumber <= 0 || !optionLabel) {
			continue;
		}

		options.push({
			number: optionNumber,
			label: optionLabel,
		});
	}

	if (options.length < 2) {
		return null;
	}

	return {
		question,
		options: options.slice(0, 8),
	};
};

export const renderPollCard = ({ poll, tone = 'other', messageId = 0, inlineTimeHtml = '' }) => {
	if (!poll || !Array.isArray(poll.options) || poll.options.length < 2) {
		return '';
	}

	const colorMap = {
		mine: {
			card: 'bg-navy-500 text-white border-navy-400',
			question: 'text-navy-50',
			meta: 'text-navy-100/90',
			option: 'bg-navy-400/45 border-navy-300/70 text-white hover:bg-navy-300/55',
		},
		ai: {
			card: 'bg-navy-50 text-navy-900 border-navy-200',
			question: 'text-navy-900',
			meta: 'text-navy-700',
			option: 'bg-white border-navy-200 text-navy-900 hover:bg-navy-100',
		},
		other: {
			card: 'bg-white text-slate-800 border-slate-200',
			question: 'text-slate-900',
			meta: 'text-slate-500',
			option: 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100',
		},
	};

	const palette = colorMap[tone] || colorMap.other;
	const safeQuestion = escapeHtml(poll.question);
	const numericMessageId = Number(messageId || 0);
	const canVote = Number.isFinite(numericMessageId) && numericMessageId > 0;
	const optionsHtml = poll.options
		.map((option) => {
			const safeLabel = escapeHtml(option.label);
			const safeNumber = Number(option.number || 0);
			if (!canVote) {
				return `<div class="rounded-xl border px-2.5 py-2 text-xs font-medium ${palette.option}" data-poll-option-row="1" data-poll-option-number="${safeNumber}"><div class="flex items-center gap-2"><span class="font-bold">${safeNumber}.</span><span class="min-w-0 flex-1 truncate">${safeLabel}</span><span class="text-[10px] font-semibold opacity-80" data-poll-option-count>0</span></div></div>`;
			}

			return `
				<button
					type="button"
					class="w-full rounded-xl border px-2.5 py-2 text-left text-xs font-medium transition ${palette.option}"
					data-poll-vote="1"
					data-poll-id="${numericMessageId}"
					data-poll-option="${safeNumber}"
					data-poll-label="${escapeAttr(option.label)}"
					data-poll-option-row="1"
					data-poll-option-number="${safeNumber}"
				>
					<span class="flex items-center gap-2"><span class="font-bold">${safeNumber}.</span><span class="min-w-0 flex-1 truncate">${safeLabel}</span><span class="text-[10px] font-semibold opacity-80" data-poll-option-count>0</span></span>
				</button>
			`;
		})
		.join('');

	return `
		<div class="rounded-2xl border px-3 py-2.5 ${palette.card}" data-message-body="1" data-poll-card="1" data-poll-id="${numericMessageId}" data-poll-tone="${escapeAttr(tone)}">
			<p class="text-[11px] font-semibold uppercase tracking-wide ${palette.meta}">Polling</p>
			<p class="mt-1 text-sm font-semibold ${palette.question}">${safeQuestion}</p>
			<div class="mt-2 space-y-1.5">${optionsHtml}</div>
			<p class="mt-2 text-[11px] ${palette.meta}" data-poll-summary>Belum ada yang memilih</p>
			${inlineTimeHtml}
		</div>
	`;
};

const parseVoteContent = (rawContent) => {
	const normalized = String(rawContent || '').replace(/\r\n/g, '\n').trim();
	if (!normalized) {
		return null;
	}

	const match = normalized.match(/^🗳️\s*vote\s+poll\s+#(\d+)\s*:\s*(\d+)/i);
	if (!match) {
		return null;
	}

	const pollId = Number(match[1] || 0);
	const optionNumber = Number(match[2] || 0);
	if (!Number.isFinite(pollId) || pollId <= 0 || !Number.isFinite(optionNumber) || optionNumber <= 0) {
		return null;
	}

	return {
		pollId,
		optionNumber,
	};
};

export const buildPollVoteUrl = (pollId) => {
	const numericPollId = Number(pollId || 0);
	if (!Number.isFinite(numericPollId) || numericPollId <= 0) {
		return '';
	}

	if (pollVoteUrlTemplateFromServer.includes('__MESSAGE__')) {
		return pollVoteUrlTemplateFromServer.replace('__MESSAGE__', String(numericPollId));
	}

	if (groupId) {
		return `/groups/${groupId}/polls/${numericPollId}/vote`;
	}

	return '';
};

const collectPollCardIds = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return [];
	}

	return Array.from(chatFeed.querySelectorAll('[data-poll-card]'))
		.map((card) => Number(card.getAttribute('data-poll-id') || 0))
		.filter((pollId) => Number.isFinite(pollId) && pollId > 0)
		.filter((pollId, index, list) => list.indexOf(pollId) === index);
};

export const mergePollStatsIntoCache = (pollStatsPayload) => {
	const pollId = Number(pollStatsPayload?.poll_id || 0);
	if (!Number.isFinite(pollId) || pollId <= 0) {
		return;
	}

	const optionCountsRaw = pollStatsPayload?.option_counts;
	const optionCounts = {};
	if (optionCountsRaw && typeof optionCountsRaw === 'object') {
		Object.entries(optionCountsRaw).forEach(([key, value]) => {
			const optionNumber = Number(key || 0);
			const count = Number(value || 0);
			if (!Number.isFinite(optionNumber) || optionNumber <= 0) {
				return;
			}

			optionCounts[optionNumber] = Number.isFinite(count) && count > 0 ? count : 0;
		});
	}

	pollStatsById.set(pollId, {
		totalVotes: Math.max(0, Number(pollStatsPayload?.total_votes || 0)),
		myVoteOption: Math.max(0, Number(pollStatsPayload?.my_vote_option || 0)),
		optionCounts,
	});
};

const requestPollStatsForVisibleCards = async () => {
	if (!pollStatsUrlFromServer || pollStatsRequestInFlight) {
		return;
	}

	const pollIds = collectPollCardIds();
	if (!pollIds.length) {
		return;
	}

	const missingIds = pollIds.filter((pollId) => !pollStatsById.has(pollId));
	if (!missingIds.length) {
		return;
	}

	pollStatsRequestInFlight = true;
	try {
		let didUpdate = false;
		const result = await chatApiRequest(`${pollStatsUrlFromServer}?ids=${missingIds.join(',')}`);
		if (result?.polls && typeof result.polls === 'object') {
			Object.values(result.polls).forEach((pollStatsPayload) => {
				mergePollStatsIntoCache(pollStatsPayload);
				didUpdate = true;
			});
		}

		if (didUpdate) {
			refreshPollCardsFromFeed();
		}
	} catch {
		// Ignore polling stat fetch errors and keep feed-based fallback active.
	} finally {
		pollStatsRequestInFlight = false;
	}
};

const collectPollVotesFromFeed = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return new Map();
	}

	const votesByPoll = new Map();
	const nodes = Array.from(chatFeed.querySelectorAll('[data-message-id]'));
	nodes.forEach((node) => {
		if (!(node instanceof HTMLElement) || node.classList.contains('hidden')) {
			return;
		}

		const vote = parseVoteContent(node.dataset.messageContent || '');
		if (!vote) {
			return;
		}

		const senderId = Number(node.dataset.messageSenderId || 0);
		const messageId = Number(node.getAttribute('data-message-id') || 0);
		if (!Number.isFinite(senderId) || senderId <= 0 || !Number.isFinite(messageId) || messageId <= 0) {
			return;
		}

		if (!votesByPoll.has(vote.pollId)) {
			votesByPoll.set(vote.pollId, new Map());
		}

		const senderVotes = votesByPoll.get(vote.pollId);
		const existing = senderVotes.get(senderId);
		if (!existing || messageId > existing.messageId) {
			senderVotes.set(senderId, {
				messageId,
				optionNumber: vote.optionNumber,
			});
		}
	});

	return votesByPoll;
};

export const refreshPollCardsFromFeed = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return;
	}

	const votesByPoll = collectPollVotesFromFeed();
	requestPollStatsForVisibleCards();
	chatFeed.querySelectorAll('[data-poll-card]').forEach((card) => {
		if (!(card instanceof HTMLElement)) {
			return;
		}

		const pollId = Number(card.getAttribute('data-poll-id') || 0);
		const serverStats = pollStatsById.get(pollId);

		const optionCounts = new Map();
		let totalVotes = 0;
		let myVoteOption = 0;

		if (serverStats) {
			Object.entries(serverStats.optionCounts || {}).forEach(([optionNumber, count]) => {
				const parsedOption = Number(optionNumber || 0);
				const parsedCount = Number(count || 0);
				if (Number.isFinite(parsedOption) && parsedOption > 0) {
					optionCounts.set(parsedOption, Math.max(0, parsedCount));
				}
			});

			totalVotes = Math.max(0, Number(serverStats.totalVotes || 0));
			myVoteOption = Math.max(0, Number(serverStats.myVoteOption || 0));
		} else {
			const senderVotes = votesByPoll.get(pollId) || new Map();
			senderVotes.forEach((vote) => {
				optionCounts.set(vote.optionNumber, (optionCounts.get(vote.optionNumber) || 0) + 1);
			});
			totalVotes = senderVotes.size;

			const mine = senderVotes.get(authUserId);
			if (mine && Number.isFinite(mine.optionNumber)) {
				myVoteOption = mine.optionNumber;
			}
		}

		card.querySelectorAll('[data-poll-option-row="1"]').forEach((row) => {
			if (!(row instanceof HTMLElement)) {
				return;
			}

			const optionNumber = Number(row.getAttribute('data-poll-option-number') || 0);
			const count = optionCounts.get(optionNumber) || 0;
			const countEl = row.querySelector('[data-poll-option-count]');
			if (countEl instanceof HTMLElement) {
				countEl.textContent = String(count);
			}

			const ratio = totalVotes > 0 ? Math.max(0, Math.min(100, Math.round((count / totalVotes) * 100))) : 0;
			row.style.backgroundImage = ratio > 0
				? `linear-gradient(90deg, rgba(113, 134, 163, 0.16) ${ratio}%, rgba(0,0,0,0) ${ratio}%)`
				: '';

			const isMine = optionNumber > 0 && optionNumber === myVoteOption;
			row.classList.toggle('ring-2', isMine);
			row.classList.toggle('ring-navy-400', isMine);
			row.classList.toggle('ring-offset-1', isMine);
		});

		const summary = card.querySelector('[data-poll-summary]');
		if (summary instanceof HTMLElement) {
			if (totalVotes <= 0) {
				summary.textContent = 'Belum ada yang memilih';
			} else if (myVoteOption > 0) {
				summary.textContent = `${totalVotes} suara • Kamu memilih nomor ${myVoteOption}`;
			} else {
				summary.textContent = `${totalVotes} suara`;
			}
		}
	});
};

export const hydrateExistingPollMessages = () => {
	if (!(chatFeed instanceof HTMLElement)) {
		return;
	}

	chatFeed.querySelectorAll('[data-message-id]').forEach((messageNode) => {
		if (!(messageNode instanceof HTMLElement)) {
			return;
		}

		if (messageNode.querySelector('[data-poll-card]')) {
			return;
		}

		const pollData = parsePollContent(messageNode.dataset.messageContent || '');
		if (!pollData) {
			return;
		}

		const bubble = messageNode.querySelector('.bubble-mine, .bubble-ai, .bubble-other');
		if (!(bubble instanceof HTMLElement)) {
			return;
		}

		const inlineTimeEl = bubble.querySelector('.nc-inline-time');
		const inlineTimeHtml = inlineTimeEl instanceof HTMLElement ? inlineTimeEl.outerHTML : '';
		const messageId = Number(messageNode.getAttribute('data-message-id') || 0);
		const tone = messageNode.classList.contains('justify-end')
			? 'mine'
			: (bubble.classList.contains('bubble-ai') ? 'ai' : 'other');

		bubble.outerHTML = renderPollCard({
			poll: pollData,
			tone,
			messageId,
			inlineTimeHtml,
		});
	});
};
