<?php

namespace App\Http\Middleware;

use App\Traits\HttpResponses;
use Closure;
use Illuminate\Http\Request;

class RestrictRequestsByCors
{
    use HttpResponses;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $source = $request->header('Source');

        if($source !== "TYKREWQTH&^%EWQRFEGRTEHRUTIYHRSDFAF!ETHRBDGFEDAWHYJDTRGSEDFRTYUJTRGFD")
            return $this->message('Unauthorized source')->statusCode(401)->respond();
        return $next($request);
    }
}
