<?php

namespace App\Services;

use App\Models\NcrReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NcrVisibilityService
{
    public function scope(Builder $query, User $user): Builder
    {
        if ($user->can('ncr.view.all')) return $query;

        $query->where(function (Builder $draftRule) use ($user) {
            $draftRule->where('current_status', '!=', 'DRAFT')->orWhere('reporter_id', $user->id);
        });

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('reporter_id', $user->id)->orWhere('current_owner_user_id', $user->id);
            $visible->orWhereHas('initialReviews', fn (Builder $q) => $q->where('reviewed_by', $user->id));
            if ($user->department_id) {
                $visible->orWhere('current_owner_department_id', $user->department_id)
                    ->orWhere('related_department_id', $user->department_id)
                    ->orWhereHas('dispositions', fn (Builder $q) => $q->where('status', 'APPROVED')->where('responsible_department_id', $user->department_id))
                    ->orWhereHas('correctiveActions', fn (Builder $q) => $q->where('responsible_department_id', $user->department_id));
            }
            $visible->orWhereHas('correctiveActions', fn (Builder $q) => $q->where('owner_user_id', $user->id));
            if ($user->can('ncr.review') || $user->can('ncr.view')) $visible->orWhere('current_status', 'INITIAL_REVIEW');
            if ($user->can('ncr.disposition.approve')) $visible->orWhereIn('current_status', ['PENDING_MANAGER_APPROVAL', 'RETURNED_FOR_DISPOSITION_REVISION', 'PENDING_DESTRUCTION_APPROVAL', 'DISPOSITION_APPROVED', 'ACTION_IN_PROGRESS', 'PENDING_ACTION_VERIFICATION', 'RETURNED_FOR_REWORK', 'QA_VERIFIED', 'CLOSED']);
            if ($user->can('ncr.destruction.approve')) $visible->orWhere('current_status', 'PENDING_DESTRUCTION_APPROVAL');
            if ($user->can('ncr.action.verify')) $visible->orWhere('current_status', 'PENDING_ACTION_VERIFICATION');
            if ($user->can('ncr.qa.followup')) $visible->orWhereIn('current_status', ['QA_VERIFIED', 'RETURNED_FOR_REWORK']);
            if ($user->can('ncr.close')) $visible->orWhere('current_status', 'CLOSED');
        });
    }

    public function canView(NcrReport $report, User $user): bool
    {
        return $this->scope(NcrReport::query()->whereKey($report->id), $user)->exists();
    }
}
