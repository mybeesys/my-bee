<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\SettingService;
use App\Services\TenantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateTenantSettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->queue = "update-all-tenants-settings";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenantService = TenantService::instance();
        foreach (Tenant::all() as $tenant) {
            $tenantService->updateOrCreateSettings($tenant->id);
        }
    }
}
