<?php

namespace App\Filament\Admin\Resources\PlanResource\Pages;

use App\Filament\Admin\Resources\PlanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if(!array_key_exists('max_allowed_companies', $data))
            $data['max_allowed_companies'] = -1; //unlimited

        if(!array_key_exists('max_allowed_users', $data))
            $data['max_allowed_users'] = -1; //unlimited

        if(!array_key_exists('max_allowed_purchase_invoices', $data))
            $data['max_allowed_purchase_invoices'] = -1; //unlimited

        if(!array_key_exists('max_allowed_sales_invoices', $data))
            $data['max_allowed_sales_invoices'] = -1; //unlimited

        if(!array_key_exists('restrict_account_after_days', $data))
            $data['restrict_account_after_days'] = -1;

        if (($data['span'] ?? null) === \App\Models\Plan::SPAN_SPECIFIED) {
            $data['span_duration'] = 'monthly';
        }

        return $data;
    }
}
