<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branding grows past the logo.
 *
 * Whoever buys this runs it under their own name, and until now that meant the panel only. The
 * public site still said RazinSoft in its footer, wrote to our support address and showed our
 * social accounts — hard-coded, so changing them meant editing Vue files and rebuilding.
 *
 * Everything here is nullable on purpose: an empty field means "use what the software shipped
 * with" rather than "show nothing", so an operator who fills in three of them is not left with a
 * footer full of blanks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_settings', function (Blueprint $table) {
            // ---- Basic information ----
            $table->string('company_name')->nullable()->after('tagline');
            $table->string('support_email')->nullable()->after('company_name');
            $table->string('phone', 40)->nullable()->after('support_email');
            $table->string('website_url')->nullable()->after('phone');
            $table->string('address', 300)->nullable()->after('website_url');

            // ---- Website header ----
            $table->string('header_cta_label', 40)->nullable()->after('address');
            $table->string('header_cta_url')->nullable()->after('header_cta_label');

            // ---- Website footer ----
            $table->string('footer_about', 400)->nullable()->after('header_cta_url');
            $table->string('footer_note', 200)->nullable()->after('footer_about');

            // ---- Login screens ----
            $table->string('login_heading', 120)->nullable()->after('footer_note');
            $table->string('login_subheading', 250)->nullable()->after('login_heading');

            // ---- Social ----
            // One JSON column rather than five: the set of networks worth linking changes every
            // couple of years, and a column per network means a migration each time it does.
            $table->json('social')->nullable()->after('login_subheading');
        });
    }

    public function down(): void
    {
        Schema::table('brand_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_name', 'support_email', 'phone', 'website_url', 'address',
                'header_cta_label', 'header_cta_url', 'footer_about', 'footer_note',
                'login_heading', 'login_subheading', 'social',
            ]);
        });
    }
};
