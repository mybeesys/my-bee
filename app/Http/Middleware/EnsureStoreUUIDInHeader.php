<?php

namespace App\Http\Middleware;

use App\Traits\HttpResponses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreUUIDInHeader
{
    use HttpResponses;

    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Store-UUID');
        if (!$header or blank($header))
            return $this->message('Store-UUID not provided.')->statusCode(400)->respond();
        return $next($request);
    }
}
