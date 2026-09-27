<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Models\Client;
use App\Models\HyperPayPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Rules\UniqueClientAttributeRule;
use App\Services\HyperPay\HyperPayCheckoutService;
use App\Services\HyperPay\HyperPayConfig;
use App\Services\RoleService;
use App\Services\SubscriptionPricingService;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CompleteRegistrationPayment extends Page
{
    protected static ?string $slug = 'complete-payment';

    protected static string $layout = 'filament.tenant.layout.login';

    protected static string $view = 'filament.tenant.pages.complete-registration-payment';

    protected static bool $shouldRegisterNavigation = false;

    protected static string | array $withoutRouteMiddleware = [
        Authenticate::class,
    ];

    public string $billingGivenName = '';

    public string $billingSurname = '';

    public string $billingEmail = '';

    public string $billingStreet1 = '';

    public string $billingCity = '';

    public string $billingState = '';

    public string $billingCountry = 'SA';

    public string $billingPostcode = '';

    public static function registerRoutes(Panel $panel): void
    {
        // Registered at the panel root via TenantPanelProvider::routes().
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        unset($parameters['tenant']);

        $panel = $panel ? Filament::getPanel($panel) : (Filament::getCurrentPanel() ?? Filament::getPanel('tenant'));

        return $panel->route(static::getRelativeRouteName(), $parameters, $isAbsolute);
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->redirect(\App\Filament\Tenant\Pages\ChooseRegistrationPlan::getUrl(), navigate: false);
    }

    public function getTitle(): string|Htmlable
    {
        return __('fields.registration_step_pay');
    }

    public function getHeading(): string|Htmlable
    {
        return __('fields.registration_pay_title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('fields.registration_pay_subheading');
    }

    public function getPendingPlan(): ?Plan
    {
        $client = Filament::auth()->check() ? get_client() : null;

        return $client?->pendingPlan ?? registration_selected_plan();
    }

    /** @return array<string, mixed>|null */
    public function getQuote(): ?array
    {
        $client = Filament::auth()->check() ? get_client() : null;
        $plan = $this->getPendingPlan();

        if (! $plan) {
            return null;
        }

        $period = $client?->pending_billing_period
            ?? registration_plan_selection()['billing_period']
            ?? SubscriptionPricingService::BILLING_MONTHLY;

        return SubscriptionPricingService::instance()->quote($plan, (string) $period);
    }

    public function getBillingPeriod(): ?string
    {
        $client = Filament::auth()->check() ? get_client() : null;

        return $client?->pending_billing_period
            ?? registration_plan_selection()['billing_period']
            ?? null;
    }

    public function getHyperPayConfiguredProperty(): bool
    {
        return HyperPayConfig::instance()->isConfigured();
    }

    public function getPaymentBrandsProperty(): array
    {
        return preg_split('/\s+/', HyperPayConfig::instance()->brands()) ?: ['MADA', 'VISA', 'MASTER'];
    }

    /** @return array<string, string> */
    public function getBillingCountryOptionsProperty(): array
    {
        return [
            'SA' => __('fields.country_sa'),
            'AE' => __('fields.country_ae'),
            'KW' => __('fields.country_kw'),
            'BH' => __('fields.country_bh'),
            'OM' => __('fields.country_om'),
            'QA' => __('fields.country_qa'),
            'JO' => __('fields.country_jo'),
            'EG' => __('fields.country_eg'),
        ];
    }

    public function pay(): void
    {
        $client = $this->resolveClientForCheckout();
        $plan = $client?->pendingPlan ?? $this->getPendingPlan();

        if (! $client || ! $plan) {
            fns()->sendWarning(__('fields.hyperpay_free_plan_no_checkout'));

            return;
        }

        $billing = [
            'given_name' => trim($this->billingGivenName),
            'surname' => trim($this->billingSurname),
            'email' => trim($this->billingEmail),
            'street1' => trim($this->billingStreet1),
            'city' => trim($this->billingCity),
            'state' => trim($this->billingState) ?: trim($this->billingCity),
            'country' => strtoupper(trim($this->billingCountry) ?: 'SA'),
            'postcode' => trim($this->billingPostcode),
        ];

        try {
            $payment = HyperPayCheckoutService::instance()->start(
                $client,
                $plan,
                (string) ($client->pending_billing_period ?: $this->getBillingPeriod()),
                $billing,
                null,
                Filament::getTenant(),
                HyperPayPayment::SOURCE_REGISTRATION,
            );
        } catch (InvalidArgumentException $exception) {
            fns()->sendWarning($exception->getMessage());

            return;
        } catch (RuntimeException $exception) {
            fns()->sendDanger(__('fields.hyperpay_checkout_failed'), $exception->getMessage());

            return;
        }

        $this->redirect(route('filament.tenant.hyperpay.checkout', [
            'uid' => $payment->uid,
        ]), navigate: false);
    }

    protected function resolveClientForCheckout(): ?Client
    {
        if (Filament::auth()->check()) {
            $client = get_client();
            $this->ensurePendingPlan($client);

            return $client?->fresh(['pendingPlan']);
        }

        $this->validate([
            'billingGivenName' => ['required', 'string', 'max:80'],
            'billingSurname' => ['required', 'string', 'max:80'],
            'billingEmail' => ['required', 'email', new UniqueClientAttributeRule('email', 'email')],
        ]);

        try {
            $client = $this->registerGuestFromBilling();
        } catch (\Throwable $exception) {
            report($exception);
            fns()->sendDanger(__('fields.join_activity_failed_title'), __('fields.join_activity_failed_body'));

            return null;
        }

        $this->ensurePendingPlan($client);

        return $client->fresh(['pendingPlan']);
    }

    protected function registerGuestFromBilling(): Client
    {
        $fullName = trim($this->billingGivenName.' '.$this->billingSurname);
        $phone = $this->uniqueCheckoutPhone();

        $user = User::query()->create([
            'first_name' => $this->billingGivenName,
            'second_name' => $this->billingSurname,
            'phone' => $phone,
            'email' => $this->billingEmail,
            'password' => Hash::make(Str::password(20)),
        ]);

        (new RoleService())->assignRole($user, User::ROLE_CLIENT);

        $client = Client::query()->create([
            'name' => $fullName,
            'phone' => $phone,
            'email' => $this->billingEmail,
            'user_id' => $user->id,
        ]);

        Filament::auth()->login($user);
        session()->regenerate();

        return $client;
    }

    protected function uniqueCheckoutPhone(): string
    {
        do {
            $phone = '9665'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (
            User::query()->where('phone', $phone)->exists()
            || Client::query()->where('phone', $phone)->exists()
        );

        return $phone;
    }

    protected function ensurePendingPlan(?Client $client): void
    {
        if (! $client) {
            return;
        }

        if ($client->hasPendingPaidRegistration()) {
            return;
        }

        $selection = registration_plan_selection();
        $plan = $selection ? Plan::query()->find($selection['plan_id']) : null;

        if (! $plan || ! registration_selection_requires_payment()) {
            return;
        }

        $freePlan = Plan::query()
            ->where('code', Plan::CODE_FREE)
            ->where('active', true)
            ->first()
            ?? Plan::query()->where('active', true)->where('price', 0)->first();

        if ($freePlan && ! $client->subscription) {
            Subscription::subscribe($freePlan, $client, SubscriptionPricingService::BILLING_MONTHLY);
        }

        $client->forceFill([
            'pending_plan_id' => $plan->id,
            'pending_billing_period' => $selection['billing_period'],
        ])->save();
    }

    protected function fillBillingFromClient(?Client $client): void
    {
        if (! $client) {
            return;
        }

        $billing = HyperPayCheckoutService::instance()->billingFromClient($client);

        $this->billingGivenName = $billing['given_name'];
        $this->billingSurname = $billing['surname'];
        $this->billingEmail = $billing['email'];
        $this->billingStreet1 = $billing['street1'];
        $this->billingCity = $billing['city'];
        $this->billingState = $billing['state'] ?: $billing['city'];
        $this->billingCountry = $billing['country'] ?: 'SA';
        $this->billingPostcode = $billing['postcode'];
    }
}
