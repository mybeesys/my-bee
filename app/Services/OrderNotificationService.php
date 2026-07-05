<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class OrderNotificationService
{
    public static function notifyNewOrder(Order $order): void
    {
        $order->loadMissing(['tenant', 'customer']);

        if (! $order->tenant_id || ! $order->tenant) {
            return;
        }

        $recipients = self::recipientsForTenant((int) $order->tenant_id);

        if ($recipients->isEmpty()) {
            return;
        }

        $customerName = $order->customer?->name ?? __('fields.customer');
        $source = $order->source === 'shop'
            ? __('fields.notification_order_source_shop')
            : __('fields.notification_order_source_dashboard');

        $url = route('filament.tenant.resources.orders.view', [
            'tenant' => $order->tenant->slug,
            'record' => $order->getKey(),
        ]);

        $databaseNotification = Notification::make()
            ->title(__('fields.notification_new_order_title', ['no' => $order->no]))
            ->body(__('fields.notification_new_order_body', [
                'customer' => $customerName,
                'source' => $source,
            ]))
            ->icon('heroicon-o-shopping-bag')
            ->status('info')
            ->actions([
                Action::make('view')
                    ->label(__('fields.view_order'))
                    ->url($url)
                    ->markAsRead(),
            ])
            ->toDatabase();

        foreach ($recipients as $user) {
            if (! (bool) $user->setting('enable_notifications', true)) {
                continue;
            }

            $user->notifyNow($databaseNotification);
            DatabaseNotificationsSent::dispatch($user);
        }
    }

    public static function recipientsForTenant(int $tenantId): Collection
    {
        return User::query()
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId));
            })
            ->get()
            ->unique('id')
            ->values();
    }
}
