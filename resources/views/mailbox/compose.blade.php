@extends('layouts.app', ['title' => 'Tulis Pesan - Normchat'])

@section('content')
    <section class="px-4 pb-6 pt-5">
        <a href="{{ route('mailbox.inbox') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-700">
            <span aria-hidden="true">&larr;</span> Kembali
        </a>

        <h1 class="mt-3 font-display text-xl font-extrabold text-slate-900">Tulis Pesan</h1>
        <p class="mt-1 text-sm text-slate-500">Kirim pesan baru melalui Interdotz Mailbox.</p>

        @if(session('error'))
            <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('mailbox.send') }}" class="mt-5 space-y-3" id="composeForm">
            @csrf

            <div class="panel-card px-4 py-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dari</label>
                <p class="mt-1 text-sm text-slate-600">{{ $user->email }}</p>
            </div>

            <div class="panel-card px-4 py-3">
                <label for="to" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kepada</label>
                <input
                    id="to"
                    type="text"
                    name="to"
                    value="{{ old('to', request('reply_to', '')) }}"
                    placeholder="username atau email penerima"
                    required
                    class="mt-1 w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400"
                />
                @error('to')
                    <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="panel-card px-4 py-3">
                <label for="subject" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Subjek</label>
                <input
                    id="subject"
                    type="text"
                    name="subject"
                    value="{{ old('subject', request('subject', '')) }}"
                    placeholder="Subjek pesan"
                    required
                    maxlength="255"
                    class="mt-1 w-full bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400"
                />
                @error('subject')
                    <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-2xl border border-[#dbe6ff] bg-white shadow-sm overflow-hidden">
                <label class="block px-4 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Isi Pesan</label>
                <div id="quill-editor">{!! old('body', '') !!}</div>
                <input type="hidden" name="body" id="bodyInput" />
                @error('body')
                    <p class="px-4 pb-2 text-xs text-rose-500">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-cta flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                </svg>
                Kirim Pesan
            </button>
        </form>
    </section>

    {{-- Quill Rich Text Editor --}}
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <style>
        #quill-editor .ql-editor {
            min-height: 200px;
            font-size: 14px;
            line-height: 1.6;
            color: #1e293b;
            font-family: Inter, sans-serif;
            padding: 12px 16px;
        }
        #quill-editor .ql-editor.ql-blank::before {
            color: #94a3b8;
            font-style: normal;
            padding-left: 0;
        }
        .ql-toolbar.ql-snow {
            border: none !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 4px 6px !important;
            display: flex;
            flex-wrap: wrap;
            gap: 0;
        }
        .ql-container.ql-snow {
            border: none !important;
        }
        .ql-snow .ql-stroke {
            stroke: #64748b;
        }
        .ql-snow .ql-fill {
            fill: #64748b;
        }
        .ql-snow .ql-picker-label {
            color: #64748b;
        }
        /* Mobile-friendly toolbar */
        .ql-toolbar .ql-formats {
            margin-right: 4px !important;
            margin-bottom: 2px !important;
        }
        .ql-snow .ql-picker {
            font-size: 12px !important;
        }
        .ql-snow .ql-picker-label {
            padding: 2px 4px !important;
        }
        .ql-snow.ql-toolbar button {
            width: 26px !important;
            height: 26px !important;
            padding: 3px !important;
        }
        .ql-snow.ql-toolbar button svg {
            width: 16px !important;
            height: 16px !important;
        }
        /* Picker dropdown on mobile */
        .ql-snow .ql-picker-options {
            max-height: 200px;
            overflow-y: auto;
        }
        .ql-snow .ql-tooltip {
            left: 4px !important;
            right: 4px !important;
            max-width: calc(100vw - 64px);
        }
        .ql-snow .ql-tooltip input[type=text] {
            width: 100% !important;
            max-width: 200px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var quill = new Quill('#quill-editor', {
                theme: 'snow',
                placeholder: 'Tulis pesan kamu di sini...',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }, { 'size': ['small', false, 'large', 'huge'] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'list': 'ordered' }, { 'list': 'bullet' }, { 'indent': '-1' }, { 'indent': '+1' }],
                        [{ 'align': [] }, 'blockquote', 'code-block'],
                        ['link', 'image', 'video'],
                        ['clean']
                    ]
                }
            });

            document.getElementById('composeForm').addEventListener('submit', function () {
                document.getElementById('bodyInput').value = quill.root.innerHTML;
            });
        });
    </script>
@endsection
