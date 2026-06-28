<?php

namespace App\Filament\Tenant\Resources\OrderResource\Pages;

use App\Filament\Tenant\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('change_status')
                ->label(__('fields.change_status'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->disabled(fn () => ! OrderResource::orderStatusService()->canChangeStatus($this->record))
                ->modalWidth(MaxWidth::Small)
                ->form(fn () => OrderResource::orderChangeStatusFormSchema($this->record))
                ->action(fn (array $data) => OrderResource::runOrderChangeStatus($this->record, $data)),

            Actions\Action::make('review_sales_invoice')
                ->label(__('fields.review_order_as_sales_invoice'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn () => filled($this->record->invoice_id)
                    && $this->record->invoice?->isEditable()
                    && $this->record->status !== Order::$STATUS_CANCELLED)
                ->url(fn () => \App\Filament\Tenant\Resources\SalesInvoiceResource::getUrl('edit', [
                    'record' => $this->record->invoice->id,
                ]), true),

            Actions\Action::make('confirm_sales_invoice')
                ->label(__('fields.confirm_order_as_sales_invoice'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('fields.confirm_order_as_sales_invoice'))
                ->modalDescription(__('fields.confirm_order_as_sales_invoice_desc'))
                ->visible(fn () => filled($this->record->invoice_id)
                    && $this->record->invoice?->isEditable()
                    && $this->record->status !== Order::$STATUS_CANCELLED)
                ->action(function () {
                    try {
                        OrderResource::orderStatusService()->confirmSalesInvoice($this->record);
                        Notification::make()
                            ->title(__('fields.invoice_confirmed_successfully'))
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        report($exception);
                        fns()->displayException($exception);
                    }
                }),

            Actions\Action::make('complete_payment')
                ->label(__('fields.payment_details'))
                ->icon('heroicon-o-currency-dollar')
                ->color('primary')
                ->visible(fn () => ! $this->record->invoice?->paid)
                ->url(function () {
                    if ($this->record->invoice->salesPayments->isEmpty()) {
                        return \App\Filament\Tenant\Resources\ReceiptVoucherResource::getUrl('create', [
                            'invoice_id' => $this->record->invoice->id,
                            'order_id' => $this->record->id,
                        ]);
                    }

                    $rv = \App\Models\ReceiptVoucher::findForInvoice($this->record->invoice->id);

                    if ($rv) {
                        return \App\Filament\Tenant\Resources\ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id]);
                    }
                }, true),
        ];
    }
}
