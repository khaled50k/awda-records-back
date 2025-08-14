<?php

namespace App\Providers;

use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\RecordTransfer;
use App\Models\User;
use App\Policies\MedicalRecordPolicy;
use App\Policies\PatientPolicy;
use App\Policies\RecordTransferPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Patient::class => PatientPolicy::class,
        MedicalRecord::class => MedicalRecordPolicy::class,
        RecordTransfer::class => RecordTransferPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define additional gates for specific actions
        Gate::define('manage-users', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('manage-patients', function (User $user) {
            return $user->isAdmin() || $user->isEmployee();
        });

        Gate::define('manage-records', function (User $user) {
            return $user->isAdmin() || $user->isEmployee();
        });

        Gate::define('manage-transfers', function (User $user) {
            return $user->isAdmin() || $user->isEmployee();
        });
    }
}
