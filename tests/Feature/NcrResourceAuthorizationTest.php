<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NcrActionAttachment;
use App\Models\NcrAttachment;
use App\Models\NcrCorrectiveAction;
use App\Models\NcrInitialReview;
use App\Models\NcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcrResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrelated_user_cannot_edit_a_draft_by_direct_url_in_production(): void
    {
        $reporter = User::factory()->create();
        $unrelated = $this->userWithPermission('ncr.update');
        $report = $this->createDraft($reporter);

        $this->withProductionEnvironment(function () use ($unrelated, $report): void {
            $this->actingAs($unrelated)
                ->get(route('ncr.edit', $report))
                ->assertNotFound();
        });
    }

    public function test_unrelated_user_cannot_download_report_attachment_by_direct_url_in_production(): void
    {
        Storage::fake('local');
        $reporter = User::factory()->create();
        $unrelated = $this->userWithPermission('ncr.view');
        $report = $this->createDraft($reporter);
        $attachment = NcrAttachment::create([
            'ncr_report_id' => $report->id,
            'attachment_stage' => 'REPORTER',
            'original_filename' => 'evidence.pdf',
            'stored_filename' => 'evidence.pdf',
            'storage_path' => 'ncr/evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'description' => null,
            'uploaded_at' => now(),
            'uploaded_by' => $reporter->id,
            'is_active' => true,
        ]);
        Storage::disk('local')->put($attachment->storage_path, 'private evidence');

        $this->withProductionEnvironment(function () use ($unrelated, $report, $attachment): void {
            $this->actingAs($unrelated)
                ->get(route('ncr.attachments.download', [$report, $attachment]))
                ->assertNotFound();
        });
    }

    public function test_unrelated_user_cannot_download_action_attachment_by_direct_url_in_production(): void
    {
        Storage::fake('local');
        $reporter = User::factory()->create();
        $unrelated = $this->userWithPermission('ncr.view');
        $report = $this->createDraft($reporter);
        $action = NcrCorrectiveAction::create([
            'ncr_report_id' => $report->id,
            'action_no' => 1,
            'action_type' => 'CORRECTIVE',
            'corrective_action_detail' => 'Restricted action fixture',
            'responsible_department_id' => $this->department()->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);
        $attachment = NcrActionAttachment::create([
            'corrective_action_id' => $action->id,
            'original_filename' => 'action-evidence.pdf',
            'stored_filename' => 'action-evidence.pdf',
            'storage_path' => 'ncr/action-evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'description' => null,
            'uploaded_at' => now(),
            'uploaded_by' => $reporter->id,
            'is_active' => true,
        ]);
        Storage::disk('local')->put($attachment->storage_path, 'private action evidence');

        $this->withProductionEnvironment(function () use ($unrelated, $report, $attachment): void {
            $this->actingAs($unrelated)
                ->get(route('ncr.corrective-action-attachments.download', [$report, $attachment]))
                ->assertNotFound();
        });
    }

    public function test_reporter_can_edit_and_download_own_draft_resources(): void
    {
        Storage::fake('local');
        $reporter = $this->userWithPermission('ncr.update');
        $reporter->givePermissionTo(Permission::findOrCreate('ncr.view', 'web'));
        $report = $this->createDraft($reporter);
        $attachment = NcrAttachment::create([
            'ncr_report_id' => $report->id,
            'attachment_stage' => 'REPORTER',
            'original_filename' => 'evidence.pdf',
            'stored_filename' => 'evidence.pdf',
            'storage_path' => 'ncr/owner-evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'description' => null,
            'uploaded_at' => now(),
            'uploaded_by' => $reporter->id,
            'is_active' => true,
        ]);
        Storage::disk('local')->put($attachment->storage_path, 'owner evidence');

        $this->actingAs($reporter)->get(route('ncr.edit', $report))->assertOk();
        $this->actingAs($reporter)
            ->get(route('ncr.attachments.download', [$report, $attachment]))
            ->assertOk();
    }

    public function test_corrective_action_owner_can_view_assigned_ncr(): void
    {
        $reporter = User::factory()->create(['department_id' => $this->department()->id]);
        $actionOwner = $this->userWithPermission('ncr.view');
        $report = $this->createDraft($reporter);
        $report->update(['current_status' => 'ACTION_IN_PROGRESS']);

        NcrCorrectiveAction::create([
            'ncr_report_id' => $report->id,
            'action_no' => 1,
            'action_type' => 'CORRECTIVE',
            'corrective_action_detail' => 'Action assigned directly to this user',
            'responsible_department_id' => $this->department()->id,
            'owner_user_id' => $actionOwner->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);

        $this->actingAs($actionOwner)
            ->get(route('ncr.show', $report))
            ->assertOk();
    }

    public function test_initial_reviewer_can_always_view_an_ncr_they_reviewed(): void
    {
        $reporter = User::factory()->create(['department_id' => $this->department()->id]);
        $reviewer = $this->userWithPermission('ncr.review');
        $reviewer->givePermissionTo(Permission::findOrCreate('ncr.view', 'web'));
        $unrelatedReviewer = $this->userWithPermission('ncr.review');
        $unrelatedReviewer->givePermissionTo(Permission::findOrCreate('ncr.view', 'web'));
        $report = $this->createDraft($reporter);
        $report->update([
            'current_status' => 'ACTION_IN_PROGRESS',
            'current_owner_user_id' => null,
            'current_owner_department_id' => null,
            'related_department_id' => null,
        ]);

        NcrInitialReview::create([
            'ncr_report_id' => $report->id,
            'review_round' => 1,
            'is_complete' => true,
            'review_result' => 'COMPLETE',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('ncr.show', $report))
            ->assertOk();

        $this->actingAs($unrelatedReviewer)
            ->get(route('ncr.show', $report))
            ->assertNotFound();
    }

    private function createDraft(User $reporter): NcrReport
    {
        return NcrReport::create([
            'ncr_no' => 'NCR-2026-RESOURCE-' . $reporter->id,
            'report_date' => '2026-09-14',
            'found_date' => '2026-09-14',
            'reporter_id' => $reporter->id,
            'reporter_department_id' => $this->department()->id,
            'defect_quantity' => 1,
            'total_quantity' => 1,
            'problem_description' => 'Resource authorization fixture',
            'current_status' => 'DRAFT',
            'version_no' => 1,
            'created_by' => $reporter->id,
            'updated_by' => $reporter->id,
        ]);
    }

    private function department(): Department
    {
        return Department::firstOrCreate([
            'code' => 'RESOURCE-AUTH',
        ], [
            'name_th' => 'Resource authorization fixtures',
            'is_active' => true,
        ]);
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));

        return $user;
    }

    private function withProductionEnvironment(callable $callback): void
    {
        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            $callback();
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }
}
