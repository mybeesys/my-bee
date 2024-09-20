<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ReceiptVoucherResource\Pages;
use App\Filament\Tenant\Resources\ReceiptVoucherResource\RelationManagers;
use App\Models\Acc4;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ReceiptVoucherResource extends Resource
{
    protected static ?string $model = ReceiptVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = "no";

    protected static ?string $slug = "transactions/receipt-vouchers";

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('fields.nav_group_transactions');
    }

    public static function getLabel(): ?string
    {
        return __('fields.receipt_voucher');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.receipt_vouchers');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }


    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([

                    hidden_user_id_field(),


                    TextInput::make('no')
                        ->label(__('fields.voucher_no'))
                        ->readOnly()
                        ->required(),

                    DatePicker::make('date')
                        ->label(__('fields.date'))
                        ->required()
                        ->seconds(false)
                        ->minDate(now()->subDays(30))
                        ->maxDate(now())
                        ->default(now())
                        ->displayFormat('d/m/Y'),

                    Forms\Components\Radio::make('for')
                        ->live()
                        ->label(__('fields.make_receipt_voucher_for'))
                        ->disabledOn(Pages\EditReceiptVoucher::class)
                        ->afterStateUpdated(function ($state, Set $set, $livewire) {
                            $set('acc4_code', null);
                            $set('invoice_id', null);
                            $livewire->data['payments'] = [];
//                            self::updateInvoiceProperties($record?->invoice, $livewire);
                        })
                        ->options([
                            'customer' => __('fields.receipt_voucher_for_customer'),
                            'other_entity' => __('fields.receipt_voucher_for_other_entity'),
                        ]),


                    Select::make('acc4_code')
                        ->live()
                        ->disabledOn(Pages\EditReceiptVoucher::class)
                        ->label(function (Get $get) {
                            if ($get('for') == "customer") {
                                return __('fields.client');
                            } else if ($get('for') == "other_entity") {
                                return __('fields.receipt_voucher_for_other_entity');
                            } else {
                                return "account";
                            }
                        })
                        ->searchable()
                        ->options(function (Get $get) {
                            if ($get('for') == "customer") {
                                return Acc4::asOptions(only_item_class: [Customer::class]);
                            } else if ($get('for') == "other_entity") {
                                return Acc4::asOptions(exclude_item_class: [Customer::class, Supplier::class, Product::class, ProductVariant::class, ProductExtra::class], with_code: true);
                            } else {
                                return [];
                            }
                        })
                        ->afterStateUpdated(function ($state, Set $set, $record, $livewire) {
                            $set('invoice_id', null);
                            self::updateInvoiceProperties($record?->invoice, $livewire);
                        })
                        ->required(),


                    Select::make('invoice_id')
                        ->visible(fn(Get $get) => $get('for') === "customer")
                        ->disabledOn(Pages\EditReceiptVoucher::class)
                        ->live()
                        ->label(__('fields.invoice_no'))
                        ->options(function (Get $get) {
                            $customer_id = Acc4::with('item')->firstWhere('code', $get('acc4_code'))?->item_id;

                            if ($customer_id) {
                                return Invoice::dropdownUnpaidForCustomer($customer_id, false);
                            }

                            return [];
                        })
                        ->afterStateHydrated(function (Get $get, $record, $livewire, Select $component) {
                            $options = [];

                            $invoice = self::getInvoice($livewire, $record);

                            if ($invoice) {
                                $options = Invoice::dropdownUnpaidForCustomer($invoice->customer_id, false);

                                $component->helperText($invoice->no);

                                self::updateInvoiceProperties($invoice, $livewire);

                            }

                            return $options;
                        })
                        ->afterStateUpdated(function (Set $set, $state, $livewire) {
                            if ($state) {
                                $invoice = Invoice::with('items', 'salesPayments')->find($state);

                                self::updateInvoiceProperties($invoice, $livewire);
                            }
                        })
                        ->required(),

                ])->columns(3),

                Forms\Components\Section::make([

                    Forms\Components\Placeholder::make('total_invoice')
                        ->label(__('fields.amount_money'))
                        ->dehydrated(false)
                        ->content(function ($livewire) {
                            $value = $livewire->data['total_invoice'];
                            return new HtmlString("<h3 style='color: #0464ff;font-weight: bold'>$value</h3>");
                        }),

                    Forms\Components\Placeholder::make('total_paid_amount')
                        ->label(__('fields.paid_amount'))
                        ->dehydrated(false)
                        ->content(function ($livewire) {
                            $value = $livewire->data['total_paid_amount'];
                            return new HtmlString("<h3 style='color: #0464ff;font-weight: bold'>$value</h3>");
                        }),

                    Forms\Components\Placeholder::make('total_unpaid_amount')
                        ->label(__('fields.unpaid_amount'))
                        ->dehydrated(false)
                        ->content(function ($livewire) {
                            $value = $livewire->data['total_unpaid_amount'];
                            return new HtmlString("<h3 style='color: #0464ff;font-weight: bold'>$value</h3>");
                        }),

                ])
                    ->visible(fn(Get $get) => $get('invoice_id') != null)
                    ->columns(3),


                Forms\Components\Section::make([

                    TableRepeater::make('payments')
                        ->required()
                        ->minItems(1)
                        ->label(__('fields.payments'))
                        ->relationship('payments')
                        ->addActionLabel(__('fields.add'))
                        ->defaultItems(1)
                        ->emptyLabel(__('fields.no_records_placeholder'))
                        ->columnSpan('full')
                        ->headers([
                            Header::make('credit_acc4_code')
                                ->width("200px")
                                ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                ->markAsRequired()
                                ->label(__('fields.account')),

                            Header::make('amount')
                                ->width("200px")
                                ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                ->markAsRequired()
                                ->label(__('fields.amount_money')),

                            Header::make('date')
                                ->width("200px")
                                ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                ->markAsRequired()
                                ->label(__('fields.date')),

                            Header::make('statement')
                                ->width("400px")
                                ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                ->markAsRequired()
                                ->label(__('fields.statement')),

                            Header::make('attachments')
                                ->width("120px")
                                ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                ->label(__('fields.attachments')),

                        ])
                        ->live(true)
                        ->afterStateHydrated(function ($record, $livewire, Set $set) {
                            self::updateInvoiceProperties($record?->invoice, $livewire);
                            self::updatePayments($record?->invoice, $livewire);
                        })
                        ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                            $data['amount'] = number_format($data['amount'] ?? 0, currency_decimals(), '.', '');
                            return $data;
                        })
                        ->afterStateUpdated(fn($record, $livewire, Set $set) => self::updatePayments($record?->invoice, $livewire))
                        ->deletable(function ($record, $state, Forms\Components\Repeater $component) {
                            return $record == null;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function ($data, $state, $record, $livewire) {
                            $amount = collect($state)->sum('amount');
                            $invoice = self::getInvoice($livewire, null);

                            if ($amount > $invoice?->getItemsCost(true, true, true)) {
                                fns()->sendWarning(__("fields.payments_are_bigger_than_invoice_amount"));
                                throw new Halt();
                            }
                            return $data;
                        })
                        ->itemLabel(fn(array $state): ?string => $state['amount'] ?? null)
                        ->addable(function ($livewire, $record) {
                            $invoice = self::getInvoice($livewire, $record);
                            if ($invoice) {
                                return $invoice->total_unpaid > 0;
                            }

                            return true;
                        })
                        ->schema([

                            Forms\Components\Hidden::make('model_type')->default(Invoice::class),
                            Forms\Components\Hidden::make('model_id')->formatStateUsing(function (Get $get) {
                                return $get('data.invoice_id', true);
                            }),

                            hidden_tenant_id_field(),

                            hidden_user_id_field(),

                            Forms\Components\Hidden::make('id')->default(null),

                            Forms\Components\Hidden::make('credit_acc4_code')
                                ->formatStateUsing(function (Get $get, $livewire) {
                                    //for customer
                                    $invoice = self::getInvoice($livewire, null);
                                    if ($invoice) {
                                        return $invoice->customer->acc4->code;
                                    }
                                    //for other_entity
                                    return $get('data.acc4_code', true);
                                }),

                            Select::make('debit_acc4_code')
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

                            TextInput::make('amount')
                                ->label(__('fields.amount_money'))
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(PHP_INT_MAX)
                                ->extraInputAttributes(['min' => 1, 'max' => PHP_INT_MAX])
                                ->disabled(fn($record) => $record !== null)
                                ->required(),

                            DatePicker::make('date')
                                ->label(__('fields.date'))
                                ->disabled(fn($record) => $record !== null)
                                ->seconds(false)
                                ->minDate(now()->subDays(90))
                                ->maxDate(now())
                                ->default(now())
                                ->displayFormat('d/m/Y'),

                            TextInput::make('statement')
                                ->required()
                                ->disabled(fn($record) => $record !== null)
                                ->label(__('fields.statement')),

                            SpatieMediaLibraryFileUpload::make('attachments')
                                ->label(__('fields.attachments'))
                                ->image()
                                ->reorderable()
                                ->openable()
                                ->downloadable()
                                ->multiple()
                                ->maxSize(2048)
                                ->disk('public')
                                ->previewable(false)
                                ->directory('receipt_voucher_payments'),

                        ]),
                ]),


                Forms\Components\Section::make([

                    TextInput::make('total_payments')
                        ->label(__('fields.total_payments'))
                        ->readOnly()
                        ->currency()
                        ->dehydrated(false),
                ])
                    ->columns(3),


            ]);
    }


    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.voucher_no')),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('fields.date'))
                    ->dateTime('M j, Y')
                    ->searchable(),

                Tables\Columns\TextColumn::make('for')
                    ->label(__('fields.entity'))
                    ->getStateUsing(function ($record) {
                        if ($record->for == "customer")
                            return __('fields.receipt_voucher_for_customer');

                        return __('fields.receipt_voucher_for_other_entity');
                    })
                    ->description(function ($record) {
                        if ($record->invoice?->customer)
                            return $record->invoice->customer->name;

                        return $record->acc4->name . " - " . $record->acc4->code;

                    })
                    ->url(function ($record) {
                        if ($record->invoice?->customer)
                            return CustomerResource::getUrl('edit', ['record' => $record->invoice->customer_id]);
                    }, true)
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice.no')
                    ->label(__('fields.invoice_no'))
                    ->url(function ($record) {
                        if ($record->invoice_id)
                            return SalesInvoiceResource::getUrl('edit', ['record' => $record->invoice_id]);
                    }, true)->color(Color::Sky),


                Tables\Columns\TextColumn::make('payments.amount')
                    ->label(__('fields.paid_amount'))
                    ->getStateUsing(function ($record) {
                        return main_currency_iso_code() . " " . format_amount($record->payments->sum('amount'));
                    })
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->label(__('fields.total'))
                        ->formatStateUsing(function ($state) {
                            return main_currency_iso_code() . " " . format_amount($state);
                        })),

                Tables\Columns\TextColumn::make('paid_amount_percent')
                    ->extraAttributes(function ($record) {
                        if ($record->invoice)
                            if (percent($record->invoice->total_paid, $record->invoice->getItemsCost(true, true, true)) > 0) {
                                return ['class' => 'text-success-700'];
                            }

                        return ['class' => 'text-danger-700'];
                    })
                    ->label(__('fields.paid_amount_percent'))
                    ->getStateUsing(function ($record) {
                        if ($record->invoice)
                            return format_amount(percent($record->invoice->total_paid, $record->invoice->getItemsCost(true, true, true))) . "%";
                    }),

                Tables\Columns\TextColumn::make('invoice_total')
                    ->label(__('fields.invoice_total'))
                    ->getStateUsing(function ($record) {
                        if ($record->invoice)
                            return main_currency_iso_code() . " " . format_amount($record->invoice->getItemsCost(true, true, true));
                    }),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('for')
                    ->label(__('fields.entity'))
                    ->multiple()
                    ->options([
                        'customer' => __('fields.client'),
                        'other_entity' => __('fields.receipt_voucher_for_other_entity')
                    ]),

                Tables\Filters\SelectFilter::make('invoice_id')
                    ->label(__('fields.invoice_no'))
                    ->multiple()
                    ->options(function () {
                        return Invoice::whereIn('id', ReceiptVoucher::all()->pluck('invoice_id')->toArray())->pluck('no', 'id')->toArray();
                    }),

                Tables\Filters\SelectFilter::make('acc4_code')
                    ->label(__('fields.account'))
                    ->multiple()
                    ->options(function () {
                        $options = [];
                        foreach (Acc4::whereIn('code', ReceiptVoucher::all()->pluck('acc4_code')->toArray())->get() as $acc) {
                            $options[$acc->code] = $acc->code . " - " . $acc->name;
                        }
                        return $options;
                    }),

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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                ])
            ])
            ->bulkActions([
            ]);
    }

    public static function getInvoice($livewire, $record): ?Invoice
    {
        if ($record and $record->invoice)
            return $record->invoice;

        return Invoice::find($livewire->data['invoice_id']);
    }

    public static function updateInvoiceProperties($invoice, $livewire)
    {
        if ($invoice) {
            $livewire->data['total_invoice'] = format_amount($invoice->getItemsCost(true, true, true));
            $livewire->data['total_paid_amount'] = format_amount($invoice->total_paid);
            $livewire->data['total_unpaid_amount'] = format_amount($invoice->total_unpaid);
        }
    }

    public static function updatePayments($invoice, $livewire)
    {
        $total_payments = 0;

        foreach ($livewire->data['payments'] ?? [] as $item) {
            $amount = $item['amount'] ?? 0;

            if (is_number($amount))
                $total_payments += $amount;
        }

        $livewire->data['total_payments'] = format_amount($total_payments);

    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['invoice.customer.acc4', 'acc4', 'invoice.order', 'invoice.salesPayments', 'user'])->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceiptVouchers::route('/'),
            'create' => Pages\CreateReceiptVoucher::route('/create'),
            'edit' => Pages\EditReceiptVoucher::route('/{record}/edit'),
        ];
    }

}
