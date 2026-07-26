<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lead / deal / client can be interested in several product categories at once, so the single
 * product_category + product_sub_category columns become rows here. A row pointing at a top-level
 * category means "this whole category"; one pointing at a child means that sub-category.
 *
 * The old columns are left in place: they still hold what was recorded before this, and the up()
 * below copies those values across by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_interests', function (Blueprint $table) {
            $table->id();
            $table->morphs('interestable');                 // Lead | Deal | User (client)
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['interestable_type', 'interestable_id', 'product_category_id'], 'product_interest_unique');
        });

        $this->backfill(\App\Models\Lead::class, 'leads', 'product_category', 'product_sub_category');
        $this->backfill(\App\Models\Deal::class, 'deals', 'product_category', 'product_sub_category');
        // Clients keep the same idea under different column names.
        $this->backfill(\App\Models\User::class, 'users', 'client_category', 'client_sub_category', "role = 'customer'");
    }

    /**
     * Copy the old string columns into the pivot. The sub-category wins when both are set: it is
     * the more specific answer, and its parent is implied by the tree.
     */
    private function backfill(string $model, string $table, string $catCol, string $subCol, ?string $where = null): void
    {
        if (! Schema::hasColumn($table, $catCol)) {
            return;
        }

        $categories = \App\Models\ProductCategory::all();
        $rows = \Illuminate\Support\Facades\DB::table($table)
            ->when($where, fn ($q) => $q->whereRaw($where))
            ->where(fn ($q) => $q->whereNotNull($catCol)->orWhereNotNull($subCol))
            ->get(['id', $catCol.' as cat', $subCol.' as sub']);

        foreach ($rows as $row) {
            $match = $categories->firstWhere('name', $row->sub) ?? $categories->firstWhere('name', $row->cat);

            if ($match) {
                \Illuminate\Support\Facades\DB::table('product_interests')->insertOrIgnore([
                    'interestable_type' => $model,
                    'interestable_id' => $row->id,
                    'product_category_id' => $match->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_interests');
    }
};
