<?php

namespace App\Filament\Admin\Resources\HyperPayPaymentResource\Pages;

use App\Filament\Admin\Resources\HyperPayPaymentResource;
use App\Models\HyperPayPayment;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListHyperPayPayments extends ListRecords
{
    protected static string $resource = HyperPayPaymentResource::class;

    public function getTabs(): array
    {
        return [
            'paid' => Tab::make(__('fields.hyperpay_status_paid'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', HyperPayPayment::STATUS_PAID))
                ->badge(HyperPayPayment::query()->where('status', HyperPayPayment::STATUS_PAID)->count())
                ->badgeColor('success'),
            'open' => Tab::make(__('fields.hyperpay_status_checkout'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    HyperPayPayment::STATUS_PENDING,
                    HyperPayPayment::STATUS_CHECKOUT,
                    HyperPayPayment::STATUS_PENDING_GATEWAY,
                ]))
                ->badge(HyperPayPayment::query()->whereIn('status', [
                    HyperPayPayment::STATUS_PENDING,
                    HyperPayPayment::STATUS_CHECKOUT,
                    HyperPayPayment::STATUS_PENDING_GATEWAY,
                ])->count()),
            'failed' => Tab::make(__('fields.hyperpay_status_failed'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', HyperPayPayment::STATUS_FAILED))
                ->badge(HyperPayPayment::query()->where('status', HyperPayPayment::STATUS_FAILED)->count())
                ->badgeColor('danger'),
            'all' => Tab::make(__('fields.all'))
                ->badge(HyperPayPayment::query()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'paid';
    }
}
