<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ExpenseResource\Pages;
use App\Filament\Tenant\Resources\ExpenseResource\RelationManagers;
use App\Filament\Tenant\Resources\ExpenseResource\Widgets\ExpensesOverview;
use App\Livewire\ExpenseChart;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Rules\UniqueTenantItemRule;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $slug = "expenses";

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.expenses');
    }

    public static function getLabel(): ?string
    {
        return __('fields.expense');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.expenses');
    }

    public static function getNavigationBadge(): ?string
    {
        return Expense::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\Select::make('expense_category_id')
                        ->label(__('fields.expense_category'))
                        ->required()
                        ->searchable()
                        ->createOptionForm([
                            Forms\Components\Section::make(__('fields.expense_category'))
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('fields.name'))
                                        ->rules([new UniqueTenantItemRule(ExpenseCategory::class, 'name')])
                                        ->required()
                                        ->autofocus(),
                                ])
                        ])
                        ->createOptionUsing(function ($data) {
                            $model = new ExpenseCategory();
                            $model->tenant_id = filament()->getTenant()->id;
                            $model->name = $data['name'];
                            $model->save();
                            return $model->id;
                        })
                        ->createOptionAction(
                            fn(Forms\Components\Actions\Action $action) => $action->modalWidth('md'),
                        )
                        ->options(ExpenseCategory::pluck('name', 'id')),

                    Forms\Components\TextInput::make('amount')
                        ->label(__("fields.amount_money"))
                        ->numeric()
                        ->minValue(1)
                        ->currency()
                        ->required(),

                    Forms\Components\DatePicker::make('date')
                        ->label(__("fields.date"))
                        ->required()
                        ->time(false),

                ])->columns(3),

                Forms\Components\Section::make()->schema([
                    Forms\Components\RichEditor::make('description')
                        ->label(__("fields.description"))
                        ->required()
                        ->label(__("fields.description")),
                ]),

                Forms\Components\Section::make()->schema([
                    Forms\Components\SpatieMediaLibraryFileUpload::make('attachments')
                        ->label(__('fields.attachments'))
                        ->disk('public')
                        ->multiple()
                        ->reorderable()
                        ->downloadable()
                        ->previewable()
                        ->openable()
                        ->collection('attachments'),
                ]),

                Forms\Components\Section::make()->schema([
                    Forms\Components\RichEditor::make('notes')
                        ->label(__("fields.notes")),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('description')
                    ->label(__("fields.description"))
                    ->searchable()
                    ->getStateUsing(fn($record) => strip_tags($record->description))
                    ->limit(35),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__("fields.category"))
                    ->url(fn($record) => ExpenseCategoryResource::getUrl('edit', ['record' => $record->expense_category_id]), true)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__("fields.amount_money"))
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(function (Expense $record) {
                        return main_currency_iso_code() . " " . format_amount($record->amount);
                    })
                    ->moneyTooltip()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->formatStateUsing(function ($state) {
                        return main_currency_iso_code() . " " . format_amount($state);
                    })),

                Tables\Columns\TextColumn::make('amount_words')
                    ->label(__("fields.amount_money_written"))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(function ($record) {
                        return numbers_to_words($record->amount);
                    }),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->searchable()
                    ->dateTime('j M , Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('media_count')
                    ->label(__('fields.attachments'))
                    ->counts('media'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('j M , Y H:i')
                    ->sortable(),

            ])
            ->filters([

                Tables\Filters\Filter::make('date')
                    ->indicator('advanced_filter')
                    ->form([

                        Forms\Components\Select::make('expense_category_id')
                            ->label(__('fields.expense_category'))
                            ->multiple()
                            ->options(ExpenseCategory::whereIn('id', Expense::pluck('expense_category_id')->toArray())->pluck('name', 'id')),


                        Forms\Components\DatePicker::make('date_from')->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('date_until')->label(__('fields.created_until')),

//                        Forms\Components\TextInput::make('amount_from')->money()->label(__('fields.created_from')),
//                        Forms\Components\TextInput::make('amount_until')->money()->label(__('fields.created_until')),

                        Forms\Components\Checkbox::make('attachments')
                            ->label(__('fields.only_display_records_with_attachments')),

                    ])->columns(3)
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
                        if ($data['expense_category_id']) {
                            $indicator = $indicator . __('fields.expense_category');
                        }
                        if ($data['date_from'] or $data['date_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
//                        if ($data['amount_from'] or $data['amount_until']) {
//                            $indicator = $indicator . __('fields.amount_money');
//                        }
                        if ($data['attachments']) {
                            $indicator = $indicator . __('fields.only_display_records_with_attachments');
                        }
                        return $indicator;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['expense_category_id'],
                                fn(Builder $query, $expense_category_id): Builder => $query->where('expense_category_id', $expense_category_id),
                            )
                            ->when(
                                $data['date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            )
//                            ->when(
//                                $data['amount_from'],
//                                fn(Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
//                            )->when(
//                                $data['amount_until'],
//                                fn(Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
//                            )
                            ->when(
                                $data['attachments'],
                                fn(Builder $query, $attachments): Builder => $query->whereHas('media'),
                            );
                    })

            ], Tables\Enums\FiltersLayout::Modal)
            ->filtersFormWidth(MaxWidth::FiveExtraLarge)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'media'])->latest();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [
            ExpenseChart::class,
            ExpensesOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\ExpenseResource\Pages\ListExpenses::route('/'),
            'create' => \App\Filament\Tenant\Resources\ExpenseResource\Pages\CreateExpense::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\ExpenseResource\Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
