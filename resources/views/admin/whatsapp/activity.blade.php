@extends('admin.layouts.app')
@section('title', 'WhatsApp Activity')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">WhatsApp Activity</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Oversight of every connected number — status and full conversation history (read-only).</p>
    </div>

    @if ($accounts->isEmpty())
        <div class="rounded-xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-400">No WhatsApp numbers yet.</div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($accounts as $acc)
                @php $s = $stats[$acc->id]; @endphp
                <a href="{{ route('admin.whatsapp-activity.show', $acc) }}" class="block rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-white" style="background: {{ $acc->color }}">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2Z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-[var(--color-heading)]">{{ $acc->name }}</p>
                            <p class="truncate text-xs text-gray-400">{{ $acc->display_number ? '+'.$acc->display_number : 'not connected' }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $acc->isConnected() ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $acc->isConnected() ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                            {{ $acc->isConnected() ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-gray-50 py-2">
                            <p class="text-base font-bold text-[var(--color-heading)]">{{ number_format($s['chats']) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Chats</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 py-2">
                            <p class="text-base font-bold text-[var(--color-heading)]">{{ number_format($s['messages']) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Messages</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 py-2">
                            <p class="text-base font-bold {{ $s['unread'] ? 'text-emerald-600' : 'text-[var(--color-heading)]' }}">{{ number_format($s['unread']) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400">Unread</p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between text-[11px] text-gray-400">
                        <span>{{ $acc->users->count() }} agent{{ $acc->users->count() === 1 ? '' : 's' }}</span>
                        <span>Last: {{ $s['last_at'] ? \Illuminate\Support\Carbon::parse($s['last_at'])->diffForHumans() : '—' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
