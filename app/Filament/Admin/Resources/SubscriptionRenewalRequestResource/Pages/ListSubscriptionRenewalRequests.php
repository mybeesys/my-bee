<?php

namespace App\Filament\Admin\Resources\SubscriptionRenewalRequestResource\Pages;

use App\Filament\Admin\Resources\SubscriptionRenewalRequestResource;
use App\Models\SubscriptionRenewalRequest;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptionRenewalRequests extends ListRecords
{
    protected static string $resource = SubscriptionRenewalRequestResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make(__('fields.subscription_request_status_pending'))
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->pending())
                ->badge(SubscriptionRenewalRequest::query()->pending()->count())
                ->badgeColor('warning'),
            'active' => Tab::make(__('fields.subscription_request_status_active'))
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->approved())
                ->badge(SubscriptionRenewalRequest::query()->approved()->count())
                ->badgeColor('success'),
            'cancelled' => Tab::make(__('fields.subscription_request_status_cancelled'))
                ->icon('heroicon-m-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->cancelled())
                ->badge(SubscriptionRenewalRequest::query()->cancelled()->count())
                ->badgeColor('danger'),
            'all' => Tab::make(__('fields.all'))
                ->badge(SubscriptionRenewalRequest::query()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
