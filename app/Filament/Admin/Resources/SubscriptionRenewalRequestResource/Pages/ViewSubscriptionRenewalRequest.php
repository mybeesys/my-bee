<?php

namespace App\Filament\Admin\Resources\SubscriptionRenewalRequestResource\Pages;

use App\Filament\Admin\Concerns\ManagesSubscriptionRenewalRequests;
use App\Filament\Admin\Resources\SubscriptionRenewalRequestResource;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscriptionRenewalRequest extends ViewRecord
{
    use ManagesSubscriptionRenewalRequests;

    protected static string $resource = SubscriptionRenewalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::approveSubscriptionRequestHeaderAction(),
            static::cancelSubscriptionRequestHeaderAction(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema(static::subscriptionRequestInfolistSchema());
    }
}
