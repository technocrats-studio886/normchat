// Alamat fitur produktivitas grup (App\Http\Controllers\GroupToolsController), relatif terhadap /groups/{id}.

import { chatFeed } from '../context.js';
import { chatApiRequest } from '../utils.js';

const base = () => String(chatFeed?.getAttribute('data-tools-base') || '').replace(/\/$/, '');

export const toolsAvailable = () => base() !== '';

export const toolsRequest = (path, method = 'GET', body = undefined) => chatApiRequest(`${base()}${path}`, { method, body });

export const viewerIsModerator = () => chatFeed?.getAttribute('data-viewer-is-moderator') === '1';

export const groupFrozen = () => chatFeed?.getAttribute('data-group-frozen') === '1';
