<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ExpenseResource\Pages;
use App\Filament\Tenant\Resources\ExpenseResource\RelationManagers;
use App\Filament\Tenant\Resources\ExpenseResource\Widgets\ExpensesOverview;
use App\Livewire\ExpenseChart;
use App\Models\Acc4;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\TaxProfile;
use App\Rules\UniqueTenantItemRule;
use App\Services\MathService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $slug = "expenses/manage";

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.expenses', false) ? __('fields.navigation_group_favourites') : null;
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

                    Select::make('credit_acc4_code')
                        ->live()
                        ->disabled(fn($record) => $record !== null)
                        ->label(__('fields.account'))
                        ->hint(function (Get $get) {
                            $acc_id = $get('debit_acc4_code');

                            if ($acc_id)
                                return Acc4::find($acc_id)->acc4_code;

                            return null;
                        })
                        ->options(function () {
                            //add bank transfers accounts
                            return Acc4::whereIn('code', [120100001])->OrWhereIn('acc3_code', [1227])->pluck('name', 'code');
                        })
                        ->required(),

                    Forms\Components\Hidden::make('debit_acc4_code')
                        ->label(__('fields.expense_account'))
                        ->required()
                        ->default('122300001'),

                    Forms\Components\Select::make('expense_category_id')
                        ->label(__('fields.category'))
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


                    Forms\Components\DatePicker::make('date')
                        ->label(__("fields.date"))
                        ->required()
                        ->default(now())
                        ->time(false),

                ])->columns(3),

                Section::make()->schema([

                    Forms\Components\TextInput::make('amount')
                        ->live()
                        ->label(__("fields.amount_money"))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(PHP_INT_MAX)
                        ->afterStateUpdated(function ($state, $livewire, Set $set, Forms\Get $get) {
                            $taxProfile = TaxProfile::with('taxes')->find($get('tax_profile_id'));
                            if ($taxProfile) {
                                if ($state and is_number($state)) {
                                    $tax = MathService::instance()->getTaxFromTaxProfile($state, $taxProfile);
                                    $set('amount_without_tax', number_format($state - $tax, currency_decimals(), '.', ''));
                                    $set('tax', number_format($tax, currency_decimals(), '.', ''));
                                }
                                $set('tax_profile_data', json_encode($taxProfile->toArray()));
                            } else {
                                $set('tax_profile_data', null);
                            }
                        })
                        ->required()
                        ->disabledOn('edit')
                        ->currency(),

                    Forms\Components\Toggle::make('amount_includes_tax')
                        ->label(__('fields.amount_includes_tax'))
                        ->dehydrated(false)
                        ->visible(fn(Forms\Get $get) => is_number($get('amount')))
                        ->live()
                        ->disabledOn('edit')
                        ->afterStateUpdated(function ($state, Forms\Set $set, Get $get) {
                            $amount = $get('amount');
                            $taxProfile = TaxProfile::with('taxes')->find($get('tax_profile_id'));

                            if ($taxProfile) {
                                if ($amount and is_number($amount)) {
                                    $tax = MathService::instance()->getTaxFromTaxProfile($amount, $taxProfile);
                                    $set('amount_without_tax', number_format($amount - $tax, currency_decimals(), '.', ''));
                                    $set('tax', number_format($tax, currency_decimals(), '.', ''));
                                }
                                $set('tax_profile_data', json_encode($taxProfile->toArray()));
                            } else {
                                $set('tax_profile_data', null);
                            }
                            if (!$state) {
                                $set('amount_without_tax', null);
                                $set('tax', 0);
                                $set('tax_percent', null);
                            }
                        })
                        ->inline(false),

                    Forms\Components\Hidden::make('tax_profile_data'),

                    Select::make('tax_profile_id')
                        ->required()
                        ->visible(fn(Forms\Get $get) => $get('amount_includes_tax') == true and is_number($get('amount')))
                        ->label(__('fields.tax'))
                        ->live()
                        ->disabledOn('edit')
                        ->afterStateUpdated(function ($state, $livewire, Set $set, Forms\Get $get) {
                            $taxProfile = TaxProfile::with('taxes')->find($state);
                            if ($taxProfile) {
                                if ($amount = $get('amount') and is_number($amount)) {
                                    $tax = MathService::instance()->getTaxFromTaxProfile($amount, $taxProfile);
                                    $set('amount_without_tax', number_format($amount - $tax, currency_decimals(), '.', ''));
                                    $set('tax', number_format($tax, currency_decimals(), '.', ''));
                                }
                                $set('tax_profile_data', json_encode($taxProfile->toArray()));
                            } else {
                                $set('tax_profile_data', null);
                            }
                        })
                        ->options(TaxProfile::asOptions())
                        ->createOptionForm(TaxProfileResource::getSchemaForCreateOption())
                        ->createOptionUsing(function ($data) {
                            $data['tenant_id'] = filament()->getTenant()->id;
                            $model = TaxProfile::create(Arr::except($data, ['taxes']));
                            foreach ($data['taxes'] as $tax) {
                                $model->taxes()->create([
                                    'tenant_id' => $data['tenant_id'],
                                    'tax_profile_id' => $model->id,
                                    'description' => $tax['description'],
                                    'percent' => $tax['percent'],
                                ]);
                            }
                            return $model->id;
                        })
                        ->createOptionAction(
                            fn(Forms\Components\Actions\Action $action) => $action->modalWidth('5xl'),
                        )
                        ->searchable(),

                    Forms\Components\Hidden::make('tax')->default(0),

                    Forms\Components\Placeholder::make('amount_without_tax')
                        ->visible(fn(Forms\Get $get) => $get('tax_profile_id'))
                        ->label(__('fields.amount_without_tax'))
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->content(function ($livewire) {
                            $value = $livewire->data['amount_without_tax'];
                            return new HtmlString("<h3 style='color: #0464ff;font-weight: bold'>$value</h3>");
                        }),

                    Forms\Components\Placeholder::make('tax_placeholder')
                        ->visible(fn(Forms\Get $get) => $get('tax_profile_id'))
                        ->label(__('fields.tax'))
                        ->columnSpanFull()
                        ->content(function ($livewire) {
                            $value = $livewire->data['tax'];
                            return new HtmlString("<h3 style='color: #0464ff;font-weight: bold'>$value</h3>");
                        }),

//                    Forms\Components\TextInput::make('amount_without_tax')
//                        ->visible(fn(Forms\Get $get) => $get('amount_includes_tax') == true and is_number($get('amount')))
//                        ->label(__("fields.amount_without_tax"))
//                        ->numeric()
//                        ->minValue(1)
//                        ->dehydrated(false)
//                        ->readOnly()
//                        ->currency()
//                        ->required(),

//                    Forms\Components\TextInput::make('tax')
//                        ->visible(fn(Forms\Get $get) => is_number($get('amount')))
//                        ->label(__("fields.tax"))
//                        ->numeric()
//                        ->default(0)
//                        ->minValue(0)
//                        ->readOnly()
//                        ->lt('amount')
//                        ->currency()
//                        ->required(),
                ])->columns(5),

                Forms\Components\Section::make()->schema([
                    Forms\Components\Textarea::make('description')
                        ->label(__("fields.description"))
                        ->rows(10)
                        ->cols(20)
                        ->required(),
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                Tables\Columns\TextColumn::make('creditAccount.name')
                    ->label(__('fields.account'))
                    ->description(fn($record) => $record->creditAccount->code)
                    ->searchable(),

//                Tables\Columns\TextColumn::make('debitAccount.name')
//                    ->label(__('fields.expense_account'))
//                    ->description(fn($record) => $record->debitAccount->code)
//                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__("fields.description"))
                    ->searchable()
                    ->getStateUsing(fn($record) => strip_tags($record->description))
                    ->limit(35),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__("fields.category"))
                    ->url(fn($record) => ExpenseCategoryResource::getUrl('edit', ['record' => $record->expense_category_id]), true)
                    ->searchable()
                    ->description(function (Expense $record) {
//                        $meta = json_decode($record->meta, true);
                        if ($record->meta and $record->meta['invoice_id'] ?? null) {
                            return Invoice::find($record->meta['invoice_id'])?->no;
                        }
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__("fields.amount_money"))
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(function (Expense $record) {
                        return main_currency_iso_code() . " " . format_amount($record->amount);
                    })
                    ->moneyTooltip(),

                Tables\Columns\TextColumn::make('tax')
                    ->label(__("fields.tax"))
                    ->searchable()
                    ->sortable()
                    ->description(function ($record) {
                        if ($record->taxProfile) {
                            return collect($record->taxProfile->taxes)->sum('percent') . "%";
                        }
                    })
                    ->getStateUsing(function (Expense $record) {
                        return main_currency_iso_code() . " " . format_amount($record->tax);
                    })
                    ->moneyTooltip(),

                Tables\Columns\TextColumn::make('total')
                    ->label(__("fields.total"))
                    ->sortable()
                    ->getStateUsing(function (Expense $record) {
                        return main_currency_iso_code() . " " . format_amount($record->total);
                    })
                    ->moneyTooltip(),

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

                        Forms\Components\Select::make('credit_acc4_code')
                            ->label(__('fields.account'))
                            ->multiple()
                            ->options(Acc4::whereIn('code', [120100001])->OrWhereIn('acc3_code', [1227])->pluck('name', 'code')),

//                        Forms\Components\Select::make('debit_acc4_code')
//                            ->label(__('fields.expense_account'))
//                            ->multiple()
//                            ->options(function () {
//                                return Acc4::whereHas('acc3', function ($q) {
//                                    return $q->whereIn('acc2_code', [51, 52, 53]);
//                                })->pluck('name', 'code');
//                            }),

                        Forms\Components\Select::make('expense_category_id')
                            ->label(__('fields.category'))
                            ->multiple()
                            ->options(ExpenseCategory::whereIn('id', Expense::pluck('expense_category_id')->toArray())->pluck('name', 'id')),


                        Forms\Components\DatePicker::make('date_from')->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('date_until')->label(__('fields.created_until')),

//                        Forms\Components\TextInput::make('amount_from')->money()->label(__('fields.created_from')),
//                        Forms\Components\TextInput::make('amount_until')->money()->label(__('fields.created_until')),

                        Forms\Components\Checkbox::make('attachments')
                            ->label(__('fields.only_display_records_with_attachments')),

                    ])->columns(4)
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
//                        if ($data['debit_acc4_code']) {
//                            $indicator = $indicator . __('fields.account');
//                        }
                        if ($data['credit_acc4_code']) {
                            $indicator = $indicator . __('fields.expense_account');
                        }
                        if ($data['expense_category_id']) {
                            $indicator = $indicator . __('fields.category');
                        }
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
//                            ->when(
//                                $data['debit_acc4_code'],
//                                fn(Builder $query, $codes): Builder => $query->whereIn('debit_acc4_code', $codes),
//                            )
                            ->when(
                                $data['credit_acc4_code'],
                                fn(Builder $query, $codes): Builder => $query->whereIn('credit_acc4_code', $codes),
                            )
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
//                Tables\Actions\DeleteAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'media', 'taxProfile', 'debitAccount', 'creditAccount'])->latest();
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
