<?php

namespace App\Filament\Tenant\Resources\CustomerResource\RelationManagers;

use App\Filament\Tenant\Resources\CustomerResource;
use App\Filament\Tenant\Resources\ReceiptVoucherResource;
use App\Filament\Tenant\Resources\SalesInvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ReceiptVoucher;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.invoices');
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
            ->recordTitleAttribute('no')
            ->modifyQueryUsing(fn ($query) => $query->withCount('salesReturns'))
            ->columns([
                SalesInvoiceResource::invoiceSalesReturnIndicatorTableColumn(),

                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.invoice_no'))
                    ->description(function (Invoice $record) {
                        $order = Order::where('invoice_id', $record->id)->first();

                        if ($order) {
                            return "Order No: $order->no";
                        }
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('fields.client'))
                    ->url(function ($record) {
                        return CustomerResource::getUrl('edit', ['record' => $record->customer_id]);
                    }, true)
                    ->color(Color::Sky)
                    ->searchable(),

                \App\Filament\Tenant\Resources\SalesInvoiceResource::invoiceSettlementStatusTableColumn(),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->dateTime('M j, Y')
                    ->searchable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('fields.paid_amount'))
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->total_paid);
                    }),

                Tables\Columns\TextColumn::make('paid_amount_percent')
                    ->extraAttributes(function ($record) {
                        if (percent($record->total_paid, $record->getItemsCost(true, true, true)) > 0) {
                            return ['class' => 'text-success-700'];
                        }

                        return ['class' => 'text-danger-700'];
                    })
                    ->label(__('fields.paid_amount_percent'))
                    ->getStateUsing(function ($record) {
                        return format_amount(percent($record->total_paid, $record->getItemsCost(true, true, true))) . "%";
                    }),


                Tables\Columns\TextColumn::make('additional_costs')
                    ->label(__('fields.additional_costs'))
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->getAdditionalCosts());
                    }),

                Tables\Columns\TextColumn::make('invoice_total')
                    ->label(__('fields.invoice_total'))
                    ->color(Color::Violet)
                    ->description(function ($record) {
                        return numbers_to_words($record->getItemsCost(true, true, true));
                    })
                    ->getStateUsing(function ($record) {
                        return format_amount($record->getItemsCost(true, true, true));
                    }),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('fields.status'))
                    ->multiple()
                    ->options([
                        'cancelled' => __('fields.invoice_status_cancelled'),
                        'confirmed' => __('fields.invoice_status_confirmed'),
                    ]),

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
            ->actions([
                SalesInvoiceResource::configureInvoiceTableActionGroup(Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    SalesInvoiceResource::salesReturnInvoiceTableAction(),

                    Tables\Actions\Action::make('complete_payment')
                        ->label(__('fields.payment_details'))
                        ->icon('heroicon-o-pencil')
                        ->color('success')
                        ->visible(function ($record) {
                            return !$record->paid;
                        })
                        ->action(function (Invoice $record) {
                            if ($record->salesPayments->isEmpty()) {
                                return redirect(ReceiptVoucherResource::getUrl('create', ['invoice_id' => $record->id]));
                            }

                            $rv = ReceiptVoucher::whereInvoiceId($record->id)->first();

                            if ($rv) {
                                return redirect(ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id, 'rv' => $rv->id]));
                            }
                        }),
                ])),
            ]);
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
