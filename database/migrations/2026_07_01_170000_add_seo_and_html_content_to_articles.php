<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('image_alt')->nullable()->after('image');
            $table->string('meta_title')->nullable()->after('takeaways');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->string('meta_keywords', 500)->nullable()->after('meta_description');
            $table->longText('content_html')->nullable()->after('content');
        });

        // Convert the existing paragraph arrays into HTML.
        foreach (DB::table('articles')->get(['id', 'content']) as $row) {
            $paras = json_decode((string) $row->content, true) ?: [];
            $html = collect($paras)->map(fn ($p) => '<p>'.e($p).'</p>')->implode("\n");
            DB::table('articles')->where('id', $row->id)->update(['content_html' => $html]);
        }

        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn('content'));
        Schema::table('articles', fn (Blueprint $table) => $table->renameColumn('content_html', 'content'));
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['image_alt', 'meta_title', 'meta_description', 'meta_keywords']);
        });
    }
};
