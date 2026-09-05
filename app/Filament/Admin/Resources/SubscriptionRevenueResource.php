<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\AdjustsSubscriptionInvoiceDiscount;
use App\Filament\Admin\Concerns\SharesSubscriptionInvoiceUrl;
use App\Filament\Admin\Resources\SubscriptionRevenueResource\Pages;
use App\Models\Subscription;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionRevenueResource extends Resource
{
    use AdjustsSubscriptionInvoiceDiscount;
    use SharesSubscriptionInvoiceUrl;

    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $slug = 'subscription-revenue';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('fields.revenue_subscription_record');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.subscription_revenue');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.subscription_revenue');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([]);
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
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('fields.subscription_plan'))
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing_period')
                    ->label(__('fields.billing_period'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'yearly' => __('fields.yearly'),
                        default => __('fields.monthly'),
                    }),

                Tables\Columns\TextColumn::make('price_ex_tax')
                    ->label(__('fields.revenue_before_tax'))
                    ->formatStateUsing(fn ($state): string => format_amount((float) ($state ?? 0)))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label(__('fields.revenue_discount'))
                    ->formatStateUsing(fn ($state): string => $state > 0 ? '- ' . format_amount((float) $state) : '—')
                    ->color(fn ($state) => $state > 0 ? 'success' : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('coupon_code')
                    ->label(__('fields.code'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('admin_discount_percent')
                    ->label(__('fields.revenue_admin_discount'))
                    ->formatStateUsing(function ($state, Subscription $record): string {
                        if (! $record->hasAdminDiscount()) {
                            return '—';
                        }

                        return format_amount((float) $state) . '%';
                    })
                    ->description(fn (Subscription $record): ?string => $record->hasAdminDiscount()
                        ? '- ' . format_amount((float) $record->admin_discount_amount)
                        : null)
                    ->color(fn (Subscription $record) => $record->hasAdminDiscount() ? 'warning' : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tax_amount')
                    ->label(__('fields.tax'))
                    ->formatStateUsing(fn ($state): string => format_amount((float) ($state ?? 0)))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('fields.revenue_total'))
                    ->formatStateUsing(fn ($state): string => format_amount((float) $state))
                    ->weight('bold')
                    ->color(fn (Subscription $record): string => $record->hasAdminDiscount() ? 'warning' : 'success')
                    ->description(function (Subscription $record): ?string {
                        if (! $record->hasAdminDiscount() || $record->original_price === null) {
                            return null;
                        }

                        return __('fields.revenue_admin_discount_was', [
                            'amount' => format_amount((float) $record->original_price),
                        ]);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan_id')
                    ->label(__('fields.subscription_plan'))
                    ->relationship('plan', 'name'),

                Tables\Filters\SelectFilter::make('billing_period')
                    ->label(__('fields.billing_period'))
                    ->options([
                        'monthly' => __('fields.monthly'),
                        'yearly' => __('fields.yearly'),
                    ]),

                Tables\Filters\Filter::make('has_admin_discount')
                    ->label(__('fields.revenue_admin_discount'))
                    ->query(fn (Builder $query): Builder => $query->where('admin_discount_percent', '>', 0)),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('fields.from_date')),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label(__('fields.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    static::shareSubscriptionInvoiceUrlAction(),
                    static::applySubscriptionAdminDiscountAction(),
                    static::restoreSubscriptionAdminDiscountAction(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->tooltip(__('fields.actions'))
                    ->color('gray'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionRevenues::route('/'),
            'view' => Pages\ViewSubscriptionRevenue::route('/{record}'),
        ];
    }
}
