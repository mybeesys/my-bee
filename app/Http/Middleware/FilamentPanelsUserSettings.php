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
        $defaultFont = Filament::getCurrentPanel()?->getId() === 'tenant' ? 'Cairo' : 'Poppins';

        Filament::getCurrentPanel()
            ->font(user_setting('font', $defaultFont))
            ->databaseNotifications((bool)user_setting('enable_notifications', true))
            ->topNavigation((bool)user_setting('enable_top_navigation', false));

        //store the primary color to cookies using:
        //Cookie::queue(Cookie::make($key, $value))

        if(auth()->user())
        {
            $primaryColor = user_setting('primary_color', default: "#e8d41b");
        }else{
            $primaryColor = $request->cookies->get('primary_color', default: "#e8d41b");
        }

        FilamentColor::register([
            'primary' => $primaryColor,
        ]);

        return $next($request);
    }
}
