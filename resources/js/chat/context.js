// Konteks halaman chat: elemen utama, data dari server, dan cache bersama.

export const chatShell = document.querySelector('[data-chat-shell]');
export const chatFeed = document.querySelector('[data-chat-feed]');
export const groupId = chatFeed?.getAttribute('data-chat-group-id');
export const chatThemeStorageKey = `nc:chatTheme:${groupId || 'global'}`;
export const hiddenMessagesStorageKey = `nc:hiddenMessages:${groupId || 'global'}`;
const authUserIdFromFeed = Number(chatFeed?.getAttribute('data-auth-user-id') || 0);
const authUserIdFromMeta = Number(document.querySelector('meta[name="auth-user-id"]')?.getAttribute('content') || 0);
export const authUserId = authUserIdFromFeed || authUserIdFromMeta;
export const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
export const lastReadMessageIdFromServer = Number(chatFeed?.getAttribute('data-last-read-message-id') || 0);
export const latestMessageIdFromServer = Number(chatFeed?.getAttribute('data-latest-message-id') || 0);
export const unreadCountFromServer = Number(chatFeed?.getAttribute('data-unread-count') || 0);
export const hasReadBeforeFromServer = String(chatFeed?.getAttribute('data-has-read-before') || '0') === '1';
export const chatReadUrlFromServer = String(chatFeed?.getAttribute('data-chat-read-url') || '');
export const pollStatsUrlFromServer = String(chatFeed?.getAttribute('data-poll-stats-url') || '');
export const pollVoteUrlTemplateFromServer = String(chatFeed?.getAttribute('data-poll-vote-url-template') || '');
export const pendingMessagesByLocalId = new Map();
export const pollStatsById = new Map();
