<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessTenant
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $user = auth('sanctum')->user();

        $tenant = Tenant::find($request->header('Tenant-Id'));

        if (!$tenant)
            abort(400, "Tenant not found.");

        $canAccess = $user->canAccessTenant($tenant);

        if (!$canAccess)
            abort(403, "$tenant->name: permission denied.");

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        session()->put("ExecuteTimeOf-EnsureUserCanAccessTenant@$tenant->id", $executionTime);
        return $next($request);
    }
}
