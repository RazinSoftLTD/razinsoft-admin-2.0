@php
    $isGroup = $active->isGroup();
    $other = $active->counterpart($me);
    $title = $active->titleFor($me);
    $subtitle = $isGroup ? $active->members->count().' members' : ($other->designation->name ?? 'Team member');

    $avatar = function ($u, $size = 'h-9 w-9') {
        if ($u && $u->photo_url) {
            return '<img src="'.e($u->photo_url).'" class="'.$size.' rounded-full object-cover" alt="">';
        }
        $initial = strtoupper(substr($u->name ?? '?', 0, 1));
        return '<span class="'.$size.' grid place-items-center rounded-full bg-[var(--color-primary-soft)] text-sm font-bold text-[var(--color-primary)]">'.$initial.'</span>';
    };
@endphp

<div id="thread-root" class="relative flex min-h-0 flex-1 flex-col"
     data-conv-id="{{ $active->id }}"
     data-me="{{ (int) $me->id }}"
     data-counterpart-id="{{ optional($active->counterpart($me))->id }}"
     data-is-group="{{ $isGroup ? '1' : '0' }}"
     data-conv-type="{{ $active->type }}"
     data-is-admin="{{ $me->isAdmin() ? '1' : '0' }}"
     data-url="{{ route('admin.chat.show', $active) }}"
     data-store-url="{{ route('admin.chat.messages.store', $active) }}"
     data-typing-url="{{ route('admin.chat.typing', $active) }}"
     data-read-url="{{ route('admin.chat.read', $active) }}"
     data-older-url="{{ route('admin.chat.older', $active) }}"
     data-del-base="{{ url('admin/chat/messages') }}">

    {{-- Header --}}
    <div class="flex shrink-0 items-center gap-3 border-b border-gray-100 px-5 py-3">
        @if ($isGroup)
            <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-gray-100 text-gray-500">
                @if ($active->photo_url)
                    <img src="{{ $active->photo_url }}" class="h-full w-full object-cover" alt="">
                @else
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 9h12M6 15h12M9 4 7 20M17 4l-2 16"/></svg>
                @endif
            </span>
        @else
            <span class="relative">
                {!! $avatar($other, 'h-10 w-10') !!}
                <span data-online="{{ $other->id }}" class="{{ $other->isOnline() ? '' : 'hidden' }} absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-green-500 ring-2 ring-white"></span>
            </span>
        @endif
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-[var(--color-heading)]">{{ $title }}</p>
            @if ($isGroup)
                <p class="truncate text-xs text-[var(--color-muted)]">@if ($active->slug)<span class="font-medium">#{{ $active->slug }}</span> · @endif{{ $subtitle }}</p>
            @else
                <p class="truncate text-xs text-[var(--color-muted)]">
                    <span id="peer-status" data-peer="{{ $other->id }}">{{ $other->isOnline() ? 'Online' : 'Offline' }}</span> · {{ $subtitle }}
                </p>
            @endif
        </div>
        <div class="ml-auto flex items-center gap-2">
            @if ($isGroup)
                <div class="mr-1 hidden -space-x-2 xl:flex">
                    @foreach ($active->members->take(4) as $mem)
                        <span title="{{ $mem->name }}" class="rounded-full ring-2 ring-white">{!! $avatar($mem, 'h-7 w-7') !!}</span>
                    @endforeach
                </div>
            @endif
            {{-- Files --}}
            <button type="button" id="chat-files-btn" title="Shared files"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/></svg>
                Files
            </button>
            {{-- Search in conversation --}}
            <button type="button" id="chat-insearch-btn" title="Search in conversation"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
                Search
            </button>
            {{-- Mute --}}
            <button type="button" id="chat-mute-btn" title="Mute notifications"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-600 transition hover:bg-gray-50">
                <svg data-bell-on class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                <svg data-bell-off class="hidden h-4 w-4 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-9.3-5M6 8c0 7-3 9-3 9h13M13.7 21a2 2 0 0 1-3.4 0M3 3l18 18"/></svg>
            </button>
            {{-- More --}}
            <div class="relative">
                <button type="button" id="chat-more-btn" title="More"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                    <span class="hidden sm:inline">More</span>
                </button>
                <div id="chat-more-menu" style="top:2.75rem" class="absolute right-0 z-30 hidden min-w-[12rem] overflow-hidden rounded-xl border border-gray-100 bg-white py-1 text-sm shadow-lg">
                    <button type="button" data-more="mark-read" class="flex w-full items-center gap-2.5 px-3 py-2 text-left font-medium text-[var(--color-heading)] hover:bg-gray-50">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>Mark all as read
                    </button>
                    @if ($isGroup && $active->isManagedBy($me))
                        <a href="{{ route('admin.chat.groups.edit', $active) }}" class="flex w-full items-center gap-2.5 px-3 py-2 text-left font-medium text-[var(--color-heading)] hover:bg-gray-50">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 0 1-4 0v-.2a1.7 1.7 0 0 0-2.9-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.1-2.9H3a2 2 0 0 1 0-4h.2a1.7 1.7 0 0 0 1.1-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 2.9-1.1V3a2 2 0 0 1 4 0v.2a1.7 1.7 0 0 0 2.9 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9Z"/></svg>Channel settings
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- In-conversation search bar (toggled from the header) --}}
    <div id="chat-search-bar" class="hidden shrink-0 items-center gap-2 border-b border-gray-100 bg-white px-5 py-2">
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="chat-search-input" placeholder="Search in this conversation…" class="h-8 flex-1 border-0 bg-transparent text-sm focus:outline-none focus:ring-0">
        <span id="chat-search-count" class="text-xs text-gray-400"></span>
        <button type="button" id="chat-search-close" class="grid h-7 w-7 place-items-center rounded-lg text-gray-400 hover:bg-gray-100"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
    </div>

    {{-- Shared-files dropdown --}}
    <div id="chat-files-panel" style="right:1rem" class="absolute top-16 z-30 hidden max-h-80 w-72 overflow-y-auto rounded-xl border border-gray-100 bg-white p-2 shadow-lg">
        <p class="px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-gray-400">Shared files</p>
        <div id="chat-files-list" class="space-y-0.5"></div>
    </div>

    {{-- Drag & drop overlay --}}
    <div id="chat-drop-overlay" class="pointer-events-none absolute inset-0 z-40 m-3 hidden flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[var(--color-primary)] bg-[var(--color-primary-soft)]/95 text-[var(--color-primary)]">
        <svg class="h-12 w-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0 4 4m-4-4-4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        <p class="mt-2 text-sm font-bold">Drop the file to attach</p>
    </div>

    {{-- Messages --}}
    <div id="chat-scroll" class="flex min-h-0 flex-1 flex-col space-y-3 overflow-y-auto bg-gray-50/60 px-5 py-4">
        {{-- Spacer: pushes a short thread to the bottom (WhatsApp-style); collapses to 0 once it overflows. --}}
        <div class="flex-1"></div>
        {{-- Load earlier messages --}}
        <div id="chat-load-earlier" class="{{ ($hasMore ?? false) ? 'flex' : 'hidden' }} justify-center pb-1">
            <button type="button" id="chat-load-earlier-btn" class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-gray-600 shadow-sm hover:bg-gray-50">Load earlier messages</button>
        </div>
        @php $lastDate = null; @endphp
        @foreach ($messages as $msg)
            @php
                $mine = $msg->user_id === $me->id; $q = $msg->quoted();
                $day = $msg->created_at->toDateString();
                $showSep = $day !== $lastDate; $lastDate = $day;
                $dayLabel = $msg->created_at->isToday() ? 'Today' : ($msg->created_at->isYesterday() ? 'Yesterday' : $msg->created_at->format('F j, Y'));
            @endphp
            @if ($showSep)
                <div class="flex justify-center py-1" data-date-sep="{{ $day }}">
                    <span class="rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-gray-500 shadow-sm ring-1 ring-gray-100">{{ $dayLabel }}</span>
                </div>
            @endif
            <div class="group flex items-end gap-2 {{ $mine ? 'flex-row-reverse' : '' }}"
                 data-msg-id="{{ $msg->id }}" data-mine="{{ $mine ? '1' : '0' }}"
                 data-author="{{ $msg->author->name ?? '—' }}"
                 data-reactions="{{ json_encode($msg->reactionMap()) }}"
                 data-created="{{ $msg->created_at->timestamp }}">
                @unless ($mine){!! $avatar($msg->author, 'h-7 w-7') !!}@endunless
                <div class="max-w-[75%]" data-bubble-wrap>
                    @if ($isGroup && ! $mine)<p class="mb-0.5 px-1 text-xs font-semibold text-[var(--color-heading)]">{{ $msg->author->name ?? '—' }}</p>@endif
                    <div class="rounded-2xl px-3.5 py-2 text-sm {{ $mine ? 'bg-[var(--color-primary)] text-white rounded-br-sm' : 'bg-white text-[var(--color-heading)] border border-gray-100 rounded-bl-sm' }}">
                        @if ($q)
                            <div class="mb-1 rounded-md border-l-4 px-2 py-1 text-xs {{ $mine ? 'border-white/70 bg-white/15' : 'border-[var(--color-primary)] bg-gray-100' }}">
                                <span class="block font-semibold {{ $mine ? 'text-white' : 'text-[var(--color-primary)]' }}">{{ $q['author'] }}</span>
                                <span class="block truncate {{ $mine ? 'text-white/80' : 'text-gray-500' }}">{{ $q['is_image'] ? '📷 Photo' : $q['preview'] }}</span>
                            </div>
                        @endif
                        @if ($msg->body)<div class="chat-html break-words">{!! $msg->body !!}</div>@endif
                        @if (!empty($msg->checklist))
                            <ul class="chat-checklist mt-1 space-y-1" data-msg-checklist="{{ $msg->id }}">
                                @foreach ($msg->checklist as $ci => $item)
                                    <li class="flex items-start gap-2 text-sm">
                                        <button type="button" data-check-toggle data-msg="{{ $msg->id }}" data-idx="{{ $ci }}"
                                                class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded border {{ !empty($item['checked']) ? 'border-emerald-500 bg-emerald-500 text-white' : ($mine ? 'border-white/40' : 'border-gray-300') }}">
                                            @if (!empty($item['checked']))<svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>@endif
                                        </button>
                                        <span class="{{ !empty($item['checked']) ? 'line-through opacity-60' : '' }}">{{ $item['text'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($msg->attachment)
                            @if ($msg->is_image)
                                <a href="{{ $msg->attachment_url }}" target="_blank" rel="noopener"><img src="{{ $msg->attachment_url }}" class="mt-1 max-h-56 rounded-lg" alt="{{ $msg->attachment_name }}"></a>
                            @else
                                <a href="{{ $msg->attachment_url }}" target="_blank" rel="noopener" class="mt-1 flex items-center gap-2 rounded-lg {{ $mine ? 'bg-white/15' : 'bg-gray-50 border border-gray-100' }} px-3 py-2">
                                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/></svg>
                                    <span class="truncate text-xs font-medium">{{ $msg->attachment_name }}</span>
                                </a>
                            @endif
                        @endif
                    </div>
                    <div data-reactions-box class="mt-1 flex flex-wrap gap-1 {{ $mine ? 'justify-end' : '' }}"></div>
                    <p class="mt-0.5 px-1 text-[11px] text-gray-400 {{ $mine ? 'text-right' : '' }}">
                        {{ $msg->created_at->format('g:i A') }}<span data-edited-tag class="{{ $msg->edited_at ? '' : 'hidden' }}"> · edited</span>
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Typing indicator --}}
    <div id="typing-ind" class="hidden shrink-0 items-center gap-2 px-5 py-1.5 text-xs text-[var(--color-muted)]">
        <span class="flex gap-0.5">
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:0ms"></span>
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:120ms"></span>
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:240ms"></span>
        </span>
        <span id="typing-text"></span>
    </div>

    {{-- Rich composer — always pinned at the bottom, never shrinks --}}
    <form id="chat-form" class="shrink-0 border-t border-gray-100">
        @csrf
        <div id="chat-edit-banner" class="hidden items-center gap-2 border-b border-indigo-100 bg-indigo-50 px-4 py-2 text-xs text-indigo-700">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            <span class="font-semibold">Editing message</span>
            <button type="button" id="chat-edit-cancel" class="ml-auto font-semibold text-indigo-500 hover:underline">Cancel</button>
        </div>
        <div id="chat-reply-banner" class="hidden items-center gap-2 border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs">
            <span class="h-8 w-1 shrink-0 rounded bg-[var(--color-primary)]"></span>
            <div class="min-w-0 flex-1">
                <span id="chat-reply-author" class="block font-semibold text-[var(--color-primary)]"></span>
                <span id="chat-reply-text" class="block truncate text-gray-500"></span>
            </div>
            <button type="button" id="chat-reply-cancel" class="shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600" title="Cancel reply">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div id="chat-file-chip" class="hidden items-center gap-2 border-b border-gray-100 px-4 py-2 text-xs">
            <svg class="h-4 w-4 text-[var(--color-muted)]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/></svg>
            <span id="chat-file-name" class="truncate font-medium text-[var(--color-heading)]"></span>
            <button type="button" id="chat-file-remove" class="ml-1 text-red-500 hover:underline">Remove</button>
        </div>
        {{-- Composer: clean borderless bar + rich FORMAT / INSERT / SHORTCUTS panel --}}
        @php
            $fmtTools = [
                ['bold', 'Bold', 'M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8'],
                ['italic', 'Italic', 'M19 4h-9M14 20H5M15 4 9 20'],
                ['underline', 'Underline', 'M6 4v6a6 6 0 0 0 12 0V4M4 20h16'],
                ['strikeThrough', 'Strikethrough', 'M16 4H9a3 3 0 0 0-2.83 4M14 12a4 4 0 0 1 0 8H6M4 12h16'],
                ['code', 'Code', 'M8 8l-4 4 4 4M16 8l4 4-4 4'],
                ['blockquote', 'Quote', 'M6 17h3l2-4V7H5v6h3zM14 17h3l2-4V7h-6v6h3z'],
                ['createLink', 'Link', 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71'],
                ['insertUnorderedList', 'Bulleted list', 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'],
                ['insertOrderedList', 'Numbered list', 'M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.4-2-1'],
            ];
            $insertTools = [
                ['file', 'File', 'M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8'],
                ['image', 'Image', 'M4 5h16v14H4zM4 16l4-4 3 3 4-4 5 5M9.5 9.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0'],
                ['emoji', 'Emoji', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM9 10h.01M15 10h.01M8.5 14.5c.8.9 2 1.5 3.5 1.5s2.7-.6 3.5-1.5'],
                ['mention', 'Mention', 'M16 12a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm0 0v1.5a2.5 2.5 0 0 0 5 0V12a9 9 0 1 0-4 7.5'],
                ['channel', 'Channel', 'M6 9h12M6 15h12M9 4 7 20M17 4l-2 16'],
                ['flag', 'Flag', 'M5 21V4h13l-2 4 2 4H5'],
                ['task', 'Task', 'M9 6h11M9 12h11M9 18h11M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2'],
                ['template', 'Template', 'M4 5h16v5H4zM4 14h9v5H4zM16 14h4v5h-4z'],
            ];
            $shortcuts = [
                ['*bold*', '*Bold*'], ['_italic_', '_Italic_'], ['-strike-', '- Strikethrough -'],
                ['`code`', '`Code`'], ['> quote ', '> Quote'], ['[Link](url)', '[Link](url)'],
                ['• ', '• List'], ['1. ', '1. List'],
            ];
        @endphp
        <div class="relative bg-white px-4 py-3">
            {{-- Rich panel (opens above on the "Aa" / "+" trigger) --}}
            <div id="chat-format-panel" style="left:1rem;right:1rem;bottom:100%" class="absolute z-30 mb-2 hidden rounded-2xl border border-gray-100 bg-white p-5 shadow-xl">
                <p class="mb-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-400">Format</p>
                <div class="mb-5 flex flex-wrap gap-2">
                    @foreach ($fmtTools as [$cmd, $label, $icon])
                        <button type="button" data-fmt="{{ $cmd }}" tabindex="-1" title="{{ $label }}" class="group flex w-16 flex-col items-center gap-1.5">
                            <span class="grid h-11 w-11 place-items-center rounded-xl border border-gray-200 text-gray-600 transition group-hover:border-[var(--color-primary)] group-hover:text-[var(--color-primary)]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                            </span>
                            <span class="text-[11px] text-gray-500">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
                <p class="mb-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-400">Insert</p>
                <div class="mb-5 flex flex-wrap gap-2">
                    @foreach ($insertTools as [$key, $label, $icon])
                        <button type="button" data-insert="{{ $key }}" tabindex="-1" title="{{ $label }}" class="group flex w-16 flex-col items-center gap-1.5">
                            <span class="grid h-11 w-11 place-items-center rounded-xl border border-gray-200 text-gray-600 transition group-hover:border-[var(--color-primary)] group-hover:text-[var(--color-primary)]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                            </span>
                            <span class="text-[11px] text-gray-500">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
                <p class="mb-2.5 text-[11px] font-bold uppercase tracking-wider text-gray-400">Shortcuts</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($shortcuts as [$insert, $label])
                        <button type="button" data-shortcut="{{ $insert }}" tabindex="-1" class="rounded-full bg-[var(--color-primary-soft)] px-3 py-1 text-xs font-medium text-[var(--color-primary)]">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Checklist builder (hidden until opened) --}}
            <div id="chat-checklist" class="mb-2 hidden rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">
                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Checklist</p>
                <div id="chat-checklist-items" class="space-y-1"></div>
                <div class="mt-1.5 flex items-center gap-2">
                    <input id="chat-checklist-input" type="text" maxlength="500" placeholder="Add an item, press Enter"
                           class="h-8 flex-1 rounded-lg border border-gray-200 px-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                    <button type="button" id="chat-checklist-add" class="rounded-lg bg-[var(--color-primary)] px-3 py-1.5 text-xs font-semibold text-white">Add</button>
                </div>
            </div>

            <div class="flex items-end gap-3">
                {{-- + trigger (opens the panel) --}}
                <button type="button" id="chat-plus" title="Format &amp; insert"
                        class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[var(--color-primary-soft)] text-[var(--color-primary)]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                </button>

                {{-- Borderless bar: quick icons + input --}}
                <div class="chat-input-wrap min-w-0 flex-1">
                    {{-- Quick icon row (hidden until the + button reveals it) --}}
                    <div id="chat-quickbar" class="mb-1.5 hidden items-center gap-1">
                        <button type="button" id="chat-format-btn" data-quick="format" title="Formatting" tabindex="-1" class="chat-quick flex h-7 items-center rounded-md px-1.5 text-xs font-semibold text-[var(--color-primary)] transition hover:bg-gray-100">
                            <span style="text-decoration:underline">Aa</span>
                        </button>
                        <label class="chat-quick grid h-7 w-7 cursor-pointer place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" title="Attach a file">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/></svg>
                            <input type="file" id="chat-file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.csv">
                        </label>
                        <button type="button" data-quick="emoji" title="Emoji" tabindex="-1" class="chat-quick grid h-7 w-7 place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M9 10h.01M15 10h.01M8.5 14.5c.8.9 2 1.5 3.5 1.5s2.7-.6 3.5-1.5"/></svg>
                        </button>
                        <button type="button" id="chat-mention-btn" data-quick="mention" title="Mention a teammate" tabindex="-1" class="chat-quick grid h-7 w-7 place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
                        </button>
                        <button type="button" data-quick="channel" title="Channel" tabindex="-1" class="chat-quick grid h-7 w-7 place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 9h12M6 15h12M9 4 7 20M17 4l-2 16"/></svg>
                        </button>
                        <button type="button" id="chat-checklist-btn" data-quick="checklist" title="Checklist" tabindex="-1" class="chat-quick grid h-7 w-7 place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m8.5 12 2.2 2.2L15.5 9.5"/></svg>
                        </button>
                        <button type="button" data-quick="code" title="Code" tabindex="-1" class="chat-quick grid h-7 w-7 place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6c-1.5 0-2 1-2 2v2c0 1-1 2-2 2 1 0 2 1 2 2v2c0 1 .5 2 2 2M16 6c1.5 0 2 1 2 2v2c0 1 1 2 2 2-1 0-2 1-2 2v2c0 1-.5 2-2 2"/></svg>
                        </button>
                    </div>
                    {{-- Input row --}}
                    <div class="flex items-end gap-2">
                        <div id="chat-input" contenteditable="true" data-placeholder="Type a message… (Enter to send, Shift+Enter for a new line)"
                             class="chat-composer min-w-0 flex-1 self-center overflow-y-auto py-2 text-sm leading-5 text-gray-800 outline-none" style="min-height:1.75rem;max-height:10rem"></div>
                        <button type="button" id="chat-emoji-btn" title="Insert emoji" tabindex="-1" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M9 10h.01M15 10h.01M8.5 14.5c.8.9 2 1.5 3.5 1.5s2.7-.6 3.5-1.5"/></svg>
                        </button>
                        <button type="submit" title="Send" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--color-primary)] text-white shadow-sm transition hover:bg-[var(--color-primary-hover)]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
