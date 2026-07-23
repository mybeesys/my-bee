<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Models\User;
use App\Services\CouponService;
use App\Tables\Columns\DateColumn;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($locale = session('locale')) {
            $supported = config('system.supported_languages', ['ar', 'en']);

            if (in_array($locale, $supported, true)) {
                app()->setLocale($locale);
            }
        }

        Number::useLocale('en');

        FilamentAsset::register([
            Js::make('custom-script', __DIR__ . '/../../resources/js/custom.js'),
        ]);
        $this->configPublicPath();
        $this->configureApplicationUrl();
        $this->ensureUploadDirectoriesExist();
        $this->ensurePublicStorageSymlink();
        $this->configureFilesystemUrls();
        $this->configFilament();
        $this->configMacros();
        $this->configSpatieBackupPluginPermissions();

        Page::$reportValidationErrorUsing = function (ValidationException $exception) {
            fns()->sendWarning($exception->getMessage());
        };

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['ar', 'en'])
                ->labels([
                    'ar' => 'العربية',
                    'en' => 'English',
                ])
                ->visible(outsidePanels: true);
        });

        FilamentView::registerRenderHook(
             'panels::global-search.after',
            fn (): View => view('shop'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            function (): string {
                try {
                    $loginUrl = Filament::getLoginUrl();
                } catch (Throwable) {
                    $loginUrl = url('/login');
                }

                return $loginUrl
                    ? '<meta name="app-login-url" content="' . e($loginUrl) . '">'
                    : '';
            },
        );

        Order::observe(OrderObserver::class);

        Livewire::component(
            \Filament\Livewire\DatabaseNotifications::class,
            \App\Livewire\TenantDatabaseNotifications::class,
        );
    }

    protected function configPublicPath(): void
    {
        $publicPath = env('PUBLIC_PATH');

        if (blank($publicPath) && $this->app->environment('production')) {
            $host = str(config('app.url'))->remove(['https://', 'http://', 'www.'])->value();

            $candidates = array_filter([
                base_path("../{$host}"),
                base_path(),
                base_path('public'),
                base_path('../public_html'),
            ]);

            foreach ($candidates as $candidate) {
                $resolved = realpath($candidate);

                if (! $resolved || ! is_dir($resolved)) {
                    continue;
                }

                if (is_file("{$resolved}/index.php")) {
                    $publicPath = $resolved;

                    break;
                }
            }

            if (blank($publicPath)) {
                $legacy = realpath(base_path("../{$host}"));

                if ($legacy && is_dir($legacy)) {
                    $publicPath = $legacy;
                }
            }
        }

        if (blank($publicPath)) {
            return;
        }

        $resolved = realpath($publicPath) ?: $publicPath;

        if (is_dir($resolved)) {
            $this->app->usePublicPath($resolved);
        }
    }

    protected function configureApplicationUrl(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();

        if (! $request || ! $request->getHttpHost()) {
            return;
        }

        $rootUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        URL::forceRootUrl($rootUrl);

        if ($request->isSecure()) {
            URL::forceScheme('https');
        }
    }

    protected function ensureUploadDirectoriesExist(): void
    {
        foreach ([
            storage_path('app/livewire-tmp'),
            storage_path('app/public'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }
    }

    protected function ensurePublicStorageSymlink(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');

        if (is_link($linkPath) && realpath($linkPath)) {
            return;
        }

        if (file_exists($linkPath) && ! is_link($linkPath)) {
            return;
        }

        if (! is_dir($targetPath)) {
            return;
        }

        try {
            app('files')->link($targetPath, $linkPath);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    protected function publicStorageLinkIsHealthy(): bool
    {
        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');

        return is_link($linkPath)
            && realpath($linkPath)
            && realpath($linkPath) === realpath($targetPath);
    }

    protected function shouldServeMediaThroughLaravel(): bool
    {
        if (filter_var(env('SERVE_MEDIA_THROUGH_LARAVEL', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        if (! $this->app->environment('production')) {
            return false;
        }

        return ! $this->publicStorageLinkIsHealthy();
    }

    protected function configureFilesystemUrls(): void
    {
        $rootUrl = rtrim((string) config('app.url'), '/');

        if (! $this->app->runningInConsole()) {
            $request = request();

            if ($request && $request->getHttpHost()) {
                $rootUrl = rtrim($request->getSchemeAndHttpHost(), '/');
            }
        }

        $assetUrl = rtrim((string) (env('ASSET_URL') ?: env('MEDIA_URL') ?: ''), '/');

        if ($assetUrl !== '') {
            $rootUrl = $assetUrl;
        }

        $publicBase = $this->shouldServeMediaThroughLaravel() ? 'media' : 'storage';

        config([
            'filesystems.disks.public.url' => "{$rootUrl}/{$publicBase}",
            'filesystems.disks.cdn.url' => "{$rootUrl}/cdn",
        ]);
    }

    protected function configFilament()
    {
        Table::configureUsing(function (Table $table) {
            $table
                ->emptyStateHeading(__('fields.table_empty_state'))
                ->deferLoading()
                ->striped()
                ->paginationPageOptions([10, 25, 50, 100]);
        });

        TextColumn::configureUsing(function (TextColumn $textColumn) {
            if (str($textColumn->getName())->contains(['date', 'created_at', 'updated_at', '_at', 'dob'], true)) {
                $textColumn->dateTime(user_setting('date_column_format'));
            }
        });

        DatePicker::configureUsing(function (DatePicker $datePicker) {
            $datePicker->displayFormat(user_setting('date_picker_format', 'd/m/Y'));
        });


    }

    protected function configMacros()
    {
        TextInput::macro('money', function () {
            $this->numeric()->inputMode('decimal')->maxValue(100000000000);
            return $this;
        });

        TextInput::macro('mainCurrencySuffix', function () {
            $this->suffix(setting('main_currency', "SAR"));
//            $this->formatStateUsing(fn($state) => is_number($state) ? number_format($state, currency_decimals(), '.', '') : $state);
            return $this;
        });

        TextInput::macro('currency', function ($formatState = true, $showCurrencySuffix = true, $decimalSeparator = ".", $thousandsSeparator = "") {
            if ($formatState)
                $this->formatStateUsing(fn($state) => is_number($state) ? str(number_format($state, currency_decimals(), $decimalSeparator, $thousandsSeparator))->remove(".00")->value() : $state);

            if ($showCurrencySuffix)
                $this->suffix(setting('main_currency', "SAR"));

            return $this;
        });

        TextInput::macro('phone', function () {
            $this->numeric()->tel();
            return $this;
        });

        TextColumn::macro('moneyTooltip', function () {
            $this->tooltip(function () {
                return numbers_to_words($this->getState());
            });
            return $this;
        });

        TextInput::macro('translateFrontValidationGt', function () {
            $this->extraInputAttributes(function () {
                $min = $this->getMinValue() ?? 0;
                $msg = __('validation.gt.numeric', ['attribute' => $this->getLabel(), 'value' => $min]);
                return [
                    'oninvalid' => "this.setCustomValidity('$msg')",
                    'oninput' => "this.setCustomValidity('')",
                ];
            }, merge: true);
            return $this;
        });

    }

    protected function configSpatieBackupPluginPermissions()
    {
        Gate::define('download-backup', function (User $user) {
            return in_array($user->email, explode(',', env('BACKUP_MANAGERS')));
        });

        Gate::define('delete-backup', function (User $user) {
            return in_array($user->email, explode(',', env('BACKUP_MANAGERS')));
        });
    }
}
