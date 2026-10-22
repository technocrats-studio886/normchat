import './bootstrap';
import { loadSavedChatTheme, initChatThemePicker } from './chat/theme.js';
import { initChatShell } from './chat/shell.js';
import { initRenderedFeed } from './chat/feed.js';
import { initReadState } from './chat/read-state.js';
import { initRealtime } from './chat/realtime.js';
import { initComposer } from './chat/composer.js';
import { initMentionMenu } from './chat/mention-menu.js';
import { initGroupMenu } from './chat/group-menu.js';
import { initMessageSearch } from './chat/message-search.js';
import { initArchiveTabs } from './settings/archive-tabs.js';
import { initDeleteGroupDialog } from './settings/delete-group.js';
import { initMembershipWatch } from './settings/membership-watch.js';
import { initAvatarPreview } from './ui/avatar-preview.js';
import { initGroupSearch } from './ui/group-search.js';
import { initJoinById } from './ui/join-by-id.js';
import { initPackagePickers } from './ui/package-picker.js';
import { initPasswordToggles } from './ui/password-toggle.js';
import { initSharePickers } from './ui/share-picker.js';
import { initShareButtons } from './ui/share-buttons.js';

// Dulu script inline di view (berjalan sebelum app.js), jadi tetap diinisialisasi paling awal.
initChatShell();
initPasswordToggles();
initAvatarPreview();
initJoinById();
initSharePickers();
initPackagePickers();
initArchiveTabs();
initDeleteGroupDialog();
initChatThemePicker();
initMembershipWatch();

// Urutan inisialisasi mengikuti urutan app.js sebelum dipecah.
loadSavedChatTheme();
initRenderedFeed();
initReadState();
initRealtime();
initComposer();
initMentionMenu();
initGroupMenu();
initMessageSearch();
initGroupSearch();
initShareButtons();
