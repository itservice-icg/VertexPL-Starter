<?php
namespace Tests\Feature;
use App\Events\NcrWorkflowActivity;
use App\Models\Department;
use App\Models\DispositionType;
use App\Models\NcrDisposition;
use App\Models\NcrReport;
use App\Models\ProblemType;
use App\Models\Product;
use App\Models\SeverityLevel;
use App\Models\User;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
class NcrPhaseThreeTest extends TestCase{
 public function test_submit_moves_draft_directly_to_initial_review():void{$reporter=$this->user(['ncr.create','ncr.submit']);$this->actingAs($reporter)->post(route('ncr.store'),$this->reportPayload());$report=NcrReport::firstOrFail();$this->actingAs($reporter)->post(route('ncr.submit',$report),['version_no'=>$report->version_no])->assertRedirect();$report->refresh();$this->assertSame('INITIAL_REVIEW',$report->current_status);$this->assertDatabaseHas('ncr_status_history',['ncr_report_id'=>$report->id,'from_status'=>'DRAFT','to_status'=>'INITIAL_REVIEW','action_code'=>'SUBMIT','changed_by'=>$reporter->id]);}
 use RefreshDatabase;
 private Department $department;private ProblemType $problem;private SeverityLevel $severity;private DispositionType $dispositionType;
 protected function setUp():void{parent::setUp();Unit::create(['code'=>'PCS','name_th'=>'ชิ้น','is_active'=>true]);Product::create(['code'=>'P001','name_th'=>'สินค้า A','is_active'=>true]);$this->department=Department::create(['code'=>'QA','name_th'=>'ประกันคุณภาพ','is_active'=>true]);$this->problem=ProblemType::create(['code'=>'PROCESS','name_th'=>'กระบวนการ','is_active'=>true]);$this->severity=SeverityLevel::create(['code'=>'HIGH','name_th'=>'สูง','is_active'=>true]);$this->dispositionType=DispositionType::create(['code'=>'REWORK','name_th'=>'แก้ไขงาน','is_active'=>true]);}
 public function test_incomplete_initial_review_returns_to_reporter_with_required_reason():void{$report=$this->submitted();$reviewer=$this->user(['ncr.review']);$this->actingAs($reviewer)->post(route('ncr.initial-review',$report),['version_no'=>$report->version_no,'review_result'=>'INCOMPLETE'])->assertSessionHasErrors('completeness_note');$this->actingAs($reviewer)->post(route('ncr.initial-review',$report),['version_no'=>$report->version_no,'review_result'=>'INCOMPLETE','completeness_note'=>'หลักฐานไม่ครบ'])->assertRedirect();$report->refresh();$this->assertSame('RETURNED_FOR_CORRECTION',$report->current_status);$this->assertSame($report->reporter_id,$report->current_owner_user_id);$this->assertDatabaseHas('ncr_initial_reviews',['ncr_report_id'=>$report->id,'review_result'=>'INCOMPLETE']);$reporter=$report->reporter;$reporter->givePermissionTo(Permission::findOrCreate('ncr.view'));$reporter->givePermissionTo(Permission::findOrCreate('ncr.update'));$this->actingAs($reporter)->get(route('ncr.show',$report))->assertOk()->assertSee('เหตุผลที่ส่งกลับ:')->assertSee('หลักฐานไม่ครบ')->assertSee('href="'.route('ncr.edit',$report).'"',false);$this->actingAs($reporter)->get(route('ncr.edit',$report))->assertOk()->assertSee('เหตุผลที่ส่งกลับให้แก้ไข:')->assertSee('หลักฐานไม่ครบ');}
 public function test_complete_initial_review_requires_disposition_data_with_thai_messages():void{$report=$this->submitted();$reviewer=$this->user(['ncr.review']);$response=$this->actingAs($reviewer)->post(route('ncr.initial-review',$report),['version_no'=>$report->version_no,'review_result'=>'COMPLETE']);$response->assertSessionHasErrors(['disposition_type_id'=>'กรุณาเลือกข้อเสนอแนะการจัดการเบื้องต้นเมื่อข้อมูลครบถ้วน','disposition_detail'=>'กรุณาระบุรายละเอียดข้อเสนอแนะการจัดการเบื้องต้นเมื่อข้อมูลครบถ้วน','responsible_department_id'=>'กรุณาเลือกแผนก/ฝ่ายที่รับผิดชอบเมื่อข้อมูลครบถ้วน']);$this->assertDatabaseCount('ncr_initial_reviews',0);$this->assertDatabaseCount('ncr_dispositions',0);}
 public function test_status_history_displays_utc_timestamp_in_bangkok_time():void{\Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::create(2026,8,25,9,41,0,'UTC'));try{$report=$this->submitted();$viewer=$this->user(['ncr.view']);$this->actingAs($viewer)->get(route('ncr.show',$report))->assertOk()->assertSee('25/08/2026 16:41');}finally{\Illuminate\Support\Carbon::setTestNow();}}
 public function test_complete_initial_review_automatically_submits_disposition_for_manager():void{Event::fake([NcrWorkflowActivity::class]);$report=$this->submitted();$reviewer=$this->user(['ncr.review','ncr.disposition.manage']);$this->actingAs($reviewer)->post(route('ncr.initial-review',$report),$this->reviewPayload($report))->assertRedirect();$report->refresh();$this->assertSame('PENDING_MANAGER_APPROVAL',$report->current_status);$this->assertDatabaseHas('ncr_dispositions',['ncr_report_id'=>$report->id,'revision_no'=>1,'status'=>'PENDING','proposed_due_date'=>null]);Event::assertDispatched(NcrWorkflowActivity::class,2);}
 public function test_manager_sees_pending_disposition_details_next_to_decision_form():void{$report=$this->pendingApproval();$manager=$this->user(['ncr.view','ncr.disposition.approve']);$this->actingAs($manager)->get(route('ncr.show',$report))->assertOk()->assertSee('ข้อเสนอแนะการจัดการที่รอพิจารณา')->assertSee('แก้ไขสินค้า')->assertSee('แผนก/ฝ่ายที่รับผิดชอบ');}
  public function test_manager_can_approve_and_approved_disposition_is_locked():void{$report=$this->pendingApproval();$manager=$this->user(['ncr.disposition.approve']);$this->actingAs($manager)->post(route('ncr.manager-decision',$report),['version_no'=>$report->version_no,'decision'=>'APPROVED','decision_note'=>'อนุมัติ'])->assertRedirect();$report->refresh();$this->assertSame('DISPOSITION_APPROVED',$report->current_status);$this->assertSame($this->department->id,$report->current_owner_department_id);$this->assertDatabaseHas('ncr_dispositions',['ncr_report_id'=>$report->id,'status'=>'APPROVED']);$this->assertDatabaseHas('ncr_approvals',['ncr_report_id'=>$report->id,'decision'=>'APPROVED']);$this->actingAs($manager)->post(route('ncr.manager-decision',$report),['version_no'=>$report->version_no,'decision'=>'REJECTED','decision_note'=>'เปลี่ยนใจ'])->assertStatus(409);}
  public function test_revised_disposition_is_created_pending_without_a_draft_status():void{$report=$this->pendingApproval();$manager=$this->user(['ncr.view','ncr.disposition.approve']);$this->actingAs($manager)->post(route('ncr.manager-decision',$report),['version_no'=>$report->version_no,'decision'=>'REJECTED','decision_note'=>'แก้ข้อเสนอ'])->assertRedirect();$report->refresh();$reviewer=User::findOrFail($report->dispositions()->first()->proposed_by);$this->actingAs($reviewer)->post(route('ncr.disposition.revise',$report),['version_no'=>$report->version_no,'disposition_type_id'=>$this->dispositionType->id,'disposition_detail'=>'Revision sent directly','responsible_department_id'=>$this->department->id])->assertRedirect();$this->assertSame('PENDING_MANAGER_APPROVAL',$report->fresh()->current_status);$this->assertDatabaseHas('ncr_dispositions',['ncr_report_id'=>$report->id,'revision_no'=>2,'status'=>'PENDING','disposition_detail'=>'Revision sent directly']);$this->assertDatabaseMissing('ncr_dispositions',['ncr_report_id'=>$report->id,'status'=>'DRAFT']);}
  public function test_resubmitting_an_existing_draft_revision_returns_it_to_the_same_approver():void
  {
      $report = $this->pendingApproval();
      $manager = $this->user(['ncr.view', 'ncr.disposition.approve']);
      $this->actingAs($manager)->post(route('ncr.manager-decision', $report), [
          'version_no' => $report->version_no,
          'decision' => 'REJECTED',
          'decision_note' => 'แก้ข้อเสนอ',
      ])->assertRedirect();
      $report->refresh();
      $reviewer = User::findOrFail($report->dispositions()->first()->proposed_by);

      // Reproduce the state shown in the user's screenshot: a revision was saved as DRAFT already.
      NcrDisposition::create([
          'ncr_report_id' => $report->id,
          'disposition_type_id' => $this->dispositionType->id,
          'revision_no' => 2,
          'disposition_detail' => 'Revision 2 draft',
          'responsible_department_id' => $this->department->id,
          'proposed_due_date' => null,
          'status' => 'DRAFT',
          'proposed_at' => now(),
          'proposed_by' => $reviewer->id,
      ]);
      $report->increment('version_no');
      $report->refresh();
      $this->assertSame('DRAFT', $report->dispositions()->first()->status);

      $this->actingAs($reviewer)->get(route('ncr.show', $report))
          ->assertOk()
          ->assertSee('id="disposition-revision"', false)
          ->assertSee('Revision 2 draft')
          ->assertSee('ส่งข้อเสนอฉบับแก้ไขให้ผู้จัดการอนุมัติ');

      $this->actingAs($reviewer)->post(route('ncr.disposition.revise', $report), [
          'version_no' => $report->version_no,
          'disposition_type_id' => $this->dispositionType->id,
          'disposition_detail' => 'Revision 2 final',
          'responsible_department_id' => $this->department->id,
      ])->assertRedirect();

      $this->assertSame('PENDING_MANAGER_APPROVAL', $report->fresh()->current_status);
      $this->assertDatabaseHas('ncr_dispositions', [
          'ncr_report_id' => $report->id,
          'revision_no' => 2,
          'status' => 'PENDING',
          'disposition_detail' => 'Revision 2 final',
          'proposed_due_date' => null,
      ]);
      $this->assertDatabaseMissing('ncr_dispositions', [
          'ncr_report_id' => $report->id,
          'status' => 'DRAFT',
      ]);
      $this->assertSame(2, $report->fresh()->dispositions()->count());
      $this->actingAs($manager)->get(route('ncr.show', $report))
          ->assertOk()
          ->assertSee('ข้อเสนอแนะการจัดการที่รอพิจารณา')
          ->assertSee('Revision 2 final');
  }
 private function submitted():NcrReport{$reporter=$this->user(['ncr.create','ncr.submit']);$this->actingAs($reporter)->post(route('ncr.store'),$this->reportPayload());$r=NcrReport::firstOrFail();$this->actingAs($reporter)->post(route('ncr.submit',$r),['version_no'=>$r->version_no]);return $r->fresh();}
  private function pendingApproval():NcrReport{$r=$this->submitted();$reviewer=$this->user(['ncr.view','ncr.review','ncr.disposition.manage']);$this->actingAs($reviewer)->post(route('ncr.initial-review',$r),$this->reviewPayload($r));return $r->fresh();}
 private function reviewPayload(NcrReport $r):array{return ['version_no'=>$r->version_no,'review_result'=>'COMPLETE','completeness_note'=>'ครบ','disposition_type_id'=>$this->dispositionType->id,'disposition_detail'=>'แก้ไขสินค้า','responsible_department_id'=>$this->department->id];}
 private function reportPayload():array{return ['report_date'=>now()->format('Y-m-d'),'found_date'=>now()->format('Y-m-d'),'problem_type_id'=>$this->problem->id,'severity_level_id'=>$this->severity->id,'problem_description'=>'ผิดปกติ','items'=>[['product_id'=>Product::firstOrFail()->id,'product_name'=>'สินค้า A','defect_quantity'=>1,'total_quantity'=>10,'unit_name'=>'PCS']]];}
 private function user(array $permissions):User{$u=User::factory()->create(['department_id'=>$this->department->id]);foreach($permissions as $p)$u->givePermissionTo(Permission::findOrCreate($p));return $u;}
}
