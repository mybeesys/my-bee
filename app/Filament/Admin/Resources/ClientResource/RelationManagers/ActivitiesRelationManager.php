<?php

namespace App\Filament\Admin\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'tenants';


    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.activities');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->description(fn($record) => $record->company_person)
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('fields.type'))
                    ->formatStateUsing(fn($record) => str(__("fields.$record->type"))->remove('ال')->value())
                    ->description(fn($record) => $record->trn)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->actions([

            ])
            ->bulkActions([
            ])->description('');
    }
}
