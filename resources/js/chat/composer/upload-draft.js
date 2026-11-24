// One attachment draft per tab/account/group. Only the small envelope is synchronous.
import { authUserId, groupId, chatFeed } from '../context.js';
import { attachmentInput, chatForm, mentionInput, replyToInput, replyQuoteInput } from './elements.js';
import { refreshAttachmentLabel } from './attachment.js';
import { setReplyTarget } from './reply.js';
import { selectModel } from './ai-options.js';
import { isEditing } from './edit-mode.js';

const storageKey = `nc-upload-v1:${authUserId}:${groupId}`;
const lifetime = 24 * 3600 * 1000;
let dbPromise, envelope, activeFile, ready = false;
let pendingRestore = false;
let lastRequest = null;
const note = () => chatForm.querySelector('[data-upload-draft-note]');
const tell = (message) => {
    const element = note();
    if (!element) return;
    element.hidden = !message;
    element.querySelector('span').textContent = message;
};

function database() {
    return dbPromise ??= new Promise((resolve, reject) => {
        const open = indexedDB.open('nc-attachment-drafts', 1);
        open.onupgradeneeded = () => open.result.createObjectStore('files', {keyPath:'id'}).createIndex('expiry', 'expiry');
        open.onerror = () => reject(open.error);
        open.onsuccess = () => {
            const db = open.result;
            const cursor = db.transaction('files','readwrite').objectStore('files').index('expiry').openCursor(IDBKeyRange.upperBound(Date.now()));
            cursor.onsuccess = () => { if (cursor.result) { cursor.result.delete(); cursor.result.continue(); } };
            resolve(db);
        };
    });
}

async function fileOperation(operation, value) {
    const db = await database();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('files', operation === 'get' ? 'readonly' : 'readwrite');
        const request = tx.objectStore('files')[operation](value);
        tx.oncomplete = () => resolve(request.result);
        tx.onabort = () => reject(tx.error);
        tx.onerror = () => reject(tx.error);
    });
}

function forget() {
    if (envelope) fileOperation('delete', envelope.id).catch(()=>{});
    envelope = activeFile = lastRequest = null;
    pendingRestore = false;
    try { sessionStorage.removeItem(storageKey); } catch {}
    tell('');
}

function save() {
    if (!ready || isEditing()) return;
    const file = attachmentInput.files?.[0];
    if (!file && !pendingRestore && !envelope?.missing) {
        if (envelope) forget();
        return;
    }
    if (file && file !== activeFile) {
        if (envelope) fileOperation('delete', envelope.id).catch(()=>{});
        activeFile = file;
        pendingRestore = false;
        envelope = {id:crypto.randomUUID(), name:file.name, expiry:Date.now()+lifetime};
        tell('');
        const id = envelope.id;
        if (file.size <= Number(chatForm.dataset.uploadLimit || 15 * 1024 * 1024)) {
            fileOperation('put', {id, expiry:envelope.expiry, file}).catch(()=>{
                if (envelope?.id === id) tell('Penyimpanan perangkat tidak tersedia. Jika tab dimuat ulang, pilih lagi '+file.name+'.');
            });
        }
    }
    if (!envelope) return;
    Object.assign(envelope, {text:mentionInput.value, reply:replyToInput.value, quote:replyQuoteInput.value,
        model:chatForm.elements.ai_model?.value, retry:lastRequest});
    try { sessionStorage.setItem(storageKey, JSON.stringify(envelope)); }
    catch { tell('Browser tidak dapat menyimpan draf ini setelah tab dimuat ulang.'); }
}

export function uploadRequestKey(signature) {
    if (lastRequest?.signature !== signature) lastRequest = {signature,key:crypto.randomUUID()};
    save();
    return lastRequest.key;
}
export function finishUploadRequest(key) {
    if (lastRequest?.key === key) { lastRequest = null; save(); }
}
export function uploadDraftBlock() {
    if (pendingRestore) return 'Lampiran sedang dipulihkan.';
    if (envelope?.missing && !attachmentInput.files?.length) return 'Pilih lagi '+envelope.name+' atau hapus lampiran dari draf.';
    return '';
}

export function checkFileLimits() {
    const file = attachmentInput?.files?.[0];
    const policy = chatForm.dataset;
    const target = chatFeed?.querySelector(`[data-message-id="${Number(replyToInput.value)}"]`);
    const ai = /(?:^|[^\w@])@normi(?!\w)/i.test(mentionInput.value) || target?.classList.contains('nc-msg--ai');
    let reason = '';
    if (file && file.size > Number(policy.uploadLimit || 15*1024*1024)) reason = 'Lampiran chat maksimal 15 MB. Pilih file yang lebih kecil.';
    else if (file && ai && Number(policy.docLimit)>0 && (policy.docExtensions || 'pdf ppt pptx docx xlsx').split(' ').includes(file.name.split('.').pop().toLowerCase()) && file.size > Number(policy.docLimit)) {
        reason = 'Normi membaca dokumen hingga 8 MB. Kecilkan file atau pilih bagian yang diperlukan.';
    }
    const box = chatForm.querySelector('[data-doc-limit-note]');
    if (box) { box.textContent = reason; box.hidden = !reason; }
    return reason;
}

export function initUploadDraft() {
    if (!authUserId || !groupId) return;
    try { envelope = JSON.parse(sessionStorage.getItem(storageKey)); } catch {}
    if (envelope && envelope.expiry <= Date.now()) forget();
    const stored = envelope;
    if (stored) {
        pendingRestore = true;
        lastRequest = stored.retry;
        const node = chatFeed?.querySelector(`[data-message-id="${Number(stored.reply)}"]`);
        if (node) setReplyTarget(node, stored.quote);
        else { replyToInput.value = stored.reply || ''; replyQuoteInput.value = stored.quote || ''; }
        mentionInput.value = stored.text || '';
        mentionInput.dispatchEvent(new Event('input',{bubbles:true}));
        if (stored.model) selectModel(stored.model, false);
        fileOperation('get', stored.id).then(record=>{
            if (envelope?.id !== stored.id || attachmentInput.files.length) return;
            if (!record?.file) throw Error();
            const transfer = new DataTransfer();
            transfer.items.add(record.file);
            attachmentInput.files = transfer.files;
            activeFile = attachmentInput.files[0];
            stored.missing = false;
            refreshAttachmentLabel();
        }).catch(()=>{
            if (envelope?.id === stored.id) {
                stored.missing = true;
                tell('Caption dipulihkan. Pilih lagi '+stored.name+' untuk melampirkannya.');
            }
        }).finally(()=>{ pendingRestore = false; checkFileLimits(); save(); });
    }
    ready = true;
    document.addEventListener('nc:attachment-updated', () => { save(); checkFileLimits(); });
    chatForm.addEventListener('input', () => { save(); checkFileLimits(); });
    document.addEventListener('nc:reply-updated', () => {save(); checkFileLimits();});
    chatForm.querySelector('[data-forget-upload]')?.addEventListener('click',()=>{
        attachmentInput.value=''; forget(); refreshAttachmentLabel();
    });
    document.addEventListener('visibilitychange',()=>{ if(document.hidden) save(); });
    window.addEventListener('pagehide', save);
}
