<?php

namespace App\Http\Requests;

use App\Services\InventoryReportService;

class InventorySummaryReportRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = [
            InventoryReportService::TYPE_PURCHASE,
            InventoryReportService::TYPE_SALES,
            InventoryReportService::TYPE_PURCHASE_RETURN,
            InventoryReportService::TYPE_SALES_RETURN,
            InventoryReportService::TYPE_OPENING,
            InventoryReportService::TYPE_TRANSFER_IN,
            InventoryReportService::TYPE_TRANSFER_OUT,
        ];

        return [
            'from_date' => ['sometimes', 'nullable', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'nullable', 'date_format:d-m-Y', 'after_or_equal:from_date'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'warehouse_ids' => ['sometimes', 'nullable', 'array'],
            'warehouse_ids.*' => ['integer'],
            'product_ids' => ['sometimes', 'nullable', 'array'],
            'product_ids.*' => ['integer'],
            'movement_types' => ['sometimes', 'nullable', 'array'],
            'movement_types.*' => ['in:' . implode(',', $types)],
        ];
    }
}
