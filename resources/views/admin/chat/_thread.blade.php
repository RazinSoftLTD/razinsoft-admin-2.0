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
        @if ($isGroup)
            <div class="ml-auto flex items-center gap-3">
                <div class="hidden -space-x-2 sm:flex">
                    @foreach ($active->members->take(5) as $mem)
                        <span title="{{ $mem->name }}" class="ring-2 ring-white rounded-full">{!! $avatar($mem, 'h-7 w-7') !!}</span>
                    @endforeach
                </div>
                @if ($active->isManagedBy($me))
                    <a href="{{ route('admin.chat.groups.edit', $active) }}" title="Channel settings"
                       class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 0 1-4 0v-.2a1.7 1.7 0 0 0-2.9-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.1-2.9H3a2 2 0 0 1 0-4h.2a1.7 1.7 0 0 0 1.1-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 2.9-1.1V3a2 2 0 0 1 4 0v.2a1.7 1.7 0 0 0 2.9 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9Z"/></svg>
                    </a>
                @endif
            </div>
        @endif
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
        {{-- WhatsApp-style composer: attach + auto-grow pill + round send --}}
        <div class="flex items-end gap-2 px-3 py-3" style="background:#f0f2f5">
            <label class="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-full text-gray-500 transition hover:bg-gray-200" title="Attach a file">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                <input type="file" id="chat-file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.csv">
            </label>
            <div class="chat-input-wrap flex flex-1 flex-col overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200">
                {{-- Formatting toolbar --}}
                <div class="flex items-center gap-1.5 border-b border-gray-100 px-2.5 py-1.5">
                    @php
                        // Grouped Lucide-style icons; `null` marks a divider between groups.
                        $tools = [
                            ['bold', 'Bold', 'M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8'],
                            ['italic', 'Italic', 'M19 4h-9M14 20H5M15 4 9 20'],
                            ['underline', 'Underline', 'M6 4v6a6 6 0 0 0 12 0V4M4 20h16'],
                            ['strikeThrough', 'Strikethrough', 'M16 4H9a3 3 0 0 0-2.83 4M14 12a4 4 0 0 1 0 8H6M4 12h16'],
                            null,
                            ['insertUnorderedList', 'Bulleted list', 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'],
                            ['insertOrderedList', 'Numbered list', 'M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.4-2-1'],
                            null,
                            ['blockquote', 'Quote', 'M6 17h3l2-4V7H5v6h3zM14 17h3l2-4V7h-6v6h3z'],
                            ['createLink', 'Add link', 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71'],
                        ];
                    @endphp
                    @foreach ($tools as $tool)
                        @if ($tool === null)
                            <span class="mx-0.5 h-5 w-px shrink-0 bg-gray-200"></span>
                        @else
                            <button type="button" data-fmt="{{ $tool[0] }}" title="{{ $tool[1] }}" tabindex="-1"
                                    class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tool[2] }}"/></svg>
                            </button>
                        @endif
                    @endforeach
                    <span class="mx-0.5 h-5 w-px shrink-0 bg-gray-200"></span>
                    <button type="button" id="chat-checklist-btn" title="Add a checklist" tabindex="-1" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6h11M9 12h11M9 18h11M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/></svg>
                    </button>
                    <button type="button" id="chat-emoji-btn" title="Insert emoji" tabindex="-1" class="relative grid h-8 w-8 shrink-0 place-items-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M9 10h.01M15 10h.01M8.5 14.5c.8.9 2 1.5 3.5 1.5s2.7-.6 3.5-1.5"/></svg>
                    </button>
                </div>
                {{-- Checklist builder (hidden until the button is clicked) --}}
                <div id="chat-checklist" class="hidden border-b border-gray-100 px-3 py-2">
                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Checklist</p>
                    <div id="chat-checklist-items" class="space-y-1"></div>
                    <div class="mt-1.5 flex items-center gap-2">
                        <input id="chat-checklist-input" type="text" maxlength="500" placeholder="Add an item, press Enter"
                               class="h-8 flex-1 rounded-lg border border-gray-200 px-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                        <button type="button" id="chat-checklist-add" class="rounded-lg bg-[var(--color-primary)] px-3 py-1.5 text-xs font-semibold text-white">Add</button>
                    </div>
                </div>
                <div id="chat-input" contenteditable="true" data-placeholder="Type a message… (Enter to send, Shift+Enter for a new line)"
                     class="chat-composer overflow-y-auto px-4 py-3 text-sm leading-5 text-gray-800 outline-none" style="max-height:10rem;min-height:2.75rem"></div>
            </div>
            <button type="submit" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[var(--color-primary)] text-white shadow-sm transition hover:bg-[var(--color-primary-hover)]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
            </button>
        </div>
    </form>
</div>
