<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Traits\HttpResponses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSlugInHeader
{
    use HttpResponses;

    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('Store-Slug');
        if (!$slug or blank($slug))
            return $this->message('Store-Slug not provided.')->statusCode(400)->respond();

        if (Tenant::firstWhere('slug', $slug) == null){
            $msg = app()->getLocale() == "ar" ? "المتجر غير موجود" : "Invalid Store url!";
            return $this->message($msg)->statusCode(404)->respond();
        }

        return $next($request);
    }
}
