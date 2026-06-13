<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            // Route::prefix('api')
            // ->middleware('api')
            // ->as('api.')
            // ->namespace($this->app->getNamespace().'Http\Controllers\API')
            // ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('once-per-day', function (Request $request) {
            return Limit::perDay(1)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('twice-per-day', function (Request $request) {
            return Limit::perDay(2)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('three-per-day', function (Request $request) {
            return Limit::perDay(3)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('five-per-day', function (Request $request) {
            return Limit::perDay(5)->by(optional($request->user())->id ?: $request->ip());
        });

    }
}
