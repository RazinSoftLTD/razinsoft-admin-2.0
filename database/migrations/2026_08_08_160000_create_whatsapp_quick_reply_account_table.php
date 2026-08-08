<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which numbers a quick reply appears on — many, not one.
     *
     * A reply belonged to a single number, so the same sentence had to be typed again for every
     * number that needed it, and editing it meant editing each copy. The text is written once;
     * where it shows is a choice.
     */
    public function up(): void
    {
        Schema::create('whatsapp_quick_reply_account', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quick_reply_id');
            $table->unsignedBigInteger('account_id');

            $table->unique(['quick_reply_id', 'account_id']);
            $table->index('account_id');
        });

        // Every existing reply keeps exactly the number it was written for.
        $rows = DB::table('whatsapp_quick_replies')->whereNotNull('account_id')->get(['id', 'account_id']);
        foreach ($rows->chunk(200) as $chunk) {
            DB::table('whatsapp_quick_reply_account')->insert(
                $chunk->map(fn ($r) => ['quick_reply_id' => $r->id, 'account_id' => $r->account_id])->all()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_quick_reply_account');
    }
};
