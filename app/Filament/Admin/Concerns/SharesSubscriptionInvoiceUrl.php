<?php

namespace App\Filament\Admin\Concerns;

use App\Models\Subscription;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Actions\Action as HeaderAction;
use Illuminate\Support\Js;

trait SharesSubscriptionInvoiceUrl
{
    public static function shareSubscriptionInvoiceUrlAction(string $name = 'subscription_invoice_url'): TableAction
    {
        return TableAction::make($name)
            ->label(__('fields.subscription_invoice_url'))
            ->icon('heroicon-o-link')
            ->color(Color::Sky)
            ->visible(fn (Subscription $record): bool => filled($record->getKey()) && ! $record->isFree())
            ->action(fn (Subscription $record, $livewire) => static::copySubscriptionInvoiceLink($record, $livewire));
    }

    public static function shareSubscriptionInvoiceUrlHeaderAction(string $name = 'subscription_invoice_url'): HeaderAction
    {
        return HeaderAction::make($name)
            ->label(__('fields.subscription_invoice_url'))
            ->icon('heroicon-o-link')
            ->color(Color::Sky)
            ->visible(fn ($livewire): bool => filled($livewire->record?->getKey()) && ! $livewire->record->isFree())
            ->action(fn ($livewire) => static::copySubscriptionInvoiceLink($livewire->record, $livewire));
    }

    protected static function copySubscriptionInvoiceLink(Subscription $record, mixed $livewire): void
    {
        $url = $record->url;

        $livewire->js('window.navigator.clipboard.writeText(' . Js::from($url) . ')');

        Notification::make()
            ->title(__('fields.subscription_invoice_link_copied'))
            ->body($url)
            ->success()
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('open')
                    ->label(__('fields.invoice_download_view_file'))
                    ->url($url, shouldOpenInNewTab: true),
            ])
            ->send();
    }
}
