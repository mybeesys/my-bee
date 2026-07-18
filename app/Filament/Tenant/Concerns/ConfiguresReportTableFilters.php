<?php

namespace App\Filament\Tenant\Concerns;

use Filament\Forms;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

trait ConfiguresReportTableFilters
{
    protected static function reportDateFromField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('created_from')
            ->label(__('fields.created_from'))
            ->native(false)
            ->default(now()->startOfYear())
            ->maxDate(fn (Forms\Get $get) => $get('created_until') ?? now())
            ->live();
    }

    protected static function reportDateUntilField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('created_until')
            ->label(__('fields.created_until'))
            ->native(false)
            ->default(now())
            ->minDate(fn (Forms\Get $get) => $get('created_from'))
            ->maxDate(now())
            ->live();
    }

    /** @return array<int, Forms\Components\DatePicker> */
    protected static function reportDateRangeFormFields(): array
    {
        return [
            static::reportDateFromField(),
            static::reportDateUntilField(),
        ];
    }

    protected static function applyReportDateRangeQuery(Builder $query, array $data, string $column = 'created_at'): Builder
    {
        return $query
            ->when(
                $data['created_from'] ?? null,
                fn (Builder $query) => $query->whereDate($column, '>=', $data['created_from'])
            )
            ->when(
                $data['created_until'] ?? null,
                fn (Builder $query) => $query->whereDate($column, '<=', $data['created_until'])
            );
    }

    protected static function reportDateRangeIndicator(array $data): ?string
    {
        if (($data['created_from'] ?? null) || ($data['created_until'] ?? null)) {
            return __('fields.date');
        }

        return null;
    }

    protected static function configureReportTableFilters(Table $table): Table
    {
        return $table
            ->deferFilters(false)
            ->filtersFormColumns(4);
    }
}
