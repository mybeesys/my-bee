<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\ManagesSubscriptionRenewalRequests;
use App\Models\SubscriptionRenewalRequest;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class SubscriptionRequests extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use ManagesSubscriptionRenewalRequests;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string $view = 'filament.admin.pages.subscription-requests';

    protected static ?string $slug = 'subscription-requests';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.subscription_requests');
    }

    public function getTitle(): string|Htmlable
    {
        return __('fields.subscription_requests');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SubscriptionRenewalRequest::query()->pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SubscriptionRenewalRequest::query()->with([
                    'client',
                    'plan',
                    'currentPlan',
                    'approvedBy',
                    'cancelledBy',
                    'subscription',
                ])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label(__('fields.client'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (SubscriptionRenewalRequest $record): ?string => $record->client?->email),

                Tables\Columns\TextColumn::make('currentPlan.name')
                    ->label(__('fields.subscription_request_current_plan'))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('fields.subscription_request_requested_plan'))
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing_period')
                    ->label(__('fields.billing_period'))
                    ->formatStateUsing(fn (?string $state): string => $state === 'yearly'
                        ? __('fields.yearly')
                        : __('fields.monthly')),

                Tables\Columns\TextColumn::make('quoted_total')
                    ->label(__('fields.subscription_total_inc_tax'))
                    ->formatStateUsing(function ($state, SubscriptionRenewalRequest $record): string {
                        if ($state === null) {
                            return '—';
                        }

                        return main_currency_iso_code() . ' ' . format_amount((float) $state);
                    })
                    ->description(fn (SubscriptionRenewalRequest $record): ?string => filled($record->coupon_code)
                        ? __('fields.subscription_coupon') . ': ' . $record->coupon_code
                        : null),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state, SubscriptionRenewalRequest $record): string => $record->statusLabel())
                    ->color(fn ($state, SubscriptionRenewalRequest $record): string => $record->statusColor()),

                Tables\Columns\TextColumn::make('cancellation_reason')
                    ->label(__('fields.subscription_request_cancel_reason'))
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.date'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('fields.status'))
                    ->options([
                        SubscriptionRenewalRequest::STATUS_PENDING => __('fields.subscription_request_status_pending'),
                        SubscriptionRenewalRequest::STATUS_ACTIVE => __('fields.subscription_request_status_active'),
                        SubscriptionRenewalRequest::STATUS_CANCELLED => __('fields.subscription_request_status_cancelled'),
                    ])
                    ->default(SubscriptionRenewalRequest::STATUS_PENDING)
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['value'] ?? null) {
                            SubscriptionRenewalRequest::STATUS_PENDING => __('fields.subscription_request_status_pending'),
                            SubscriptionRenewalRequest::STATUS_ACTIVE => __('fields.subscription_request_status_active'),
                            SubscriptionRenewalRequest::STATUS_CANCELLED => __('fields.subscription_request_status_cancelled'),
                            default => null,
                        };
                    }),
                Tables\Filters\SelectFilter::make('plan_id')
                    ->label(__('fields.subscription_request_requested_plan'))
                    ->relationship('plan', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_subscription_request')
                        ->label(__('fields.view'))
                        ->icon('heroicon-o-eye')
                        ->infolist(static::subscriptionRequestInfolistSchema())
                        ->slideOver()
                        ->modalHeading(__('fields.subscription_request')),
                    static::approveSubscriptionRequestAction(),
                    static::cancelSubscriptionRequestAction(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->tooltip(__('fields.actions'))
                    ->color('gray'),
            ])
            ->bulkActions([]);
    }
}
