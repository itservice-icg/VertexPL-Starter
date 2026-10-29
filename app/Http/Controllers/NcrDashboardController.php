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
        $base = $this->visibility->scopeForDashboard(NcrReport::query(), $user);
        $counts = $base->clone()->select('current_status', DB::raw('count(*) as total'))->groupBy('current_status')->pluck('total', 'current_status');
        $open = $base->clone()->where('current_status', '!=', 'CLOSED')->count();
        $overdue = $base->clone()->where('current_status', '!=', 'CLOSED')->whereDate('due_date', '<', now()->toDateString())->count();
        $recent = $base->clone()->with(['reporter', 'items', 'currentOwnerDepartment', 'reporterDepartment', 'dispositions.responsibleDepartment'])
            ->latest('updated_at')
            ->limit(10)
            ->get();
        return view('ncr.dashboard', compact('counts', 'open', 'overdue', 'recent'));
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
