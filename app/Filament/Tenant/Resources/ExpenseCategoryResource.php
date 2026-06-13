<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages;
use App\Filament\Tenant\Resources\ExpenseCategoryResource\RelationManagers;
use App\Models\ExpenseCategory;
use App\Rules\UniqueTenantItemRule;
use Filament\Forms;
use Filament\Forms\Components\View;
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

    public static function shouldRegisterNavigation(): bool
    {
        return user_setting('fav.expenses_categories', false);
    }

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.expenses_categories', false) ? __('fields.navigation_group_favourites') :  __('fields.expenses');
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
                Forms\Components\Section::make(__('fields.expense_category'))
                    ->description(__('fields.expense_category_form_hint'))
                    ->icon('heroicon-o-tag')
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'expense-category-form-section'])
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('fields.name'))
                            ->placeholder(__('fields.expense_category_name_placeholder'))
                            ->autofocus()
                            ->columnSpanFull()
                            ->rules([new UniqueTenantItemRule(ExpenseCategory::class, 'name', $form->getRecord()?->id)])
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(1),

                View::make('components.loading'),
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
                            fns()->deleted();
                        } else {
                            fns()->sendRecordInUse();
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

    public static function getExpenseCategoryEditUrl(ExpenseCategory | int $record): string
    {
        $id = $record instanceof ExpenseCategory ? $record->id : $record;

        return static::getUrl('index') . '?edit=' . $id;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseCategories::route('/'),
            'create-redirect' => Pages\RedirectExpenseCategoryCreate::route('/create'),
            'edit-redirect' => Pages\RedirectExpenseCategoryEdit::route('/{record}/edit'),
        ];
    }
}
