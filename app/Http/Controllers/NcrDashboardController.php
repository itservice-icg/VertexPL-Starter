<?php

namespace App\Http\Controllers;

use App\Models\NcrReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NcrVisibilityService;

class NcrDashboardController extends Controller
{
    public function __construct(private NcrVisibilityService $visibility) {}
    public function index(Request $request)
    {
        $this->authorizePermission($request, 'dashboard.view');
        $user = $request->user();
        $base = $this->visibility->scope(NcrReport::query(), $user);
        if (!$user->can('ncr.view.all')) {
            if ($user->department_id) {
                $departmentId = $user->department_id;
                $base->where(function ($query) use ($departmentId) {
                    $query->where('reporter_department_id', $departmentId)
                        ->orWhere('current_owner_department_id', $departmentId)
                        ->orWhere('related_department_id', $departmentId)
                        ->orWhereHas('dispositions', fn ($q) => $q->where('responsible_department_id', $departmentId))
                        ->orWhereHas('correctiveActions', fn ($q) => $q->where('responsible_department_id', $departmentId));
                });
            } else {
                $base->whereRaw('1 = 0');
            }
        }
        $base->where('current_status', '!=', 'DRAFT');
        $counts = $base->clone()->select('current_status', DB::raw('count(*) as total'))->groupBy('current_status')->pluck('total', 'current_status');
        $open = $base->clone()->where('current_status', '!=', 'CLOSED')->count();
        $overdue = $base->clone()->where('current_status', '!=', 'CLOSED')->whereDate('due_date', '<', now()->toDateString())->count();
        $recent = $base->clone()->with(['reporter', 'items', 'currentOwnerDepartment', 'reporterDepartment', 'dispositions.responsibleDepartment'])
            ->latest('updated_at')
            ->limit(12)
            ->get();
        return view('ncr.dashboard', compact('counts', 'open', 'overdue', 'recent'));
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
