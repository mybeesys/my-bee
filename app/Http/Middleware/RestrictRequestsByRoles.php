<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Traits\HttpResponses;
use Closure;
use Illuminate\Http\Request;

class RestrictRequestsByRoles
{
    use HttpResponses;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();

        if($user)
        {
            $allowed = $user->hasAnyRole(
                [
                    User::ROLE_CLIENT,
                ]
            );
            if(!$allowed)
            {
                return $this->message('Unauthorized role')->statusCode(401)->respond();
            }
        }

        return $next($request);
    }
}
