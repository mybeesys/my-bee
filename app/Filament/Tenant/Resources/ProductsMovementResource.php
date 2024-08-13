<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ProductsMovementResource\Pages;
use App\Filament\Tenant\Resources\ProductsMovementResource\RelationManagers;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
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

                Tables\Columns\TextColumn::make('invoice.customer.name')
                    ->label(__('fields.client'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice.no')
                    ->label(__('fields.invoice_no'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label(__('fields.qty'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.date'))
                    ->dateTime('F j, Y, g:i a')
                    ->sortable(),
            ])
            ->filters([

                Tables\Filters\Filter::make('created_at')
                    ->label(__('fields.created_at'))
                    ->form([

                        Forms\Components\Select::make('customers')
                            ->label(__('fields.client'))
                            ->multiple()
                            ->options(Customer::pluck('name', 'id')),

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
                        if ($data['created_from'] or $data['created_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
                        if ($data['customers']) {
                            $indicator = $indicator . __('fields.client');
                        }
                        if ($data['products']) {
                            $indicator = $indicator . __('fields.products');
                        }
                        return $indicator;
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['customers'],
                                fn(Builder $query) => $query->whereHas('invoice', function ($q) use ($data) {
                                    $q->whereIn('customer_id', $data['customers']);
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
        return parent::getEloquentQuery()->with(['invoice.customer'])->latest();
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
