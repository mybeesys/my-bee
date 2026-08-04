<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientSubscriptionActiveApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('sanctum')->user();

        if (! $user?->hasRole(User::ROLE_CLIENT)) {
            return $next($request);
        }

        $client = $user->client;

        if (! $client || ! subscription_account_restricted($client)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => __('fields.subscription_trial_expired_body'),
            'code' => 'subscription_trial_expired',
            'redirectTo' => 'subscription',
            'title' => __('fields.subscription_trial_expired_title'),
        ], 403);
    }
}
