<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Exports\CashDetExporter;
use App\Filament\Tenant\Concerns\ConfiguresReportTableFilters;
use App\Filament\Tenant\Resources\AccountStatementResource\Pages;
use App\Filament\Tenant\Resources\AccountStatementResource\RelationManagers;
use App\Models\Acc4;
use App\Models\AccountStatement;
use App\Models\CashDet;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccountStatementResource extends Resource
{
    use ConfiguresReportTableFilters;

    protected static ?string $model = CashDet::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_reports');
    }

    public static function getLabel(): ?string
    {
        return __('fields.account_statement');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.account_statement');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                View::make('components.loading'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return static::configureReportTableFilters($table)
            ->modifyQueryUsing(function (Builder $query) use ($table) {
//                dd($table->getFilter('created_at')->getState()['account_code'], empty($table->getFilter('created_at')->getState()['account_code']));
                if (empty($table->getFilter('created_at')->getState()['account_code'])) {
                    return $query->where('id', 'x');
                }
            })
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                Tables\Columns\TextColumn::make('operation.no')
                    ->toggleable()
                    ->searchable()
                    ->extraAttributes(['class' => 'text-danger-700'])
                    ->label(__('fields.voucher_no')),

                Tables\Columns\TextColumn::make('date')
                    ->toggleable()
                    ->dateTime('M j, Y')
                    ->label(__('fields.date')),

                Tables\Columns\TextColumn::make('account.name')
                    ->toggleable()
                    ->searchable()
                    ->label(__('fields.account')),

//                Tables\Columns\TextColumn::make('transaction_id')
//                    ->toggleable()
//                    ->searchable()
//                    ->label(__('fields.transaction_id')),

//                Tables\Columns\TextColumn::make('currency.name')
//                    ->toggleable()
//                    ->searchable()
//                    ->label(__('fields.currency')),

                Tables\Columns\TextColumn::make('amount_in')
                    ->label(__('fields.debit'))
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return number_format($record->amount_in, 2);
                    })
                    ->color(Color::Green)
                    ->description(function ($state, $record) {
                        if ($state > 0)
                            return CashDet::with('account')->where('op_id', $record->op_id)->where('account_code', '!=', $record->account_code)?->first()->account?->name;
                    })
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label(__('fields.total'))->formatStateUsing(function ($state) {
                        return main_currency_iso_code() . " " . format_amount($state);
                    })),

                Tables\Columns\TextColumn::make('amount_out')
                    ->label(__('fields.credit'))
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return number_format($record->amount_out, 2);
                    })
                    ->color(Color::Red)
                    ->description(function ($state, $record) {
                        if ($state > 0)
                            return CashDet::with('account')->where('op_id', $record->op_id)->where('account_code', '!=', $record->account_code)?->first()->account?->name;
                    })
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label(__('fields.total'))->formatStateUsing(function ($state) {
                        return main_currency_iso_code() . " " . format_amount($state);
                    })),

                Tables\Columns\TextColumn::make('balance_post_transaction')
                    ->toggleable()
                    ->label(__('fields.balance'))
                    ->color(function ($record) {
                        if ($record->balance_post_transaction > 0) {
                            return Color::Green;
                        }
                        return Color::Red;
                    })
                    ->getStateUsing(function ($record) {
                        return number_format($record->balance_post_transaction, currency_decimals(), '.', ',');
                    }),

                Tables\Columns\TextColumn::make('statement')
                    ->getStateUsing(function ($record) {
                        return format_account_statement_text($record->statement);
                    })
                    ->toggleable()
                    ->label(__('fields.statement')),

//                Tables\Columns\TextColumn::make('balance_pre_transaction')
//                    ->toggleable()
//                    ->label(__('fields.balance_pre_transaction'))
//                    ->getStateUsing(function ($record) {
//                        return number_format($record->balance_pre_transaction, currency_decimals(), '.', '.');
//                    }),

//                Tables\Columns\TextColumn::make('operation.files')
//                    ->toggleable()
//                    ->getStateUsing(function ($record) {
//                        if (!$record->operation->files) return 0;
//                        return count($record->operation->files);
//                    })
//                    ->label(__('fields.files')),

//                Tables\Columns\BooleanColumn::make('operation.locked_at')
//                    ->toggleable()
//                    ->getStateUsing(function ($record) {
//                        return $record->operation->locked_at == null ? false : true;
//                    })
//                    ->label(__('fields.locked')),

                Tables\Columns\TextColumn::make('created_at')
                    ->toggleable()
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),

            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(CashDetExporter::class)
            ])
            ->bulkActions([
                Tables\Actions\ExportBulkAction::make()
                    ->exporter(CashDetExporter::class)
            ])
            ->filters([
//                Tables\Filters\SelectFilter::make('currency_id')
//                    ->label(__('fields.currency'))
//                    ->relationship('currency', 'name'),

                Tables\Filters\Filter::make('created_at')
                    ->label(__('fields.created_at'))
                    ->columnSpanFull()
                    ->form([

                        Forms\Components\Select::make('account_code')
                            ->label(__('fields.account'))
                            ->options(Acc4::userCreatedOtherPartyAccountOptions())
                            ->searchable(),

                        Forms\Components\Select::make('op_id')
                            ->searchable()
                            ->label(__('fields.voucher_no'))
                            ->relationship('operation', 'no'),

                        ...static::reportDateRangeFormFields(),
                    ])
                    ->columns(4)
                    ->indicateUsing(function (array $data): ?string {
                        $parts = [];

                        if ($data['account_code'] ?? null) {
                            $parts[] = __('fields.account');
                        }

                        if ($data['op_id'] ?? null) {
                            $parts[] = __('fields.voucher_no');
                        }

                        if ($dateIndicator = static::reportDateRangeIndicator($data)) {
                            $parts[] = $dateIndicator;
                        }

                        return $parts === [] ? null : implode(', ', $parts);
                    })
                    ->query(function ($query, array $data) {
                        return static::applyReportDateRangeQuery(
                            $query
                                ->when(
                                    $data['account_code'] ?? null,
                                    fn ($query) => $query->where('account_code', $data['account_code'])
                                )
                                ->when(
                                    $data['op_id'] ?? null,
                                    fn ($query) => $query->where('op_id', $data['op_id'])
                                ),
                            $data
                        );
                    })
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ActionGroup::make([
//                        Tables\Actions\EditAction::make(),
//                    Tables\Actions\Action::make('lock_unlock')
//                        ->visible(function ($record) {
//                            return $record->operation->locked_at == null;
//                        })
//                        ->modalWidth('lg')
//                        ->requiresConfirmation()
//                        ->color('danger')
//                        ->icon(function ($record){
//                            if ($record->operation->locked_at == null) {
//                                return 'heroicon-o-lock-closed';
//                            }
//                            return 'heroicon-o-lock-open';
//                        })
//                        ->action(function ($record) {
////                                !auth()->user()->isSuperAdmin()
//                            if(!can_lock_journal_entry())
//                            {
//                                Filament::notify('danger', __('fields.insufficient_permission'));
//                                return;
//                            }
//
//                            $record->operation->update(['locked_at' => now()]);
//
//                            Filament::notify('success', __('fields.alert_invoice_locked'));
//
//                        })
                ]),

            ]);
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
            'index' => Pages\ListAccountStatements::route('/'),
            'create' => Pages\CreateAccountStatement::route('/create'),
            'edit' => Pages\EditAccountStatement::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['account', 'operation', 'account.acc3', 'currency', 'invoice'])
            ->whereHas('account', fn (Builder $query) => $query->userCreatedOtherPartyAccounts())
            ->orderByDesc('id');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
