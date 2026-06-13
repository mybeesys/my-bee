<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsurePhoneIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && $request->user()->isUserApp() && !$request->user()->hasVerifiedPhone()) {
            return response()->json(
                [
                    'message' => 'Please verify your phone number',
                    'statusCode' => 403
                ], '403');
        }

        return $next($request);
    }
}
