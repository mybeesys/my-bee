<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectTenantRootToLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isTenantPanelRoot($request)) {
            return $next($request);
        }

        if (Filament::auth()->check()) {
            return $next($request);
        }

        return redirect()->guest(tenant_panel_login_url());
    }

    protected function isTenantPanelRoot(Request $request): bool
    {
        return $request->is('/') || $request->path() === '';
    }
}
