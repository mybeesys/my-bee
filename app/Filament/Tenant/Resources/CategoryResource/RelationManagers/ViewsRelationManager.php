<?php

namespace App\Filament\Tenant\Resources\CategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ViewsRelationManager extends RelationManager
{
    protected static string $relationship = 'itemViews';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    protected function getTableBulkActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(array(
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Name')
                    ->searchable()
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        if($record->user)
                        return ucwords($record->user->full_name);

                        return "Not Registered";
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('View date')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ))->pushActions([
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['from']))
                            ->when($data['to'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['to']));
                    }),
            ]);
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }
}
