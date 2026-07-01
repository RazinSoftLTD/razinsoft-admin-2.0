@extends('admin.layouts.app')
@section('title', 'Messages')

@section('content')
    <p class="mb-5 text-sm text-[var(--color-muted)]">{{ $messages->total() }} contact message(s) from the website.</p>

    <div class="space-y-4">
        @forelse ($messages as $m)
            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-[var(--color-heading)]">{{ $m->name }}
                            @unless ($m->is_read)<span class="ml-1 rounded-full bg-[var(--color-primary-soft)] px-2 py-0.5 text-[10px] font-bold uppercase text-[var(--color-primary)]">New</span>@endunless
                        </p>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                            <a href="mailto:{{ $m->email }}" class="text-[var(--color-primary)] hover:underline">{{ $m->email }}</a>
                            @if ($m->phone)<span>· {{ $m->phone }}</span>@endif
                            @if ($m->company)<span>· {{ $m->company }}</span>@endif
                            <span>· {{ $m->created_at->diffForHumans() }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($m->service)<span class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">{{ $m->service }}</span>@endif
                        @if ($m->budget)<span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">{{ $m->budget }}</span>@endif
                        <x-admin.del-button :action="route('admin.messages.destroy', $m)" />
                    </div>
                </div>
                <p class="mt-3 whitespace-pre-line text-sm text-gray-600">{{ $m->message }}</p>
                <div class="mt-3 flex gap-3 border-t border-gray-100 pt-3 text-xs font-semibold">
                    <a href="mailto:{{ $m->email }}" class="text-[var(--color-primary)] hover:underline">Reply by email</a>
                    @if ($m->phone)<a href="https://wa.me/{{ preg_replace('/\D/', '', $m->phone) }}" target="_blank" class="text-emerald-600 hover:underline">WhatsApp</a>@endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center text-gray-400">No messages yet.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $messages->links() }}</div>
@endsection
