<?php

namespace App\Observers;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;

class WorkOrderObserver
{
    public function saving(WorkOrder $order): void
    {
        $order->open_tire_id = $order->status instanceof WorkOrderStatus && $order->status->isOpen()
            ? $order->tire_id
            : null;
    }
}
