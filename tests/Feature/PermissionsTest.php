<?php
namespace Tests\Feature;
use App\Models\ClientInvoice; use App\Models\Role; use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Str; use Tests\TestCase;

class PermissionsTest extends TestCase {
    use RefreshDatabase;

    private function admin(): User {
        return User::create(['name'=>'A','email'=>'perm-admin-'.Str::random(4).'@test.local','password'=>'password','role'=>'admin']);
    }

    public function test_action_level_enforcement(): void {
        // staff who may only VIEW leads
        $staff = User::create(['name'=>'S','email'=>'p1@test.local','password'=>'password','role'=>'staff','permissions'=>['leads.view'=>true]]);
        $this->actingAs($staff);

        $this->get('/admin/leads')->assertOk();            // view granted
        $this->get('/admin/leads/create')->assertForbidden(); // no leads.create
        $this->post('/admin/leads', ['full_name'=>'X','phone'=>'1','lead_source'=>'Website','lead_status'=>'new','priority'=>'high','assigned_to'=>$staff->id])->assertForbidden();
        $this->get('/admin/invoices')->assertForbidden();  // no invoices.view at all
    }

    public function test_role_grants_and_override_wins(): void {
        $role = Role::create(['name'=>'Viewer','permissions'=>['clients.view','clients.create']]);
        $staff = User::create(['name'=>'S','email'=>'p2@test.local','password'=>'password','role'=>'staff','role_id'=>$role->id]);
        $this->actingAs($staff);

        $this->get('/admin/clients')->assertOk();          // from role
        $this->get('/admin/clients/create')->assertOk();   // from role

        // per-user override DENIES create even though the role allows it
        $staff->update(['permissions'=>['clients.create'=>false]]);
        $this->get('/admin/clients/create')->assertForbidden();

        // override GRANTS something the role lacks
        $staff->update(['permissions'=>['clients.edit'=>true]]);
        $this->assertTrue($staff->fresh()->allows('clients', 'edit'));
    }

    public function test_invoice_own_scope(): void {
        $admin = $this->admin();
        $mine = User::create(['name'=>'Mine','email'=>'mine@test.local','password'=>'password','role'=>'staff','permissions'=>['invoices.view'=>true]]);
        $a = ClientInvoice::create(['invoice_number'=>'INV-A','public_token'=>Str::random(40),'bill_to_name'=>'A','invoice_date'=>date('Y-m-d'),'currency'=>'USD','status'=>'draft','subtotal'=>10,'total'=>10,'created_by'=>$mine->id]);
        $b = ClientInvoice::create(['invoice_number'=>'INV-B','public_token'=>Str::random(40),'bill_to_name'=>'B','invoice_date'=>date('Y-m-d'),'currency'=>'USD','status'=>'draft','subtotal'=>20,'total'=>20,'created_by'=>$admin->id]);

        $this->actingAs($mine);
        // own-only: list shows mine, not the admin's
        $this->get('/admin/invoices')->assertOk()->assertSee('INV-A')->assertDontSee('INV-B');
        $this->get("/admin/invoices/{$a->id}")->assertOk();          // own → ok
        $this->get("/admin/invoices/{$b->id}")->assertForbidden();   // someone else's → 403

        // grant view_all → sees everyone's
        $mine->update(['permissions'=>['invoices.view'=>true,'invoices.view_all'=>true]]);
        $this->get('/admin/invoices')->assertOk()->assertSee('INV-A')->assertSee('INV-B');
        $this->get("/admin/invoices/{$b->id}")->assertOk();
    }

    public function test_invoice_finance_section_is_gated(): void {
        $staff = User::create(['name'=>'S','email'=>'fin@test.local','password'=>'password','role'=>'staff','permissions'=>['invoices.view'=>true]]);
        $inv = ClientInvoice::create(['invoice_number'=>'INV-F','public_token'=>Str::random(40),'bill_to_name'=>'C','invoice_date'=>date('Y-m-d'),'currency'=>'USD','status'=>'sent','subtotal'=>100,'total'=>100,'created_by'=>$staff->id]);
        $this->actingAs($staff);

        // no invoices.finance → the Record Payment / history section is hidden
        $this->get("/admin/invoices/{$inv->id}")->assertOk()->assertDontSee('Record Payment');
        // and the finance action itself is blocked
        $this->post("/admin/invoices/{$inv->id}/payments", ['amount'=>10,'paid_at'=>date('Y-m-d'),'method'=>'Cash'])->assertForbidden();

        // grant finance → section shows
        $staff->update(['permissions'=>['invoices.view'=>true,'invoices.finance'=>true]]);
        $this->get("/admin/invoices/{$inv->id}")->assertOk()->assertSee('Record Payment');
    }

    public function test_admin_reaches_everything(): void {
        $this->actingAs($this->admin());
        $this->get('/admin/leads')->assertOk();
        $this->get('/admin/invoices')->assertOk();
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/roles')->assertOk();
        $this->get('/admin/staff')->assertOk();
    }

    public function test_staff_list_renders_with_role_and_overrides(): void {
        $role = Role::create(['name'=>'Sales','permissions'=>['leads.view']]);
        User::create(['name'=>'Rep','email'=>'listed@test.local','password'=>'password','role'=>'staff','role_id'=>$role->id,'permissions'=>['deals.view'=>true]]);
        $this->actingAs($this->admin());

        // the Access column shows the role name (regression: used to call a removed Permissions::label)
        $this->get('/admin/staff')->assertOk()->assertSee('Sales')->assertSee('Rep');
    }

    public function test_super_admin_creates_role_and_assigns_via_staff_form(): void {
        $this->actingAs($this->admin());

        // create a role via the panel
        $this->post('/admin/roles', ['name'=>'Sales Rep','permissions'=>['leads.view','leads.create','bogus.x']])->assertRedirect();
        $role = Role::where('name','Sales Rep')->first();
        $this->assertSame(['leads.view','leads.create'], $role->permissions); // invalid key dropped

        // create staff on that role (the staff form only assigns a role now)
        $this->post('/admin/staff', ['name'=>'Rep','email'=>'rep@test.local','password'=>'password123','role_id'=>$role->id])->assertRedirect();
        $staff = User::where('email','rep@test.local')->first();
        $this->assertSame($role->id, $staff->role_id);
        $this->assertTrue($staff->allows('leads','view'));   // from role

        // the dedicated permissions page renders and saves per-user overrides
        $this->get("/admin/staff/{$staff->id}/permissions")->assertOk()->assertSee('Permissions — Rep');
        $this->put("/admin/staff/{$staff->id}/permissions", ['override'=>['leads.delete'=>'1','deals.view'=>'0','junk'=>'1']])->assertRedirect();
        $staff->refresh();
        $this->assertSame(['leads.delete'=>true, 'deals.view'=>false], $staff->permissions); // junk dropped, inherit skipped
        $this->assertTrue($staff->allows('leads','delete'));  // from override
    }
}
