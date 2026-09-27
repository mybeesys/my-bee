<?php

namespace App\Services\HyperPay;

use App\Models\Client;
use App\Models\HyperPayPayment;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionCouponService;
use App\Services\SubscriptionPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class HyperPayCheckoutService
{
    public function __construct(
        protected HyperPayConfig $config,
        protected HyperPayGateway $gateway,
    ) {
    }

    public static function instance(): self
    {
        $config = HyperPayConfig::instance();

        return new self($config, HyperPayGateway::make($config));
    }

    /**
     * @param  array<string, string>  $billing
     */
    public function start(
        Client $client,
        Plan $plan,
        string $billingPeriod,
        array $billing,
        ?PlatformCoupon $coupon = null,
        ?Tenant $tenant = null,
        string $source = HyperPayPayment::SOURCE_SUBSCRIPTION,
    ): HyperPayPayment {
        if (! $this->config->isConfigured()) {
            throw new RuntimeException(__('fields.hyperpay_not_configured'));
        }

        $this->assertBilling($billing);

        $quote = $this->quote($plan, $billingPeriod, $client, $coupon);
        $amount = (float) ($quote['total_inc_tax'] ?? 0);

        if ($amount <= 0) {
            throw new InvalidArgumentException(__('fields.hyperpay_free_plan_no_checkout'));
        }

        $this->persistClientBilling($client, $billing);

        $formattedAmount = $this->config->formatAmount($amount);
        $merchantTransactionId = $this->uniqueMerchantTransactionId($client);

        $payload = $this->checkoutPayload(
            $formattedAmount,
            $merchantTransactionId,
            $billing,
        );

        $payment = HyperPayPayment::query()->create([
            'client_id' => $client->id,
            'tenant_id' => $tenant?->id,
            'plan_id' => $plan->id,
            'billing_period' => $quote['billing_period'],
            'platform_coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'amount' => (float) $formattedAmount,
            'currency' => $this->config->currency(),
            'merchant_transaction_id' => $merchantTransactionId,
            'status' => HyperPayPayment::STATUS_PENDING,
            'source' => $source === HyperPayPayment::SOURCE_REGISTRATION
                ? HyperPayPayment::SOURCE_REGISTRATION
                : HyperPayPayment::SOURCE_SUBSCRIPTION,
            'billing' => $billing,
            'request_payload' => $this->safePayload($payload),
        ]);

        try {
            $checkout = $this->gateway->createCheckout($payload);
        } catch (\Throwable $exception) {
            $payment->fill([
                'status' => HyperPayPayment::STATUS_FAILED,
                'failed_at' => now(),
                'result_description' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        $payment->fill([
            'checkout_id' => $checkout['id'] ?? null,
            'integrity' => $checkout['integrity'] ?? null,
            'status' => HyperPayPayment::STATUS_CHECKOUT,
            'gateway_response' => $checkout,
        ])->save();

        return $payment->fresh(['plan', 'client']);
    }

    public function complete(HyperPayPayment $payment, string $resourcePath): HyperPayPayment
    {
        if ($payment->isPaid()) {
            return $payment;
        }

        if (! $payment->isOpen()) {
            throw new RuntimeException(__('fields.hyperpay_payment_closed'));
        }

        $status = $this->gateway->fetchPaymentStatus($resourcePath);
        $code = (string) data_get($status, 'result.code', '');
        $description = (string) data_get($status, 'result.description', '');

        $payment->fill([
            'resource_path' => $resourcePath,
            'payment_id' => $status['id'] ?? $payment->payment_id,
            'brand' => data_get($status, 'paymentBrand'),
            'result_code' => $code,
            'result_description' => $description,
            'gateway_response' => $status,
        ]);

        if (HyperPayResult::isPending($code)) {
            $payment->status = HyperPayPayment::STATUS_PENDING_GATEWAY;
            $payment->save();

            return $payment->fresh(['plan', 'client', 'subscription']);
        }

        if (! HyperPayResult::isSuccessful($code)) {
            $payment->status = HyperPayPayment::STATUS_FAILED;
            $payment->failed_at = now();
            $payment->save();

            return $payment->fresh(['plan', 'client']);
        }

        return DB::transaction(function () use ($payment) {
            $locked = HyperPayPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->isPaid()) {
                return $locked->fresh(['plan', 'client', 'subscription']);
            }

            $locked->loadMissing(['client.user', 'plan', 'platformCoupon']);

            $coupon = $this->usableCoupon($locked);

            $subscription = Subscription::subscribe(
                $locked->plan,
                $locked->client,
                $locked->billing_period,
                $coupon,
            );

            $subscription->forceFill([
                'paid_at' => now(),
            ])->save();

            $locked->fill([
                'status' => HyperPayPayment::STATUS_PAID,
                'paid_at' => now(),
                'subscription_id' => $subscription->id,
            ])->save();

            $locked->client?->clearPendingPaidRegistration();
            clear_registration_plan_selection();

            return $locked->fresh(['plan', 'client', 'subscription']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(Plan $plan, string $billingPeriod, Client $client, ?PlatformCoupon $coupon = null): array
    {
        $pricing = SubscriptionPricingService::instance();
        $quote = $pricing->quote($plan, $billingPeriod);

        if ($coupon) {
            $coupon = SubscriptionCouponService::instance()->findUsable($coupon->code, $client);
            $quote = SubscriptionCouponService::instance()->applyToQuote($quote, $coupon);
        }

        return $quote;
    }

    /**
     * @param  array<string, string>  $billing
     * @return array<string, mixed>
     */
    public function checkoutPayload(string $amount, string $merchantTransactionId, array $billing): array
    {
        $payload = [
            'entityId' => $this->config->entityId(),
            'amount' => $amount,
            'currency' => $this->config->currency(),
            'paymentType' => $this->config->paymentType(),
            'merchantTransactionId' => $merchantTransactionId,
            'customer.email' => $billing['email'],
            'customer.givenName' => $billing['given_name'],
            'customer.surname' => $billing['surname'],
            'billing.street1' => $billing['street1'],
            'billing.city' => $billing['city'],
            'billing.state' => $billing['state'],
            'billing.country' => strtoupper($billing['country']),
            'billing.postcode' => $billing['postcode'],
            'integrity' => 'true',
        ];

        if ($this->config->isTest()) {
            $payload['testMode'] = 'EXTERNAL';
            $payload['customParameters[3DS2_enrolled]'] = 'true';
            $payload['customParameters[3DS2_flow]'] = 'challenge';
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    public static function placeholderBilling(): array
    {
        return [
            'given_name' => 'MyBee',
            'surname' => 'Customer',
            'email' => 'checkout.'.strtolower(\Illuminate\Support\Str::random(10)).'@mybeesystem.com',
            'street1' => 'King Fahd Road',
            'city' => 'Riyadh',
            'state' => 'Riyadh',
            'country' => 'SA',
            'postcode' => '11564',
        ];
    }

    /**
     * @param  array<string, string>  $billing
     */
    public function persistClientBilling(Client $client, array $billing): void
    {
        $client->forceFill([
            'billing_given_name' => $billing['given_name'],
            'billing_surname' => $billing['surname'],
            'billing_email' => $billing['email'],
            'billing_street1' => $billing['street1'],
            'billing_city' => $billing['city'],
            'billing_state' => $billing['state'],
            'billing_country' => strtoupper($billing['country']),
            'billing_postcode' => $billing['postcode'],
        ])->save();
    }

    /**
     * @param  array<string, string>  $billing
     */
    public function assertBilling(array $billing): void
    {
        $required = ['given_name', 'surname', 'email', 'street1', 'city', 'state', 'country', 'postcode'];

        foreach ($required as $field) {
            if (! filled($billing[$field] ?? null)) {
                throw new InvalidArgumentException($this->trans('fields.hyperpay_billing_incomplete', 'Complete all billing fields.'));
            }
        }

        if (! preg_match('/^[A-Z]{2}$/', strtoupper((string) $billing['country']))) {
            throw new InvalidArgumentException($this->trans('fields.hyperpay_billing_country_invalid', 'Country must be a 2-letter ISO code.'));
        }

        $country = strtoupper((string) $billing['country']);
        $postcode = trim((string) $billing['postcode']);

        if ($country === 'SA' && ! preg_match('/^\d{5}$/', $postcode)) {
            throw new InvalidArgumentException($this->trans('fields.hyperpay_billing_postcode_invalid', 'Saudi postal code must be 5 digits.'));
        }

        if ($country !== 'SA' && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{2,11}$/', $postcode)) {
            throw new InvalidArgumentException($this->trans('fields.hyperpay_billing_postcode_invalid', 'Postal code format is invalid.'));
        }

        $blocked = array_map('mb_strtolower', [
            'الشارع',
            'المدينة',
            'المنطقة',
            'الرمز البريدي',
            'street',
            'city',
            'state',
            'postal code',
            'postcode',
        ]);

        try {
            $blocked = array_merge($blocked, array_map('mb_strtolower', [
                (string) __('fields.hyperpay_street'),
                (string) __('fields.city'),
                (string) __('fields.hyperpay_state'),
                (string) __('fields.hyperpay_postcode'),
            ]));
        } catch (\Throwable) {
            // Translator is not booted in isolated unit tests.
        }

        foreach (['street1', 'city', 'state'] as $field) {
            $value = mb_strtolower(trim((string) $billing[$field]));

            if (in_array($value, $blocked, true)) {
                throw new InvalidArgumentException($this->trans('fields.hyperpay_billing_address_invalid', 'Enter a real billing address.'));
            }
        }
    }

    protected function trans(string $key, string $fallback): string
    {
        try {
            if (function_exists('app') && app()->bound('translator')) {
                return (string) __($key);
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    /**
     * @return array<string, string>
     */
    public function billingFromClient(Client $client): array
    {
        $client->loadMissing('user');
        $user = $client->user;
        $parts = preg_split('/\s+/', trim((string) $client->name)) ?: [];

        $given = (string) ($client->billing_given_name ?: ($user?->first_name ?? ($parts[0] ?? '')));
        $surname = (string) ($client->billing_surname ?: ($user?->second_name ?: ($parts[1] ?? $given)));

        return [
            'given_name' => $given,
            'surname' => $surname !== '' ? $surname : $given,
            'email' => (string) ($client->billing_email ?: $client->email ?: $user?->email ?: ''),
            'street1' => (string) ($client->billing_street1 ?: $client->address ?: ''),
            'city' => (string) ($client->billing_city ?: ''),
            'state' => (string) ($client->billing_state ?: $client->billing_city ?: ''),
            'country' => strtoupper((string) ($client->billing_country ?: 'SA')),
            'postcode' => (string) ($client->billing_postcode ?: ''),
        ];
    }

    public function widgetScriptUrl(HyperPayPayment $payment): string
    {
        return $this->config->baseUrl().'/v1/paymentWidgets.js?checkoutId='.urlencode((string) $payment->checkout_id);
    }

    protected function uniqueMerchantTransactionId(Client $client): string
    {
        do {
            $id = 'MB-'.$client->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
        } while (HyperPayPayment::query()->where('merchant_transaction_id', $id)->exists());

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function safePayload(array $payload): array
    {
        unset($payload['entityId']);

        return $payload;
    }

    protected function usableCoupon(HyperPayPayment $payment): ?PlatformCoupon
    {
        if (! filled($payment->coupon_code)) {
            return null;
        }

        try {
            return SubscriptionCouponService::instance()->findUsable($payment->coupon_code, $payment->client);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
