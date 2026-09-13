<?php

namespace App\Services;

use App\Filament\Admin\Resources\SubscriptionRenewalRequestResource;
use App\Filament\Tenant\Pages\Subscription as TenantSubscriptionPage;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\Subscription;
use App\Models\SubscriptionRenewalRequest;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SubscriptionRenewalRequestService
{
    public static function instance(): self
    {
        return new self();
    }

    public function pendingForClient(Client $client): ?SubscriptionRenewalRequest
    {
        return $client->subscriptionRenewalRequests()
            ->pending()
            ->with(['plan', 'currentPlan'])
            ->latest('id')
            ->first();
    }

    public function submit(
        Client $client,
        Plan $plan,
        string $billingPeriod,
        ?PlatformCoupon $coupon = null,
    ): SubscriptionRenewalRequest {
        $client->loadMissing(['subscription.plan', 'user']);

        return DB::transaction(function () use ($client, $plan, $billingPeriod, $coupon) {
            $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);

            $hasPending = SubscriptionRenewalRequest::query()
                ->where('client_id', $lockedClient->id)
                ->pending()
                ->lockForUpdate()
                ->exists();

            if ($hasPending) {
                throw new InvalidArgumentException(__('fields.subscription_request_already_pending'));
            }

            $lockedClient->loadMissing(['subscription']);

            $pricing = SubscriptionPricingService::instance();
            $period = $pricing->normalizeBillingPeriod($billingPeriod);
            $quote = $pricing->quote($plan, $period);

            if ($coupon) {
                $coupon = SubscriptionCouponService::instance()->findUsable($coupon->code, $lockedClient);
                $quote = SubscriptionCouponService::instance()->applyToQuote($quote, $coupon);
            }

            $request = SubscriptionRenewalRequest::create([
                'client_id' => $lockedClient->id,
                'plan_id' => $plan->id,
                'current_plan_id' => $lockedClient->subscription?->plan_id,
                'billing_period' => $period,
                'platform_coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'quoted_price_ex_tax' => $quote['subtotal_ex_tax'] ?? null,
                'quoted_tax_amount' => $quote['tax_amount'] ?? null,
                'quoted_tax_percent' => $quote['tax_percent'] ?? null,
                'quoted_discount_amount' => $quote['discount_amount'] ?? null,
                'quoted_total' => $quote['total_inc_tax'] ?? null,
                'status' => SubscriptionRenewalRequest::STATUS_PENDING,
            ]);

            $request->load(['client.user', 'client.tenants', 'plan', 'currentPlan']);

            DB::afterCommit(fn () => $this->notifyAdminsOfNewRequest($request));

            return $request;
        });
    }

    public function approve(SubscriptionRenewalRequest $request, ?int $adminUserId = null): SubscriptionRenewalRequest
    {
        return DB::transaction(function () use ($request, $adminUserId) {
            $request = SubscriptionRenewalRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->id);

            if (! $request->isPending()) {
                throw new InvalidArgumentException(__('fields.subscription_request_not_pending'));
            }

            $request->loadMissing(['client.user', 'client.tenants', 'plan', 'platformCoupon']);

            $client = $request->client;
            $plan = $request->plan;

            if (! $client || ! $plan) {
                throw new InvalidArgumentException(__('fields.subscription_request_missing_plan'));
            }

            $coupon = $this->usableCoupon($request, $client);

            $subscription = Subscription::subscribe(
                $plan,
                $client,
                $request->billing_period,
                $coupon,
            );

            $request->fill([
                'status' => SubscriptionRenewalRequest::STATUS_ACTIVE,
                'approved_at' => now(),
                'approved_by' => $adminUserId,
                'subscription_id' => $subscription?->id,
            ])->save();

            $request->load(['client.user', 'client.tenants', 'plan', 'subscription']);

            DB::afterCommit(fn () => $this->notifyClientOfDecision($request, true));

            return $request->fresh(['client', 'plan', 'currentPlan', 'subscription', 'approvedBy']);
        });
    }

    public function cancel(
        SubscriptionRenewalRequest $request,
        string $reason,
        ?int $adminUserId = null,
    ): SubscriptionRenewalRequest {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(__('fields.subscription_request_cancel_reason_required'));
        }

        return DB::transaction(function () use ($request, $reason, $adminUserId) {
            $request = SubscriptionRenewalRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->id);

            if (! $request->isPending()) {
                throw new InvalidArgumentException(__('fields.subscription_request_not_pending'));
            }

            $request->fill([
                'status' => SubscriptionRenewalRequest::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $adminUserId,
            ])->save();

            $request->load(['client.user', 'client.tenants', 'plan']);

            DB::afterCommit(fn () => $this->notifyClientOfDecision($request, false));

            return $request->fresh(['client', 'plan', 'currentPlan', 'cancelledBy']);
        });
    }

    protected function usableCoupon(SubscriptionRenewalRequest $request, Client $client): ?PlatformCoupon
    {
        $code = $request->coupon_code;

        if (! filled($code)) {
            return null;
        }

        try {
            return SubscriptionCouponService::instance()->findUsable($code, $client);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    protected function notifyAdminsOfNewRequest(SubscriptionRenewalRequest $request): void
    {
        $admins = User::query()->superAdminOrSuperVisor()->get();

        if ($admins->isEmpty()) {
            return;
        }

        $url = $this->adminRequestUrl($request);
        $title = __('fields.subscription_request_notify_admin_title');
        $body = __('fields.subscription_request_notify_admin_body', [
            'client' => $request->client?->name ?? '—',
            'plan' => $request->plan?->name ?? '—',
        ]);

        $notification = Notification::make()
            ->title($this->linkedNotificationText($title, $url))
            ->body($this->linkedNotificationText($body, $url))
            ->icon('heroicon-o-clipboard-document-list')
            ->status('warning')
            ->actions($this->notificationOpenActions($url, __('fields.subscription_request_view')))
            ->toDatabase();

        foreach ($admins as $admin) {
            $admin->notifyNow($notification);
            DatabaseNotificationsSent::dispatch($admin);
        }
    }

    protected function notifyClientOfDecision(SubscriptionRenewalRequest $request, bool $approved): void
    {
        $user = $request->client?->user;

        if (! $user) {
            return;
        }

        $url = $this->tenantSubscriptionUrl($request->client);

        $title = $approved
            ? __('fields.subscription_request_notify_client_approved_title')
            : __('fields.subscription_request_notify_client_cancelled_title');

        $body = $approved
            ? __('fields.subscription_request_notify_client_approved_body', [
                'plan' => $request->plan?->name ?? '—',
            ])
            : __('fields.subscription_request_notify_client_cancelled_body', [
                'plan' => $request->plan?->name ?? '—',
                'reason' => $request->cancellation_reason ?: '—',
            ]);

        $notification = Notification::make()
            ->title($this->linkedNotificationText($title, $url))
            ->body($this->linkedNotificationText($body, $url))
            ->icon($approved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle')
            ->status($approved ? 'success' : 'danger')
            ->actions($this->notificationOpenActions($url, __('fields.subscription')))
            ->toDatabase();

        $user->notifyNow($notification);
        DatabaseNotificationsSent::dispatch($user);
    }

    /**
     * @return array<int, Action>
     */
    protected function notificationOpenActions(?string $url, string $label): array
    {
        if (! filled($url)) {
            return [];
        }

        return [
            Action::make('open')
                ->label($label)
                ->url($url)
                ->button()
                ->markAsRead()
                ->close(),
        ];
    }

    protected function linkedNotificationText(string $text, ?string $url): string
    {
        if (! filled($url)) {
            return $text;
        }

        return '<a href="' . e($url) . '" class="underline decoration-transparent hover:decoration-current">' . e($text) . '</a>';
    }

    protected function adminRequestUrl(SubscriptionRenewalRequest $request): ?string
    {
        try {
            return SubscriptionRenewalRequestResource::indexUrl();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    protected function tenantSubscriptionUrl(?Client $client): ?string
    {
        $tenant = $client?->tenants()->first();

        if (! $tenant) {
            return null;
        }

        try {
            return $this->withCurrentRequestPort(
                TenantSubscriptionPage::getUrl(
                    panel: 'tenant',
                    tenant: $tenant,
                )
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    protected function withCurrentRequestPort(string $url): string
    {
        $request = request();

        if (! $request?->getHttpHost()) {
            return $url;
        }

        $port = (int) $request->getPort();

        if ($port <= 0 || in_array($port, [80, 443], true)) {
            return $url;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || ! empty($parts['port'])) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? $request->getScheme();
        $authority = $scheme . '://' . $parts['host'];

        return preg_replace('#^' . preg_quote($authority, '#') . '#', $authority . ':' . $port, $url) ?: $url;
    }
}
