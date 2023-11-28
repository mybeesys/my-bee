<?php

/**
 * This middleware extends the Tenant for Laravel one to catch
 * tenant ID and scope future requests in the tenant panel.
 */

//declare(strict_types=1);

namespace App\Http\Middleware;

//use App\Context\Tenant\Models\Tenant;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain as BaseMiddleware;
use Closure;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;

class InitializeTenancyBySubdomain extends BaseMiddleware implements AuthenticatesSessions
{
    public function __construct(
        protected             $tenancy,
        protected             $resolver,
        protected AuthFactory $auth
    ) {
        parent::__construct($tenancy, $resolver);
    }

    public function handle($request, Closure $next): mixed
    {
        $route = $request->route();
        $record = $route->parameter('tenant');

        if ($record && $tenant = \App\Models\Tenant::find($record)) {
            if ($domain = $tenant->domain) {
                $this->initializeTenancy($request, $next, $domain->domain);
            }
        }

        return $next($request);
    }

    protected function guard(): AuthFactory|Guard
    {
        return $this->auth;
    }
}
