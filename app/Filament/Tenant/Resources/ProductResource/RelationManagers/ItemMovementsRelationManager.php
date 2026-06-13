<?php

namespace App\Filament\Tenant\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.products_movement');
    }

    /**
     * @return string|null
     */
    public static function getModelLabel(): ?string
    {
        return __('fields.products_movement');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    public function table(Table $table): Table
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
                        return $indicator;
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })
            ])
            ->headerActions([
            ])
            ->actions([

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }
}
