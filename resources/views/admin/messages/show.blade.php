@extends('admin.layouts.app')
@section('title', 'Enquiry #'.$message->id)

@section('content')
    @php
        $me = auth()->user();
        $canEdit = $me->hasPermission('messages.edit');
        $canDelete = $me->hasPermission('messages.delete');
        $phoneDigits = preg_replace('/\D/', '', (string) $message->phone);
    @endphp

    <a href="{{ route('admin.messages.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to Contact Us
    </a>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Details --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-lg font-bold text-[var(--color-heading)]">{{ $message->name }}</h1>
                        <p class="mt-0.5 text-xs text-gray-400">Enquiry #{{ $message->id }} · {{ $message->created_at->format('d M Y, g:i A') }}</p>
                    </div>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $message->statusStyle() }}">{{ $message->statusLabel() }}</span>
                </div>

                <dl class="mt-5 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Email</dt><dd class="mt-0.5 text-sm"><a href="mailto:{{ $message->email }}" class="text-[var(--color-primary)] hover:underline">{{ $message->email }}</a></dd></div>
                    <div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Phone</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $message->phone ?: '—' }}</dd></div>
                    @if ($message->company)<div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Company</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $message->company }}</dd></div>@endif
                    @if ($message->service)<div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Service</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $message->service }}</dd></div>@endif
                    @if ($message->budget)<div><dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Budget</dt><dd class="mt-0.5 text-sm text-[var(--color-heading)]">{{ $message->budget }}</dd></div>@endif
                </dl>

                <div class="mt-5">
                    <dt class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Message</dt>
                    <p class="mt-1 whitespace-pre-line rounded-lg bg-gray-50 p-4 text-sm text-gray-600">{{ $message->message }}</p>
                </div>

                <div class="mt-5 flex flex-wrap gap-3 border-t border-gray-100 pt-4 text-xs font-semibold">
                    <a href="mailto:{{ $message->email }}" class="text-[var(--color-primary)] hover:underline">Reply by email</a>
                    @if ($phoneDigits)<a href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener" class="text-emerald-600 hover:underline">WhatsApp</a>@endif
                </div>
            </div>
        </div>

        {{-- Status + actions --}}
        <div class="space-y-6">
            <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Status</h2>
                @if ($canEdit)
                    <form method="POST" action="{{ route('admin.messages.status', $message) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <select name="status" class="h-10 w-full rounded-lg border-gray-200 text-sm">
                            @foreach (\App\Models\ContactMessage::STATUSES as $k => $label)
                                <option value="{{ $k }}" @selected($message->status === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="w-full rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">Update status</button>
                    </form>
                @else
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $message->statusStyle() }}">{{ $message->statusLabel() }}</span>
                @endif
            </div>

            @if ($canDelete)
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="mb-3 text-sm font-bold text-[var(--color-heading)]">Danger zone</h2>
                    <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" onsubmit="return confirm('Delete this enquiry permanently?')">
                        @csrf @method('DELETE')
                        <button class="w-full rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-100">Delete enquiry</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
