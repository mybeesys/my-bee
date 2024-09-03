<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TaxReportResource\Pages;
use App\Filament\Tenant\Resources\TaxReportResource\RelationManagers;
use App\Models\CashDet;
use App\Models\TaxReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaxReportResource extends Resource
{
    protected static ?string $model = CashDet::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_reports');
    }

    public static function getLabel(): ?string
    {
        return __('fields.tax_report');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.tax_report');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema(static::getFormSchema(Forms\Components\Card::class))
            ->columns([
                'sm' => 3,
                'lg' => null,
            ]);
    }

    public static function getFormSchema(string $layout = Forms\Components\Grid::class): array
    {
        return [
            Forms\Components\Group::make()
                ->schema([
                ])->columnSpan([
                    'sm' => 3,
                ]),
        ];
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                Tables\Columns\TextColumn::make('operation.no')
                    ->toggleable()
                    ->searchable()
                    ->extraAttributes(['class' => 'text-danger-700'])
                    ->label(__('fields.voucher_no')),

                Tables\Columns\TextColumn::make('date')
                    ->toggleable()
                    ->dateTime('F j, Y, g:i a')
                    ->label(__('fields.date')),

                Tables\Columns\TextColumn::make('account.name')
                    ->toggleable()
                    ->searchable()
                    ->description(function ($record) {
                        return $record->account_code;
                    })
                    ->color(Color::Sky)
                    ->url(function (CashDet $record) {
                        if ($record->meta and ($record->meta['type'] ?? null) == "expense") {
                            return ExpenseResource::getUrl('edit', ['record' => $record->meta['id']]);
                        }
                        if ($record->meta and ($record->meta['type'] ?? null) == "sales_invoice") {
                            return SalesInvoiceResource::getUrl('edit', ['record' => $record->meta['id']]);
                        }
                        if ($record->meta and ($record->meta['type'] ?? null) == "purchase_invoice") {
                            return PurchaseInvoiceResource::getUrl('edit', ['record' => $record->meta['id']]);
                        }

                        return null;
                    }, true)
                    ->label(__('fields.account')),

                Tables\Columns\TextColumn::make('source')
                    ->label(__('fields.source'))
                    ->getStateUsing(function (CashDet $record) {
                        return match ($record->meta['type'] ?? null) {
                            "sales_invoice" => __('fields.sales_invoice'),
                            "purchase_invoice" => __('fields.purchases_invoice'),
                            "expense" => __('fields.expense'),
                            default => "Unknown",
                        };
                    })
                    ->url(function (CashDet $record) {
                        return match ($record->meta['type'] ?? null) {
                            "sales_invoice" => SalesInvoiceResource::getUrl('edit', ['record' => $record->meta['id']]),
                            "purchase_invoice" => PurchaseInvoiceResource::getUrl('edit', ['record' => $record->meta['id']]),
                            "expense" => ExpenseResource::getUrl('edit', ['record' => $record->meta['id']]),
                            default => null,
                        };
                    }, true)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount_in')
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return number_format($record->amount_in, 2);
                    })
                    ->color(Color::Green)
                    ->description(function ($state, $record) {
                        if ($state > 0)
                            return CashDet::with('account')->where('op_id', $record->op_id)->where('account_code', '!=', $record->account_code)?->first()->account?->name;
                    })
                    ->label(__('fields.debit')),

                Tables\Columns\TextColumn::make('amount_out')
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return number_format($record->amount_out, 2);
                    })
                    ->color(Color::Red)
                    ->description(function ($state, $record) {
                        if ($state > 0)
                            return CashDet::with('account')->where('op_id', $record->op_id)->where('account_code', '!=', $record->account_code)?->first()->account?->name;
                    })
                    ->label(__('fields.credit')),

                Tables\Columns\TextColumn::make('statement')
                    ->getStateUsing(function ($record) {
                        return strip_tags($record->statement);
                    })
                    ->toggleable()
                    ->label(__('fields.statement')),

//                Tables\Columns\TextColumn::make('balance_pre_transaction')
//                    ->toggleable()
//                    ->label(__('fields.balance_pre_transaction'))
//                    ->getStateUsing(function ($record) {
//                        return number_format($record->balance_pre_transaction, currency_decimals(), '.', '.');
//                    }),

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
                        return number_format($record->balance_post_transaction, currency_decimals(), '.', '.');
                    })->summarize(Tables\Columns\Summarizers\Sum::make()->formatStateUsing(function ($state) {
                        return main_currency_iso_code() . " " . format_amount($state);
                    })),

                Tables\Columns\TextColumn::make('operation.files')
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        if (!$record->operation->files) return 0;
                        return count($record->operation->files);
                    })
                    ->label(__('fields.files')),

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
            ->filters([
//                Tables\Filters\SelectFilter::make('currency_id')
//                    ->label(__('fields.currency'))
//                    ->relationship('currency', 'name'),

                Tables\Filters\SelectFilter::make('op_id')
                    ->searchable()
//                        ->multiple()
                    ->label(__('fields.voucher_no'))
                    ->relationship('operation', 'no'),

                Tables\Filters\SelectFilter::make('transaction_id')
                    ->searchable()
                    ->options(CashDet::pluck('transaction_id', 'transaction_id')->unique()->toArray())
                    ->label(__('fields.transaction_id')),

            ])
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

            ])
            ->bulkActions([
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $transaction_ids = CashDet::with('account')->whereHas('account', function (Builder $q) {
            $q->where('acc3_code', '1228');
        })->pluck('transaction_id')->toArray();

        return parent::getEloquentQuery()->with(['operation', 'account.acc3', 'currency', 'invoice'])
            ->whereIn('transaction_id', $transaction_ids)
            ->latest();
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
            'index' => Pages\ListTaxReports::route('/'),
            'create' => Pages\CreateTaxReport::route('/create'),
            'edit' => Pages\EditTaxReport::route('/{record}/edit'),
        ];
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
