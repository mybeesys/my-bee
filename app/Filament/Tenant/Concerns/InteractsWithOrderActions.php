<?php

namespace App\Filament\Tenant\Concerns;

use App\Filament\Tenant\Resources\ReceiptVoucherResource;
use App\Filament\Tenant\Resources\SalesInvoiceResource;
use App\Models\Order;
use App\Models\ReceiptVoucher;
use App\Services\OrderStatusService;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Illuminate\Support\HtmlString;

trait InteractsWithOrderActions
{
    public static function orderStatusService(): OrderStatusService
    {
        return app(OrderStatusService::class);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function orderChangeStatusFormSchema(Order $record): array
    {
        return [
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('status')
                    ->label(__('fields.status'))
                    ->live()
                    ->options(fn () => static::orderStatusService()->allowedStatusOptions($record))
                    ->default($record->status)
                    ->required(),

                Forms\Components\DatePicker::make('delivery_date')
                    ->label(__('fields.delivery_date'))
                    ->required()
                    ->default(today())
                    ->visible(fn (Get $get) => $get('status') === Order::$STATUS_COMPLETED),

                Forms\Components\DatePicker::make('canceled_date')
                    ->label(__('fields.canceled_date'))
                    ->required()
                    ->default(today())
                    ->visible(fn (Get $get) => $get('status') === Order::$STATUS_CANCELLED),

                Forms\Components\Textarea::make('canceled_reason')
                    ->label(__('fields.canceled_reason'))
                    ->visible(fn (Get $get) => $get('status') === Order::$STATUS_CANCELLED)
                    ->cols(5)
                    ->rows(5),

                TextInput::make('delivery')
                    ->label(__('fields.delivery_price'))
                    ->visible(fn (Get $get) => in_array($get('status'), [
                        Order::$STATUS_COMPLETED,
                        Order::$STATUS_DELIVERY_IN_PROGRESS,
                    ], true))
                    ->default($record->delivery)
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(PHP_INT_MAX)
                    ->formatStateUsing(fn ($state) => is_number($state) ? number_format($state, currency_decimals(), '.', '') : null)
                    ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX]),

                Forms\Components\Placeholder::make('completed_info')
                    ->visible(fn (Get $get) => $get('status') === Order::$STATUS_COMPLETED)
                    ->label(fn () => new HtmlString('<span class="text-sm text-gray-600 dark:text-gray-400">' . e(__('fields.order_complete_confirms_invoice')) . '</span>')),

                Forms\Components\Placeholder::make('cancelled_info')
                    ->visible(fn (Get $get) => $get('status') === Order::$STATUS_CANCELLED)
                    ->label(function () {
                        $msg = __('fields.order_will_be_locked_after_this_action');

                        return new HtmlString("<strong style='color: #ff301d;'> {$msg} </strong>");
                    }),
            ]),
        ];
    }

    public static function runOrderChangeStatus(Order $record, array $data): void
    {
        try {
            static::orderStatusService()->applyStatusChange($record, $data);
            fns()->saved();
        } catch (\Throwable $exception) {
            report($exception);
            fns()->displayException($exception);
        }
    }

    public static function makeOrderViewTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\ViewAction::make()
            ->url(fn (Order $record) => static::getUrl('view', ['record' => $record->id]), true);
    }

    public static function makeOrderChangeStatusTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('change_status')
            ->label(__('fields.change_status'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->disabled(fn (Order $record) => ! static::orderStatusService()->canChangeStatus($record))
            ->modalWidth(MaxWidth::Small)
            ->form(fn (Order $record) => static::orderChangeStatusFormSchema($record))
            ->action(fn (Order $record, array $data) => static::runOrderChangeStatus($record, $data));
    }

    public static function makeReviewSalesInvoiceTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('review_sales_invoice')
            ->label(__('fields.review_order_as_sales_invoice'))
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn (Order $record) => filled($record->invoice_id)
                && $record->invoice?->isEditable()
                && $record->status !== Order::$STATUS_CANCELLED)
            ->url(fn (Order $record) => SalesInvoiceResource::getUrl('edit', ['record' => $record->invoice->id]), true);
    }

    public static function makeConfirmSalesInvoiceTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('confirm_sales_invoice')
            ->label(__('fields.confirm_order_as_sales_invoice'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('fields.confirm_order_as_sales_invoice'))
            ->modalDescription(__('fields.confirm_order_as_sales_invoice_desc'))
            ->visible(fn (Order $record) => filled($record->invoice_id)
                && $record->invoice?->isEditable()
                && $record->status !== Order::$STATUS_CANCELLED)
            ->action(function (Order $record) {
                try {
                    static::orderStatusService()->confirmSalesInvoice($record);
                    Notification::make()
                        ->title(__('fields.invoice_confirmed_successfully'))
                        ->success()
                        ->send();
                } catch (\Throwable $exception) {
                    report($exception);
                    fns()->displayException($exception);
                }
            });
    }

    public static function makeCompletePaymentTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('complete_payment')
            ->label(__('fields.payment_details'))
            ->icon('heroicon-o-currency-dollar')
            ->color('primary')
            ->visible(fn (Order $record) => ! $record->invoice?->paid)
            ->url(function (Order $record) {
                if ($record->invoice->salesPayments->isEmpty()) {
                    return ReceiptVoucherResource::getUrl('create', [
                        'invoice_id' => $record->invoice->id,
                        'order_id' => $record->id,
                    ]);
                }

                $rv = ReceiptVoucher::findForInvoice($record->invoice->id);

                if ($rv) {
                    return ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id]);
                }
            }, true);
    }

    public static function configureOrderTableActionGroup(Tables\Actions\ActionGroup $group): Tables\Actions\ActionGroup
    {
        return InvoiceDocumentFormLayout::configureInvoiceTableActionGroup($group);
    }
}
