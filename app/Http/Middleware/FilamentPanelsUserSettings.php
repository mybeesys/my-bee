<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilamentPanelsUserSettings
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Filament::getCurrentPanel()
            ->font(user_setting('font', "Poppins"))
            ->databaseNotifications((bool)user_setting('enable_notifications', true))
            ->topNavigation((bool)user_setting('enable_top_navigation', false));

        FilamentColor::register([
            'primary' => user_setting('primary_color', "#e8d41b"),
        ]);
        return $next($request);
    }
}
