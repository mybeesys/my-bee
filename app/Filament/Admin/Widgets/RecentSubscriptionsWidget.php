<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\ClientResource;
use App\Filament\Admin\Resources\SubscriptionRevenueResource;
use App\Models\Subscription;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentSubscriptionsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('fields.admin_dashboard_recent_subscriptions'))
            ->query(
                Subscription::query()
                    ->with(['client', 'plan'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label(__('fields.client'))
                    ->weight('medium')
                    ->limit(24)
                    ->url(fn (Subscription $record): ?string => $record->client
                        ? ClientResource::getUrl('edit', ['record' => $record->client])
                        : null),

                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('fields.subscription_plan'))
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('billing_period')
                    ->label(__('fields.billing_period'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'yearly' => __('fields.yearly'),
                        default => __('fields.monthly'),
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('fields.price'))
                    ->formatStateUsing(fn ($state): string => format_amount((float) $state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.date'))
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Subscription $record): string => SubscriptionRevenueResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
