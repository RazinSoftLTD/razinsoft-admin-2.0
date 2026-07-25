<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Support\FinanceSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Finance — RazinSoft's own money: wallets, bank accounts, income, expenses, transfers,
 * conversions, payables and tax. Client billing lives in the Invoice module; paid invoices
 * arrive here as income automatically and unpaid ones are listed as receivables.
 */
class FinanceController extends Controller
{
    // ===================================================================== dashboard

    public function dashboard(Request $request)
    {
        $this->can($request, 'view');

        $accounts = FinanceAccount::active()->orderBy('type')->orderBy('sort_order')->orderBy('name')->get();
        $today = today();

        $sumIn = fn ($q) => (float) (clone $q)->where('direction', 'in')->sum('amount');
        $sumOut = fn ($q) => (float) (clone $q)->where('direction', 'out')->sum('amount');

        $incomeQ = fn () => FinanceTransaction::query()->income();
        $expenseQ = fn () => FinanceTransaction::query()->expense();

        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $monthIncome = $sumIn($incomeQ()->whereBetween('occurred_on', [$monthStart, $monthEnd]));
        $monthExpense = $sumOut($expenseQ()->whereBetween('occurred_on', [$monthStart, $monthEnd]));

        return view('admin.finance.dashboard', [
            'accounts' => $accounts,
            'walletTotal' => $this->totalsByCurrency($accounts->where('type', FinanceAccount::TYPE_WALLET)),
            'bankTotal' => $this->totalsByCurrency($accounts->where('type', FinanceAccount::TYPE_BANK)),
            'todayIncome' => $sumIn($incomeQ()->whereDate('occurred_on', $today)),
            'todayExpense' => $sumOut($expenseQ()->whereDate('occurred_on', $today)),
            'monthIncome' => $monthIncome,
            'monthExpense' => $monthExpense,
            'monthProfit' => $monthIncome - $monthExpense,
            'clientDue' => (float) FinanceSync::receivablesQuery()->sum(DB::raw('total - amount_paid')),
            'vendorDue' => (float) \App\Models\FinancePayable::where('status', '!=', 'paid')->sum(DB::raw('amount - amount_paid')),
            'recent' => FinanceTransaction::with('account', 'category')->latest('occurred_on')->latest('id')->limit(10)->get(),
            'monthly' => $this->monthlySeries(),
            'byCategory' => $this->expenseByCategory(),
        ]);
    }

    /** 12 months of income / expense / net, oldest first — drives the dashboard charts. */
    private function monthlySeries(): array
    {
        $rows = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = today()->copy()->subMonths($i)->startOfMonth();
            $range = [$month, $month->copy()->endOfMonth()];
            $income = (float) FinanceTransaction::query()->income()->where('direction', 'in')->whereBetween('occurred_on', $range)->sum('amount');
            $expense = (float) FinanceTransaction::query()->expense()->where('direction', 'out')->whereBetween('occurred_on', $range)->sum('amount');
            $rows[] = ['label' => $month->format('M'), 'income' => $income, 'expense' => $expense, 'net' => $income - $expense];
        }

        return $rows;
    }

    /** This month's expenses grouped by category, biggest first. */
    private function expenseByCategory(): array
    {
        return FinanceTransaction::query()->expense()
            ->whereBetween('occurred_on', [today()->copy()->startOfMonth(), today()->copy()->endOfMonth()])
            ->selectRaw('category_id, sum(amount) as total')
            ->groupBy('category_id')->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'name' => FinanceCategory::find($r->category_id)?->name ?? 'Uncategorised',
                'total' => (float) $r->total,
            ])->all();
    }

    /** Balances can't be added across currencies — keep them separate. */
    private function totalsByCurrency($accounts): array
    {
        return collect($accounts)->groupBy('currency')
            ->map(fn ($rows) => (float) $rows->sum('current_balance'))
            ->sortDesc()->all();
    }

    // ===================================================================== accounts (wallets + banks)

    public function accounts(Request $request, string $type)
    {
        $this->can($request, 'view');
        abort_unless(in_array($type, [FinanceAccount::TYPE_WALLET, FinanceAccount::TYPE_BANK], true), 404);

        $accounts = FinanceAccount::where('type', $type)
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.finance.accounts', [
            'type' => $type,
            'accounts' => $accounts,
            'totals' => $this->totalsByCurrency($accounts->where('status', 'active')),
            'currencies' => $this->currencyCodes(),
            'providers' => $type === FinanceAccount::TYPE_BANK ? FinanceAccount::BANK_PROVIDERS : FinanceAccount::WALLET_PROVIDERS,
        ]);
    }

    public function accountStore(Request $request)
    {
        $this->can($request, 'create');
        $data = $this->validatedAccount($request);
        $data['current_balance'] = $data['opening_balance'];

        FinanceAccount::create($data);

        return back()->with('status', FinanceAccount::TYPES[$data['type']].' added.');
    }

    public function accountUpdate(Request $request, FinanceAccount $account)
    {
        $this->can($request, 'edit');
        $account->update($this->validatedAccount($request, $account));
        $account->recalculateBalance();          // the opening balance may have moved

        return back()->with('status', 'Account updated.');
    }

    public function accountDestroy(Request $request, FinanceAccount $account)
    {
        $this->can($request, 'delete');
        if ($account->transactions()->exists()) {
            return back()->with('error', 'This account has transactions — set it to Inactive instead.');
        }
        $account->delete();

        return back()->with('status', 'Account removed.');
    }

    private function validatedAccount(Request $request, ?FinanceAccount $account = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FinanceAccount::TYPES))],
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['nullable', 'string', 'max:60'],
            'currency' => ['required', 'string', 'max:8'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'opening_balance' => ['nullable', 'numeric'],
            'status' => ['required', Rule::in(array_keys(FinanceAccount::STATUSES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['opening_balance'] = $data['opening_balance'] ?? 0;   // the column is NOT NULL

        return $data;
    }

    // ===================================================================== transactions / income / expenses

    /** One list powers Transactions, Income and Expenses — `$only` narrows the types. */
    public function transactions(Request $request, ?string $only = null)
    {
        $this->can($request, 'view');

        $types = match ($only) {
            'income' => array_merge(FinanceTransaction::INCOME_TYPES, ['refund']),
            'expense' => FinanceTransaction::EXPENSE_TYPES,
            default => null,
        };

        $q = FinanceTransaction::with('account', 'counterAccount', 'category', 'invoice')
            ->when($types, fn ($x) => $x->whereIn('type', $types))
            ->when($request->filled('type'), fn ($x) => $x->where('type', $request->query('type')))
            ->when($request->filled('account'), fn ($x) => $x->where('account_id', $request->query('account')))
            ->when($request->filled('category'), fn ($x) => $x->where('category_id', $request->query('category')))
            ->when($request->filled('currency'), fn ($x) => $x->where('currency', $request->query('currency')))
            ->when($request->filled('from'), fn ($x) => $x->whereDate('occurred_on', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($x) => $x->whereDate('occurred_on', '<=', $request->query('to')))
            ->when($request->filled('search'), function ($x) use ($request) {
                $s = $request->query('search');
                $x->where(fn ($w) => $w->where('reference', 'like', "%{$s}%")->orWhere('notes', 'like', "%{$s}%"));
            })
            ->latest('occurred_on')->latest('id');

        $totals = [
            'in' => (float) (clone $q)->where('direction', 'in')->sum('amount'),
            'out' => (float) (clone $q)->where('direction', 'out')->sum('amount'),
        ];

        return view('admin.finance.transactions', [
            'only' => $only,
            'rows' => $q->paginate(25)->withQueryString(),
            'totals' => $totals,
            'accounts' => FinanceAccount::active()->orderBy('name')->get(),
            'categories' => FinanceCategory::ofKind($only === 'income' ? 'income' : 'expense')->get(),
            'currencies' => $this->currencyCodes(),
        ]);
    }

    public function transactionStore(Request $request)
    {
        $this->can($request, 'create');

        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense', 'deposit', 'withdrawal', 'refund', 'adjustment'])],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:8'],
            'occurred_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'max:5120'],
        ]);

        $data['direction'] = FinanceTransaction::DIRECTION[$data['type']]
            ?? ($data['direction'] ?? 'out');           // refund/adjustment pick their own side
        $data['source'] = 'manual';
        $data['created_by'] = $request->user()->id;
        if ($request->hasFile('receipt')) {
            $data['receipt'] = $request->file('receipt')->store('finance/receipts', 'public');
        }

        FinanceTransaction::create($data);

        return back()->with('status', 'Transaction recorded.');
    }

    public function transactionUpdate(Request $request, FinanceTransaction $transaction)
    {
        $this->can($request, 'edit');
        abort_if($transaction->transfer_group !== null, 422, 'Edit transfers from the Transfers page.');

        $data = $request->validate([
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $transaction->update($data);

        return back()->with('status', 'Transaction updated.');
    }

    /** Soft delete only — the spec is explicit that nothing is ever purged. */
    public function transactionDestroy(Request $request, FinanceTransaction $transaction)
    {
        $this->can($request, 'delete');

        if ($transaction->transfer_group) {
            FinanceTransaction::where('transfer_group', $transaction->transfer_group)->get()->each->delete();

            return back()->with('status', 'Transfer reversed.');
        }
        $transaction->delete();

        return back()->with('status', 'Transaction removed (kept in the audit trail).');
    }

    // ===================================================================== transfers & conversions

    public function transfers(Request $request)
    {
        $this->can($request, 'view');

        return view('admin.finance.transfers', [
            'rows' => FinanceTransaction::with('account', 'counterAccount')
                ->where('type', 'transfer')->where('direction', 'out')
                ->latest('occurred_on')->latest('id')->paginate(20)->withQueryString(),
            'accounts' => FinanceAccount::active()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function conversions(Request $request)
    {
        $this->can($request, 'view');

        return view('admin.finance.conversions', [
            'rows' => FinanceTransaction::with('account', 'counterAccount')
                ->where('type', 'conversion')->where('direction', 'out')
                ->latest('occurred_on')->latest('id')->paginate(20)->withQueryString(),
            'accounts' => FinanceAccount::active()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    /**
     * Move money between two accounts. Stored as a paired out/in so both balances stay right;
     * when the currencies differ the same form doubles as a currency conversion.
     */
    public function transferStore(Request $request)
    {
        $this->can($request, 'create');

        $data = $request->validate([
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'counter_account_id' => ['required', 'different:account_id', 'exists:finance_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'converted_amount' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'bank_charge' => ['nullable', 'numeric', 'min:0'],
            'occurred_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $from = FinanceAccount::findOrFail($data['account_id']);
        $to = FinanceAccount::findOrFail($data['counter_account_id']);
        $isConversion = $from->currency !== $to->currency;

        $fee = (float) ($data['fee'] ?? 0);
        $charge = (float) ($data['bank_charge'] ?? 0);
        $sent = (float) $data['amount'];

        // What lands on the other side: the converted figure, else rate × amount, else as-is.
        $received = (float) ($data['converted_amount']
            ?? (isset($data['exchange_rate']) && $data['exchange_rate'] ? $sent * (float) $data['exchange_rate'] : $sent));
        $received = max(0, $received - ($isConversion ? 0 : 0));

        $rate = $data['exchange_rate'] ?? ($isConversion && $sent > 0 ? round($received / $sent, 6) : null);
        $group = (string) Str::uuid();
        $type = $isConversion ? 'conversion' : 'transfer';

        DB::transaction(function () use ($request, $data, $from, $to, $sent, $received, $fee, $charge, $rate, $group, $type) {
            $shared = [
                'type' => $type,
                'occurred_on' => $data['occurred_on'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transfer_group' => $group,
                'source' => 'manual',
                'created_by' => $request->user()->id,
                'exchange_rate' => $rate,
                'fee' => $fee,
                'bank_charge' => $charge,
            ];

            // Money leaves the source, including whatever the fees cost us.
            FinanceTransaction::create($shared + [
                'direction' => 'out',
                'account_id' => $from->id,
                'counter_account_id' => $to->id,
                'amount' => $sent + $fee + $charge,
                'currency' => $from->currency,
                'converted_amount' => $received,
            ]);

            // …and arrives at the destination.
            FinanceTransaction::create($shared + [
                'direction' => 'in',
                'account_id' => $to->id,
                'counter_account_id' => $from->id,
                'amount' => $received,
                'currency' => $to->currency,
                'converted_amount' => $sent,
            ]);
        });

        return back()->with('status', $isConversion ? 'Currency conversion recorded.' : 'Transfer recorded.');
    }

    // ===================================================================== receivables & payables

    public function receivables(Request $request)
    {
        $this->can($request, 'view');

        $q = FinanceSync::receivablesQuery()->with('client:id,name')
            ->when($request->filled('search'), function ($x) use ($request) {
                $s = $request->query('search');
                $x->where(fn ($w) => $w->where('invoice_number', 'like', "%{$s}%")->orWhere('bill_to_name', 'like', "%{$s}%"));
            })
            ->when($request->filled('currency'), fn ($x) => $x->where('currency', $request->query('currency')))
            ->orderBy('due_date');

        return view('admin.finance.receivables', [
            'rows' => $q->paginate(25)->withQueryString(),
            'totals' => FinanceSync::receivablesQuery()
                ->selectRaw('currency, sum(total - amount_paid) as due')
                ->groupBy('currency')->pluck('due', 'currency')->all(),
            'currencies' => $this->currencyCodes(),
        ]);
    }

    public function payables(Request $request)
    {
        $this->can($request, 'view');

        $q = \App\Models\FinancePayable::with('category')
            ->when($request->filled('status'), fn ($x) => $x->where('status', $request->query('status')))
            ->when($request->filled('search'), fn ($x) => $x->where('vendor', 'like', '%'.$request->query('search').'%'))
            ->orderByRaw("status = 'paid'")->orderBy('due_date');

        return view('admin.finance.payables', [
            'rows' => $q->paginate(25)->withQueryString(),
            'totals' => \App\Models\FinancePayable::where('status', '!=', 'paid')
                ->selectRaw('currency, sum(amount - amount_paid) as due')
                ->groupBy('currency')->pluck('due', 'currency')->all(),
            'categories' => FinanceCategory::ofKind('expense')->get(),
            'currencies' => $this->currencyCodes(),
            'accounts' => FinanceAccount::active()->orderBy('name')->get(),
        ]);
    }

    public function payableStore(Request $request)
    {
        $this->can($request, 'create');
        $data = $this->validatedPayable($request);
        $data['created_by'] = $request->user()->id;
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('finance/payables', 'public');
        }

        $payable = new \App\Models\FinancePayable($data);
        $payable->syncStatus();
        $payable->save();

        return back()->with('status', 'Payable added.');
    }

    public function payableUpdate(Request $request, \App\Models\FinancePayable $payable)
    {
        $this->can($request, 'edit');
        $payable->fill($this->validatedPayable($request));
        $payable->syncStatus();
        $payable->save();

        return back()->with('status', 'Payable updated.');
    }

    /** Settle a payable: records the expense against an account and marks the bill paid. */
    public function payablePay(Request $request, \App\Models\FinancePayable $payable)
    {
        $this->can($request, 'edit');
        $data = $request->validate([
            'account_id' => ['required', 'exists:finance_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_on' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($request, $data, $payable) {
            FinanceTransaction::create([
                'type' => 'expense',
                'direction' => 'out',
                'account_id' => $data['account_id'],
                'category_id' => $payable->category_id,
                'amount' => $data['amount'],
                'currency' => $payable->currency,
                'occurred_on' => $data['occurred_on'],
                'reference' => $payable->reference,
                'notes' => 'Payable · '.$payable->vendor,
                'source' => 'manual',
                'created_by' => $request->user()->id,
            ]);

            $payable->amount_paid = (float) $payable->amount_paid + (float) $data['amount'];
            $payable->syncStatus();
            $payable->save();
        });

        return back()->with('status', 'Payment recorded against the bill.');
    }

    public function payableDestroy(Request $request, \App\Models\FinancePayable $payable)
    {
        $this->can($request, 'delete');
        $payable->delete();

        return back()->with('status', 'Payable removed.');
    }

    private function validatedPayable(Request $request): array
    {
        $data = $request->validate([
            'vendor' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:finance_categories,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'bill_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $data['amount_paid'] = $data['amount_paid'] ?? 0;

        return $data;
    }

    // ===================================================================== VAT & tax

    public function taxes(Request $request)
    {
        $this->can($request, 'view');

        $q = \App\Models\FinanceTax::query()
            ->when($request->filled('kind'), fn ($x) => $x->where('kind', $request->query('kind')))
            ->when($request->filled('status'), fn ($x) => $x->where('status', $request->query('status')))
            ->orderByDesc('period');

        return view('admin.finance.taxes', [
            'rows' => $q->paginate(25)->withQueryString(),
            // Grouped in PHP so the query stays portable across MySQL and SQLite.
            'monthly' => \App\Models\FinanceTax::orderByDesc('period')->get()
                ->groupBy(fn ($t) => $t->period?->format('Y-m'))
                ->map(fn ($rows) => $rows->groupBy('kind')->map(fn ($g) => (float) $g->sum('amount')))
                ->take(24),
            'currencies' => $this->currencyCodes(),
        ]);
    }

    public function taxStore(Request $request)
    {
        $this->can($request, 'create');
        $data = $this->validatedTax($request);
        $data['created_by'] = $request->user()->id;

        \App\Models\FinanceTax::create($data);

        return back()->with('status', 'Tax entry added.');
    }

    public function taxUpdate(Request $request, \App\Models\FinanceTax $tax)
    {
        $this->can($request, 'edit');
        $tax->update($this->validatedTax($request));

        return back()->with('status', 'Tax entry updated.');
    }

    public function taxDestroy(Request $request, \App\Models\FinanceTax $tax)
    {
        $this->can($request, 'delete');
        $tax->delete();

        return back()->with('status', 'Tax entry removed.');
    }

    private function validatedTax(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(array_keys(\App\Models\FinanceTax::KINDS))],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'period' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(\App\Models\FinanceTax::STATUSES))],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    // ===================================================================== reports

    public function reports(Request $request)
    {
        $this->can($request, 'reports');

        $from = $request->query('from') ?: today()->copy()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: today()->toDateString();
        $report = $request->query('report', 'income');

        $data = $this->reportRows($report, $from, $to);

        if ($format = $request->query('export')) {
            return $this->exportReport($report, $data, $format, $from, $to);
        }

        return view('admin.finance.reports', [
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'rows' => $data['rows'],
            'columns' => $data['columns'],
            'summary' => $data['summary'],
        ]);
    }

    /** Every report is the same shape: columns, rows of plain values, and a summary line. */
    private function reportRows(string $report, string $from, string $to): array
    {
        $between = fn ($q) => $q->whereBetween('occurred_on', [$from, $to]);

        return match ($report) {
            'expense' => $this->txReport(FinanceTransaction::query()->expense()->tap($between), 'Expense'),
            'profit' => $this->profitReport($from, $to),
            'cash_flow' => $this->cashFlowReport($from, $to),
            'wallet' => $this->accountReport(FinanceAccount::TYPE_WALLET),
            'bank' => $this->accountReport(FinanceAccount::TYPE_BANK),
            'receivable' => $this->receivableReport(),
            'payable' => $this->payableReport(),
            'vat' => $this->taxReport('vat', $from, $to),
            'tax' => $this->taxReport(null, $from, $to),
            default => $this->txReport(FinanceTransaction::query()->income()->tap($between), 'Income'),
        };
    }

    private function txReport($query, string $label): array
    {
        $rows = (clone $query)->with('account', 'category')->orderBy('occurred_on')->get()
            ->map(fn ($t) => [
                $t->occurred_on?->format('d M Y'),
                $t->typeLabel(),
                $t->category?->name ?? '—',
                $t->account?->name ?? 'Unassigned',
                $t->reference ?: '—',
                $t->symbol().number_format((float) $t->amount, 2),
            ])->all();

        $totals = (clone $query)->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency')->all();

        return [
            'columns' => ['Date', 'Type', 'Category', 'Account', 'Reference', 'Amount'],
            'rows' => $rows,
            'summary' => collect($totals)->map(fn ($v, $c) => strtoupper($c).' '.number_format((float) $v, 2))->values()->all(),
        ];
    }

    private function profitReport(string $from, string $to): array
    {
        $rows = [];
        $period = \Carbon\CarbonPeriod::create($from, '1 month', $to);
        $totalIn = $totalOut = 0;
        foreach ($period as $month) {
            $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
            $in = (float) FinanceTransaction::query()->income()->whereBetween('occurred_on', $range)->sum('amount');
            $out = (float) FinanceTransaction::query()->expense()->whereBetween('occurred_on', $range)->sum('amount');
            $totalIn += $in;
            $totalOut += $out;
            $rows[] = [$month->format('M Y'), number_format($in, 2), number_format($out, 2), number_format($in - $out, 2)];
        }

        return [
            'columns' => ['Month', 'Income', 'Expense', 'Profit'],
            'rows' => $rows,
            'summary' => ['Income '.number_format($totalIn, 2), 'Expense '.number_format($totalOut, 2), 'Profit '.number_format($totalIn - $totalOut, 2)],
        ];
    }

    private function cashFlowReport(string $from, string $to): array
    {
        $rows = FinanceTransaction::whereBetween('occurred_on', [$from, $to])
            ->selectRaw("occurred_on, sum(case when direction = 'in' then amount else 0 end) as inflow, sum(case when direction = 'out' then amount else 0 end) as outflow")
            ->groupBy('occurred_on')->orderBy('occurred_on')->get();

        $running = 0;
        $out = [];
        foreach ($rows as $r) {
            $running += (float) $r->inflow - (float) $r->outflow;
            $out[] = [\Carbon\Carbon::parse($r->occurred_on)->format('d M Y'), number_format((float) $r->inflow, 2), number_format((float) $r->outflow, 2), number_format($running, 2)];
        }

        return [
            'columns' => ['Date', 'In', 'Out', 'Running'],
            'rows' => $out,
            'summary' => ['Net '.number_format($running, 2)],
        ];
    }

    private function accountReport(string $type): array
    {
        $rows = FinanceAccount::where('type', $type)->orderBy('name')->get()
            ->map(fn ($a) => [$a->name, $a->provider ?: '—', $a->currency, number_format((float) $a->opening_balance, 2), number_format((float) $a->current_balance, 2), FinanceAccount::STATUSES[$a->status] ?? $a->status])->all();

        return [
            'columns' => ['Account', 'Provider', 'Currency', 'Opening', 'Current', 'Status'],
            'rows' => $rows,
            'summary' => collect(FinanceAccount::where('type', $type)->where('status', 'active')->get()->groupBy('currency'))
                ->map(fn ($g, $c) => strtoupper($c).' '.number_format((float) $g->sum('current_balance'), 2))->values()->all(),
        ];
    }

    private function receivableReport(): array
    {
        $rows = FinanceSync::receivablesQuery()->with('client:id,name')->orderBy('due_date')->get()
            ->map(fn ($i) => [
                $i->invoice_number,
                $i->client?->name ?? $i->bill_to_name ?? '—',
                $i->due_date?->format('d M Y') ?? '—',
                number_format((float) $i->total, 2),
                number_format((float) $i->amount_paid, 2),
                number_format((float) $i->total - (float) $i->amount_paid, 2),
            ])->all();

        return [
            'columns' => ['Invoice', 'Client', 'Due date', 'Total', 'Paid', 'Outstanding'],
            'rows' => $rows,
            'summary' => collect(FinanceSync::receivablesQuery()->selectRaw('currency, sum(total - amount_paid) as due')->groupBy('currency')->pluck('due', 'currency'))
                ->map(fn ($v, $c) => strtoupper($c).' '.number_format((float) $v, 2))->values()->all(),
        ];
    }

    private function payableReport(): array
    {
        $rows = \App\Models\FinancePayable::with('category')->orderBy('due_date')->get()
            ->map(fn ($p) => [
                $p->vendor,
                $p->category?->name ?? '—',
                $p->due_date?->format('d M Y') ?? '—',
                number_format((float) $p->amount, 2),
                number_format((float) $p->amount_paid, 2),
                number_format($p->due(), 2),
                \App\Models\FinancePayable::STATUSES[$p->status] ?? $p->status,
            ])->all();

        return [
            'columns' => ['Vendor', 'Category', 'Due date', 'Amount', 'Paid', 'Outstanding', 'Status'],
            'rows' => $rows,
            'summary' => collect(\App\Models\FinancePayable::where('status', '!=', 'paid')->get()->groupBy('currency'))
                ->map(fn ($g, $c) => strtoupper($c).' '.number_format((float) $g->sum(fn ($p) => $p->due()), 2))->values()->all(),
        ];
    }

    private function taxReport(?string $kind, string $from, string $to): array
    {
        $q = \App\Models\FinanceTax::whereBetween('period', [$from, $to])->when($kind, fn ($x) => $x->where('kind', $kind));
        $rows = (clone $q)->orderBy('period')->get()
            ->map(fn ($t) => [
                $t->period?->format('M Y'),
                \App\Models\FinanceTax::KINDS[$t->kind] ?? $t->kind,
                $t->title,
                $t->currency,
                number_format((float) $t->amount, 2),
                \App\Models\FinanceTax::STATUSES[$t->status] ?? $t->status,
            ])->all();

        return [
            'columns' => ['Period', 'Kind', 'Title', 'Currency', 'Amount', 'Status'],
            'rows' => $rows,
            'summary' => ['Total '.number_format((float) (clone $q)->sum('amount'), 2)],
        ];
    }

    /** CSV / Excel (CSV-compatible) download, or a print-ready PDF view. */
    private function exportReport(string $report, array $data, string $format, string $from, string $to)
    {
        $name = 'finance-'.$report.'-'.$from.'-to-'.$to;

        if ($format === 'pdf') {
            return view('admin.finance.report-pdf', [
                'report' => $report, 'from' => $from, 'to' => $to,
                'columns' => $data['columns'], 'rows' => $data['rows'], 'summary' => $data['summary'],
            ]);
        }

        $ext = $format === 'excel' ? 'xls' : 'csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $data['columns']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }
            fputcsv($out, []);
            fputcsv($out, ['Summary', ...$data['summary']]);
            fclose($out);
        }, "{$name}.{$ext}", ['Content-Type' => 'text/csv']);
    }

    // ===================================================================== helpers

    private function currencyCodes(): array
    {
        return Currency::query()->orderBy('code')->pluck('code')->all() ?: ['USD', 'BDT', 'GBP', 'EUR'];
    }

    private function can(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission("finance.{$action}"), 403);
    }
}
