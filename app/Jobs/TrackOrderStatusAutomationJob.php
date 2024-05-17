<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class TrackOrderStatusAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->checkTenants();
    }

    public function checkTenants()
    {
        $tenants = Cache::remember('tenants', 60 * 10, function () {
            return Tenant::with(['client.user', 'users', 'orders'])->get();
        });

        foreach ($tenants as $tenant) {
            if ($tenant->store_enable_orders_tracking and $tenant->store_orders_tracking_mode == "automatic") {
                //update any new to packaging
                foreach ($tenant->orders->where('status', Order::$STATUS_NEW) as $newOrder) {
                    $newOrder->update(['status' => Order::$STATUS_PACKAGING]);
                    $this->notifyOrderUpdatedToPackaging($tenant, $newOrder);
                }
                //update new order if store_orders_tracking_packaging_time_hours has arrived
                foreach ($tenant->orders->where('status', Order::$STATUS_PACKAGING) as $packagingOrder) {
                    if ($packagingOrder->created_at->addMinutes($tenant->store_orders_tracking_packaging_time_hours * 60)->isPast()) {
                        $packagingOrder->update(['status' => Order::$STATUS_DELIVERY_IN_PROGRESS]);
                        $this->notifyOrderUpdatedToInDelivery($tenant, $packagingOrder);
                    }
                }

                //update new order if store_orders_tracking_delivery_time_hours has arrived
                foreach ($tenant->orders->where('status', Order::$STATUS_DELIVERY_IN_PROGRESS) as $inDeliveryOrder) {
                    if ($inDeliveryOrder->created_at and $inDeliveryOrder->created_at
                            ->addMinutes(($tenant->store_orders_tracking_packaging_time_hours + $tenant->store_orders_tracking_delivery_time_hours) * 60)
                            ->isPast()) {
                        $inDeliveryOrder->update(['status' => Order::$STATUS_COMPLETED]);
                        $this->notifyOrderUpdatedToCompleted($tenant, $inDeliveryOrder);
                    }
                }
            }
        }
    }

    public function notifyOrderUpdatedToPackaging(Tenant $tenant, $order)
    {

    }

    public function notifyOrderUpdatedToInDelivery(Tenant $tenant, $order)
    {

    }

    public function notifyOrderUpdatedToCompleted(Tenant $tenant, $order)
    {

    }
}
