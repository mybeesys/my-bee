<?php

namespace App\Filament\Admin\Concerns;

use App\Models\SubscriptionRenewalRequest;
use App\Services\SubscriptionRenewalRequestService;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use InvalidArgumentException;

trait ManagesSubscriptionRenewalRequests
{
    public static function approveSubscriptionRequestAction(string $name = 'approve_subscription_request'): TableAction
    {
        return TableAction::make($name)
            ->label(__('fields.subscription_request_approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('fields.subscription_request_approve_heading'))
            ->modalDescription(fn (SubscriptionRenewalRequest $record): string => __('fields.subscription_request_approve_help', [
                'client' => $record->client?->name ?? '—',
                'plan' => $record->plan?->name ?? '—',
            ]))
            ->modalSubmitActionLabel(__('fields.subscription_request_approve'))
            ->visible(fn (SubscriptionRenewalRequest $record): bool => $record->isPending())
            ->action(function (SubscriptionRenewalRequest $record): void {
                static::approveSubscriptionRequest($record);
            });
    }

    public static function approveSubscriptionRequestHeaderAction(string $name = 'approve_subscription_request'): HeaderAction
    {
        return HeaderAction::make($name)
            ->label(__('fields.subscription_request_approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('fields.subscription_request_approve_heading'))
            ->modalDescription(fn ($livewire): string => __('fields.subscription_request_approve_help', [
                'client' => $livewire->record?->client?->name ?? '—',
                'plan' => $livewire->record?->plan?->name ?? '—',
            ]))
            ->modalSubmitActionLabel(__('fields.subscription_request_approve'))
            ->visible(fn ($livewire): bool => $livewire->record instanceof SubscriptionRenewalRequest
                && $livewire->record->isPending())
            ->action(function ($livewire): void {
                static::approveSubscriptionRequest($livewire->record);
                $livewire->record->refresh();
            });
    }

    public static function cancelSubscriptionRequestAction(string $name = 'cancel_subscription_request'): TableAction
    {
        return TableAction::make($name)
            ->label(__('fields.subscription_request_cancel'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form(static::cancelReasonForm())
            ->modalHeading(__('fields.subscription_request_cancel_heading'))
            ->modalDescription(__('fields.subscription_request_cancel_help'))
            ->modalSubmitActionLabel(__('fields.subscription_request_cancel'))
            ->visible(fn (SubscriptionRenewalRequest $record): bool => $record->isPending())
            ->action(function (SubscriptionRenewalRequest $record, array $data): void {
                static::cancelSubscriptionRequest($record, $data['cancellation_reason'] ?? '');
            });
    }

    public static function cancelSubscriptionRequestHeaderAction(string $name = 'cancel_subscription_request'): HeaderAction
    {
        return HeaderAction::make($name)
            ->label(__('fields.subscription_request_cancel'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form(static::cancelReasonForm())
            ->modalHeading(__('fields.subscription_request_cancel_heading'))
            ->modalDescription(__('fields.subscription_request_cancel_help'))
            ->modalSubmitActionLabel(__('fields.subscription_request_cancel'))
            ->visible(fn ($livewire): bool => $livewire->record instanceof SubscriptionRenewalRequest
                && $livewire->record->isPending())
            ->action(function ($livewire, array $data): void {
                static::cancelSubscriptionRequest($livewire->record, $data['cancellation_reason'] ?? '');
                $livewire->record->refresh();
            });
    }

    /** @return array<int, Infolists\Components\Component> */
    public static function subscriptionRequestInfolistSchema(): array
    {
        return [
            Infolists\Components\Section::make(__('fields.subscription_request_client_section'))
                ->icon('heroicon-o-user')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('client.name')
                        ->label(__('fields.client')),
                    Infolists\Components\TextEntry::make('client.email')
                        ->label(__('fields.email')),
                    Infolists\Components\TextEntry::make('client.phone')
                        ->label(__('fields.phone'))
                        ->placeholder('—'),
                ]),

            Infolists\Components\Section::make(__('fields.subscription_request_plan_section'))
                ->icon('heroicon-o-briefcase')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->label(__('fields.status'))
                        ->badge()
                        ->formatStateUsing(fn ($state, SubscriptionRenewalRequest $record): string => $record->statusLabel())
                        ->color(fn ($state, SubscriptionRenewalRequest $record): string => $record->statusColor()),
                    Infolists\Components\TextEntry::make('currentPlan.name')
                        ->label(__('fields.subscription_request_current_plan'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('plan.name')
                        ->label(__('fields.subscription_request_requested_plan'))
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('billing_period')
                        ->label(__('fields.billing_period'))
                        ->formatStateUsing(fn (?string $state): string => $state === 'yearly'
                            ? __('fields.yearly')
                            : __('fields.monthly')),
                    Infolists\Components\TextEntry::make('quoted_price_ex_tax')
                        ->label(__('fields.subscription_subtotal_ex_tax'))
                        ->formatStateUsing(fn ($state): string => $state === null
                            ? '—'
                            : main_currency_iso_code() . ' ' . format_amount((float) $state)),
                    Infolists\Components\TextEntry::make('quoted_tax_amount')
                        ->label(__('fields.tax'))
                        ->formatStateUsing(function ($state, SubscriptionRenewalRequest $record): string {
                            if ($state === null) {
                                return '—';
                            }

                            $vat = rtrim(rtrim(number_format((float) ($record->quoted_tax_percent ?? 0), 2, '.', ''), '0'), '.');

                            return main_currency_iso_code() . ' ' . format_amount((float) $state)
                                . ($vat !== '' ? " ({$vat}%)" : '');
                        }),
                    Infolists\Components\TextEntry::make('quoted_total')
                        ->label(__('fields.subscription_total_inc_tax'))
                        ->weight('bold')
                        ->formatStateUsing(fn ($state): string => $state === null
                            ? '—'
                            : main_currency_iso_code() . ' ' . format_amount((float) $state)),
                    Infolists\Components\TextEntry::make('coupon_code')
                        ->label(__('fields.subscription_coupon'))
                        ->placeholder('—')
                        ->visible(fn (SubscriptionRenewalRequest $record): bool => filled($record->coupon_code)),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label(__('fields.date'))
                        ->dateTime(),
                ]),

            Infolists\Components\Section::make(__('fields.subscription_request_decision_section'))
                ->icon('heroicon-o-clipboard-document-check')
                ->columns(2)
                ->visible(fn (SubscriptionRenewalRequest $record): bool => ! $record->isPending())
                ->schema([
                    Infolists\Components\TextEntry::make('approved_at')
                        ->label(__('fields.subscription_request_approved_at'))
                        ->dateTime()
                        ->visible(fn (SubscriptionRenewalRequest $record): bool => $record->isActive()),
                    Infolists\Components\TextEntry::make('approvedBy.full_name')
                        ->label(__('fields.subscription_request_approved_by'))
                        ->placeholder('—')
                        ->visible(fn (SubscriptionRenewalRequest $record): bool => $record->isActive()),
                    Infolists\Components\TextEntry::make('cancelled_at')
                        ->label(__('fields.subscription_request_cancelled_at'))
                        ->dateTime()
                        ->visible(fn (SubscriptionRenewalRequest $record): bool => $record->isCancelled()),
                    Infolists\Components\TextEntry::make('cancelledBy.full_name')
                        ->label(__('fields.subscription_request_cancelled_by'))
                        ->placeholder('—')
                        ->visible(fn (SubscriptionRenewalRequest $record): bool => $record->isCancelled()),
                    Infolists\Components\TextEntry::make('cancellation_reason')
                        ->label(__('fields.subscription_request_cancel_reason'))
                        ->columnSpanFull()
                        ->visible(fn (SubscriptionRenewalRequest $record): bool => $record->isCancelled()
                            && filled($record->cancellation_reason)),
                ]),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    protected static function cancelReasonForm(): array
    {
        return [
            Forms\Components\Textarea::make('cancellation_reason')
                ->label(__('fields.subscription_request_cancel_reason'))
                ->placeholder(__('fields.subscription_request_cancel_reason_placeholder'))
                ->required()
                ->minLength(3)
                ->maxLength(1000)
                ->rows(4),
        ];
    }

    protected static function approveSubscriptionRequest(SubscriptionRenewalRequest $record): void
    {
        try {
            SubscriptionRenewalRequestService::instance()->approve($record, auth()->id());

            Notification::make()
                ->title(__('fields.subscription_request_approved'))
                ->success()
                ->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected static function cancelSubscriptionRequest(SubscriptionRenewalRequest $record, string $reason): void
    {
        try {
            SubscriptionRenewalRequestService::instance()->cancel($record, $reason, auth()->id());

            Notification::make()
                ->title(__('fields.subscription_request_cancelled'))
                ->success()
                ->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
