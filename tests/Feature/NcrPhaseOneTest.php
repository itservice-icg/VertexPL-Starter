<?php

namespace Tests\Feature;

use App\Models\DocumentSequence;
use App\Models\ProblemType;
use App\Models\User;
use App\Services\NcrFileUploadService;
use App\Services\NcrNumberService;
use Database\Seeders\NcrFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NcrPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_requires_permission_and_supports_crud(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('ncr-masters.index', 'problem-types'))->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('ncr.master.manage'));

        $this->actingAs($user)->get(route('ncr-masters.create', 'problem-types'))
            ->assertOk()
            ->assertSee('name="require_attachment" value="1"', false)
            ->assertDontSee('@checked', false)
            ->assertDontSee('?? true', false);

        $this->actingAs($user)->post(route('ncr-masters.store', 'problem-types'), [
            'code' => 'PROCESS', 'name_th' => 'กระบวนการ', 'require_attachment' => '1', 'is_active' => '1',
        ])->assertRedirect();
        $record = ProblemType::where('code', 'PROCESS')->firstOrFail();
        $this->assertTrue($record->require_attachment);

        $this->actingAs($user)->put(route('ncr-masters.update', ['problem-types', $record]), [
            'code' => 'PROCESS', 'name_th' => 'กระบวนการผลิต', 'is_active' => '0',
        ])->assertRedirect(route('ncr-masters.index', 'problem-types'));
        $this->assertFalse($record->fresh()->is_active);
        $this->actingAs($user)->delete(route('ncr-masters.destroy', ['problem-types', $record]))->assertRedirect();
        $this->assertSoftDeleted($record);
    }

    public function test_foundation_seeder_creates_workflow_permissions_roles_statuses_and_master_data(): void
    {
        $this->seed(NcrFoundationSeeder::class);
        $this->assertDatabaseHas('permissions', ['name' => 'ncr.close']);
        $this->assertDatabaseMissing('permissions', ['name' => 'ncr.reopen']);
        $this->assertDatabaseHas('roles', ['name' => 'QA']);
        $this->assertTrue(Role::findByName('Plant Manager')->hasPermissionTo('dashboard.view'));
        $this->assertDatabaseHas('problem_types', ['code' => 'RAW_MATERIAL']);
    }

    public function test_running_number_is_sequential_and_resets_by_year(): void
    {
        $service = app(NcrNumberService::class);
        $this->assertSame('NCR-2026-00001', $service->next(2026));
        $this->assertSame('NCR-2026-00002', $service->next(2026));
        $this->assertSame('NCR-2027-00001', $service->next(2027));
        $this->assertSame(2, DocumentSequence::where('year', 2026)->value('last_number'));
    }

    public function test_file_upload_service_stores_and_deletes_allowed_file(): void
    {
        Storage::fake('local');
        $service = app(NcrFileUploadService::class);
        $metadata = $service->store(UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'));
        Storage::disk('local')->assertExists($metadata['stored_path']);
        $this->assertTrue($service->delete($metadata['stored_path']));
        Storage::disk('local')->assertMissing($metadata['stored_path']);
    }

    public function test_used_master_data_cannot_be_deleted_and_explains_why(): void
    {
        $user = $this->userWithPermission('ncr.master.manage');
        $department = \App\Models\Department::create(['code' => 'QA', 'name_th' => 'ประกันคุณภาพ', 'is_active' => true]);
        $type = ProblemType::create(['code' => 'USED', 'name_th' => 'ถูกใช้งาน', 'is_active' => true]);
        $severity = \App\Models\SeverityLevel::create(['code' => 'LOW', 'name_th' => 'ต่ำ', 'is_active' => true]);
        \App\Models\NcrReport::create(['ncr_no'=>'NCR-2026-99999','report_date'=>'2026-08-20','found_date'=>'2026-08-20','reporter_id'=>$user->id,'reporter_department_id'=>$department->id,'problem_type_id'=>$type->id,'severity_level_id'=>$severity->id,'defect_quantity'=>1,'total_quantity'=>1,'problem_description'=>'test','current_status'=>'DRAFT','version_no'=>1,'created_by'=>$user->id,'updated_by'=>$user->id]);

        $response = $this->actingAs($user)->from(route('ncr-masters.index', 'problem-types'))->delete(route('ncr-masters.destroy', ['problem-types', $type]));
        $response
            ->assertSessionHas('error', 'ไม่สามารถลบประเภทปัญหานี้ได้ เนื่องจากมี NCR ที่ใช้งานประเภทปัญหานี้อยู่');
        $this->actingAs($user)->get(route('ncr-masters.index', 'problem-types'))
            ->assertOk()->assertSee('data-flash-type="error"', false)->assertSee('ไม่สามารถลบประเภทปัญหานี้ได้ เนื่องจากมี NCR ที่ใช้งานประเภทปัญหานี้อยู่');
        $this->assertNotNull($type->fresh());
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate($permission));
        return $user;
    }
}
