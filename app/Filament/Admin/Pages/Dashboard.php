<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\ClientResource;
use App\Filament\Admin\Resources\PlanResource;
use App\Filament\Admin\Resources\PlatformCouponResource;
use App\Filament\Admin\Resources\SubscriptionRevenueResource;
use App\Filament\Pages\Backups;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.admin.pages.dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-home';

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
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public function getQuickLinks(): array
    {
        return [
            [
                'label' => __('fields.admin_dashboard_quick_add_client'),
                'url' => ClientResource::getUrl('create'),
                'icon' => 'heroicon-o-user-plus',
            ],
            [
                'label' => __('fields.admin_dashboard_quick_plans'),
                'url' => PlanResource::getUrl('index'),
                'icon' => 'heroicon-o-briefcase',
            ],
            [
                'label' => __('fields.admin_dashboard_quick_coupons'),
                'url' => PlatformCouponResource::getUrl('index'),
                'icon' => 'heroicon-o-ticket',
            ],
            [
                'label' => __('fields.subscription_revenue'),
                'url' => SubscriptionRevenueResource::getUrl('index'),
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => __('fields.admin_dashboard_quick_settings'),
                'url' => Settings::getUrl(),
                'icon' => 'heroicon-o-cog-8-tooth',
            ],
            [
                'label' => __('fields.backups'),
                'url' => Backups::getUrl(),
                'icon' => 'heroicon-o-circle-stack',
            ],
        ];
    }
}
