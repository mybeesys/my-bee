<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Exports\ProductMovementExporter;
use App\Filament\Tenant\Concerns\ConfiguresReportTableFilters;
use App\Filament\Tenant\Resources\ProductsMovementResource\Pages;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductMovementLine;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductMovementBalanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductsMovementResource extends Resource
{
    use ConfiguresReportTableFilters;

    protected static ?string $model = ProductMovementLine::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_reports');
    }

    public static function getLabel(): ?string
    {
        return __('fields.products_movement');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.products_movement');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureReportTableFilters($table)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('movement_type')
                    ->label(__('fields.type'))
                    ->badge()
                    ->color(function (ProductMovementLine $record) {
                        return match ($record->movement_type) {
                            'purchases' => 'danger',
                            'sales' => 'success',
                            'sales_return' => 'warning',
                            'purchase_return' => 'info',
                            default => 'gray',
                        };
                    })
                    ->getStateUsing(fn (ProductMovementLine $record) => match ($record->movement_type) {
                        'purchases' => __('fields.products_movements_type_purchases'),
                        'sales' => __('fields.products_movements_type_sales'),
                        'sales_return' => __('fields.products_movements_type_sales_return'),
                        'purchase_return' => __('fields.products_movements_type_purchase_return'),
                        default => $record->movement_type,
                    }),

                Tables\Columns\TextColumn::make('entity_name')
                    ->label(__('fields.entity'))
                    ->color(Color::Sky)
                    ->getStateUsing(fn (ProductMovementLine $record) => $record->entity_name ?? '-')
                    ->url(function (ProductMovementLine $record) {
                        if ($record->customer_id) {
                            return CustomerResource::getUrl('edit', ['record' => $record->customer_id]);
                        }

                        if ($record->supplier_id) {
                            return SupplierResource::getUrl('edit', ['record' => $record->supplier_id]);
                        }

                        return null;
                    }, true),

                Tables\Columns\TextColumn::make('invoice_no')
                    ->label(__('fields.invoice_no'))
                    ->color(Color::Sky)
                    ->url(function (ProductMovementLine $record) {
                        return $record->invoice_type === 'purchases'
                            ? PurchaseInvoiceResource::getUrl('edit', ['record' => $record->invoice_id])
                            : SalesInvoiceResource::getUrl('edit', ['record' => $record->invoice_id]);
                    }, true)
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label(__('fields.qty'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty_after_movement')
                    ->label(__('fields.qty_after_movement'))
                    ->getStateUsing(fn (ProductMovementLine $record): float => app(ProductMovementBalanceService::class)->balanceAfterMovement($record)),

                Tables\Columns\TextColumn::make('discount')
                    ->label(__('fields.discount'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(fn($record) => number_format($record->discount, currency_decimals(), '.', ','))
                    ->tooltip(fn($record) => numbers_to_words($record->discount))
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label(__('fields.total'))->formatStateUsing(function ($state) {
                        return main_currency_iso_code() . " " . format_amount($state);
                    })),

                Tables\Columns\TextColumn::make('tax')
                    ->label(__('fields.tax'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(fn($record) => number_format($record->tax, currency_decimals(), '.', ','))
                    ->tooltip(fn($record) => numbers_to_words($record->tax))
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label(__('fields.total'))->formatStateUsing(function ($state) {
                        return main_currency_iso_code() . " " . format_amount($state);
                    })),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('fields.unit_price'))
                    ->getStateUsing(fn($record) => number_format($record->price, currency_decimals(), '.', ','))
                    ->tooltip(fn($record) => numbers_to_words($record->price))
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label(__('fields.total'))->formatStateUsing(function ($state) {
                        return main_currency_iso_code() . " " . format_amount($state);
                    })),

                Tables\Columns\TextColumn::make('sub_total')
                    ->label(__('fields.sub_total'))
                    ->getStateUsing(fn($record) => number_format($record->sub_total, currency_decimals(), '.', ','))
                    ->tooltip(fn($record) => numbers_to_words($record->sub_total))
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()
                        ->label(__('fields.total'))
                        ->using(function (Table $table) {
                            return main_currency_iso_code() . " " . format_amount($table->getRecords()->sum('sub_total'));
                        })
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.date'))
                    ->dateTime('F j, Y, g:i a')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(ProductMovementExporter::class)
            ])
            ->bulkActions([
                Tables\Actions\ExportBulkAction::make()
                    ->exporter(ProductMovementExporter::class)
            ])
            ->filters([

                Tables\Filters\Filter::make('created_at')
                    ->label(__('fields.created_at'))
                    ->columnSpanFull()
                    ->form([
                        ...static::reportDateRangeFormFields(),

                        Forms\Components\Select::make('type')
                            ->label(__('fields.type'))
                            ->native(false)
                            ->placeholder(__('fields.all'))
                            ->options([
                                'purchases' => __('fields.products_movements_type_purchases'),
                                'sales' => __('fields.products_movements_type_sales'),
                                'sales_return' => __('fields.products_movements_type_sales_return'),
                                'purchase_return' => __('fields.products_movements_type_purchase_return'),
                            ]),

                        Forms\Components\Select::make('customers')
                            ->label(__('fields.client'))
                            ->multiple()
                            ->searchable()
                            ->options(Customer::pluck('name', 'id')),

                        Forms\Components\Select::make('suppliers')
                            ->label(__('fields.supplier'))
                            ->multiple()
                            ->searchable()
                            ->options(Supplier::pluck('name', 'id')),

                        Forms\Components\Select::make('invoices')
                            ->label(__('fields.invoice'))
                            ->multiple()
                            ->searchable()
                            ->options(Invoice::pluck('no', 'id')),

                        Forms\Components\Select::make('products')
                            ->label(__('fields.products'))
                            ->multiple()
                            ->searchable()
                            ->options(Product::pluck('name', 'id')),
                    ])
                    ->columns(4)
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = static::reportDateRangeIndicator($data) ?? '';

                        if ($data['type'] ?? null) {
                            $indicator .= __('fields.type');
                        }
                        if ($data['customers'] ?? null) {
                            $indicator .= __('fields.client');
                        }
                        if ($data['suppliers'] ?? null) {
                            $indicator .= __('fields.supplier');
                        }
                        if ($data['invoices'] ?? null) {
                            $indicator .= __('fields.invoice');
                        }
                        if ($data['products'] ?? null) {
                            $indicator .= __('fields.products');
                        }

                        return $indicator ?: null;
                    })
                    ->query(fn ($query) => $query),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductsMovements::route('/'),
            'create' => Pages\CreateProductsMovement::route('/create'),
            'edit' => Pages\EditProductsMovement::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereRaw('0 = 1');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
