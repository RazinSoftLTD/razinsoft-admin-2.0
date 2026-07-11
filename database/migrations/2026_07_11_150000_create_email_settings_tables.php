<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row SMTP / mail configuration (overrides config/mail.php at runtime).
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mailer')->default('smtp');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->default(587);
            $table->string('username')->nullable();
            $table->text('password')->nullable();          // encrypted cast
            $table->string('encryption')->nullable()->default('tls'); // tls | ssl | null
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        // Editable email templates keyed by a stable slug.
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->string('variables')->nullable();       // comma-separated placeholder list (docs)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the default templates the app sends.
        $now = now();
        DB::table('email_templates')->insert([
            [
                'key' => 'meeting_booked',
                'name' => 'Meeting booked (to client)',
                'subject' => 'Your meeting with RazinSoft is confirmed',
                'body' => "<p>Hi {{name}},</p><p>Thanks for booking a consultation with <strong>RazinSoft</strong>. Here are your details:</p><p><strong>{{day}}</strong><br>{{slot}}</p><p>We look forward to speaking with you.</p><p>— The RazinSoft Team</p>",
                'variables' => 'name, email, day, slot, set_password_url',
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'welcome_client',
                'name' => 'Welcome new client',
                'subject' => 'Welcome to RazinSoft — set your password',
                'body' => "<p>Hi {{name}},</p><p>An account was created for you at RazinSoft. Set your password to sign in and manage your meetings and invoices:</p><p><a href=\"{{set_password_url}}\">Set your password</a></p><p>— The RazinSoft Team</p>",
                'variables' => 'name, email, set_password_url',
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_settings');
    }
};
