<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InitializeTenantData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Tenant $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->settings();
//        $this->units();
//        $this->financeAccounts();
//        $this->invoiceStatuses();
    }

    protected function settings(){
        $service = new SettingService($this->tenant->id);

        $service->createOrUpdate('company.name', "default", 'text', true, $service->rulesForString(), ['company']);
        $service->createOrUpdate('company.address', "default", 'text', true, [], ['general']);
        $service->createOrUpdate('company.contact.phone', "default", 'text', true, $service->rulesForString(), ['company', 'contact']);
        $service->createOrUpdate('company.contact.mobile', "default", 'text', true, $service->rulesForString(), ['company', 'contact']);
        $service->createOrUpdate('company.contact.email', "default", 'text', true, $service->rulesForString(), ['company', 'contact']);

    }
    protected function units(){

    }
    protected function financeAccounts(){

    }
    protected function invoiceStatuses(){

    }
}
