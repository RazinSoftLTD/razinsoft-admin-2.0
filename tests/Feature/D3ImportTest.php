<?php
namespace Tests\Feature;
use App\Models\Lead; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Http\UploadedFile; use Tests\TestCase;
class D3ImportTest extends TestCase {
    use RefreshDatabase;
    public function test_import(): void {
        $admin = User::create(['name'=>'A','email'=>'d3@test.local','password'=>'password','role'=>'admin']);
        $this->actingAs($admin);
        Lead::create(['full_name'=>'Dup','email'=>'dup@x.com','phone'=>'9','lead_source'=>'Website','lead_status'=>'new','assigned_to'=>$admin->id,'priority'=>'high']);

        $csv = "full_name,email,phone,company_name,lead_source,lead_status,priority\n".
               "Alice,alice@x.com,111,Acme,Website,new,high\n".
               "Bob,bob@x.com,222,BobCo,LinkedIn,contacted,medium\n".
               ",noname@x.com,333,X,Website,new,high\n".        // missing name -> skip
               "Dup Again,dup@x.com,444,Y,Website,new,high\n";   // dup email -> skip
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->get('/admin/leads/import')->assertOk()->assertSee('Import Leads');
        $this->post('/admin/leads/import',['file'=>$file,'assigned_to'=>$admin->id])->assertRedirect('/admin/leads');

        $this->assertNotNull(Lead::where('email','alice@x.com')->first());
        $this->assertNotNull(Lead::where('email','bob@x.com')->first());
        $this->assertEquals(3, Lead::count(), '1 existing + 2 imported (2 skipped)');
        // sample template downloads
        $this->get('/admin/leads/import/sample')->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
    }
}
