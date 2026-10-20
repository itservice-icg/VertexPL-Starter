<?php
namespace App\Events;
use App\Models\NcrReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class NcrWorkflowActivity { use Dispatchable,SerializesModels; public function __construct(public NcrReport $report,public string $action){} }
