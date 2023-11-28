<?php

namespace App\Http\Middleware;

use App\Traits\HttpResponses;
use Closure;
use Illuminate\Http\Request;

class Localization
{
    use HttpResponses;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language');

        if (!$locale) {
            return $this->message('Language not provided.')->statusCode(400)->respond();
        }
        // check the languages defined is supported
        if (!in_array($locale, config('system.supported_languages', []))) {
            // respond with error
            return $this->message('Language not supported.')->statusCode(400)->respond();
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
