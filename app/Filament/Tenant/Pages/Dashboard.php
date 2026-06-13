<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Pages\IncomeStatement;
use App\Filament\Tenant\Resources\ExpenseResource;
use App\Filament\Tenant\Resources\OrderResource;
use App\Filament\Tenant\Resources\SalesInvoiceResource;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.tenant.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('fields.dashboard');
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }

    /**
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public function getQuickLinks(): array
    {
        return [
            [
                'label' => __('fields.order'),
                'url' => OrderResource::getUrl('create'),
                'icon' => 'heroicon-o-shopping-cart',
            ],
            [
                'label' => __('fields.sales_invoice'),
                'url' => SalesInvoiceResource::getUrl('create'),
                'icon' => 'heroicon-o-document-text',
            ],
            [
                'label' => __('fields.expenses'),
                'url' => ExpenseResource::getUrl('index'),
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => __('fields.income_statement'),
                'url' => IncomeStatement::getUrl(),
                'icon' => 'heroicon-o-chart-bar',
            ],
        ];
    }
}
