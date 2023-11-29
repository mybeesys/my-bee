<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages;
use App\Filament\Tenant\Resources\ExpenseCategoryResource\RelationManagers;
use App\Models\ExpenseCategory;
use App\Rules\UniqueTenantItemRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $slug = "expenses/categories";

    protected static ?int $navigationSort = 1;

    public static function getLabel(): ?string
    {
        return __('fields.expense_category');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('fields.expenses');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.expense_categories');
    }

    public static function getNavigationBadge(): ?string
    {
        return ExpenseCategory::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__("fields.expense_category"))->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__("fields.name"))
                        ->rules([new UniqueTenantItemRule(ExpenseCategory::class, 'name', $form->getRecord()?->id)])
                        ->required()
                        ->maxLength(255),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__("fields.name"))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expenses_count')
                    ->label(__("fields.expenses"))
                    ->counts('expenses'),
                Tables\Columns\TextColumn::make('expenses_total')
                    ->label(__("fields.total"))
                    ->moneyTooltip()
                    ->getStateUsing(function (ExpenseCategory $record) {
                        return main_currency_iso_code() . " " .$record->expenses_total_formatted;
                    }),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(function (ExpenseCategory $record) {
                        if ($record->expenses->isEmpty()) {
                            $record->delete();
                        } else {
                            Notification::make()
                                ->title(__('fields.record_in_use_alert'))
                                ->warning()
                                ->send();
                        }
                    })
            ])
            ->bulkActions([
//                Tables\Actions\BulkActionGroup::make([
//                    Tables\Actions\DeleteBulkAction::make(),
//                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['expenses'])->latest();
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
            'index' => \App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages\ListExpenseCategories::route('/'),
            'create' => \App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages\CreateExpenseCategory::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
