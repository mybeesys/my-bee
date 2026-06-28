<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Exports\ProductMovementExporter;
use App\Filament\Tenant\Resources\ProductsMovementResource\Pages;
use App\Filament\Tenant\Resources\ProductsMovementResource\RelationManagers;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductsMovementResource extends Resource
{
    protected static ?string $model = InvoiceItem::class;

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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('fields.type'))
                    ->badge()
                    ->color(function (InvoiceItem $record) {
                        return match ($record->invoice->type) {
                            'purchases' => 'danger',
                            'sales' => 'success',
                            default => 'warning',
                        };
                    })
                    ->getStateUsing(function (InvoiceItem $record) {
                        return $record->invoice->type == "purchases" ?
                            __('fields.products_movements_type_purchases')
                            : __('fields.products_movements_type_sales');
                    }),

                Tables\Columns\TextColumn::make('entity')
                    ->label(__('fields.entity'))
                    ->color(Color::Sky)
                    ->getStateUsing(function (InvoiceItem $record) {
                        if ($record->invoice->customer_id) {
                            return $record->invoice->customer?->name ?? '-';
                        }

                        return $record->invoice->supplier?->name ?? '-';
                    })
                    ->url(function (InvoiceItem $record) {
                        if ($record->invoice->customer_id && $record->invoice->customer) {
                            return CustomerResource::getUrl('edit', ['record' => $record->invoice->customer_id]);
                        }

                        if ($record->invoice->supplier_id && $record->invoice->supplier) {
                            return SupplierResource::getUrl('edit', ['record' => $record->invoice->supplier_id]);
                        }

                        return null;
                    }, true),

                Tables\Columns\TextColumn::make('invoice.no')
                    ->label(__('fields.invoice_no'))
                    ->color(Color::Sky)
                    ->url(function (InvoiceItem $record) {
                        return $record->invoice->type == "purchases" ?
                            PurchaseInvoiceResource::getUrl('edit', ['record' => $record->invoice_id])
                            : SalesInvoiceResource::getUrl('edit', ['record' => $record->invoice_id]);
                    }, true)
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label(__('fields.qty'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('current_qty_movement_balance')
                    ->label(__('fields.qty_after_movement'))
                    ->searchable(),

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
                    ->form([

                        Forms\Components\Radio::make('type')
                            ->label(__('fields.type'))
                            ->default(null)
                            ->options([
                                null => __('fields.all'),
                                'purchases' => __('fields.products_movements_type_purchases'),
                                'sales' => __('fields.products_movements_type_sales'),
                            ]),

                        Forms\Components\Select::make('customers')
                            ->label(__('fields.client'))
                            ->multiple()
                            ->options(Customer::pluck('name', 'id')),

                        Forms\Components\Select::make('suppliers')
                            ->label(__('fields.supplier'))
                            ->multiple()
                            ->options(Supplier::pluck('name', 'id')),

                        Forms\Components\Select::make('invoices')
                            ->label(__('fields.invoice'))
                            ->multiple()
                            ->options(Invoice::pluck('no', 'id')),

                        Forms\Components\Select::make('products')
                            ->label(__('fields.products'))
                            ->multiple()
                            ->options(Product::pluck('name', 'id')),

                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('fields.created_from')),

                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('fields.created_until')),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
                        if ($data['type']) {
                            $indicator = $indicator . __('fields.type');
                        }
                        if ($data['customers']) {
                            $indicator = $indicator . __('fields.client');
                        }
                        if ($data['suppliers']) {
                            $indicator = $indicator . __('fields.supplier');
                        }
                        if ($data['invoices']) {
                            $indicator = $indicator . __('fields.invoice');
                        }
                        if ($data['products']) {
                            $indicator = $indicator . __('fields.products');
                        }
                        if ($data['created_from'] or $data['created_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
                        return $indicator;
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['customers'],
                                fn(Builder $query) => $query->whereHas('invoice', function ($q) use ($data) {
                                    $q->whereIn('customer_id', $data['customers']);
                                }))
                            ->when($data['suppliers'],
                                fn(Builder $query) => $query->whereHas('invoice', function ($q) use ($data) {
                                    $q->whereIn('supplier_id', $data['suppliers']);
                                }))
                            ->when($data['invoices'],
                                fn(Builder $query) => $query->whereHas('invoice', function ($q) use ($data) {
                                    $q->whereIn('id', $data['invoices']);
                                }))
                            ->when($data['type'],
                                fn(Builder $query) => $query->whereHas('invoice', function ($q) use ($data) {
                                    $q->where('type', $data['type']);
                                }))
                            ->when($data['products'],
                                fn($query) => $query->whereIn('product_id', $data['products']))
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })
            ])
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
        return parent::getEloquentQuery()->with(['invoice.customer', 'invoice.supplier'])->latest();
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
