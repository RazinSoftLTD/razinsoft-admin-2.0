{{--
    The WhatsApp Activity tab bar, shared by the pages it spans.

    Three questions about the same thing: what came in during a window, how each number is
    doing, and how the site's WhatsApp button is performing. They are separate pages because
    each carries its own state (a period, a page, a click window) — the bar makes them read as
    one place. No page title above it: the top bar already names the page, and repeating it
    just pushed everything down.

    Expects $active: 'report' | 'numbers' | 'button' | 'config' | 'labels'.
--}}
@php
    $u = auth()->user();
    $mayActivity = $u->hasPermission('whatsapp.activity');
    $maySettings = $u->hasPermission('whatsapp.settings');

    // A tab nobody may open should not be offered — the two halves are separately permitted.
    $tabs = array_filter([
        'report' => $mayActivity ? ['label' => 'Conversation', 'url' => route('admin.whatsapp-activity', array_filter([
            'period' => request('period'), 'from' => request('from'), 'to' => request('to'),
        ]))] : null,
        'numbers' => $mayActivity ? ['label' => 'Connected', 'url' => route('admin.whatsapp-activity', ['tab' => 'numbers']), 'badge' => $tabCount ?? \App\Models\WhatsappAccount::count()] : null,
        'button' => $mayActivity ? ['label' => 'Wp ShortLink', 'url' => route('admin.whatsapp-links')] : null,
        // Labels and quick replies are the team's words, not the numbers' wiring.
        'labels' => ($u->hasPermission('whatsapp.labels') || $u->hasPermission('whatsapp.quick_replies'))
            ? ['label' => 'Labels & Replies', 'url' => route('admin.whatsapp-settings', ['section' => 'labels'])] : null,
        'config' => $maySettings ? ['label' => 'Configuration', 'url' => route('admin.whatsapp-settings')] : null,
    ]);
@endphp

<div class="mb-6 flex flex-wrap gap-1 border-b border-gray-100">
    @foreach ($tabs as $key => $tab)
        <a href="{{ $tab['url'] }}"
           class="border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $active === $key ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
            {{ $tab['label'] }}@isset($tab['badge'])<span class="ml-1 opacity-70">({{ $tab['badge'] }})</span>@endisset
        </a>
    @endforeach
</div>
