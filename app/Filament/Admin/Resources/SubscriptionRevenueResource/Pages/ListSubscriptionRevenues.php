<?php

namespace App\Filament\Admin\Resources\SubscriptionRevenueResource\Pages;

use App\Filament\Admin\Resources\SubscriptionRevenueResource;
use App\Filament\Admin\Resources\SubscriptionRevenueResource\Widgets\RevenueOverviewWidget;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionRevenues extends ListRecords
{
    protected static string $resource = SubscriptionRevenueResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RevenueOverviewWidget::class,
        ];
    }
}
