<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\ManagesSubscriptionRenewalRequests;
use App\Filament\Admin\Resources\SubscriptionRenewalRequestResource\Pages;
use App\Models\SubscriptionRenewalRequest;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionRenewalRequestResource extends Resource
{
    use ManagesSubscriptionRenewalRequests;

    protected static ?string $model = SubscriptionRenewalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $slug = 'subscription-renewal-requests';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('fields.subscription_request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.subscription_requests');
    }

    protected static bool $isScopedToTenant = false;

    protected static bool $shouldSkipAuthorization = true;

    public static function getNavigationLabel(): string
    {
        return __('fields.subscription_requests');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema(static::subscriptionRequestInfolistSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ]),
                Tables\Filters\SelectFilter::make('plan_id')
                    ->label(__('fields.subscription_request_requested_plan'))
                    ->relationship('plan', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'client',
            'plan',
            'currentPlan',
            'approvedBy',
            'cancelledBy',
            'subscription',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionRenewalRequests::route('/'),
            'view' => Pages\ViewSubscriptionRenewalRequest::route('/{record}'),
        ];
    }

    public static function getNavigationUrl(): string
    {
        return static::indexUrl();
    }

    public static function indexUrl(): string
    {
        try {
            return \App\Filament\Admin\Pages\SubscriptionRequests::getUrl(panel: 'admin');
        } catch (\Throwable) {
            return static::adminPanelUrl('subscription-requests');
        }
    }

    public static function viewUrl(\Illuminate\Database\Eloquent\Model|int|string $record): string
    {
        return static::indexUrl();
    }

    public static function adminPanelUrl(string $path = ''): string
    {
        $path = trim($path, '/');
        $domain = config('app.env') === 'local' ? 'admin.my-bee.test' : 'admin.mybeesystem.com';
        $request = request();
        $scheme = $request?->getScheme() ?: 'http';
        $port = (int) ($request?->getPort() ?: 0);
        $portSuffix = $port > 0 && ! in_array($port, [80, 443], true) ? ':'.$port : '';

        return $scheme.'://'.$domain.$portSuffix.'/'.$path;
    }
}
