@php
    // Sub-navigation shared by every Finance page.
    $tabs = [
        ['label' => 'Dashboard', 'route' => 'admin.finance.dashboard'],
        ['label' => 'Wallets', 'route' => 'admin.finance.wallets'],
        ['label' => 'Bank Accounts', 'route' => 'admin.finance.banks'],
        ['label' => 'Transactions', 'route' => 'admin.finance.transactions'],
        ['label' => 'Income', 'route' => 'admin.finance.income'],
        ['label' => 'Expenses', 'route' => 'admin.finance.expenses'],
        ['label' => 'Transfers', 'route' => 'admin.finance.transfers'],
        ['label' => 'Currency Conversion', 'route' => 'admin.finance.conversions'],
        ['label' => 'Receivables', 'route' => 'admin.finance.receivables'],
        ['label' => 'Payables', 'route' => 'admin.finance.payables'],
        ['label' => 'VAT & Tax', 'route' => 'admin.finance.taxes'],
        ['label' => 'Reports', 'route' => 'admin.finance.reports'],
    ];
@endphp

<div class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-100 ">
    @foreach ($tabs as $tab)
        @php $on = request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}"
           class="shrink-0 border-b-2 px-3.5 py-2.5 text-sm font-semibold transition {{ $on ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-muted)] hover:text-[var(--color-heading)]' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
