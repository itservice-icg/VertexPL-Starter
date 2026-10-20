<?php

namespace App\Services;

use App\Models\NcrAttachment;
use App\Models\NcrItem;
use App\Models\NcrReport;
use App\Models\NcrStatusHistory;
use App\Models\User;
use App\Models\Department;
use App\Models\NcrInitialReview;
use App\Models\NcrDisposition;
use App\Models\NcrApproval;
use App\Models\NcrCorrectiveAction;
use App\Models\NcrActionAttachment;
use App\Models\NcrActionProgressHistory;
use App\Models\NcrActionVerification;
use App\Models\NcrQaFollowup;
use App\Models\NcrClosure;
use App\Models\NcrCorrectiveActionCycle;
use App\Models\NcrCorrectiveActionPlanSnapshot;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Events\NcrWorkflowActivity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Validation\ValidationException;

class NcrWorkflowService
{
    public function __construct(private NcrNumberService $numbers, private NcrFileUploadService $files) {}

    public function createDraft(array $data, User $actor, array $items, array $attachments = []): NcrReport
    {
        $departmentId = $actor->department_id ?: ($data['reporter_department_id'] ?? null);
        if (! $departmentId || ! Department::query()->whereKey($departmentId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['reporter_department_id' => 'กรุณาเลือกหน่วยงานผู้รายงานที่เปิดใช้งาน']);
        }
        unset($data['reporter_department_id']);
        return DB::transaction(function () use ($data,$actor,$items,$attachments,$departmentId) {
            $items = $this->snapshotItemReferences(null, $items);
            $data = $this->snapshotSupplierReference($data, null);
            $report = NcrReport::create(array_merge($data, ['ncr_no'=>$this->numbers->next(),'reporter_id'=>$actor->id,'reporter_department_id'=>$departmentId,'current_status'=>'DRAFT','current_owner_user_id'=>$actor->id,'current_owner_department_id'=>$departmentId,'version_no'=>1,'created_by'=>$actor->id,'updated_by'=>$actor->id]));
            $this->syncItems($report,$items,$actor); $this->history($report,null,'DRAFT','CREATE_DRAFT',$actor);
            foreach ($attachments as $file) $this->attach($report,$file,$actor);
            AuditLogger::log('ncr','ncr.created',"สร้าง NCR {$report->ncr_no}",$report,[],$this->snapshot($report));
            return $report;
        });
    }

    public function updateDraft(NcrReport $report, array $data, array $items, User $actor, int $version, array $attachments = []): NcrReport
    {
        unset($data['reporter_department_id']);
        $this->assertOwner($report,$actor); if (! in_array($report->current_status,['DRAFT','RETURNED_FOR_CORRECTION'],true)) throw new ConflictHttpException('สถานะปัจจุบันไม่อนุญาตให้แก้ไข');
        return DB::transaction(function () use ($report,$data,$items,$actor,$version,$attachments) {
            $items = $this->snapshotItemReferences($report, $items);
            $data = $this->snapshotSupplierReference($data, $report);
            $old=$this->snapshot($report); $affected=NcrReport::query()->whereKey($report->id)->where('version_no',$version)->update($data+['updated_by'=>$actor->id,'version_no'=>DB::raw('version_no + 1'),'updated_at'=>now()]);
            if ($affected!==1) throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้ใช้อื่น กรุณาโหลดหน้าใหม่');
            $report->refresh(); $this->syncItems($report,$items,$actor); foreach($attachments as $file)$this->attach($report,$file,$actor);
            AuditLogger::log('ncr','ncr.updated',"แก้ไข NCR {$report->ncr_no}",$report,$old,$this->snapshot($report)); return $report;
        });
    }

    public function submit(NcrReport $report, User $actor, int $version): NcrReport
    {
        $this->assertOwner($report,$actor); if(!in_array($report->current_status,['DRAFT','RETURNED_FOR_CORRECTION'],true))throw new ConflictHttpException('สถานะปัจจุบันส่งตรวจไม่ได้');
        return $this->transition($report,'INITIAL_REVIEW','SUBMIT',null,$actor,$version);
    }

    public function initialReview(NcrReport $report, User $actor, int $version, array $data): NcrReport
    {
        if($report->current_status!=='INITIAL_REVIEW')throw new ConflictHttpException('บันทึกผลตรวจได้เฉพาะ NCR ที่อยู่ระหว่าง Initial Review');
        return DB::transaction(function()use($report,$actor,$version,$data){
            if((int)$report->version_no!==$version)throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้ใช้อื่น กรุณาโหลดหน้าใหม่');
            $complete=$data['review_result']==='COMPLETE';$round=(int)$report->initialReviews()->max('review_round')+1;
            NcrInitialReview::create(['ncr_report_id'=>$report->id,'review_round'=>$round,'is_complete'=>$complete,'completeness_note'=>$data['completeness_note']??null,'review_result'=>$data['review_result'],'reviewed_at'=>now(),'reviewed_by'=>$actor->id]);
            if(!$complete){$result=$this->transition($report,'RETURNED_FOR_CORRECTION','INITIAL_REVIEW_INCOMPLETE',$data['completeness_note'],$actor,$version,$report->reporter_id,$report->reporter_department_id);event(new NcrWorkflowActivity($result,'INITIAL_REVIEW_INCOMPLETE'));return $result;}
            $revision=(int)$report->dispositions()->max('revision_no')+1;
            NcrDisposition::create(['ncr_report_id'=>$report->id,'disposition_type_id'=>$data['disposition_type_id'],'revision_no'=>$revision,'disposition_detail'=>$data['disposition_detail'],'responsible_department_id'=>$data['responsible_department_id'],'proposed_due_date'=>null,'status'=>'PENDING','proposed_at'=>now(),'proposed_by'=>$actor->id]);
            $this->history($report,'INITIAL_REVIEW','INITIAL_REVIEW','INITIAL_REVIEW_COMPLETE',$actor,$data['completeness_note']??null);
            $result=$this->transition($report,'PENDING_MANAGER_APPROVAL','SUBMIT_DISPOSITION',null,$actor,$version);
            AuditLogger::log('ncr','ncr.initial_review_complete',"Initial Review {$report->ncr_no}",$result);
            event(new NcrWorkflowActivity($result,'INITIAL_REVIEW_COMPLETE'));
            event(new NcrWorkflowActivity($result,'SUBMIT_DISPOSITION'));
            return $result;
        });
    }

    public function submitDispositionRevision(NcrReport $report,User $actor,int $version,array $data):NcrReport
    {
        if($report->current_status!=='RETURNED_FOR_DISPOSITION_REVISION')throw new ConflictHttpException('สถานะปัจจุบันไม่อนุญาตให้แก้ไข Disposition');
        return DB::transaction(function()use($report,$actor,$version,$data){
            if((int)$report->version_no!==$version)throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้อื่น');
            $draft=$report->dispositions()->where('status','DRAFT')->latest('revision_no')->first();
            if($draft){
                $draft->update(['disposition_type_id'=>$data['disposition_type_id'],'disposition_detail'=>$data['disposition_detail'],'responsible_department_id'=>$data['responsible_department_id'],'proposed_due_date'=>null,'status'=>'PENDING','proposed_by'=>$actor->id,'proposed_at'=>now()]);
            }else{
                $revision=(int)$report->dispositions()->max('revision_no')+1;
                NcrDisposition::create(['ncr_report_id'=>$report->id,'disposition_type_id'=>$data['disposition_type_id'],'revision_no'=>$revision,'disposition_detail'=>$data['disposition_detail'],'responsible_department_id'=>$data['responsible_department_id'],'proposed_due_date'=>null,'status'=>'PENDING','proposed_at'=>now(),'proposed_by'=>$actor->id]);
            }
            $result=$this->transition($report,'PENDING_MANAGER_APPROVAL','SUBMIT_DISPOSITION',null,$actor,$version);
            event(new NcrWorkflowActivity($result,'DISPOSITION_REVISED'));
            event(new NcrWorkflowActivity($result,'SUBMIT_DISPOSITION'));
            return $result;
        });
    }

    public function reviseDisposition(NcrReport $report,User $actor,int $version,array $data):NcrReport
    {
        if($report->current_status!=='RETURNED_FOR_DISPOSITION_REVISION')throw new ConflictHttpException('สถานะปัจจุบันไม่อนุญาตให้แก้ไข Disposition');
        return DB::transaction(function()use($report,$actor,$version,$data){if((int)$report->version_no!==$version)throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้ใช้อื่น');$draft=$report->dispositions()->where('status','DRAFT')->latest('revision_no')->first();if($draft){$draft->update(['disposition_type_id'=>$data['disposition_type_id'],'disposition_detail'=>$data['disposition_detail'],'responsible_department_id'=>$data['responsible_department_id'],'proposed_by'=>$actor->id,'proposed_at'=>now()]);}else{$revision=(int)$report->dispositions()->max('revision_no')+1;NcrDisposition::create(['ncr_report_id'=>$report->id,'disposition_type_id'=>$data['disposition_type_id'],'revision_no'=>$revision,'disposition_detail'=>$data['disposition_detail'],'responsible_department_id'=>$data['responsible_department_id'],'proposed_due_date'=>null,'status'=>'DRAFT','proposed_at'=>now(),'proposed_by'=>$actor->id]);}$report->update(['updated_by'=>$actor->id,'version_no'=>DB::raw('version_no + 1')]);$report->refresh();event(new NcrWorkflowActivity($report,'DISPOSITION_REVISED'));return $report;});
    }

    public function submitDisposition(NcrReport $report,User $actor,int $version):NcrReport
    {
        if(!in_array($report->current_status,['INITIAL_REVIEW','RETURNED_FOR_DISPOSITION_REVISION'],true))throw new ConflictHttpException('สถานะปัจจุบันส่งอนุมัติไม่ได้');
        $disposition=$report->dispositions()->where('status','DRAFT')->latest('revision_no')->firstOrFail();
        return DB::transaction(function()use($report,$actor,$version,$disposition){$disposition->update(['status'=>'PENDING']);$result=$this->transition($report,'PENDING_MANAGER_APPROVAL','SUBMIT_DISPOSITION',null,$actor,$version);event(new NcrWorkflowActivity($result,'SUBMIT_DISPOSITION'));return $result;});
    }

    public function managerDecision(NcrReport $report,User $actor,int $version,string $decision,?string $note):NcrReport
    {
        if($report->current_status!=='PENDING_MANAGER_APPROVAL')throw new ConflictHttpException('NCR ไม่ได้รอผู้จัดการอนุมัติ');
        $disposition=$report->dispositions()->where('status','PENDING')->latest('revision_no')->firstOrFail();
        return DB::transaction(function()use($report,$actor,$version,$decision,$note,$disposition){
            NcrApproval::create(['ncr_report_id'=>$report->id,'disposition_id'=>$disposition->id,'approval_stage'=>'PLANT_MANAGER_APPROVAL','approval_level'=>1,'approver_id'=>$actor->id,'decision'=>$decision,'decision_note'=>$note,'decided_at'=>now()]);
            $approved=$decision==='APPROVED';
            $disposition->update($approved?['status'=>'APPROVED','approved_at'=>now(),'approved_by'=>$actor->id]:['status'=>'REJECTED','rejected_at'=>now(),'rejected_by'=>$actor->id,'rejection_reason'=>$note]);
            if($approved)$report->update(['due_date'=>$disposition->proposed_due_date]);
            $result=$this->transition($report,
                $approved ? 'DISPOSITION_APPROVED' : 'RETURNED_FOR_DISPOSITION_REVISION',
                $approved ? 'APPROVE_DISPOSITION' : 'REJECT_DISPOSITION',
                $note,$actor,$version,$approved?null:$disposition->proposed_by,$approved?$disposition->responsible_department_id:null);
            if ($approved) {
                $cycle = NcrCorrectiveActionCycle::create([
                    'ncr_report_id' => $result->id,
                    'cycle_no' => 1,
                    'origin' => 'INITIAL',
                    'status' => 'DRAFT',
                    'opened_by' => $actor->id,
                    'opened_at' => now(),
                ]);
                $result->update(['current_corrective_action_cycle_id' => $cycle->id]);
            }
            event(new NcrWorkflowActivity($result,$approved?'APPROVE_DISPOSITION':'REJECT_DISPOSITION'));
            return $result;
        });
    }

    public function destructionDecision(NcrReport $report, User $actor, int $version, string $decision, ?string $note): NcrReport
    {
        return DB::transaction(function () use ($report, $actor, $version, $decision, $note) {
            $locked = $this->lockReportForAction($report, $version, ['PENDING_DESTRUCTION_APPROVAL']);
            $disposition = $locked->dispositions()->where('status', 'APPROVED')->latest('revision_no')->firstOrFail();
            $cycle = $locked->currentCorrectiveActionCycle()->lockForUpdate()->first();
            if (! $cycle || $cycle->status !== 'APPROVED') {
                throw new ConflictHttpException('ไม่พบรอบ Corrective Action ปัจจุบันที่อนุมัติแล้ว');
            }
            $snapshot = $cycle->snapshots()->lockForUpdate()->latest('revision_no')->first();
            $actionApproval = NcrApproval::query()
                ->where('ncr_report_id', $locked->id)
                ->where('approval_stage', 'CORRECTIVE_ACTION_APPROVAL')
                ->where('decision', 'APPROVED')
                ->where('corrective_action_cycle_id', $cycle->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $actions = $this->currentCycleActions($locked)->lockForUpdate();

            if (
                $disposition->type?->code !== 'SCRAP'
                || ! $snapshot
                || ! $actionApproval
                || (int) $actionApproval->corrective_action_plan_snapshot_id !== (int) $snapshot->id
                || $actions->where('action_status', 'APPROVED')->doesntExist()
            ) {
                throw new ConflictHttpException('อนุมัติการทำลายได้เฉพาะ Disposition S ที่ Corrective Action ผ่านการอนุมัติแล้ว');
            }
            NcrApproval::create(['ncr_report_id'=>$locked->id,'disposition_id'=>$disposition->id,'corrective_action_cycle_id'=>$cycle->id,'corrective_action_plan_snapshot_id'=>$snapshot->id,'approval_stage'=>'DESTRUCTION_COMMITTEE_APPROVAL','approval_level'=>1,'approver_id'=>$actor->id,'decision'=>$decision,'decision_note'=>$note,'decided_at'=>now()]);
            $this->injectDestructionDecisionFailureForTesting();
            if ($decision === 'APPROVED') {
                $this->currentCycleActions($locked)->where('action_status','APPROVED')->update(['action_status'=>'IN_PROGRESS','updated_by'=>$actor->id]);
                return $this->transition($locked, 'ACTION_IN_PROGRESS', 'APPROVE_DESTRUCTION', $note, $actor, $version, null, $disposition->responsible_department_id);
            }
            $this->currentCycleActions($locked)->where('action_status','APPROVED')->update(['action_status'=>'REJECTED','updated_by'=>$actor->id]);
            $cycle->update(['status' => 'REJECTED']);
            return $this->transition($locked, 'DISPOSITION_APPROVED', 'REJECT_DESTRUCTION', $note, $actor, $version, null, $disposition->responsible_department_id);
        });
    }

    public function createCorrectiveAction(NcrReport $report, User $actor, array $data, int $version, array $attachments = []): NcrCorrectiveAction
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($report, $actor, $data, $version, $attachments, &$storedPaths) {
                $lockedReport = $this->lockReportForAction($report, $version, ['DISPOSITION_APPROVED']);
                $responsibleDepartmentId = $this->approvedResponsibleDepartmentId($lockedReport);
                $this->assertSubmittedActionDepartment($data, $responsibleDepartmentId);
                $this->assertValidActionOwner($data['owner_user_id'] ?? null, $responsibleDepartmentId);
                $this->assertActionAttachmentLimit(0, count($attachments));

                $actionNo = (int) $lockedReport->correctiveActions()->max('action_no') + 1;
                $cycle = $lockedReport->currentCorrectiveActionCycle()->lockForUpdate()->first();
                if (! $cycle || ! in_array($cycle->status, ['DRAFT', 'REJECTED'], true)) {
                    throw new ConflictHttpException('ไม่พบรอบ Corrective Action ที่รอจัดทำแผน');
                }
                $attributes = $this->actionAttributes($lockedReport, $actor, $data, $actionNo);
                $attributes['corrective_action_cycle_id'] = $cycle->id;
                $action = NcrCorrectiveAction::create($attributes);
                $this->recordActionProgress($action, null, $action->progress_percent, null, $action->action_status, $action->completion_note, $actor);
                $storedPaths = $this->storeActionAttachments($action, $attachments, $actor);

                $this->bumpReportVersion($lockedReport, $actor, $version);

                AuditLogger::log('ncr', 'ncr.corrective_action_created', "สร้าง Corrective Action {$lockedReport->ncr_no} #{$action->action_no}", $lockedReport, [], $action->only(['action_no', 'action_type', 'action_status', 'progress_percent', 'due_date']));
                return $action->fresh(['attachments', 'progressHistory']);
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredFiles($storedPaths);
            throw $exception;
        }
    }

    public function updateCorrectiveAction(NcrReport $report, NcrCorrectiveAction $action, User $actor, array $data, int $version, array $attachments = []): NcrCorrectiveAction
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($report, $action, $actor, $data, $version, $attachments, &$storedPaths) {
                $lockedReport = $this->lockReportForAction($report, $version, ['DISPOSITION_APPROVED', 'ACTION_IN_PROGRESS']);
                $lockedAction = NcrCorrectiveAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $lockedAction->ncr_report_id === (int) $lockedReport->id, 404);
                if ($lockedReport->current_corrective_action_cycle_id && (int) $lockedAction->corrective_action_cycle_id !== (int) $lockedReport->current_corrective_action_cycle_id) {
                    throw new ConflictHttpException('Corrective Action นี้ไม่อยู่ในรอบปัจจุบัน');
                }
                $latestActionId = $this->currentCycleActions($lockedReport)
                    ->reorder('action_no', 'desc')
                    ->lockForUpdate()
                    ->value('id');
                if ((int) $lockedAction->id !== (int) $latestActionId) {
                    throw new ConflictHttpException('แก้ไขได้เฉพาะ Corrective Action ล่าสุดเท่านั้น');
                }

                $responsibleDepartmentId = $this->approvedResponsibleDepartmentId($lockedReport);
                abort_unless((int) $lockedAction->responsible_department_id === $responsibleDepartmentId, 409);
                $this->assertSubmittedActionDepartment($data, $responsibleDepartmentId);
                if ($lockedReport->current_status === 'DISPOSITION_APPROVED') {
                    $this->assertValidActionOwner($data['owner_user_id'] ?? null, $responsibleDepartmentId);
                }
                $this->assertActionAttachmentLimit(
                    $lockedAction->attachments()->where('is_active', true)->count(),
                    count($attachments)
                );

                $oldValues = $lockedAction->only(['action_type', 'responsible_department_id', 'owner_user_id', 'responsible_acknowledged_at', 'start_date', 'due_date', 'completed_date', 'progress_percent', 'action_status', 'completion_note']);
                $oldProgress = (int) $lockedAction->progress_percent;
                $oldStatus = $lockedAction->action_status;
                $oldNote = $lockedAction->completion_note;
                if ($lockedReport->current_status === 'DISPOSITION_APPROVED') {
                    if (! in_array($lockedAction->action_status, ['OPEN', 'REJECTED'], true)) {
                        throw new ConflictHttpException('Corrective Action อยู่ระหว่างหรือผ่านการอนุมัติแล้ว');
                    }
                    $data['action_status'] = 'OPEN';
                    $data['progress_percent'] = 0;
                    $data['completed_date'] = null;
                    $lockedAction->update($this->actionAttributes($lockedReport, $actor, $data, (int) $lockedAction->action_no, false));
                    $storedPaths = $this->storeActionAttachments($lockedAction, $attachments, $actor);
                } else {
                    $cycle = $lockedReport->currentCorrectiveActionCycle()->lockForUpdate()->first();
                    if (! $cycle || $cycle->status !== 'APPROVED' || (int) $lockedAction->corrective_action_cycle_id !== (int) $cycle->id) {
                        throw new ConflictHttpException('Corrective Action ไม่ได้อยู่ในรอบที่อนุมัติให้ดำเนินการ');
                    }
                    if ($attachments !== [] || ! $this->matchesActionPlan($lockedAction, $data)) {
                        throw new ConflictHttpException('แผน Corrective Action ที่อนุมัติแล้วไม่สามารถแก้ไขได้');
                    }
                    if (! in_array($data['action_status'], ['IN_PROGRESS', 'COMPLETED'], true)) {
                        throw new ConflictHttpException('สถานะดำเนินการต้องเป็น IN_PROGRESS หรือ COMPLETED');
                    }
                    $lockedAction->update([
                        'progress_percent' => $data['action_status'] === 'COMPLETED' ? 100 : 0,
                        'action_status' => $data['action_status'],
                        'completed_date' => $data['completed_date'] ?? null,
                        'completion_note' => $data['completion_note'] ?? null,
                        'updated_by' => $actor->id,
                    ]);
                }
                if ($oldProgress !== (int) $lockedAction->progress_percent || $oldStatus !== $lockedAction->action_status || $oldNote !== $lockedAction->completion_note) {
                    $this->recordActionProgress($lockedAction, $oldProgress, $lockedAction->progress_percent, $oldStatus, $lockedAction->action_status, $lockedAction->completion_note, $actor);
                }
                $this->bumpReportVersion($lockedReport, $actor, $version);
                AuditLogger::log('ncr', 'ncr.corrective_action_updated', "แก้ไข Corrective Action {$lockedReport->ncr_no} #{$lockedAction->action_no}", $lockedReport, $oldValues, $lockedAction->only(array_keys($oldValues)));
                return $lockedAction->fresh(['attachments', 'progressHistory']);
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredFiles($storedPaths);
            throw $exception;
        }
    }

    public function submitCorrectiveActionsForApproval(NcrReport $report, User $actor, int $version): NcrReport
    {
        return DB::transaction(function () use ($report, $actor, $version) {
            $locked = $this->lockReportForAction($report, $version, ['DISPOSITION_APPROVED']);
            $departmentId = $this->approvedResponsibleDepartmentId($locked);
            $actions = $this->currentCycleActions($locked)->with('attachments')->lockForUpdate()->get();
            if ($actions->isEmpty() || $actions->contains(fn (NcrCorrectiveAction $action) => ! in_array($action->action_status, ['OPEN', 'REJECTED'], true))) {
                throw new ConflictHttpException('Corrective Action ไม่อยู่ในสถานะที่ส่งอนุมัติได้');
            }
            foreach ($actions as $action) {
                $this->assertValidActionOwner($action->owner_user_id, $departmentId);
            }
            $cycle = $locked->currentCorrectiveActionCycle()->lockForUpdate()->first();
            if (! in_array($cycle->status, ['DRAFT', 'REJECTED'], true)) {
                throw new ConflictHttpException('รอบ Corrective Action ไม่อยู่ในสถานะที่ส่งอนุมัติได้');
            }
            $snapshotPayload = $actions->map(function (NcrCorrectiveAction $action) {
                $payload = $action->only([
                    'action_no', 'action_type', 'root_cause_type_id', 'root_cause_detail',
                    'immediate_correction', 'corrective_action_detail', 'preventive_action_detail',
                    'responsible_department_id', 'owner_user_id', 'start_date', 'due_date',
                    'progress_percent', 'action_status', 'is_required', 'completion_note',
                ]);
                $payload['attachments'] = $action->attachments
                    ->where('is_active', true)
                    ->map(fn (NcrActionAttachment $attachment) => $attachment->only([
                        'original_filename', 'mime_type', 'file_size', 'description',
                    ]))
                    ->values()
                    ->all();

                return $payload;
            })->values()->all();
            $snapshot = NcrCorrectiveActionPlanSnapshot::create([
                'corrective_action_cycle_id' => $cycle->id,
                'revision_no' => (int) $cycle->snapshots()->max('revision_no') + 1,
                'plan_hash' => hash('sha256', json_encode($snapshotPayload, JSON_THROW_ON_ERROR)),
                'plan_payload' => $snapshotPayload,
                'report_version_at_submit' => $locked->version_no,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);
            $cycle->update([
                'status' => 'PENDING_APPROVAL',
                'submitted_by' => $actor->id,
                'submitted_at' => $snapshot->submitted_at,
                'report_version_at_submit' => $locked->version_no,
            ]);
            $actions->each(function (NcrCorrectiveAction $action) use ($actor) {
                $from = $action->action_status;
                $action->update(['action_status'=>'PENDING_APPROVAL','updated_by'=>$actor->id]);
                $this->recordActionProgress($action, (int)$action->progress_percent, (int)$action->progress_percent, $from, 'PENDING_APPROVAL', null, $actor);
            });
            $this->bumpReportVersion($locked, $actor, $version);
            $this->history(
                $locked,
                $locked->current_status,
                $locked->current_status,
                'SUBMIT_CORRECTIVE_ACTION_APPROVAL',
                $actor
            );
            return $locked->fresh();
        });
    }

    public function correctiveActionDecision(NcrReport $report, User $actor, int $version, string $decision, ?string $note): NcrReport
    {
        return DB::transaction(function () use ($report, $actor, $version, $decision, $note) {
            $locked = $this->lockReportForAction($report, $version, ['DISPOSITION_APPROVED']);
            $cycle = $locked->currentCorrectiveActionCycle()->lockForUpdate()->first();
            if (! $cycle || $cycle->status !== 'PENDING_APPROVAL') {
                throw new ConflictHttpException('รอบ Corrective Action ไม่ได้อยู่ระหว่างรออนุมัติ');
            }
            $snapshot = $cycle->snapshots()->latest('revision_no')->firstOrFail();
            $currentActions = $this->currentCycleActions($locked);
            $actions = $currentActions->where('action_status','PENDING_APPROVAL')->lockForUpdate()->get();
            if ($actions->isEmpty() || $currentActions->where('action_status','!=','PENDING_APPROVAL')->exists()) {
                throw new ConflictHttpException('Corrective Action ไม่ได้อยู่ระหว่างรออนุมัติ');
            }
            $disposition = $locked->dispositions()->where('status','APPROVED')->latest('revision_no')->firstOrFail();
            NcrApproval::create(['ncr_report_id'=>$locked->id,'disposition_id'=>$disposition->id,'corrective_action_cycle_id'=>$cycle->id,'corrective_action_plan_snapshot_id'=>$snapshot->id,'approval_stage'=>'CORRECTIVE_ACTION_APPROVAL','approval_level'=>1,'approver_id'=>$actor->id,'decision'=>$decision,'decision_note'=>$note,'decided_at'=>now()]);
            if ($decision === 'REJECTED') {
                $actions->each(fn (NcrCorrectiveAction $action) => $action->update(['action_status'=>'REJECTED','updated_by'=>$actor->id]));
                $cycle->update(['status' => 'REJECTED']);
                return $this->transition($locked, 'DISPOSITION_APPROVED', 'REJECT_CORRECTIVE_ACTION', $note, $actor, $version, null, $disposition->responsible_department_id);
            }
            $actions->each(fn (NcrCorrectiveAction $action) => $action->update(['action_status'=>'APPROVED','updated_by'=>$actor->id]));
            $cycle->update(['status' => 'APPROVED']);
            $isScrap = $disposition->type?->code === 'SCRAP';
            return $this->transition($locked, $isScrap ? 'PENDING_DESTRUCTION_APPROVAL' : 'ACTION_IN_PROGRESS', $isScrap ? 'SUBMIT_DESTRUCTION_APPROVAL' : 'APPROVE_CORRECTIVE_ACTION', $note, $actor, $version, null, $isScrap ? null : $disposition->responsible_department_id);
        });
    }

    public function submitActionsForVerification(NcrReport $report, User $actor, int $version): NcrReport
    {
        return DB::transaction(function () use ($report, $actor, $version) {
            $lockedReport = $this->lockReportForAction($report, $version, ['ACTION_IN_PROGRESS']);
            $responsibleDepartmentId = $this->approvedResponsibleDepartmentId($lockedReport);
            $actions = $this->currentCycleActions($lockedReport)->with('attachments')->lockForUpdate()->get();
            if ($actions->isEmpty()) {
                throw ValidationException::withMessages(['actions' => 'ต้องมี Corrective Action อย่างน้อย 1 รายการ']);
            }
            foreach ($actions as $action) {
                if ((int) $action->responsible_department_id !== $responsibleDepartmentId) {
                    throw new ConflictHttpException("Corrective Action #{$action->action_no} มีหน่วยงานรับผิดชอบไม่ตรงกับ Disposition ที่อนุมัติ");
                }
                if ($action->is_required) {
                    $missingRequirements = [];
                    if ($action->action_status !== 'COMPLETED') {
                        $missingRequirements[] = 'สถานะต้องเป็น “เสร็จแล้ว (COMPLETED)”';
                    }
                    if ($missingRequirements) {
                        throw ValidationException::withMessages([
                            'actions' => "Corrective Action #{$action->action_no} ยังส่งตรวจไม่ได้: ".implode(', ', $missingRequirements),
                        ]);
                    }
                }
            }

            $result = $this->transition($lockedReport, 'PENDING_ACTION_VERIFICATION', 'SUBMIT_ACTION_VERIFICATION', null, $actor, $version);
            event(new NcrWorkflowActivity($result, 'SUBMIT_ACTION_VERIFICATION'));
            return $result;
        });
    }

    public function verifyActions(NcrReport $report, User $actor, int $version, array $data): NcrReport
    {
        return DB::transaction(function () use ($report, $actor, $version, $data) {
            $lockedReport = NcrReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedReport->version_no !== $version) {
                throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้ใช้อื่น กรุณาโหลดหน้าใหม่');
            }
            if ($lockedReport->current_status !== 'PENDING_ACTION_VERIFICATION') {
                throw new ConflictHttpException('ตรวจยืนยันได้เฉพาะ NCR ที่รอตรวจ Corrective Action');
            }

            $correctiveActionId = $data['corrective_action_id'] ?? null;
            $actions = $this->currentCycleActions($lockedReport)->lockForUpdate()->get();
            if ($actions->isEmpty()) {
                throw ValidationException::withMessages([
                    'actions' => 'ไม่พบ Corrective Action ในรอบปัจจุบัน',
                ]);
            }
            if ($actions->contains(fn (NcrCorrectiveAction $action) => $action->is_required && $action->action_status !== 'COMPLETED')) {
                throw ValidationException::withMessages([
                    'actions' => 'Corrective Action ที่ต้องดำเนินการในรอบปัจจุบันยังไม่เสร็จสิ้น',
                ]);
            }
            if ($correctiveActionId && ! $actions->contains('id', (int) $correctiveActionId)) {
                throw ValidationException::withMessages([
                    'corrective_action_id' => 'Corrective Action ที่เลือกไม่ได้อยู่ใน NCR นี้',
                ]);
            }

            $round = (int) $lockedReport->actionVerifications()->max('verification_round') + 1;
            NcrActionVerification::create([
                'ncr_report_id' => $lockedReport->id,
                'corrective_action_id' => $correctiveActionId,
                'verification_round' => $round,
                'result' => $data['result'],
                'verification_detail' => $data['verification_detail'],
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ]);

            if ($data['result'] === 'PASSED') {
                $actions->where('action_status', 'COMPLETED')->each(function (NcrCorrectiveAction $action) use ($actor) {
                    $fromStatus = $action->action_status;
                    $action->update(['action_status' => 'VERIFIED', 'updated_by' => $actor->id]);
                    $this->recordActionProgress($action, (int) $action->progress_percent, (int) $action->progress_percent, $fromStatus, 'VERIFIED', 'ผ่านการตรวจยืนยัน', $actor);
                });

                $result = $this->transition($lockedReport, 'QA_VERIFIED', 'VERIFY_ACTION_PASSED', $data['verification_detail'], $actor, $version);
                event(new NcrWorkflowActivity($result, 'VERIFY_ACTION_PASSED'));
                return $result;
            }

            $responsibleDepartmentId = $this->approvedResponsibleDepartmentId($lockedReport);
            $result = $this->transition($lockedReport, 'RETURNED_FOR_REWORK', 'VERIFY_ACTION_FAILED', $data['verification_detail'], $actor, $version, null, $responsibleDepartmentId);
            event(new NcrWorkflowActivity($result, 'VERIFY_ACTION_FAILED'));
            return $result;
        });
    }

    public function startRework(NcrReport $report, User $actor, int $version): NcrReport
    {
        return DB::transaction(function () use ($report, $actor, $version) {
            $lockedReport = NcrReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedReport->version_no !== $version) {
                throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้ใช้อื่น กรุณาโหลดหน้าใหม่');
            }
            if ($lockedReport->current_status !== 'RETURNED_FOR_REWORK') {
                throw new ConflictHttpException('เริ่ม Rework ได้เฉพาะ NCR ที่ถูกส่งกลับแก้ไขเพิ่มเติม');
            }

            $responsibleDepartmentId = $this->approvedResponsibleDepartmentId($lockedReport);
            $previousCycle = $lockedReport->currentCorrectiveActionCycle()->lockForUpdate()->first();
            if ($previousCycle && $previousCycle->status !== 'APPROVED') {
                throw new ConflictHttpException('ไม่พบรอบ Corrective Action ที่อนุมัติแล้วสำหรับเริ่ม Rework');
            }
            if (! $previousCycle && ! $lockedReport->approvals()->where('approval_stage', 'CORRECTIVE_ACTION_APPROVAL')->where('decision', 'APPROVED')->exists()) {
                throw new ConflictHttpException('ไม่พบหลักฐานการอนุมัติ Corrective Action เดิมสำหรับเริ่ม Rework');
            }
            if (! $previousCycle && $lockedReport->dispositions()->where('status', 'APPROVED')->latest('revision_no')->first()?->type?->code === 'SCRAP'
                && ! $lockedReport->approvals()->where('approval_stage', 'DESTRUCTION_COMMITTEE_APPROVAL')->where('decision', 'APPROVED')->exists()) {
                throw new ConflictHttpException('ไม่พบหลักฐานการอนุมัติทำลายเดิมสำหรับเริ่ม Rework');
            }
            $sourceActions = $this->currentCycleActions($lockedReport)->lockForUpdate()->get();
            if ($sourceActions->isEmpty()) {
                throw new ConflictHttpException('ไม่พบ Corrective Action ในรอบที่อนุมัติแล้วสำหรับเริ่ม Rework');
            }
            $returnEvent = $this->currentCycleReworkReturn($lockedReport, $previousCycle);
            $returnReason = $returnEvent->action_note;
            $origins = [
                'VERIFY_ACTION_FAILED' => 'VERIFICATION_FAILED',
                'QA_FOLLOWUP_FAILED' => 'QA_NOT_EFFECTIVE',
                'QA_RETURN_FOR_REWORK' => 'QA_RETURN_FOR_REWORK',
            ];
            $nextCycle = NcrCorrectiveActionCycle::create([
                'ncr_report_id' => $lockedReport->id,
                'cycle_no' => (int) $lockedReport->correctiveActionCycles()->max('cycle_no') + 1,
                'origin' => $origins[$returnEvent->action_code],
                'status' => 'DRAFT',
                'opened_by' => $actor->id,
                'opened_at' => now(),
            ]);
            $nextActionNo = (int) $lockedReport->correctiveActions()->max('action_no');
            foreach ($sourceActions as $sourceAction) {
                $clone = NcrCorrectiveAction::create([
                    'ncr_report_id' => $lockedReport->id,
                    'corrective_action_cycle_id' => $nextCycle->id,
                    'supersedes_corrective_action_id' => $sourceAction->id,
                    'action_no' => ++$nextActionNo,
                    'action_type' => $sourceAction->action_type,
                    'root_cause_type_id' => $sourceAction->root_cause_type_id,
                    'root_cause_detail' => $sourceAction->root_cause_detail,
                    'immediate_correction' => $sourceAction->immediate_correction,
                    'corrective_action_detail' => $sourceAction->corrective_action_detail,
                    'preventive_action_detail' => $sourceAction->preventive_action_detail,
                    'responsible_department_id' => $sourceAction->responsible_department_id,
                    'owner_user_id' => $sourceAction->owner_user_id,
                    'responsible_acknowledged_at' => $sourceAction->responsible_acknowledged_at,
                    'start_date' => $sourceAction->start_date,
                    'due_date' => $sourceAction->due_date,
                    'progress_percent' => 0,
                    'action_status' => 'OPEN',
                    'is_required' => $sourceAction->is_required,
                    'completion_note' => null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $this->recordActionProgress($clone, null, 0, null, 'OPEN', $returnReason, $actor);
            }
            $lockedReport->update(['current_corrective_action_cycle_id' => $nextCycle->id]);
            $result = $this->transition($lockedReport, 'DISPOSITION_APPROVED', 'START_REWORK', $returnReason, $actor, $version, null, $responsibleDepartmentId);
            event(new NcrWorkflowActivity($result, 'START_REWORK'));
            return $result;
        });
    }

    public function saveQaFollowup(NcrReport $report, User $actor, int $version, array $data): NcrQaFollowup
    {
        return DB::transaction(function () use ($report, $actor, $version, $data) {
            $locked = $this->lockReportForAction($report, $version, ['QA_VERIFIED']);
            $round = (int) $locked->qaFollowups()->max('followup_round') + 1;
            $followup = NcrQaFollowup::create([
                'ncr_report_id' => $locked->id, 'followup_round' => $round,
                'followup_date' => $data['followup_date'], 'effectiveness_result' => $data['effectiveness_result'],
                'followup_detail' => $data['followup_detail'] ?? '',
                'return_reason' => $data['return_reason'] ?? null, 'next_followup_date' => $data['next_followup_date'] ?? null,
                'followed_up_at' => now(), 'followed_up_by' => $actor->id,
            ]);
            if ($data['effectiveness_result'] === 'EFFECTIVE') {
                $currentActions = $this->currentCycleActions($locked);
                if (! $currentActions->exists() || $currentActions->whereNotIn('action_status', ['VERIFIED'])->exists()) {
                    throw ValidationException::withMessages(['actions' => 'ยังมี Corrective Action ที่ไม่ผ่านการตรวจยืนยัน']);
                }

                $followupDetail = $data['followup_detail'] ?? '';
                $result = $this->transition($locked, 'CLOSED', 'CLOSE_NCR', $followupDetail, $actor, $version);
                NcrClosure::create([
                    'ncr_report_id' => $result->id,
                    'closure_result' => 'CLOSED',
                    'closure_note' => $followupDetail,
                    'closed_at' => now(),
                    'closed_by' => $actor->id,
                ]);
                $result->update(['closed_at' => now(), 'closed_by' => $actor->id]);
                event(new NcrWorkflowActivity($result, 'CLOSE_NCR'));
            } elseif ($data['effectiveness_result'] === 'NOT_EFFECTIVE') {
                $result = $this->transition($locked, 'RETURNED_FOR_REWORK', 'QA_FOLLOWUP_FAILED', $data['return_reason'], $actor, $version, null, $this->approvedResponsibleDepartmentId($locked));
                event(new NcrWorkflowActivity($result, 'QA_FOLLOWUP_FAILED'));
            } else {
                $this->bumpReportVersion($locked, $actor, $version);
                event(new NcrWorkflowActivity($locked, 'QA_FOLLOWUP_RECORDED'));
            }
            return $followup;
        });
    }

    public function returnForReworkFromQa(NcrReport $report, User $actor, int $version, string $reason): NcrReport
    {
        return DB::transaction(function () use ($report, $actor, $version, $reason) {
            $locked = $this->lockReportForAction($report, $version, ['QA_VERIFIED']);
            return $this->transition($locked, 'RETURNED_FOR_REWORK', 'QA_RETURN_FOR_REWORK', $reason, $actor, $version, null, $this->approvedResponsibleDepartmentId($locked));
        });
    }

    private function assertValidActionOwner($ownerUserId, int $responsibleDepartmentId): void
    {
        if (! $ownerUserId || ! User::query()->whereKey($ownerUserId)->where('is_active', true)->where('department_id', $responsibleDepartmentId)->exists()) {
            throw ValidationException::withMessages(['owner_user_id' => 'กรุณาเลือกผู้รับผิดชอบจากหน่วยงานที่ได้รับมอบหมาย']);
        }
    }

    private function actionAttributes(NcrReport $report, User $actor, array $data, int $actionNo, bool $creating = true): array
    {
        $attributes = [
            'ncr_report_id' => $report->id,
            'action_no' => $actionNo,
            'action_type' => $data['action_type'],
            'root_cause_type_id' => $data['root_cause_type_id'] ?? null,
            'root_cause_detail' => $data['root_cause_detail'] ?? null,
            'immediate_correction' => $data['immediate_correction'] ?? null,
            'corrective_action_detail' => $data['corrective_action_detail'],
            'preventive_action_detail' => $data['preventive_action_detail'] ?? null,
            'responsible_department_id' => $data['responsible_department_id'],
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'responsible_acknowledged_at' => $data['responsible_acknowledged_at'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'],
            'completed_date' => ($data['action_status'] ?? 'OPEN') === 'COMPLETED' ? ($data['completed_date'] ?? now()->toDateString()) : null,
            'progress_percent' => ($data['action_status'] ?? 'OPEN') === 'COMPLETED' ? 100 : 0,
            'action_status' => $data['action_status'] ?? 'OPEN',
            'completion_note' => $data['completion_note'] ?? null,
            'updated_by' => $actor->id,
        ];
        if ($creating) {
            $attributes['created_by'] = $actor->id;
            $attributes['is_required'] = $data['is_required'] ?? true;
        }
        return $attributes;
    }

    private function matchesActionPlan(NcrCorrectiveAction $action, array $data): bool
    {
        foreach ([
            'action_type', 'root_cause_type_id', 'root_cause_detail', 'immediate_correction',
            'corrective_action_detail', 'preventive_action_detail', 'responsible_department_id',
            'owner_user_id', 'responsible_acknowledged_at', 'start_date', 'due_date',
        ] as $field) {
            if ($this->normalizedPlanValue($action->getAttribute($field), $field) !== $this->normalizedPlanValue($data[$field] ?? null, $field)) {
                return false;
            }
        }

        return true;
    }

    private function currentCycleActions(NcrReport $report)
    {
        $query = $report->correctiveActions();

        if ($report->current_corrective_action_cycle_id) {
            return $query->where('corrective_action_cycle_id', $report->current_corrective_action_cycle_id);
        }

        return $query->whereNull('corrective_action_cycle_id');
    }

    private function injectDestructionDecisionFailureForTesting(): void
    {
        if (app()->environment('testing') && config('ncr.testing.fail_destruction_decision_after_approval_write', false)) {
            throw new \RuntimeException('Injected destruction-decision transaction failure.');
        }
    }

    private function currentCycleReworkReturn(NcrReport $report, ?NcrCorrectiveActionCycle $cycle): \App\Models\NcrStatusHistory
    {
        $history = $report->statusHistory()->reorder();
        if ($cycle) {
            if (! $cycle->opened_at) {
                throw new ConflictHttpException('ไม่พบขอบเขตรอบ Corrective Action สำหรับเริ่ม Rework');
            }
            $history->where('changed_at', '>=', $cycle->opened_at);
            if ((int) $cycle->cycle_no > 1) {
                // START_REWORK is written after the new cycle opens, even when timestamps tie.
                $opening = $report->statusHistory()->reorder()->where('action_code', 'START_REWORK')
                    ->where('changed_at', '>=', $cycle->opened_at)->orderByDesc('id')->first();
                if (! $opening) {
                    throw new ConflictHttpException('ไม่พบเหตุการณ์เปิดรอบ Corrective Action สำหรับเริ่ม Rework');
                }
                $history->where('id', '>', $opening->id);
            }
        }
        $event = $history->where('to_status', 'RETURNED_FOR_REWORK')
            ->whereIn('action_code', ['VERIFY_ACTION_FAILED', 'QA_FOLLOWUP_FAILED', 'QA_RETURN_FOR_REWORK'])
            ->orderByDesc('id')->first();
        if (! $event) {
            throw new ConflictHttpException('ไม่พบเหตุการณ์ส่งกลับในรอบ Corrective Action ปัจจุบัน');
        }
        return $event;
    }

    private function normalizedPlanValue(mixed $value, ?string $field = null): string
    {
        if ($field === 'responsible_acknowledged_at') {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i');
            }

            if ($value !== null && $value !== '') {
                return \Carbon\Carbon::parse((string) $value)->format('Y-m-d H:i');
            }
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) ($value ?? '');
    }

    private function lockReportForAction(NcrReport $report, int $version, array $allowedStatuses): NcrReport
    {
        $lockedReport = NcrReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
        if ((int) $lockedReport->version_no !== $version) {
            throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้ใช้อื่น กรุณาโหลดหน้าใหม่');
        }
        if (! in_array($lockedReport->current_status, $allowedStatuses, true)) {
            throw new ConflictHttpException('สถานะปัจจุบันไม่อนุญาตให้ดำเนินการ Corrective Action');
        }
        return $lockedReport;
    }

    private function approvedResponsibleDepartmentId(NcrReport $report): int
    {
        $departmentId = $report->dispositions()
            ->where('status', 'APPROVED')
            ->latest('revision_no')
            ->value('responsible_department_id');

        if (! $departmentId) {
            throw new ConflictHttpException('ไม่พบหน่วยงานรับผิดชอบจาก Disposition ที่อนุมัติแล้ว');
        }
        return (int) $departmentId;
    }

    private function assertSubmittedActionDepartment(array $data, int $departmentId): void
    {
        if ((int) $data['responsible_department_id'] !== $departmentId) {
            throw ValidationException::withMessages([
                'responsible_department_id' => 'ต้องใช้หน่วยงานเดียวกับ Disposition ที่อนุมัติแล้ว',
            ]);
        }
    }

    private function assertActionAttachmentLimit(int $currentCount, int $newCount): void
    {
        if ($currentCount + $newCount > 10) {
            throw ValidationException::withMessages([
                'attachments' => 'Corrective Action หนึ่งรายการแนบไฟล์ได้สูงสุด 10 ไฟล์',
            ]);
        }
    }

    private function storeActionAttachments(NcrCorrectiveAction $action, array $attachments, User $actor): array
    {
        $report = NcrReport::query()->findOrFail($action->ncr_report_id);
        $storedPaths = [];
        try {
            foreach ($attachments as $file) {
                $meta = $this->files->store($file, 'ncr/'.now()->format('Y').'/'.$report->ncr_no.'/action-'.$action->action_no);
                $storedPaths[] = $meta['stored_path'];
                NcrActionAttachment::create(['corrective_action_id' => $action->id, 'original_filename' => $meta['original_name'], 'stored_filename' => basename($meta['stored_path']), 'storage_path' => $meta['stored_path'], 'mime_type' => $meta['mime_type'], 'file_size' => $meta['file_size'], 'uploaded_at' => now(), 'uploaded_by' => $actor->id, 'is_active' => true]);
            }
        } catch (\Throwable $exception) {
            $this->deleteStoredFiles($storedPaths);
            throw $exception;
        }
        return $storedPaths;
    }

    private function deleteStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->files->delete($path);
        }
    }

    private function bumpReportVersion(NcrReport $report, User $actor, int $version): void
    {
        $affected = NcrReport::query()
            ->whereKey($report->id)
            ->where('version_no', $version)
            ->update([
                'updated_by' => $actor->id,
                'version_no' => DB::raw('version_no + 1'),
                'updated_at' => now(),
            ]);
        if ($affected !== 1) {
            throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้ใช้อื่น กรุณาโหลดหน้าใหม่');
        }
        $report->refresh();
    }

    private function recordActionProgress(NcrCorrectiveAction $action, ?int $fromProgress, int $toProgress, ?string $fromStatus, string $toStatus, ?string $note, User $actor): void
    {
        NcrActionProgressHistory::create(['corrective_action_id' => $action->id, 'from_progress_percent' => $fromProgress, 'to_progress_percent' => $toProgress, 'from_status' => $fromStatus, 'to_status' => $toStatus, 'progress_note' => $note, 'changed_at' => now(), 'changed_by' => $actor->id]);
    }

    public function attach(NcrReport $report, UploadedFile $file, User $actor): NcrAttachment
    {
        $meta=$this->files->store($file,'ncr/'.now()->format('Y').'/'.$report->ncr_no.'/report');
        return $report->attachments()->create(['attachment_stage'=>'REPORT','original_filename'=>$meta['original_name'],'stored_filename'=>basename($meta['stored_path']),'storage_path'=>$meta['stored_path'],'mime_type'=>$meta['mime_type'],'file_size'=>$meta['file_size'],'uploaded_at'=>now(),'uploaded_by'=>$actor->id,'is_active'=>true]);
    }

    private function transition(NcrReport $report,string $to,string $action,?string $note,User $actor,int $version,?int $ownerUser=null,?int $ownerDept=null):NcrReport
    {
        return DB::transaction(function()use($report,$to,$action,$note,$actor,$version,$ownerUser,$ownerDept){$from=$report->current_status;$affected=NcrReport::query()->whereKey($report->id)->where('version_no',$version)->update(['current_status'=>$to,'current_owner_user_id'=>$ownerUser,'current_owner_department_id'=>$ownerDept,'updated_by'=>$actor->id,'version_no'=>DB::raw('version_no + 1'),'updated_at'=>now()]);if($affected!==1)throw new ConflictHttpException('ข้อมูลถูกแก้ไขโดยผู้ใช้อื่น กรุณาโหลดหน้าใหม่');$report->refresh();$this->history($report,$from,$to,$action,$actor,$note);AuditLogger::log('ncr','ncr.'.strtolower($action),"{$action} {$report->ncr_no}",$report,['current_status'=>$from],['current_status'=>$to]);return $report;});
    }
    private function syncItems(NcrReport $report, array $items, User $actor): void
    {
        $existingItems = $report->items()->get()->keyBy('id');
        $retainedIds = [];
        $lineOffset = (int) $existingItems->max('line_no') + count($items) + 1;

        foreach ($existingItems as $existing) {
            $existing->update(['line_no' => (int) $existing->line_no + $lineOffset]);
        }

        foreach (array_values($items) as $index => $item) {
            $itemId = $item['_item_id'] ?? null;
            unset($item['_item_id']);

            if ($itemId && $existingItems->has($itemId)) {
                $existing = $existingItems->get($itemId);
                $existing->update($item + ['line_no' => $index + 1]);
                $retainedIds[] = (int) $existing->id;
                continue;
            }

            $created = $report->items()->create($item + ['line_no' => $index + 1, 'created_by' => $actor->id]);
            $retainedIds[] = (int) $created->id;
        }

        $report->items()->whereNotIn('id', $retainedIds)->delete();
    }
    private function snapshotItemReferences(?NcrReport $report, array $items): array
    {
        $existingItems = $report ? $report->items()->get()->keyBy('id') : collect();

        foreach ($items as $index => $item) {
            $existing = ! empty($item['item_id']) ? $existingItems->get((int) $item['item_id']) : null;
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $customerId = isset($item['customer_id']) ? (int) $item['customer_id'] : null;

            if ($productId && $existing && (int) $existing->product_id === $productId) {
                $items[$index]['product_code'] = $existing->product_code;
                $items[$index]['product_name'] = $existing->product_name;
            } elseif ($productId) {
                $product = Product::query()->where('is_active', true)->find($productId);
                if (! $product) throw ValidationException::withMessages(["items.{$index}.product_id" => 'กรุณาเลือกสินค้าที่เปิดใช้งาน']);
                $items[$index]['product_code'] = $product->code;
                $items[$index]['product_name'] = $product->name_th;
            } elseif ($existing) {
                $items[$index]['product_code'] = $existing->product_code;
                $items[$index]['product_name'] = $existing->product_name;
            }

            if ($customerId && $existing && (int) $existing->customer_id === $customerId) {
                $items[$index]['customer_name'] = $existing->customer_name;
            } elseif ($customerId) {
                $customer = Customer::query()->where('is_active', true)->find($customerId);
                if (! $customer) throw ValidationException::withMessages(["items.{$index}.customer_id" => 'กรุณาเลือกลูกค้าที่เปิดใช้งาน']);
                $items[$index]['customer_name'] = $customer->name_th;
            } elseif ($existing && ! $existing->customer_id) {
                $items[$index]['customer_name'] = $existing->customer_name;
            } else {
                $items[$index]['customer_name'] = null;
            }

            $items[$index]['_item_id'] = $items[$index]['item_id'] ?? null;
            unset($items[$index]['item_id']);
        }

        return $items;
    }

    private function snapshotSupplierReference(array $data, ?NcrReport $report): array
    {
        $supplierId = isset($data['supplier_id']) ? (int) $data['supplier_id'] : null;
        if ($supplierId && $report && (int) $report->supplier_id === $supplierId) {
            $data['supplier_name'] = $report->supplier_name;
        } elseif ($supplierId) {
            $supplier = Supplier::query()->where('is_active', true)->find($supplierId);
            if (! $supplier) throw ValidationException::withMessages(['supplier_id' => 'กรุณาเลือกผู้ผลิต/ผู้ขายที่เปิดใช้งาน']);
            $data['supplier_name'] = $supplier->name_th;
        } elseif ($report && ! $report->supplier_id) {
            $data['supplier_name'] = $report->supplier_name;
        } else {
            $data['supplier_name'] = null;
        }

        $data['supplier_id'] = $supplierId;

        return $data;
    }
    private function history(NcrReport $r,?string $from,string $to,string $action,User $a,?string $note=null):void { NcrStatusHistory::create(['ncr_report_id'=>$r->id,'from_status'=>$from,'to_status'=>$to,'action_code'=>$action,'action_note'=>$note,'assigned_user_id'=>$r->current_owner_user_id,'assigned_department_id'=>$r->current_owner_department_id,'changed_at'=>now(),'changed_by'=>$a->id]); }
    private function assertOwner(NcrReport $r,User $a):void { if((int)$r->reporter_id!==(int)$a->id)throw new AuthorizationException('แก้ไขได้เฉพาะ NCR ของตนเอง'); }
    private function snapshot(NcrReport $r):array { return $r->only(['ncr_no','report_date','found_date','problem_type_id','severity_level_id','problem_description','current_status','version_no']); }
}
