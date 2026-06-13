<?php

namespace App\Filament\Tenant\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Support\HtmlString;

class RawMaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'rawMaterials';

    protected static ?string $recordTitleAttribute = 'name';

//    public static function getTitle(): string
//    {
//        return "المواد الخام";
//    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('price_per_unit')
                    ->label('سعر الحبه')
                    ->required()
                    ->minValue(1)
                    ->numeric(),
                Forms\Components\TextInput::make('qty')
                    ->label('الكميه')
                    ->required()
                    ->minValue(1)
                    ->maxValue(100000),
                Forms\Components\RichEditor::make('description')
                    ->label('الوصف')
                    ->columnSpan(3),
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rawMaterial.name')
                    ->label('المادة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price_per_unit')
                    ->label('سعر الحبه')
                    ->searchable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label('الكميه')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sub_total')
                    ->label('التكلفة الكلية')
                    ->getStateUsing(function ($record){
                        return number_format($record->qty * $record->price_per_unit, 2);
                }),
                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->getStateUsing(function ($record){
                    return new HtmlString($record->description);
                }),
                Tables\Columns\TextColumn::make('created_at')
                    ->toggleable()
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
//                Tables\Filters\SelectFilter::make('measurement_unit_id')
//                    ->label('وحدة القياس')
//                    ->options(function (){
//                        return Unit::pluck('name', 'id');
//                    }),
            ])
            ->headerActions([
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
            ]);
    }

}
