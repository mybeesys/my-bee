<?php

namespace App\Filament\Admin\Concerns;

use App\Models\Subscription;
use App\Services\SubscriptionInvoiceAdjustmentService;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

trait AdjustsSubscriptionInvoiceDiscount
{
    public static function applySubscriptionAdminDiscountAction(string $name = 'apply_admin_discount'): TableAction
    {
        return TableAction::make($name)
            ->label(__('fields.revenue_admin_discount'))
            ->icon('heroicon-o-receipt-percent')
            ->color(Color::Orange)
            ->visible(fn (Subscription $record): bool => static::canAdjustSubscriptionInvoice($record))
            ->form(fn (Subscription $record): array => static::adminDiscountForm($record))
            ->modalHeading(__('fields.revenue_admin_discount'))
            ->modalDescription(__('fields.revenue_admin_discount_help'))
            ->modalSubmitActionLabel(__('fields.revenue_admin_discount_apply'))
            ->action(function (Subscription $record, array $data): void {
                static::applyAdminDiscountToSubscription($record, $data);
            });
    }

    public static function applySubscriptionAdminDiscountHeaderAction(string $name = 'apply_admin_discount'): HeaderAction
    {
        return HeaderAction::make($name)
            ->label(__('fields.revenue_admin_discount'))
            ->icon('heroicon-o-receipt-percent')
            ->color(Color::Orange)
            ->visible(fn ($livewire): bool => $livewire->record instanceof Subscription
                && static::canAdjustSubscriptionInvoice($livewire->record))
            ->form(fn ($livewire): array => static::adminDiscountForm($livewire->record))
            ->modalHeading(__('fields.revenue_admin_discount'))
            ->modalDescription(__('fields.revenue_admin_discount_help'))
            ->modalSubmitActionLabel(__('fields.revenue_admin_discount_apply'))
            ->action(function ($livewire, array $data): void {
                static::applyAdminDiscountToSubscription($livewire->record, $data);
                $livewire->record->refresh();
            });
    }

    public static function restoreSubscriptionAdminDiscountAction(string $name = 'restore_admin_discount'): TableAction
    {
        return TableAction::make($name)
            ->label(__('fields.revenue_admin_discount_restore'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color(Color::Gray)
            ->requiresConfirmation()
            ->modalHeading(__('fields.revenue_admin_discount_restore'))
            ->modalDescription(__('fields.revenue_admin_discount_restore_help'))
            ->visible(fn (Subscription $record): bool => $record->hasAdminDiscount())
            ->action(function (Subscription $record): void {
                SubscriptionInvoiceAdjustmentService::instance()->restore($record);

                Notification::make()
                    ->title(__('fields.revenue_admin_discount_restored'))
                    ->success()
                    ->send();
            });
    }

    public static function restoreSubscriptionAdminDiscountHeaderAction(string $name = 'restore_admin_discount'): HeaderAction
    {
        return HeaderAction::make($name)
            ->label(__('fields.revenue_admin_discount_restore'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color(Color::Gray)
            ->requiresConfirmation()
            ->modalHeading(__('fields.revenue_admin_discount_restore'))
            ->modalDescription(__('fields.revenue_admin_discount_restore_help'))
            ->visible(fn ($livewire): bool => $livewire->record instanceof Subscription
                && $livewire->record->hasAdminDiscount())
            ->action(function ($livewire): void {
                SubscriptionInvoiceAdjustmentService::instance()->restore($livewire->record);
                $livewire->record->refresh();

                Notification::make()
                    ->title(__('fields.revenue_admin_discount_restored'))
                    ->success()
                    ->send();
            });
    }

    public static function canAdjustSubscriptionInvoice(Subscription $record): bool
    {
        if ($record->isFree() && ! $record->hasAdminDiscount()) {
            return false;
        }

        $service = SubscriptionInvoiceAdjustmentService::instance();

        return $service->originalTotalIncTax($record) > 0
            || $service->taxableBaseBeforeAdminDiscount($record) > 0;
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function adminDiscountForm(Subscription $record): array
    {
        $service = SubscriptionInvoiceAdjustmentService::instance();
        $currency = main_currency_iso_code();

        return [
            Forms\Components\TextInput::make('percent')
                ->label(__('fields.revenue_admin_discount_percent'))
                ->numeric()
                ->minValue(0.01)
                ->maxValue(100)
                ->step(0.01)
                ->suffix('%')
                ->default(fn (): ?float => $record->admin_discount_percent)
                ->required()
                ->live(onBlur: true)
                ->helperText(__('fields.revenue_admin_discount_percent_hint')),

            Forms\Components\Textarea::make('note')
                ->label(__('fields.revenue_admin_discount_note'))
                ->rows(2)
                ->maxLength(500)
                ->default(fn (): ?string => $record->admin_discount_note)
                ->placeholder(__('fields.revenue_admin_discount_note_placeholder')),

            Forms\Components\Placeholder::make('preview')
                ->label(__('fields.revenue_admin_discount_preview'))
                ->content(function (Get $get) use ($record, $service, $currency): HtmlString {
                    $percent = (float) ($get('percent') ?? 0);

                    if ($percent <= 0 || $percent > 100) {
                        return new HtmlString(
                            '<p class="text-sm text-gray-500">' . e(__('fields.revenue_admin_discount_preview_empty')) . '</p>'
                        );
                    }

                    try {
                        $quote = $service->preview($record, $percent);
                    } catch (ValidationException) {
                        return new HtmlString(
                            '<p class="text-sm text-danger-600">' . e(__('fields.revenue_admin_discount_percent_invalid')) . '</p>'
                        );
                    }

                    $fmt = fn (float $amount): string => $currency . ' ' . format_amount($amount);

                    return new HtmlString(
                        '<div class="space-y-1 text-sm">'
                        . '<p>' . e(__('fields.revenue_admin_discount_preview_original')) . ': <strong>' . e($fmt($quote['original_total_inc_tax'])) . '</strong></p>'
                        . '<p>' . e(__('fields.revenue_admin_discount')) . ': <strong>' . e($quote['percent']) . '% (− ' . e($fmt($quote['admin_discount_amount'])) . ' ' . e(__('fields.revenue_before_tax')) . ')</strong></p>'
                        . '<p>' . e(__('fields.tax')) . ': <strong>' . e($fmt($quote['tax_amount'])) . '</strong></p>'
                        . '<p>' . e(__('fields.revenue_admin_discount_preview_recognized')) . ': <strong>' . e($fmt($quote['total_inc_tax'])) . '</strong></p>'
                        . '<p class="text-warning-600">' . e(__('fields.revenue_admin_discount_preview_waived')) . ': <strong>' . e($fmt($quote['waived_inc_tax'])) . '</strong></p>'
                        . '</div>'
                    );
                }),
        ];
    }

    /**
     * @param  array{percent?: mixed, note?: mixed}  $data
     */
    protected static function applyAdminDiscountToSubscription(Subscription $record, array $data): void
    {
        $updated = SubscriptionInvoiceAdjustmentService::instance()->apply(
            $record,
            (float) ($data['percent'] ?? 0),
            isset($data['note']) ? (string) $data['note'] : null,
            auth()->id(),
        );

        Notification::make()
            ->title(__('fields.revenue_admin_discount_applied'))
            ->body(__('fields.revenue_admin_discount_applied_body', [
                'percent' => format_amount((float) $updated->admin_discount_percent),
                'total' => format_amount((float) $updated->price),
            ]))
            ->success()
            ->send();
    }
}
