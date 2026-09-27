<?php

namespace App\Http\Middleware;

use App\Filament\Tenant\Pages\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user?->hasRole(User::ROLE_CLIENT)) {
            return $next($request);
        }

        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        $client = $user->client;

        if ($client?->hasPendingPaidRegistration() && ! $this->canAccessWhilePendingPayment($request)) {
            return redirect()->to(\App\Filament\Tenant\Pages\ChooseRegistrationPlan::getUrl());
        }

        if (! subscription_account_restricted()) {
            return $next($request);
        }

        if ($this->canAccessWhileRestricted($request)) {
            return $next($request);
        }

        fns()->sendWarning(
            __('fields.subscription_trial_expired_title'),
            __('fields.subscription_trial_expired_body'),
        );

        return redirect()->to(Subscription::getUrl(['tenant' => $tenant]));
    }

    protected function canAccessWhileRestricted(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (str_contains($path, 'subscription')
            || str_contains($path, 'choose-plan')
            || str_contains($path, 'complete-payment')
            || str_contains($path, 'billing/card')) {
            return true;
        }

        if ($request->routeIs('filament.tenant.auth.logout')) {
            return true;
        }

        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '' && str_contains($referer, 'subscription')) {
            return true;
        }

        return false;
    }

    protected function canAccessWhilePendingPayment(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return str_contains($path, 'join')
            || str_contains($path, 'new-activity')
            || str_contains($path, 'complete-payment')
            || str_contains($path, 'subscription/pay')
            || $request->routeIs('filament.tenant.auth.logout');
    }

    protected function isExemptRequest(Request $request): bool
    {
        return false;
    }
}
