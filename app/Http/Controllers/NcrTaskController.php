<?php

namespace App\Http\Controllers;

use App\Models\NcrReport;
use App\Services\NcrVisibilityService;
use Illuminate\Http\Request;

class NcrTaskController extends Controller
{
    public function __construct(private NcrVisibilityService $visibility) {}
    public function index(Request $request)
    {
        $user = $request->user();
        $reports = $this->visibility->scope(NcrReport::query(), $user)->with('reporter')->whereNotIn('current_status', ['DRAFT', 'CLOSED'])->latest('updated_at')->paginate(20);
        return view('ncr.tasks', compact('reports'));
    }
}
