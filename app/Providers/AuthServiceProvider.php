<?php

namespace App\Providers;

use App\Models\NcrReport;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('ncr.corrective-action.approve', function (User $user, NcrReport $report) {
            $departmentId = $report->dispositions()
                ->where('status', 'APPROVED')
                ->latest('revision_no')
                ->value('responsible_department_id');

            return $user->can('ncr.action.manage')
                && $user->canApproveCorrectiveActionIn($departmentId ? (int) $departmentId : null);
        });
    }
}
