<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Give existing gallery images a real per-group serial (they were all sort_order 0). */
    public function up(): void
    {
        foreach (DB::table('gallery_groups')->pluck('id') as $groupId) {
            $pos = 0;
            foreach (DB::table('gallery_images')->where('gallery_group_id', $groupId)->orderBy('id')->pluck('id') as $imageId) {
                DB::table('gallery_images')->where('id', $imageId)->update(['sort_order' => $pos++]);
            }
        }
    }

    public function down(): void
    {
        // no-op — sort_order stays
    }
};
