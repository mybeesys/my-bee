<?php

namespace App\Filament\Tenant\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    public static function getNavigationLabel(): string
    {
        return __('fields.dashboard');
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }
//    public function filtersForm(Form $form): Form
//    {
//        return $form
//            ->schema([
//                Section::make()
//                    ->schema([
//                        Select::make('businessCustomersOnly')
//                            ->boolean(),
//                        DatePicker::make('startDate')
//                            ->maxDate(fn (Get $get) => $get('endDate') ?: now()),
//                        DatePicker::make('endDate')
//                            ->minDate(fn (Get $get) => $get('startDate') ?: now())
//                            ->maxDate(now()),
//                    ])
//                    ->columns(3),
//            ]);
//    }
}
