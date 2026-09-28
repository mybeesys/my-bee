<?php

namespace App\Http\Controllers;

use App\Filament\Tenant\Pages\ChooseRegistrationPlan;
use App\Filament\Tenant\Pages\CompleteRegistrationPayment;
use App\Filament\Tenant\Pages\Subscription as SubscriptionPage;
use App\Models\HyperPayPayment;
use App\Models\User;
use App\Services\HyperPay\HyperPayCheckoutService;
use App\Services\HyperPay\HyperPayConfig;
use App\Services\SubscriptionCouponService;
use App\Services\SubscriptionPricingService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class HyperPayCheckoutController extends Controller
{
    public function show(Request $request, string $uid): View|RedirectResponse
    {
        $payment = $this->ownedPayment($uid);

        if ($payment->isPaid()) {
            return $this->finish($request, $payment, 'hyperpay_paid');
        }

        if ($payment->status === HyperPayPayment::STATUS_FAILED) {
            return $this->finish($request, $payment, 'hyperpay_failed', [
                'reason' => $payment->result_description,
            ]);
        }

        $config = HyperPayConfig::instance();
        $service = HyperPayCheckoutService::instance();
        $billing = is_array($payment->billing) ? $payment->billing : [];

        return view('hyperpay.checkout', [
            'payment' => $payment,
            'config' => $config,
            'widgetUrl' => $service->widgetScriptUrl($payment),
            'resultUrl' => $this->resultUrl($request, $payment),
            'cancelUrl' => $this->cancelUrl($request, $payment),
            'brands' => $config->brands(),
            'isRegistration' => $payment->source === HyperPayPayment::SOURCE_REGISTRATION,
            'quote' => $this->checkoutQuote($payment),
            'cardholder' => trim(($billing['given_name'] ?? '').' '.($billing['surname'] ?? '')),
        ]);
    }

    public function result(Request $request, string $uid): RedirectResponse
    {
        $payment = $this->ownedPayment($uid);
        $resourcePath = (string) $request->query('resourcePath', $request->input('resourcePath', ''));

        if ($resourcePath === '') {
            fns()->sendDanger(__('fields.hyperpay_missing_resource'));

            return redirect()->to($this->cancelUrl($request, $payment));
        }

        try {
            $payment = HyperPayCheckoutService::instance()->complete($payment, $resourcePath);
        } catch (RuntimeException $exception) {
            fns()->sendDanger(__('fields.hyperpay_payment_failed_title'), $exception->getMessage());

            return redirect()->to($this->cancelUrl($request, $payment));
        }

        if ($payment->isPaid()) {
            session()->flash('subscription_updated', true);
            fns()->sendSuccess(__('fields.hyperpay_payment_success_title'), __('fields.hyperpay_payment_success_body'));

            return redirect()->to($this->nextUrl($request, $payment));
        }

        if ($payment->status === HyperPayPayment::STATUS_PENDING_GATEWAY) {
            fns()->sendWarning(__('fields.hyperpay_payment_pending_title'), __('fields.hyperpay_payment_pending_body'));

            return redirect()->to($this->cancelUrl($request, $payment));
        }

        fns()->sendDanger(
            __('fields.hyperpay_payment_failed_title'),
            $payment->result_description ?: __('fields.hyperpay_payment_failed_body'),
        );

        return redirect()->to($this->cancelUrl($request, $payment));
    }

    protected function ownedPayment(string $uid): HyperPayPayment
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasRole(User::ROLE_CLIENT), 403);

        $payment = HyperPayPayment::query()
            ->with(['plan', 'client.user'])
            ->where('uid', $uid)
            ->firstOrFail();

        $client = $user->client;
        abort_unless($client && (int) $payment->client_id === (int) $client->id, 403);

        return $payment;
    }

    protected function resultUrl(Request $request, HyperPayPayment $payment): string
    {
        return route('filament.tenant.hyperpay.result', [
            'uid' => $payment->uid,
        ]);
    }

    protected function subscriptionUrl(Request $request, ?HyperPayPayment $payment = null): string
    {
        $payment?->loadMissing(['tenant', 'client.tenants']);

        $tenant = $payment?->tenant
            ?? Filament::getTenant()
            ?? $request->route('tenant')
            ?? $payment?->client?->tenants?->first();

        try {
            return SubscriptionPage::getUrl(tenant: $tenant);
        } catch (\Throwable) {
            $slug = is_object($tenant) ? ($tenant->slug ?? null) : null;

            if (filled($slug)) {
                return url('/'.$slug.'/subscription');
            }

            return url('/');
        }
    }

    protected function cancelUrl(Request $request, HyperPayPayment $payment): string
    {
        if ($payment->source === HyperPayPayment::SOURCE_REGISTRATION) {
            try {
                return ChooseRegistrationPlan::getUrl();
            } catch (\Throwable) {
                //
            }
        }

        return $this->subscriptionUrl($request, $payment);
    }

    protected function nextUrl(Request $request, HyperPayPayment $payment): string
    {
        if ($payment->source === HyperPayPayment::SOURCE_REGISTRATION && $payment->isPaid()) {
            remember_registration_paid_client((int) $payment->client_id);

            if (Filament::auth()->check()) {
                Filament::auth()->logout();
            }

            $request->session()->regenerate();
            remember_registration_paid_client((int) $payment->client_id);

            try {
                return filament()->getTenantRegistrationUrl();
            } catch (\Throwable) {
                //
            }
        }

        return $this->subscriptionUrl($request, $payment);
    }

    protected function finish(Request $request, HyperPayPayment $payment, string $flash, array $data = []): RedirectResponse
    {
        if ($flash === 'hyperpay_paid') {
            session()->flash('subscription_updated', true);
            fns()->sendSuccess(__('fields.hyperpay_payment_success_title'), __('fields.hyperpay_payment_success_body'));

            return redirect()->to($this->nextUrl($request, $payment));
        }

        fns()->sendDanger(
            __('fields.hyperpay_payment_failed_title'),
            $data['reason'] ?? __('fields.hyperpay_payment_failed_body'),
        );

        return redirect()->to($this->cancelUrl($request, $payment));
    }

    /**
     * @return array{subtotal_ex_tax: float, tax_amount: float, tax_percent: float, total_inc_tax: float, currency: string}
     */
    protected function checkoutQuote(HyperPayPayment $payment): array
    {
        $plan = $payment->plan;
        $currency = (string) ($payment->currency ?: 'SAR');
        $fallback = [
            'subtotal_ex_tax' => (float) $payment->amount,
            'tax_amount' => 0.0,
            'tax_percent' => 0.0,
            'total_inc_tax' => (float) $payment->amount,
            'currency' => $currency,
        ];

        if (! $plan) {
            return $fallback;
        }

        $quote = SubscriptionPricingService::instance()->quote($plan, (string) ($payment->billing_period ?: 'monthly'));

        if (filled($payment->coupon_code)) {
            try {
                $coupon = $payment->platformCoupon
                    ?? SubscriptionCouponService::instance()->findUsable($payment->coupon_code, $payment->client);
                if ($coupon) {
                    $quote = SubscriptionCouponService::instance()->applyToQuote($quote, $coupon);
                }
            } catch (\Throwable) {
                // Keep the uncouponed quote; charged amount still comes from the payment row.
            }
        }

        $quote['currency'] = $currency;

        if (abs((float) ($quote['total_inc_tax'] ?? 0) - (float) $payment->amount) > 0.05) {
            $quote['total_inc_tax'] = (float) $payment->amount;
        }

        return $quote;
    }
}
