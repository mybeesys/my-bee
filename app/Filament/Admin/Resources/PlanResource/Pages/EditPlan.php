<?php

namespace App\Filament\Admin\Resources\PlanResource\Pages;

use App\Filament\Admin\Resources\PlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $max_allowed_companies = $data['max_allowed_companies'] ?? null;
        $max_allowed_users = $data['max_allowed_users'] ?? null;
        $max_allowed_purchase_invoices = $data['max_allowed_purchase_invoices'] ?? null;
        $max_allowed_sales_invoices = $data['max_allowed_sales_invoices'] ?? null;
        $restrict_account_after_days = $data['restrict_account_after_days'] ?? null;

        $data['unlimited_companies'] = $max_allowed_companies == -1;
        $data['unlimited_users'] = $max_allowed_users == -1;
        $data['unlimited_purchase_invoices'] = $max_allowed_purchase_invoices == -1;
        $data['unlimited_sales_invoices'] = $max_allowed_sales_invoices == -1;
        $data['restrict_account_after_period'] = $restrict_account_after_days > 0;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

        return $data;
    }
}
