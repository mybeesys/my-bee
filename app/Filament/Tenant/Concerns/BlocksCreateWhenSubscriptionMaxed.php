<?php

namespace App\Filament\Tenant\Concerns;

use App\Filament\Tenant\Pages\Subscription;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

trait BlocksCreateWhenSubscriptionMaxed
{
    abstract protected static function subscriptionLimitType(): string;

    public function create(bool $another = false): void
    {
        if ($this->subscriptionLimitIsReached()) {
            $this->notifySubscriptionLimitReached();
            $this->redirect(static::getResource()::getUrl('index'));

            return;
        }

        parent::create($another);
    }

    protected function beforeCreate(): void
    {
        if ($this->subscriptionLimitIsReached()) {
            $this->notifySubscriptionLimitReached();

            throw new Halt();
        }
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->hidden(fn () => $this->subscriptionLimitIsReached());
    }

    protected function abortCreateWhenSubscriptionMaxed(string $redirectUrl): void
    {
        if (! $this->subscriptionLimitIsReached()) {
            return;
        }

        $this->notifySubscriptionLimitReached();
        $this->redirect($redirectUrl);
    }

    protected function subscriptionLimitIsReached(?Client $client = null): bool
    {
        return subscription_resource_maxed_out(static::subscriptionLimitType(), $client);
    }

    protected function notifySubscriptionLimitReached(?Client $client = null): void
    {
        $usage = subscription_limit_usage(static::subscriptionLimitType(), $client);

        Notification::make()
            ->warning()
            ->title($usage['title'] ?? __('fields.subscription_limit_reached_title'))
            ->body($usage['body'] ?? __('fields.subscription_limit_reached_body'))
            ->actions([
                NotificationAction::make('upgrade')
                    ->label($usage['upgrade_label'] ?? __('fields.sales_invoices_maxed_out_upgrade'))
                    ->url(Subscription::getUrl())
                    ->button(),
            ])
            ->persistent()
            ->send();
    }
}
