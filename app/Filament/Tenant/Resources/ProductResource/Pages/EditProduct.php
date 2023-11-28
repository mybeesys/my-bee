<?php

namespace App\Filament\Tenant\Resources\ProductResource\Pages;

use App\Filament\Tenant\Resources\ProductResource;
use App\Models\ItemPrice;
use App\Services\PricingService;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            DB::beginTransaction();

            $mainUnitCost = $data['main_unit_cost'];
            $mainUnitPrice = $data['main_unit_price'];

            $model = parent::handleRecordUpdate($record, Arr::except($data, ['main_unit_price', 'main_unit_cost']));

            $newPrice = (new PricingService())->addPrice($model, $data['main_unit_id'], $mainUnitCost, $mainUnitPrice);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            fns()->sendWarning($exception->getMessage());
            $this->halt();
        }
        return $model;
    }

    protected function afterSave(): void
    {
        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record->id]));
    }
}
