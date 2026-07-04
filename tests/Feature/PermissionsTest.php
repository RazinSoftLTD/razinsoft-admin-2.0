<?php
namespace Tests\Feature;
use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;

class PermissionsTest extends TestCase {
    use RefreshDatabase;

    public function test_staff_only_reaches_granted_sections(): void {
        $staff = User::create(['name'=>'S','email'=>'perm-staff@test.local','password'=>'password','role'=>'staff','permissions'=>['leads']]);
        $this->actingAs($staff);

        // granted → OK
        $this->get('/admin/leads')->assertOk();
        // not granted → 403
        $this->get('/admin/invoices')->assertForbidden();
        $this->get('/admin/products')->assertForbidden();
        // super-admin-only section → 403 (redirect to login via admin middleware)
        $this->get('/admin/staff')->assertRedirect(route('admin.login'));

        // sidebar shows only the permitted section
        $html = $this->get('/admin/leads')->assertOk();
        $html->assertSee('Leads')->assertDontSee('Products')->assertDontSee('All Invoices');
    }

    public function test_admin_reaches_everything(): void {
        $admin = User::create(['name'=>'A','email'=>'perm-admin@test.local','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);

        $this->get('/admin/leads')->assertOk();
        $this->get('/admin/invoices')->assertOk();
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/staff')->assertOk();
    }

    public function test_super_admin_grants_permissions_via_staff_form(): void {
        $admin = User::create(['name'=>'A','email'=>'perm-admin2@test.local','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);

        // create staff with a chosen permission set
        $this->post('/admin/staff', [
            'name'=>'New Staff','email'=>'new-staff@test.local','password'=>'password123',
            'permissions'=>['leads','invoices','bogus'],
        ])->assertRedirect();

        $staff = User::where('email','new-staff@test.local')->first();
        $this->assertNotNull($staff);
        $this->assertEqualsCanonicalizing(['leads','invoices'], $staff->permissions, 'invalid keys dropped');
        $this->assertTrue($staff->hasPermission('invoices'));
        $this->assertFalse($staff->hasPermission('products'));

        // update: revoke invoices
        $this->put("/admin/staff/{$staff->id}", [
            'name'=>'New Staff','email'=>'new-staff@test.local','permissions'=>['leads'],
        ])->assertRedirect();
        $this->assertSame(['leads'], $staff->fresh()->permissions);
    }
}
