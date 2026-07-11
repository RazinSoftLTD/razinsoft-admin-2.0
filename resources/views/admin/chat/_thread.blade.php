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

<div id="thread-root" class="flex min-h-0 flex-1 flex-col"
     data-conv-id="{{ $active->id }}"
     data-is-group="{{ $isGroup ? '1' : '0' }}"
     data-is-admin="{{ $me->isAdmin() ? '1' : '0' }}"
     data-url="{{ route('admin.chat.show', $active) }}"
     data-store-url="{{ route('admin.chat.messages.store', $active) }}"
     data-typing-url="{{ route('admin.chat.typing', $active) }}"
     data-read-url="{{ route('admin.chat.read', $active) }}"
     data-del-base="{{ url('admin/chat/messages') }}">

    {{-- Header --}}
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3">
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

    {{-- Messages --}}
    <div id="chat-scroll" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-gray-50/60 px-5 py-4">
        @foreach ($messages as $msg)
            @php $mine = $msg->user_id === $me->id; $canDel = $mine || $me->isAdmin(); @endphp
            <div class="group flex items-end gap-2 {{ $mine ? 'flex-row-reverse' : '' }}" data-msg-id="{{ $msg->id }}">
                @unless ($mine){!! $avatar($msg->author, 'h-7 w-7') !!}@endunless
                @if ($canDel)
                    <button type="button" data-del="{{ $msg->id }}" title="Delete message"
                            class="mb-5 grid h-7 w-7 shrink-0 place-items-center rounded-full text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-500 group-hover:opacity-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7"/></svg>
                    </button>
                @endif
                <div class="max-w-[75%]">
                    @if ($isGroup && ! $mine)<p class="mb-0.5 px-1 text-xs font-semibold text-[var(--color-heading)]">{{ $msg->author->name ?? '—' }}</p>@endif
                    <div class="rounded-2xl px-3.5 py-2 text-sm {{ $mine ? 'bg-[var(--color-primary)] text-white rounded-br-sm' : 'bg-white text-[var(--color-heading)] border border-gray-100 rounded-bl-sm' }}">
                        @if ($msg->body)<div class="chat-html break-words">{!! $msg->body !!}</div>@endif
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
                    <p class="mt-0.5 px-1 text-[11px] text-gray-400 {{ $mine ? 'text-right' : '' }}">{{ $msg->created_at->format('g:i A') }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Typing indicator --}}
    <div id="typing-ind" class="hidden items-center gap-2 px-5 py-1.5 text-xs text-[var(--color-muted)]">
        <span class="flex gap-0.5">
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:0ms"></span>
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:120ms"></span>
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay:240ms"></span>
        </span>
        <span id="typing-text"></span>
    </div>

    {{-- Rich composer --}}
    <form id="chat-form" class="border-t border-gray-100">
        @csrf
        <div id="chat-file-chip" class="hidden items-center gap-2 border-b border-gray-100 px-4 py-2 text-xs">
            <svg class="h-4 w-4 text-[var(--color-muted)]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/></svg>
            <span id="chat-file-name" class="truncate font-medium text-[var(--color-heading)]"></span>
            <button type="button" id="chat-file-remove" class="ml-1 text-red-500 hover:underline">Remove</button>
        </div>
        <div class="chat-composer">
            <div id="chat-editor"></div>
        </div>
        <div class="flex items-center justify-between gap-2 px-3 py-2">
            <label class="grid h-9 w-9 cursor-pointer place-items-center rounded-lg text-gray-500 hover:bg-gray-50" title="Attach a file">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/></svg>
                <input type="file" id="chat-file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.csv">
            </label>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">
                Send
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
            </button>
        </div>
    </form>
</div>
