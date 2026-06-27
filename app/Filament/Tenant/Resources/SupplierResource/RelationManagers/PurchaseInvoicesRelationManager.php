<?php

namespace App\Filament\Tenant\Resources\SupplierResource\RelationManagers;

use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
use App\Models\Invoice;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseInvoices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.purchases_invoices');
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('no')
            ->modifyQueryUsing(fn ($query) => $query->withCount('purchasesReturns'))
            ->columns([
                PurchaseInvoiceResource::invoicePurchaseReturnIndicatorTableColumn(),

                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.invoice_no'))
                    ->searchable(),

                PurchaseInvoiceResource::invoiceSettlementStatusTableColumn(),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->dateTime('M j, Y')
                    ->searchable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('fields.paid_amount'))
                    ->getStateUsing(fn (Invoice $record) => main_currency_iso_code() . ' ' . format_amount($record->total_paid)),

                Tables\Columns\TextColumn::make('invoice_total')
                    ->label(__('fields.invoice_total'))
                    ->color(Color::Violet)
                    ->getStateUsing(fn (Invoice $record) => format_amount($record->getItemsCost(true, true, true))),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('fields.status'))
                    ->options([
                        'purchase_order' => __('fields.invoice_status_purchase_order'),
                        'cancelled' => __('fields.invoice_status_cancelled'),
                        'confirmed' => __('fields.invoice_status_confirmed'),
                    ]),
            ])
            ->headerActions([])
            ->actions([
                PurchaseInvoiceResource::configureInvoiceTableActionGroup(Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->url(fn (Invoice $record) => PurchaseInvoiceResource::getUrl('edit', ['record' => $record->id]), true),

                    PurchaseInvoiceResource::purchaseReturnInvoiceTableAction(),

                    Tables\Actions\Action::make('payment_details')
                        ->label(__('fields.payment_details'))
                        ->icon('heroicon-o-currency-dollar')
                        ->color('success')
                        ->visible(fn (Invoice $record) => ! $record->paid)
                        ->url(fn (Invoice $record) => $record->getPaymentVoucherResourceUrl(), true),
                ])),
            ])
            ->bulkActions([]);
    }

    protected function canCreate(): bool
    {
        return false;
    }
}
