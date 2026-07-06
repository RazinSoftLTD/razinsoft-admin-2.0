<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();  // list of module.action keys
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        // All actions of the given modules; optionally grant view_all for scopables listed.
        $grant = function (array $modules, array $viewAll = []): array {
            $keys = [];
            foreach ($modules as $mod) {
                $cfg = Permissions::MODULES[$mod] ?? null;
                if (! $cfg) {
                    continue;
                }
                foreach ($cfg['actions'] as $act) {
                    $keys[] = "{$mod}.{$act}";
                }
                if (! empty($cfg['scope']) && in_array($mod, $viewAll, true)) {
                    $keys[] = "{$mod}.view_all";
                }
            }

            return $keys;
        };

        $now = now();
        $allModules = array_keys(Permissions::MODULES);
        $scopables = ['leads', 'deals', 'invoices'];

        $roles = [
            ['name' => 'Full Access', 'description' => 'Everything in the panel', 'is_system' => true,
                'permissions' => $grant($allModules, $scopables)],
            ['name' => 'Sales', 'description' => 'CRM, invoices, products & orders',
                'permissions' => $grant(['leads', 'deals', 'clients', 'invoices', 'products', 'orders', 'coupons'], ['invoices'])],
            ['name' => 'Support', 'description' => 'Questions, messages, reviews & read-only clients/orders',
                'permissions' => array_merge($grant(['questions', 'messages', 'reviews']), ['clients.view', 'orders.view'])],
            ['name' => 'Content', 'description' => 'Blog & subscribers',
                'permissions' => $grant(['blog', 'subscribers', 'reviews'])],
        ];

        foreach ($roles as $r) {
            DB::table('roles')->insert([
                'name' => $r['name'],
                'description' => $r['description'],
                'permissions' => json_encode($r['permissions']),
                'is_system' => $r['is_system'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Backfill existing staff: convert their old flat section permissions ([leads, invoices…])
        // into a granular OVERRIDE map ({leads.view:true, …}) so nobody loses access. Invoices were
        // unscoped before → grant view_all; leads/deals stay own-scoped (as they were for staff).
        foreach (DB::table('users')->where('role', 'staff')->get(['id', 'permissions']) as $u) {
            $old = json_decode($u->permissions ?? '[]', true) ?: [];
            $override = [];
            foreach ($old as $mod) {
                $cfg = Permissions::MODULES[$mod] ?? null;
                if (! $cfg) {
                    continue;
                }
                foreach ($cfg['actions'] as $act) {
                    $override["{$mod}.{$act}"] = true;
                }
                if (! empty($cfg['scope']) && $mod === 'invoices') {
                    $override["{$mod}.view_all"] = true;
                }
            }
            DB::table('users')->where('id', $u->id)->update(['permissions' => json_encode($override)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('roles');
    }
};
