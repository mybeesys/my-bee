<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\ClientResource;
use App\Filament\Admin\Resources\PlatformCouponResource;
use App\Filament\Admin\Resources\SubscriptionRevenueResource;
use App\Filament\Tenant\Concerns\BuildsMonthlySparklineChart;
use App\Models\Client;
use App\Models\PlatformCoupon;
use App\Models\Subscription;
use App\Models\Tenant;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends BaseWidget
{
    use BuildsMonthlySparklineChart;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('fields.admin_dashboard_stats');
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $clientsTotal = Client::count();
        $newClientsWeek = Client::query()
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
        $tenantsTotal = Tenant::count();
        $revenueMonth = (float) Subscription::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('price');
        $activeCoupons = PlatformCoupon::query()->valid()->count();
        $trialsExpiring = $this->trialsExpiringSoonCount();

        return [
            Stat::make(__('fields.admin_dashboard_total_clients'), number_format($clientsTotal))
                ->description(__('fields.admin_dashboard_new_clients_week', ['count' => $newClientsWeek]))
                ->descriptionIcon('heroicon-m-user-plus')
                ->color(Color::Amber)
                ->chart($this->monthlyCountChart(Client::query()))
                ->icon('heroicon-o-user-group')
                ->url(ClientResource::getUrl('index')),

            Stat::make(__('fields.admin_dashboard_total_tenants'), number_format($tenantsTotal))
                ->description(__('fields.admin_dashboard_all_time'))
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color(Color::Sky)
                ->chart($this->monthlyCountChart(Tenant::query()))
                ->icon('heroicon-o-building-storefront'),

            Stat::make(__('fields.admin_dashboard_subscription_revenue'), format_amount($revenueMonth))
                ->description(__('fields.admin_dashboard_this_month'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color(Color::Emerald)
                ->chart($this->monthlySumChart(Subscription::query(), 'price'))
                ->icon('heroicon-o-currency-dollar')
                ->url(SubscriptionRevenueResource::getUrl('index')),

            Stat::make(__('fields.admin_dashboard_active_coupons'), (string) $activeCoupons)
                ->description(
                    $trialsExpiring > 0
                        ? __('fields.admin_dashboard_trials_expiring', ['count' => $trialsExpiring])
                        : __('fields.admin_dashboard_coupons_valid')
                )
                ->descriptionIcon(
                    $trialsExpiring > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge'
                )
                ->color($trialsExpiring > 0 ? Color::Orange : Color::Green)
                ->icon('heroicon-o-ticket')
                ->url(PlatformCouponResource::getUrl('index')),
        ];
    }

    protected function trialsExpiringSoonCount(): int
    {
        return Client::query()
            ->with(['subscription.plan'])
            ->get()
            ->filter(function (Client $client) {
                $expiresAt = subscription_trial_expires_at($client);

                return $expiresAt
                    && ! $expiresAt->isPast()
                    && $expiresAt->lte(now()->addDays(7));
            })
            ->count();
    }

    /**
     * @return array<int, float>
     */
    protected function monthlySumChart(\Illuminate\Database\Eloquent\Builder $query, string $column): array
    {
        $start = now()->subMonths(6)->startOfMonth();
        $end = now()->endOfMonth();
        $table = $query->getModel()->getTable();

        $sums = (clone $query)
            ->whereBetween("{$table}.created_at", [$start, $end])
            ->selectRaw("DATE_FORMAT({$table}.created_at, '%Y-%m') as period, SUM({$table}.{$column}) as aggregate")
            ->groupByRaw("DATE_FORMAT({$table}.created_at, '%Y-%m')")
            ->pluck('aggregate', 'period');

        $data = [];

        foreach (\Carbon\CarbonPeriod::create($start, '1 month', $end) as $date) {
            $data[] = (float) ($sums[$date->format('Y-m')] ?? 0);
        }

        return $data;
    }
}
