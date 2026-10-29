<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NcrReport;
use App\Models\ProblemType;
use App\Models\Product;
use App\Models\SeverityLevel;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrPhaseTwoTest extends TestCase
{
    use RefreshDatabase;
    private Department $department; private ProblemType $problemType; private SeverityLevel $severity; private Product $product;
    protected function setUp():void{parent::setUp();Unit::create(['code'=>'PCS','name_th'=>'ชิ้น','is_active'=>true]);$this->product=Product::create(['code'=>'P001','name_th'=>'Product A','is_active'=>true]);$this->department=Department::create(['code'=>'QA','name_th'=>'ประกันคุณภาพ','is_active'=>true]);$this->problemType=ProblemType::create(['code'=>'PROCESS','name_th'=>'กระบวนการ','is_active'=>true]);$this->severity=SeverityLevel::create(['code'=>'HIGH','name_th'=>'สูง','is_active'=>true]);}

    public function test_reporter_can_create_draft_with_number_item_history_and_attachment():void
    {
        Storage::fake('local');$user=$this->user(['ncr.create']);
        $this->actingAs($user)->get(route('ncr.create'))->assertOk()->assertSee('สร้างรายงาน NCR ใหม่');
        $this->actingAs($user)->post(route('ncr.store'),$this->payload()+['attachments'=>[UploadedFile::fake()->create('proof.pdf',50,'application/pdf')]])->assertRedirect();
        $report=NcrReport::firstOrFail();$this->assertMatchesRegularExpression('/^NCR-\d{4}-00001$/',$report->ncr_no);$this->assertSame('DRAFT',$report->current_status);
        $this->assertDatabaseHas('ncr_items',['ncr_report_id'=>$report->id,'line_no'=>1]);$this->assertDatabaseHas('ncr_status_history',['ncr_report_id'=>$report->id,'action_code'=>'CREATE_DRAFT']);
        $attachment=$report->attachments()->firstOrFail();Storage::disk('local')->assertExists($attachment->storage_path);
    }

    public function test_reporter_can_select_one_ai_or_up_checkbox(): void
    {
        $user = $this->user(['ncr.create']);

        $this->actingAs($user)->get(route('ncr.create'))
            ->assertOk()
            ->assertSee('type="checkbox"', false)
            ->assertSee('name="legacy_form_scope_codes[]"', false);

        $this->actingAs($user)->post(route('ncr.store'), $this->payload([
            'legacy_form_scope_codes' => ['AI'],
        ]))->assertRedirect();

        $this->assertDatabaseHas('ncr_reports', ['legacy_form_scope_code' => 'AI']);

        $this->actingAs($user)->post(route('ncr.store'), $this->payload([
            'legacy_form_scope_codes' => ['AI', 'UP'],
        ]))->assertSessionHasErrors('legacy_form_scope_codes');
    }

    public function test_product_section_offers_add_item_button(): void
    {
        $user = $this->user(['ncr.create']);

        $this->actingAs($user)->get(route('ncr.create'))
            ->assertOk()
            ->assertSee('+ เพิ่มรายการ');
    }

    public function test_problem_department_defaults_to_reporter_department(): void
    {
        $user = $this->user(['ncr.create']);

        $response = $this->actingAs($user)->get(route('ncr.create'));

        $response->assertOk()->assertSee('หน่วยงานที่พบปัญหา');
        $this->assertMatchesRegularExpression(
            '/<select\s+name="related_department_id".*?<option\s+value="'.$this->department->id.'"[^>]*selected/s',
            $response->getContent()
        );
    }

    public function test_report_can_create_edit_and_remove_multiple_items():void
    {
        $user=$this->user(['ncr.create','ncr.update','ncr.view']);
        $productA = Product::create(['code'=>'A','name_th'=>'สินค้า A','is_active'=>true]);
        $productB = Product::create(['code'=>'B','name_th'=>'สินค้า B','is_active'=>true]);
        $items=[
            ['product_id'=>$productA->id,'product_code'=>'A','product_name'=>'สินค้า A','lot_no'=>'L1','defect_quantity'=>1,'total_quantity'=>10,'unit_name'=>'PCS'],
            ['product_id'=>$productB->id,'product_code'=>'B','product_name'=>'สินค้า B','lot_no'=>'L2','defect_quantity'=>2,'total_quantity'=>20,'unit_name'=>'PCS'],
        ];
        $this->actingAs($user)->post(route('ncr.store'),$this->payload(['items'=>$items]))->assertRedirect();
        $report=NcrReport::firstOrFail();$this->assertCount(2,$report->items);$this->assertSame('สินค้า A',$report->items->first()->product_name);
        $this->actingAs($user)->get(route('ncr.index', ['search' => 'สินค้า B']))->assertOk()->assertSee($report->ncr_no);
        $this->actingAs($user)->get(route('ncr.show',$report))->assertOk()->assertSee('สินค้า A')->assertSee('สินค้า B');
        $items[1]['item_id'] = $report->items()->where('product_id', $productB->id)->firstOrFail()->id;
        $this->actingAs($user)->put(route('ncr.update',$report),$this->payload(['version_no'=>$report->version_no,'items'=>[$items[1]]]))->assertRedirect();
        $this->assertCount(1,$report->fresh()->items);$this->assertSame('สินค้า B',$report->fresh()->items->first()->product_name);
    }

    public function test_report_does_not_store_a_duplicate_product_snapshot(): void
    {
        foreach (['product_code', 'product_name', 'lot_no', 'po_no', 'batch_no', 'customer_name', 'defect_quantity', 'defect_unit_name', 'total_quantity', 'total_unit_name', 'unit_name'] as $column) {
            $this->assertFalse(Schema::hasColumn('ncr_reports', $column), "ncr_reports.{$column} should be stored on ncr_items only");
        }
    }


    public function test_each_item_rejects_defect_quantity_greater_than_total():void
    {
        $user=$this->user(['ncr.create']);
        $this->actingAs($user)->post(route('ncr.store'),$this->payload(['items'=>[['product_id'=>$this->product->id,'product_name'=>'ผิด','defect_quantity'=>11,'total_quantity'=>10]]]))
            ->assertSessionHasErrors('items.0.defect_quantity');
        $this->assertDatabaseCount('ncr_reports',0);
    }
    public function test_list_supports_search_status_and_department_filters():void
    {
        $user=$this->user(['ncr.create','ncr.view']);$target=Product::create(['code'=>'TARGET','name_th'=>'Target Product','is_active'=>true]);$this->actingAs($user)->post(route('ncr.store'),$this->payload(['product_id'=>$target->id,'product_name'=>'Target Product','product_code'=>'TARGET','related_department_id'=>$this->department->id]));
        $this->actingAs($user)->get(route('ncr.index',['search'=>'Target','status'=>'DRAFT','department_id'=>$this->department->id]))->assertOk()->assertSee('Target Product')->assertSee($user->name)->assertSee($user->email)->assertSee('20/08/2569');
    }
    public function test_list_has_pdf_download_and_visible_user_can_download_it():void
    {
        $user=$this->user(['ncr.create','ncr.view']);
        $this->actingAs($user)->post(route('ncr.store'),$this->payload());
        $report=NcrReport::firstOrFail();

        $this->actingAs($user)->get(route('ncr.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('ncr.pdf',$report).'"', false)
            ->assertSee('ดาวน์โหลดได้เมื่อปิด NCR แล้ว');
        $this->actingAs($user)->get(route('ncr.pdf',$report))->assertStatus(409);
        $this->actingAs($user)->get(route('ncr.pdf',['ncr'=>$report,'preview'=>1]))->assertStatus(409);

        $report->update(['current_status'=>'CLOSED']);

        $this->actingAs($user)->get(route('ncr.index'))
            ->assertOk()
            ->assertSee('โหลด PDF')
            ->assertSee('View PDF')
            ->assertSee(route('ncr.pdf',['ncr'=>$report,'preview'=>1]),false)
            ->assertSee(route('ncr.pdf',$report),false);

        $this->actingAs($user)->get(route('ncr.pdf',$report))
            ->assertOk()
            ->assertHeader('content-type','application/pdf')
            ->assertHeader('content-disposition','attachment; filename="'.$report->ncr_no.'.pdf"');

        $this->actingAs($user)->get(route('ncr.pdf',['ncr'=>$report,'preview'=>1]))
            ->assertOk()
            ->assertHeader('content-type','application/pdf')
            ->assertHeader('content-disposition','inline; filename="'.$report->ncr_no.'.pdf"');
    }
    public function test_user_without_profile_department_can_select_active_reporter_department():void
    {
        $user=User::factory()->create(['department_id'=>null]);$user->givePermissionTo(Permission::findOrCreate('ncr.create'));
        $this->actingAs($user)->get(route('ncr.create'))->assertOk()->assertSee('หน่วยงานผู้รายงาน');
        $this->actingAs($user)->post(route('ncr.store'),$this->payload(['reporter_department_id'=>$this->department->id]))->assertRedirect();
        $this->assertDatabaseHas('ncr_reports',['reporter_id'=>$user->id,'reporter_department_id'=>$this->department->id]);
    }
    public function test_owner_can_update_and_submit_while_other_user_cannot_update():void
    {
        $owner=$this->user(['ncr.create','ncr.update','ncr.submit']);$this->actingAs($owner)->post(route('ncr.store'),$this->payload());$report=NcrReport::firstOrFail();
        $other=$this->user(['ncr.update']);$this->actingAs($other)->put(route('ncr.update',$report),$this->payload(['version_no'=>$report->version_no]))->assertForbidden();
        $this->actingAs($owner)->put(route('ncr.update',$report),$this->payload(['product_name'=>'Updated','version_no'=>$report->version_no]))->assertRedirect();$report->refresh();
        $this->actingAs($owner)->post(route('ncr.submit',$report),['version_no'=>$report->version_no])->assertRedirect();
        $this->assertSame('INITIAL_REVIEW',$report->fresh()->current_status);$this->assertDatabaseHas('ncr_status_history',['ncr_report_id'=>$report->id,'from_status'=>'DRAFT','to_status'=>'INITIAL_REVIEW','action_code'=>'SUBMIT']);
    }
    public function test_draft_is_hidden_from_everyone_except_its_creator():void
    {
        Storage::fake('local');
        $owner=$this->user(['ncr.create','ncr.view']);
        $this->actingAs($owner)->post(route('ncr.store'),$this->payload()+['attachments'=>[UploadedFile::fake()->create('private.pdf',10,'application/pdf')]]);
        $report=NcrReport::firstOrFail();$attachment=$report->attachments()->firstOrFail();
        $other=$this->user(['ncr.view']);
        $this->actingAs($other)->get(route('ncr.index'))->assertOk()->assertDontSee($report->ncr_no);
        $this->actingAs($other)->get(route('ncr.show',$report))->assertNotFound();
        $this->actingAs($other)->get(route('ncr.pdf',$report))->assertNotFound();
        $this->actingAs($other)->get(route('ncr.pdf',['ncr'=>$report,'preview'=>1]))->assertNotFound();
        $this->actingAs($other)->get(route('ncr.attachments.download',[$report,$attachment]))->assertNotFound();
        $this->actingAs($owner)->get(route('ncr.index'))->assertOk()->assertSee($report->ncr_no);
        $this->actingAs($owner)->get(route('ncr.show',$report))->assertOk();
    }
    public function test_stale_version_is_rejected_with_conflict():void
    {
        $user=$this->user(['ncr.create','ncr.update']);$this->actingAs($user)->post(route('ncr.store'),$this->payload());$report=NcrReport::firstOrFail();
        $this->actingAs($user)->put(route('ncr.update',$report),$this->payload(['version_no'=>999]))->assertStatus(409);
    }
    private function user(array $permissions):User{$u=User::factory()->create(['department_id'=>$this->department->id]);foreach($permissions as $p)$u->givePermissionTo(Permission::findOrCreate($p));return $u;}
    private function payload(array $overrides=[]):array
    {
        $item=['product_id'=>$this->product->id,'product_code'=>'P001','product_name'=>'Product A','lot_no'=>'LOT-1','defect_quantity'=>2,'total_quantity'=>10,'unit_name'=>'PCS','problem_description'=>'พบความผิดปกติ'];
        foreach(['product_id','product_code','product_name','lot_no','po_no','batch_no','customer_name','defect_quantity','total_quantity','unit_name'] as $field)if(array_key_exists($field,$overrides))$item[$field]=$overrides[$field];
        return array_merge(['report_date'=>'2026-08-20','found_date'=>'2026-08-20','problem_type_id'=>$this->problemType->id,'severity_level_id'=>$this->severity->id,'problem_description'=>'พบความผิดปกติ','items'=>[$item]],$overrides);
    }
}
