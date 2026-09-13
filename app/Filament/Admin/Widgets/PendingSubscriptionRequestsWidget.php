<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\ManagesSubscriptionRenewalRequests;
use App\Models\SubscriptionRenewalRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingSubscriptionRequestsWidget extends BaseWidget
{
    use ManagesSubscriptionRenewalRequests;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('fields.subscription_requests'))
            ->description(__('fields.subscription_request_widget_description'))
            ->query(
                SubscriptionRenewalRequest::query()
                    ->pending()
                    ->with(['client', 'plan', 'currentPlan'])
                    ->latest()
            )
            ->emptyStateHeading(__('fields.subscription_request_widget_empty'))
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label(__('fields.client'))
                    ->weight('medium')
                    ->description(fn (SubscriptionRenewalRequest $record): ?string => $record->client?->email)
                    ->url('/subscription-requests'),

                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('fields.subscription_request_requested_plan'))
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('billing_period')
                    ->label(__('fields.billing_period'))
                    ->formatStateUsing(fn (?string $state): string => $state === 'yearly'
                        ? __('fields.yearly')
                        : __('fields.monthly')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.date'))
                    ->since(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url('/subscription-requests'),
                static::approveSubscriptionRequestAction(),
                static::cancelSubscriptionRequestAction(),
            ])
            ->paginated([5])
            ->defaultSort('created_at', 'desc');
    }
}
