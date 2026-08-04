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
        if (str_contains(trim($request->path(), '/'), 'subscription')) {
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

    protected function isExemptRequest(Request $request): bool
    {
        return false;
    }
}
