<?php

namespace App\Providers;

use App\Models\Tire;
use App\Models\TireAssignment;
use App\Models\TireAssignmentSegment;
use App\Models\TireMovement;
use App\Models\WorkOrder;
use App\Observers\ImmutableRecordObserver;
use App\Observers\TireAssignmentObserver;
use App\Observers\TireAssignmentSegmentObserver;
use App\Observers\TireObserver;
use App\Observers\WorkOrderObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Paginator::defaultView('vendor.pagination.bootstrap-5');

        Password::defaults(fn () => Password::min(8));

        Tire::observe(TireObserver::class);
        TireAssignment::observe(TireAssignmentObserver::class);
        TireAssignmentSegment::observe(TireAssignmentSegmentObserver::class);
        TireMovement::observe(ImmutableRecordObserver::class);
        WorkOrder::observe(WorkOrderObserver::class);
    }
}
