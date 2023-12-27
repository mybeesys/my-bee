<?php

namespace App\Filament\Tenant\Resources\CategoryResource\RelationManagers;

use App\Filament\Tenant\Resources\ProductResource;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.products');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->color(Color::Sky)
                    ->searchable()
                    ->url(fn($record) => ProductResource::getUrl('edit', ['record' => $record->id]), true),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('fields.type'))
                    ->getStateUsing(function (Product $record) {

                        if ($record->type === Product::$TYPE_BASIC)
                            return __('fields.product_type_basic');

                        if ($record->type === Product::$TYPE_UNITS)
                            return __('fields.product_type_units');

                        if ($record->type === Product::$TYPE_VARIANTS)
                            return __('fields.product_type_variants');

                        if ($record->type === Product::$TYPE_SERVICE)
                            return __('fields.product_type_service');

                        return "-";
                    })
                    ->searchable(),

            ])
            ->bulkActions([])
            ->actions([])
            ->filters([]);
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canCreate(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->products->isNotEmpty();
    }

}
