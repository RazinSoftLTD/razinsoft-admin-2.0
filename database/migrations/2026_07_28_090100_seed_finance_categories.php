<?php

use App\Models\FinanceCategory;
use Illuminate\Database\Migrations\Migration;

/** The starting expense/income categories from the module spec. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (FinanceCategory::DEFAULT_EXPENSES as $i => $name) {
            FinanceCategory::firstOrCreate(['kind' => 'expense', 'name' => $name], ['sort_order' => $i]);
        }
        foreach (FinanceCategory::DEFAULT_INCOMES as $i => $name) {
            FinanceCategory::firstOrCreate(['kind' => 'income', 'name' => $name], ['sort_order' => $i]);
        }
    }

    public function down(): void
    {
        FinanceCategory::whereIn('name', array_merge(FinanceCategory::DEFAULT_EXPENSES, FinanceCategory::DEFAULT_INCOMES))->delete();
    }
};
