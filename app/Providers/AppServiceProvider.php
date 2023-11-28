<?php

namespace App\Providers;

use App\Tables\Columns\DateColumn;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

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
        if (app()->isProduction()) {
            FilamentAsset::register([
                Js::make('custom-script', __DIR__ . '/../../resources/js/custom.js'),
            ]);
        }
        $this->configPublicPath();
        $this->configFilament();
        $this->configMacros();

        Page::$reportValidationErrorUsing = function (ValidationException $exception) {
            fns()->sendWarning($exception->getMessage());
        };
    }

    protected function configPublicPath()
    {
        if ($this->app->environment('production')) {
            $app_url = str(config('app.url'))->remove(['https://', 'http://'])->value();
            $this->app->usePublicPath(realpath(base_path() . "/../$app_url"));
        }
    }

    protected function configFilament()
    {
        Table::configureUsing(function (Table $table) {
            $table->deferLoading()->striped()->paginationPageOptions([10, 25, 50, 100]);
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
    }
}
