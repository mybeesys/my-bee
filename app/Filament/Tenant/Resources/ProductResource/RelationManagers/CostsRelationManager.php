<?php

namespace App\Filament\Tenant\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class CostsRelationManager extends RelationManager
{
    protected static string $relationship = 'costs';

    protected static ?string $recordTitleAttribute = 'name';

//    public static function getTitle(): string
//    {
//        return "التكاليف";
//    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Radio::make('type')
                    ->label('النوع')
                    ->required()
                    ->options(function (){
                        return [
                            'pre-production' => 'قبل الإنتاج',
                            'post-production' => 'بعد الإنتاج'
                        ];
                    }),

                Forms\Components\TextInput::make('cost')
                    ->label('القيمة')
                    ->required()
                    ->minValue(1)
                    ->numeric(),
                Forms\Components\RichEditor::make('description')
                    ->label('الوصف')
                    ->columnSpan(3),
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->getStateUsing(function ($record){
                        if($record->type == 'pre-production')
                        return "قبل الإنتاج";

                        return "بعد الإنتاج";
                    })
                    ->label('النوع')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ProductionCost.name')
                    ->label(__('fields.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('cost')
                    ->label('القيمة')
                    ->searchable(),
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
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(
                    [
                        'pre-production' => 'قبل الإنتاج',
                        'post-production' => 'بعد الإنتاج'
                    ]
                )
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
