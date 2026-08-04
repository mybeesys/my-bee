<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Traits\HttpResponses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantStoreEnabled
{
    use HttpResponses;

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('Store-Slug');

        if (! $slug) {
            return $next($request);
        }

        $tenant = Tenant::query()->with('client.subscription.plan')->where('slug', $slug)->first();

        if (! $tenant?->client?->subscription?->plan?->enable_store) {
            $message = app()->getLocale() === 'ar'
                ? 'المتجر الإلكتروني غير متاح في باقتك الحالية. قم بترقية اشتراكك للوصول إلى المتجر.'
                : 'The online store is not available on your current plan. Upgrade your subscription to enable the store.';

            return $this->message($message)->statusCode(403)->respond();
        }

        return $next($request);
    }
}
