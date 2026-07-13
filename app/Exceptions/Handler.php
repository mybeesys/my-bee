<?php

namespace App\Exceptions;

use App\Traits\HttpResponses;
use Filament\Facades\Filament;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    use HttpResponses;
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($request->wantsJson()) {
            parent::report($e);
            if ($e->getCode() !== 400) {
                return $this->error($e)->respond();
            }
        }

        if ($e instanceof TokenMismatchException && $this->shouldRedirectExpiredSessionToLogin($request)) {
            return redirect()->guest($this->resolveLoginUrl());
        }

        return parent::render($request, $e);
    }

    protected function shouldRedirectExpiredSessionToLogin($request): bool
    {
        if ($request->is('api/*')) {
            return false;
        }

        $routeName = $request->route()?->getName() ?? '';

        return str_starts_with($routeName, 'filament.');
    }

    protected function resolveLoginUrl(): string
    {
        try {
            return Filament::getLoginUrl() ?? url('/login');
        } catch (Throwable) {
            return url('/login');
        }
    }
}
