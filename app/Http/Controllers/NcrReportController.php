<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveNcrReportRequest;
use App\Http\Requests\SaveCorrectiveActionRequest;
use App\Http\Requests\VerifyNcrActionRequest;
use App\Http\Requests\SaveNcrQaFollowupRequest;
use App\Models\Department;
use App\Models\NcrAttachment;
use App\Models\NcrReport;
use App\Models\NcrFormScope;
use App\Models\NcrCorrectiveAction;
use App\Models\NcrActionAttachment;
use App\Models\ProblemType;
use App\Models\SeverityLevel;
use App\Models\Unit;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\RootCauseType;
use App\Models\User;
use App\Models\DispositionType;
use App\Services\NcrWorkflowService;
use App\Services\NcrVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class NcrReportController extends Controller
{
    public function __construct(private NcrWorkflowService $workflow, private NcrVisibilityService $visibility)
    {
        $this->middleware('permission:ncr.view')->only(['index','show','downloadPdf','download','downloadActionAttachment']);
        $this->middleware('permission:ncr.create')->only(['create','store']);
        $this->middleware('permission:ncr.update')->only(['edit','update']);
        $this->middleware('permission:ncr.submit')->only('submit');
        $this->middleware('permission:ncr.review')->only('initialReview');
        $this->middleware('permission:ncr.disposition.manage')->only(['reviseDisposition','submitDisposition']);
        $this->middleware('permission:ncr.disposition.approve')->only('managerDecision');
        $this->middleware('permission:ncr.destruction.approve')->only('destructionDecision');
        $this->middleware('permission:ncr.disposition.approve')->only('closureDecision');
        $this->middleware('can:ncr.corrective-action.approve,ncr')->only('correctiveActionDecision');
        $this->middleware('permission:ncr.action.manage')->only(['storeCorrectiveAction', 'updateCorrectiveAction', 'submitCorrectiveActionsForApproval', 'submitActionsForVerification', 'startRework']);
        $this->middleware('permission:ncr.action.verify')->only('verifyActions');
        $this->middleware('permission:ncr.qa.followup')->only(['saveQaFollowup', 'returnForReworkFromQa']);
    }
    public function index(Request $request)
    {
        $filters=$request->validate(['search'=>['nullable','string','max:100'],'status'=>['nullable','string','max:50'],'department_id'=>['nullable','exists:departments,id'],'date_from'=>['nullable','date'],'date_to'=>['nullable','date','after_or_equal:date_from'],'overdue'=>['nullable','boolean'],'dashboard_scope'=>['nullable','boolean']]);
        $reportQuery = NcrReport::query();
        $reports = ($filters['dashboard_scope'] ?? false)
            ? $this->visibility->scopeForDashboard($reportQuery, $request->user())
            : $this->visibility->scope($reportQuery, $request->user());
        $reports = $reports->with(['reporter','relatedDepartment','problemType','items','latestDisposition.type'])
            ->when($filters['search']??null,fn($q,$s)=>$q->where(fn($q)=>$q->where('ncr_no','like',"%{$s}%")->orWhereHas('items',fn($items)=>$items->where('product_name','like',"%{$s}%")->orWhere('product_code','like',"%{$s}%"))->orWhere('problem_description','like',"%{$s}%")))->when($filters['status']??null,fn($q,$v)=>$q->where('current_status',$v))->when($filters['department_id']??null,fn($q,$v)=>$q->where('related_department_id',$v))->when($filters['date_from']??null,fn($q,$v)=>$q->whereDate('report_date','>=',$v))->when($filters['date_to']??null,fn($q,$v)=>$q->whereDate('report_date','<=',$v))->when($filters['overdue']??false,fn($q)=>$q->whereNotIn('current_status',['DRAFT','CLOSED'])->whereDate('due_date','<',now()->toDateString()))->latest('report_date')->latest('id')->paginate(15)->withQueryString();
        $departments=Department::where('is_active',true)->orderBy('code')->get(); $statuses=['DRAFT','RETURNED_FOR_CORRECTION','INITIAL_REVIEW','PENDING_MANAGER_APPROVAL','RETURNED_FOR_DISPOSITION_REVISION','PENDING_DESTRUCTION_APPROVAL','DISPOSITION_APPROVED','ACTION_IN_PROGRESS','PENDING_ACTION_VERIFICATION','RETURNED_FOR_REWORK','QA_VERIFIED','CLOSED'];
        return view('ncr.index',compact('reports','filters','departments','statuses'));
    }
    public function create(){return view('ncr.form',$this->options()+['ncr'=>new NcrReport()]);}
    public function store(SaveNcrReportRequest $request){$validated=$request->validated();$report=$this->workflow->createDraft($request->safe()->except(['version_no','attachments','items','company_customer_id']),$request->user(),$validated['items'],$request->file('attachments',[]));return redirect()->route('ncr.show',$report)->with('success','สร้าง NCR Draft เรียบร้อยแล้ว');}
    public function show(Request $request, NcrReport $ncr)
    {
        abort_unless($this->visibility->canView($ncr, $request->user()), 404);

        $ncr->load([
            'reporter', 'reporterDepartment', 'currentOwner', 'currentOwnerDepartment', 'formScope',
            'currentCorrectiveActionCycle', 'relatedDepartment', 'problemType', 'severityLevel',
            'items', 'attachments', 'statusHistory.changedBy', 'initialReviews.reviewer',
            'dispositions.type', 'dispositions.responsibleDepartment', 'dispositions.approvals.approver',
            'correctiveActions.rootCauseType', 'correctiveActions.responsibleDepartment',
            'correctiveActions.owner', 'correctiveActions.attachments', 'correctiveActions.progressHistory',
            'actionVerifications.verifier', 'actionVerifications.correctiveAction', 'qaFollowups.follower',
            'closure.closer', 'formSignoffs.signer', 'formSignoffs.recorder',
        ]);

        $approvedDisposition = $ncr->dispositions()
            ->with('responsibleDepartment')
            ->where('status', 'APPROVED')
            ->orderByDesc('revision_no')
            ->first();
        $approvedResponsibleDepartment = $approvedDisposition?->responsibleDepartment;
        $approvedResponsibleDepartmentId = $approvedDisposition?->responsible_department_id;

        $departments = Department::where('is_active', true)->orderBy('code')->get();
        $dispositionTypes = DispositionType::where('is_active', true)->orderBy('code')->get();
        $rootCauseTypes = RootCauseType::where('is_active', true)->orderBy('code')->get();
        $actionOwners = User::where('is_active', true)->where('department_id', $approvedResponsibleDepartmentId)->with('department')->orderBy('name')->get();

        return view('ncr.show', compact(
            'ncr', 'departments', 'dispositionTypes', 'rootCauseTypes', 'actionOwners',
            'approvedResponsibleDepartment', 'approvedResponsibleDepartmentId'
        ));
    }
    public function downloadPdf(Request $request, NcrReport $ncr)
    {
        $this->assertVisible($ncr, $request);
        abort_unless($ncr->current_status === 'CLOSED', 409, 'ดาวน์โหลด PDF ได้เฉพาะ NCR ที่ปิดแล้ว');
        $ncr->load([
            'reporter', 'reporterDepartment', 'relatedDepartment', 'problemType', 'severityLevel', 'items',
            'formScope',
            'dispositions.type', 'dispositions.responsibleDepartment',
            'correctiveActions.responsibleDepartment', 'correctiveActions.owner',
            'qaFollowups.follower', 'closure.closer', 'formSignoffs.signer',
        ]);

        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);
        $pdf = new Mpdf([
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'garuda',
            'margin_top' => 7,
            'margin_right' => 7,
            'margin_bottom' => 7,
            'margin_left' => 7,
        ]);
        $pdf->SetTitle($ncr->ncr_no);
        $formScopes = $this->formScopeOptions($ncr, true);
        $pdf->WriteHTML(view('ncr.pdf', compact('ncr', 'formScopes'))->render());

        $disposition = $request->boolean('preview') ? 'inline' : 'attachment';

        return response($pdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$ncr->ncr_no.'.pdf"',
        ]);
    }
    public function edit(Request $request, NcrReport $ncr)
    {
        $this->assertVisible($ncr, $request);
        abort_unless(in_array($ncr->current_status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true), 409);

        $returnReason = $ncr->current_status === 'RETURNED_FOR_CORRECTION'
            ? trim((string) $ncr->statusHistory()->latest('id')->value('action_note'))
            : '';

        return view('ncr.form', $this->options($ncr) + ['ncr' => $ncr, 'returnReason' => $returnReason]);
    }
    public function update(SaveNcrReportRequest $request,NcrReport $ncr){$validated=$request->validated();$report=$this->workflow->updateDraft($ncr,$request->safe()->except(['version_no','attachments','items','company_customer_id']),$validated['items'],$request->user(),(int)$request->input('version_no'),$request->file('attachments',[]));return redirect()->route('ncr.show',$report)->with('success','บันทึก NCR เรียบร้อยแล้ว');}
    public function submit(Request $request,NcrReport $ncr){$data=$request->validate(['version_no'=>['required','integer']]);$this->workflow->submit($ncr,$request->user(),(int)$data['version_no']);return redirect()->route('ncr.show',$ncr)->with('success','ส่ง NCR ตรวจสอบแล้ว');}
    public function initialReview(Request $request, NcrReport $ncr)
    {
        $data = $request->validate([
            'version_no' => ['required', 'integer'],
            'review_result' => ['required', 'in:COMPLETE,INCOMPLETE'],
            'completeness_note' => ['nullable', 'required_if:review_result,INCOMPLETE', 'string', 'max:2000'],
            'disposition_type_id' => ['nullable', 'required_if:review_result,COMPLETE', 'exists:disposition_types,id'],
            'disposition_detail' => ['nullable', 'required_if:review_result,COMPLETE', 'string', 'max:5000'],
            'responsible_department_id' => ['nullable', 'required_if:review_result,COMPLETE', 'exists:departments,id'],
        ], [
            'completeness_note.required_if' => 'กรุณาระบุเหตุผลที่ส่งกลับเมื่อข้อมูลไม่ครบถ้วน',
            'disposition_type_id.required_if' => 'กรุณาเลือกข้อเสนอแนะการจัดการเบื้องต้นเมื่อข้อมูลครบถ้วน',
            'disposition_detail.required_if' => 'กรุณาระบุรายละเอียดข้อเสนอแนะการจัดการเบื้องต้นเมื่อข้อมูลครบถ้วน',
            'responsible_department_id.required_if' => 'กรุณาเลือกแผนก/ฝ่ายที่รับผิดชอบเมื่อข้อมูลครบถ้วน',
        ]);

        $this->workflow->initialReview($ncr, $request->user(), (int) $data['version_no'], $data);

        return redirect()->route('ncr.show', $ncr)->with(
            'success',
            $data['review_result'] === 'COMPLETE'
            ? 'บันทึก Initial Review และส่งข้อเสนอแนะให้ผู้จัดการอนุมัติแล้ว'
                : 'ส่งกลับผู้รายงานแก้ไขแล้ว'
        );
    }
    public function reviseDisposition(Request $request,NcrReport $ncr){$data=$request->validate(['version_no'=>['required','integer'],'disposition_type_id'=>['required','exists:disposition_types,id'],'disposition_detail'=>['required','string','max:5000'],'responsible_department_id'=>['required','exists:departments,id']]);$this->workflow->submitDispositionRevision($ncr,$request->user(),(int)$data['version_no'],$data);return redirect()->route('ncr.show',$ncr)->with('success','ส่งข้อเสนอแนะฉบับแก้ไขให้ผู้จัดการอนุมัติแล้ว');}
    public function submitDisposition(Request $request,NcrReport $ncr){$data=$request->validate(['version_no'=>['required','integer']]);$this->workflow->submitDisposition($ncr,$request->user(),(int)$data['version_no']);return redirect()->route('ncr.show',$ncr)->with('success','ส่งข้อเสนอแนะการจัดการให้ผู้จัดการอนุมัติแล้ว');}
    public function managerDecision(Request $request,NcrReport $ncr){$data=$request->validate(['version_no'=>['required','integer'],'decision'=>['required','in:APPROVED,REJECTED'],'decision_note'=>['nullable','required_if:decision,REJECTED','string','max:2000']],['decision_note.required_if'=>'กรุณาระบุรายละเอียดเมื่อไม่อนุมัติ']);$updated=$this->workflow->managerDecision($ncr,$request->user(),(int)$data['version_no'],$data['decision'],$data['decision_note']??null);$message=['APPROVED'=>'อนุมัติข้อเสนอแนะการจัดการแล้ว','REJECTED'=>'ส่งข้อเสนอแนะการจัดการกลับแก้ไขแล้ว'][$data['decision']];$redirect=$this->visibility->canView($updated,$request->user())?redirect()->route('ncr.show',$updated):redirect()->route('ncr.index');return $redirect->with('success',$message);}
    public function destructionDecision(Request $request, NcrReport $ncr){$data=$request->validate(['version_no'=>['required','integer'],'decision'=>['required','in:APPROVED,REJECTED'],'decision_note'=>['nullable','required_if:decision,REJECTED','string','max:2000']]);$updated=$this->workflow->destructionDecision($ncr,$request->user(),(int)$data['version_no'],$data['decision'],$data['decision_note']??null);$redirect=$this->visibility->canView($updated,$request->user())?redirect()->route('ncr.show',$updated):redirect()->route('ncr.index');return $redirect->with('success','บันทึกผลการพิจารณาทำลายแล้ว');}
    public function submitCorrectiveActionsForApproval(Request $request, NcrReport $ncr){$data=$request->validate(['version_no'=>['required','integer']]);$this->workflow->submitCorrectiveActionsForApproval($ncr,$request->user(),(int)$data['version_no']);return redirect()->route('ncr.show',$ncr)->with('success','ส่ง Corrective Action และระยะเวลาดำเนินการเพื่ออนุมัติแล้ว');}
    public function correctiveActionDecision(Request $request, NcrReport $ncr){$data=$request->validate(['version_no'=>['required','integer'],'decision'=>['required','in:APPROVED,REJECTED'],'decision_note'=>['nullable','required_if:decision,REJECTED','string','max:2000']],['decision_note.required_if'=>'กรุณาระบุเหตุผลเมื่อไม่อนุมัติ Corrective Action']);$this->workflow->correctiveActionDecision($ncr,$request->user(),(int)$data['version_no'],$data['decision'],$data['decision_note']??null);return redirect()->route('ncr.show',$ncr)->with('success','บันทึกผลพิจารณา Corrective Action แล้ว');}
    public function download(Request $request,NcrReport $ncr,NcrAttachment $attachment){$this->assertVisible($ncr,$request);abort_unless((int)$attachment->ncr_report_id===(int)$ncr->id&&$attachment->is_active,404);abort_unless(Storage::disk('local')->exists($attachment->storage_path),404);return Storage::disk('local')->download($attachment->storage_path,$attachment->original_filename);}
    private function options(?NcrReport $ncr = null): array
    {
        $itemIds = $ncr ? $ncr->items()->get(['product_id', 'customer_id']) : collect();
        return [
            'departments' => Department::where('is_active', true)->orderBy('code')->get(),
            'formScopes' => $this->formScopeOptions(null, false),
            'problemTypes' => ProblemType::where('is_active', true)->orderBy('code')->get(),
            'severityLevels' => SeverityLevel::where('is_active', true)->orderBy('sort_order')->get(),
            'dispositionTypes' => DispositionType::where('is_active', true)->orderBy('code')->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->get(),
            'products' => Product::where('is_active', true)->orderBy('code')->get(),
            'customers' => $this->referenceOptions(Customer::class, $itemIds->pluck('customer_id')->filter()->all()),
            'suppliers' => $this->referenceOptions(Supplier::class, $ncr && $ncr->supplier_id ? [$ncr->supplier_id] : []),
        ];
    }

    private function referenceOptions(string $model, array $currentIds)
    {
        return $model::withTrashed()
            ->where(function ($query) use ($currentIds) {
                $query->where('is_active', true);
                if ($currentIds) $query->orWhereIn('id', $currentIds);
            })
            ->orderBy('code')
            ->get();
    }

    private function formScopeOptions(?NcrReport $ncr = null, bool $includeCurrentInactive = true)
    {
        $currentCodes = $ncr && $ncr->legacy_form_scope_code
            ? explode(',', $ncr->legacy_form_scope_code)
            : [];

        $query = $includeCurrentInactive ? NcrFormScope::withTrashed() : NcrFormScope::query();

        return $query
            ->where(function ($query) use ($currentCodes, $includeCurrentInactive) {
                $query->where('is_active', true);
                if ($includeCurrentInactive && $currentCodes) $query->orWhereIn('code', $currentCodes);
            })
            ->orderBy('code')
            ->get();
    }
    private function assertVisible(NcrReport $ncr,Request $request):void { abort_unless($this->visibility->canView($ncr,$request->user()),404); }
    public function storeCorrectiveAction(SaveCorrectiveActionRequest $request, NcrReport $ncr)
    {
        $data = $request->validated();
        $version = (int) $data['version_no'];
        unset($data['version_no'], $data['attachments']);
        $this->workflow->createCorrectiveAction($ncr, $request->user(), $data, $version, $request->file('attachments', []));
        return redirect()->route('ncr.show', $ncr)->with('success', 'สร้าง Corrective Action แล้ว');
    }

    public function updateCorrectiveAction(SaveCorrectiveActionRequest $request, NcrReport $ncr, NcrCorrectiveAction $action)
    {
        $data = $request->validated();
        $version = (int) $data['version_no'];
        unset($data['version_no'], $data['attachments']);
        $this->workflow->updateCorrectiveAction($ncr, $action, $request->user(), $data, $version, $request->file('attachments', []));
        return redirect()->route('ncr.show', $ncr)->with('success', 'อัปเดต Corrective Action แล้ว');
    }

    public function submitActionsForVerification(Request $request, NcrReport $ncr)
    {
        $data = $request->validate(['version_no' => ['required', 'integer', 'min:1']]);
        $this->workflow->submitActionsForVerification($ncr, $request->user(), (int) $data['version_no']);
        return redirect()->route('ncr.show', $ncr)->with('success', 'ส่ง Corrective Action เพื่อตรวจยืนยันแล้ว');
    }

    public function verifyActions(VerifyNcrActionRequest $request, NcrReport $ncr)
    {
        $data = $request->validated();
        $version = (int) $data['version_no'];
        unset($data['version_no']);
        $this->workflow->verifyActions($ncr, $request->user(), $version, $data);

        return redirect()->route('ncr.show', $ncr)->with(
            'success',
            $data['result'] === 'PASSED'
                ? 'ตรวจยืนยัน Corrective Action ผ่านและส่งต่อ QA แล้ว'
                : 'ส่ง Corrective Action กลับให้หน่วยงานรับผิดชอบทำ Rework แล้ว'
        );
    }

    public function startRework(Request $request, NcrReport $ncr)
    {
        $data = $request->validate(['version_no' => ['required', 'integer', 'min:1']]);
        $this->workflow->startRework($ncr, $request->user(), (int) $data['version_no']);

        return redirect()->route('ncr.show', $ncr)->with('success', 'เริ่ม Rework Corrective Action แล้ว');
    }

    public function saveQaFollowup(SaveNcrQaFollowupRequest $request, NcrReport $ncr)
    {
        $data = $request->validated(); $version = (int) $data['version_no']; unset($data['version_no']);
        if ($data['effectiveness_result'] === 'EFFECTIVE') {
            abort_unless($request->user()->can('ncr.close'), 403);
        }
        $this->workflow->saveQaFollowup($ncr, $request->user(), $version, $data);
        $message = $data['effectiveness_result'] === 'EFFECTIVE' ? 'บันทึกผลและปิด NCR แล้ว' : 'บันทึกการติดตามผลแล้ว';
        return redirect()->route('ncr.show', $ncr)->with('success', $message);
    }

    public function returnForReworkFromQa(Request $request, NcrReport $ncr)
    {
        $data = $request->validate(['version_no' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:10000']]);
        $this->workflow->returnForReworkFromQa($ncr, $request->user(), (int) $data['version_no'], $data['reason']);
        return redirect()->route('ncr.show', $ncr)->with('success', 'ส่ง NCR กลับให้ทำ Rework แล้ว');
    }

    public function downloadActionAttachment(Request $request, NcrReport $ncr, NcrActionAttachment $attachment)
    {
        $this->assertVisible($ncr, $request);
        abort_unless((int) $attachment->action()->value('ncr_report_id') === (int) $ncr->id && $attachment->is_active, 404);
        abort_unless(Storage::disk('local')->exists($attachment->storage_path), 404);
        return Storage::disk('local')->download($attachment->storage_path, $attachment->original_filename);
    }
}
