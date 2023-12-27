<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OrderResource\Pages;
use App\Filament\Tenant\Resources\OrderResource\RelationManagers;
use App\Filament\Tenant\Resources\OrderResource\Widgets\OrderStats;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Services\InvoiceService;
use Awcodes\FilamentTableRepeater\Components\TableRepeater;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $slug = "orders";

    protected static ?int $navigationSort = 1;


    public static function getLabel(): ?string
    {
        return __('fields.order');
    }


    public static function getPluralLabel(): ?string
    {
        return __('fields.orders');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::new()->count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make()
                    ->disabled(fn($record) => $record and $record->status == Order::$STATUS_COMPLETED)
                    ->schema([

                        hidden_tenant_id_field(),

                        Select::make('customer_id')
                            ->required()
                            ->label(__('fields.client'))
                            ->searchable()
                            ->options(Customer::pluck('name', 'id'))
                            ->live()
                            ->createOptionForm(CustomerResource::getSchema())
                            ->createOptionUsing(function ($data) {
                                $data['tenant_id'] = filament()->getTenant()->id;
                                $model = Customer::create($data);
                                return $model->id;
                            })
                            ->createOptionAction(
                                fn(Forms\Components\Actions\Action $action) => $action->modalWidth(MaxWidth::Small),
                            )->afterStateUpdated(function ($state, Forms\Set $set) {
                                $customer = Customer::find($state);

                                if ($customer) {
                                    $set('delivery_address', $customer->delivery_address);
                                } else {
                                    $set('delivery_address', null);
                                }
                            }),

                        TextInput::make('delivery_address')
                            ->label(__('fields.delivery_address'))
                            ->required()
                            ->maxLength(255),
                    ])->columns(2),


                Forms\Components\Section::make()
                    ->disabled(fn($record) => $record and $record->status == Order::$STATUS_COMPLETED)
                    ->key('details-section')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('add_product')
                            ->disabled(fn($record) => $record and $record->status == Order::$STATUS_COMPLETED)
                            ->color('gray')
                            ->label(__('fields.add_product'))
                            ->modalSubmitActionLabel(__('fields.add'))
//                            ->extraModalFooterActions(fn (Forms\Components\Actions\Action $action): array => [
//                                $action->makeModalSubmitAction('createAnother', ['another' => true]),
//                            ])
                            ->form([
                                Forms\Components\Section::make()
                                    ->schema([

                                        hidden_tenant_id_field(),

                                        Forms\Components\Hidden::make('name'),
                                        Forms\Components\Hidden::make('type'),
                                        Forms\Components\Hidden::make('model_id'),
                                        Forms\Components\Hidden::make('model_type'),
                                        Forms\Components\Hidden::make('unit_price'),
                                        Forms\Components\Hidden::make('max_qty')->default(0),

                                        Forms\Components\Hidden::make('variant_options'),

                                        Select::make('product_id')
                                            ->label(__('fields.product'))
                                            ->required()
                                            ->live()
                                            ->searchable()
                                            ->options(Product::groupedAsOptions())
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if ($state) {
                                                    $product = Product::find($state);

                                                    if (!$product) {
                                                        $set('type', null);
                                                        $set('name', null);
                                                        $set('model_id', null);
                                                        $set('model_type', null);
                                                        $set('unit_price', null);
                                                        $set('max_qty', 0);
                                                        fns()->sendDanger("Product not found!");
                                                        return;
                                                    }

                                                    if ($product->type == Product::$TYPE_BASIC or $product->type == Product::$TYPE_SERVICE) {
                                                        $set('type', $product->type);
                                                        $set('name', $product->name);
                                                        $set('model_id', $product->id);
                                                        $set('model_type', Product::class);
                                                        $set('unit_price', number_format($product->getPrice(), currency_decimals(), '.', ''));
                                                        $set('max_qty', $product->qty);
                                                    }

                                                } else {
                                                    $set('type', null);
                                                    $set('name', null);
                                                    $set('model_id', null);
                                                    $set('model_type', null);
                                                    $set('unit_price', null);
                                                    $set('max_qty', 0);
                                                }
                                            }),


                                        Select::make('unit_id')
                                            ->visible(fn(Forms\Get $get) => Product::where('type', Product::$TYPE_UNITS)->where('id', $get('product_id'))->first())
                                            ->required()
                                            ->label(__('fields.unit'))
                                            ->live()
                                            ->options(function (Forms\Get $get) {
                                                $product = Product::with(['units'])->where('type', Product::$TYPE_UNITS)->where('id', $get('product_id'))->first();

                                                if ($product) {
                                                    return $product->unitsAsOptions();
                                                }
                                            })
                                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                                if ($state) {
                                                    $product = Product::with(['mainUnit'])->find($get('product_id'));

                                                    $productUnit = ProductUnit::with(['product', 'unit'])->firstWhere('unit_id', $state);


                                                    //watch out of main unit because its has no model it's linked with product model

                                                    if ($product->main_unit_id != $state and !$productUnit) {
                                                        $set('type', null);
                                                        $set('name', null);
                                                        $set('model_id', null);
                                                        $set('model_type', null);
                                                        $set('unit_price', null);
                                                        $set('max_qty', 0);
                                                        fns()->sendDanger("Unit not found!");
                                                        return;
                                                    }

                                                    //main unit
                                                    if ($product->main_unit_id == $state) {

                                                        $set('type', $product->type);
                                                        $set('name', $product->name . " - " . $product->mainUnit?->name);
                                                        $set('model_id', $product->id);
                                                        $set('model_type', Product::class);
                                                        $set('unit_price', number_format($product->getPrice(), currency_decimals(), '.', ''));
                                                        $set('max_qty', $product->qty);
                                                    } else { //product with separate productunit model
                                                        $set('type', $product->type);
                                                        $set('name', $product->name . " - " . $productUnit->unit->name);
                                                        $set('model_id', $productUnit->id);
                                                        $set('model_type', ProductUnit::class);
                                                        $set('unit_price', number_format($productUnit->retail_price, currency_decimals(), '.', ''));
                                                        $set('max_qty', $productUnit->qty);
                                                    }
                                                } else {
                                                    $set('type', null);
                                                    $set('name', null);
                                                    $set('model_id', null);
                                                    $set('model_type', null);
                                                    $set('unit_price', null);
                                                    $set('max_qty', 0);
                                                }
                                            }),


                                        Forms\Components\Fieldset::make(__('fields.options'))
                                            ->visible(fn(Forms\Get $get) => Product::where('type', Product::$TYPE_VARIANTS)->where('id', $get('product_id'))->first())
                                            ->schema(function (Forms\Get $get, $livewire) {
                                                $product_id = $get('product_id');
                                                if ($product_id)
                                                    return self::getVariantFieldsBasedOnOptions($product_id, $livewire);

                                                return [];
                                            }),

                                    ])->columns(2)
                            ])
                            ->action(function (array $data, $livewire, Forms\Components\Actions\Action $action, array $arguments) {

//                                dd($arguments, $action->getArguments());
                                $product = Product::with(['units', 'variants'])->findOrFail($data['product_id']);

                                $existingDetails = $livewire->data['details'] ?? [];

                                $max_qty = $data['max_qty'];
                                $unlimited_qty = $data['unlimited_qty'] ?? false;

                                if ($product->type == Product::$TYPE_VARIANTS) {

                                    $variantOptions = extract_data_from_array_that_has_key_starts_with("vo@", $data);
                                    $variantLibraryOptions = extract_values_from_array_that_has_key_starts_with("vo@", $data);

                                    if (count($variantOptions) < 0) {
                                        fns()->sendDanger("Something went-wrong!");
                                        $action->halt();
                                    }

                                    //check if variant is available

                                    $variant = $product->Variants->filter(function ($item) use ($variantLibraryOptions) {
                                        $array1 = $item->variant_library_options_ids;
                                        $array2 = $variantLibraryOptions;
                                        return array_diff($array1, $array2) == array_diff($array2, $array1);
                                    })->first();

                                    if (!$variant) {
                                        fns()->sendDanger("Option not found");
                                    }

                                    $tenant_id = $data['tenant_id'];
                                    $type = "variants";
                                    $name = $variant->name;
                                    $model_id = $variant->id;
                                    $model_type = ProductVariant::class;
                                    $price = $variant->retail_price;
                                    $unlimited_qty = $variant->unlimited_qty;
                                    $max_qty = $unlimited_qty ? 1000 : $variant->qty;
                                    $qty = 1;

                                    if (!$unlimited_qty and $max_qty == 0) {
                                        fns()->sendWarning(__('fields.no_stock_available'));
                                        $action->halt();
                                    }

                                    if (!$price or $price == 0) {
                                        fns()->sendWarning(__('fields.product_variant_not_priced'));
                                        $action->halt();
                                    }

                                    $item[Str::uuid()->toString()] = [
                                        'tenant_id' => $tenant_id,
                                        'item_id' => $model_id,
                                        'item_type' => $model_type,
                                        'type' => $type,
                                        'display_name' => $name,
                                        'max_qty' => $max_qty,
                                        'qty' => $qty,
                                        'unit_price' => $price,
                                        'price' => format_amount($qty * $price),
                                    ];

                                } else {

                                    $tenant_id = $data['tenant_id'];
                                    $type = $data['type'];
                                    $name = $data['name'];
                                    $model_id = $data['model_id'];
                                    $model_type = $data['model_type'];
                                    $price = $data['unit_price'];
                                    $max_qty = $data['max_qty'];

                                    if (!$unlimited_qty and $max_qty == 0) {
                                        fns()->sendWarning(__('fields.no_stock_available'));
                                        $action->halt();
                                    }

                                    if (!$price or $price == 0) {
                                        fns()->sendWarning(__('fields.product_variant_not_priced'));
                                        $action->halt();
                                    }

                                    $item[Str::uuid()->toString()] = [
                                        'tenant_id' => $tenant_id,
                                        'item_id' => $model_id,
                                        'item_type' => $model_type,
                                        'type' => $type,
                                        'display_name' => $name,
                                        'max_qty' => $max_qty,
                                        'qty' => $max_qty,
                                        'unit_price' => $price,
                                        'price' => format_amount($max_qty * $price),
                                    ];
                                }

                                $itemExists = collect($existingDetails)->where('item_id', $model_id)->where('item_type', $model_type)->first();

                                if ($itemExists) {
                                    fns()->sendWarning(__('fields.order_details_item_already_exists'));
                                    $action->halt();
                                }


                                $livewire->data['details'] = array_merge($existingDetails, $item);

                                self::updateTotal($livewire);

//                                dd($arguments, $action->getArguments());
//                                fns()->sendWarning($arguments['another'] ?? false);
//
//                                if ($arguments['another'] ?? false) {
//                                    fns()->sendWarning('another');
//                                    return;
//                                }

                                fns()->saved();

                                $action->halt();
                            }),
                    ])
                    ->schema([
                        TableRepeater::make('details')
                            ->label(__('fields.order_details'))
                            ->relationship('details')
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->addActionLabel(__('fields.add'))
                            ->alignHeaders(fn() => app()->getLocale() == "ar" ? "right" : "left")
                            ->hideLabels()
                            ->addable(false)
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->afterStateHydrated(fn($livewire) => self::updateTotal($livewire))
                            ->afterStateUpdated(fn($livewire) => self::updateTotal($livewire))
                            ->mutateRelationshipDataBeforeFillUsing(function ($data, $record) {
                                $data['price'] = number_format($data['qty'] * $data['unit_price'], currency_decimals(), '.', '');
                                return $data;
                            })
                            ->live()
                            ->schema([

                                hidden_tenant_id_field(),

                                Forms\Components\Hidden::make('max_qty')->dehydrated(false),

                                Forms\Components\Hidden::make('type'),
                                Forms\Components\Hidden::make('item_id'),
                                Forms\Components\Hidden::make('item_type'),
                                Forms\Components\Hidden::make('unit_price'),

                                TextInput::make('display_name')->label(__('fields.product'))->readOnly(),

                                TextInput::make('qty')
                                    ->label(__('fields.qty'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(fn(Forms\Get $get) => $get('max_qty'))
                                    ->live(true)
                                    ->afterStateHydrated(function ($record, Forms\Set $set){
                                        if($record){
//                                            $set('max_qty', $record->item->inventory_count ?? 0);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state) {
                                            $set('price', format_amount($state * $get('unit_price')));
                                        }
                                    })
                                    ->extraInputAttributes(function (Forms\Get $get) {
                                        return [
                                            'min' => 1,
                                            'max' => $get('max_qty'),
                                        ];
                                    }),

                                TextInput::make('price')
                                    ->label(__('fields.price'))
                                    ->prefixIcon('heroicon-o-calculator')
                                    ->suffix(fn() => main_currency_iso_code())
                                    ->dehydrated(false)
                                    ->readOnly(),

                            ])
                    ]),

                Forms\Components\Section::make()->schema([
                    TextInput::make('total')
                        ->label(__('fields.total'))
                        ->readOnly()
                        ->suffix(fn() => main_currency_iso_code())
                        ->dehydrated(false),

                ])->columns(4),

            ]);
    }


    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.order_no'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('fields.client'))
                    ->searchable(),
//                    ->url(function (Order $record) {
//                        return CustomerRe::getUrl("edit", $record->client_id);
//                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('fields.status'))
                    ->getStateUsing(fn($record) => __('fields.order_status_' . $record->status))
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->label(__('fields.payment_status'))
                    ->getStateUsing(fn(Order $record) => $record->invoice?->payment_status),

                Tables\Columns\TextColumn::make('sub_total')
                    ->toggleable()
                    ->label(__('fields.sub_total'))
                    ->tooltip(function ($record) {
                        return format_amount($record->sub_total);
                    })
                    ->getStateUsing(function (Order $record) {
                        return main_currency_iso_code() . " " . format_amount($record->sub_total);
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->toggleable()
                    ->label(__('fields.total'))
//                    ->tooltip('sub total + delivery price + additional delivery price - discount')
                    ->getStateUsing(function (Order $record) {
                        return main_currency_iso_code() . " " . format_amount($record->total);
                    }),

                Tables\Columns\TextColumn::make('delivery_type')
                    ->toggleable()
                    ->label(__('fields.delivery_type'))
                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('payment_method')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.payment_method'))
//                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('other_payment_method')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.other_payment_method'))
//                    ->searchable(),

                Tables\Columns\TextColumn::make('delivery_address')
                    ->toggleable()
                    ->label(__('fields.delivery_address'))
                    ->searchable(),

//                Tables\Columns\TextColumn::make('discount')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.discount'))
//                    ->searchable(),

//                Tables\Columns\TextColumn::make('delivery')
//                    ->toggleable()
//                    ->label(__('fields.delivery_price'))
//                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('delivery_extra')
//                    ->toggleable()
//                    ->label(__('fields.additional_delivery_price'))
//                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.order_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.delivery_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_date')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.paid_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('canceled_date')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.canceled_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('fields.status'))
                    ->multiple()
                    ->options([
                        Order::$STATUS_READY => __('fields.order_status_'.Order::$STATUS_READY),
                        Order::$STATUS_DELIVERY_IN_PROGRESS => __('fields.order_status_'.Order::$STATUS_DELIVERY_IN_PROGRESS),
                        Order::$STATUS_CANCELLED => __('fields.order_status_'.Order::$STATUS_CANCELLED),
                        Order::$STATUS_COMPLETED => __('fields.order_status_'.Order::$STATUS_COMPLETED),
                    ]),


                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('fields.client'))
                    ->multiple()
                    ->options(Order::with('customer')->get()->pluck('customer.name', 'customer.id')),


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

                    Tables\Actions\ViewAction::make(),

//                    Tables\Actions\Action::make('download_invoice')
//                        ->label(__('fields.download_invoice'))
//                        ->icon('heroicon-o-download')
//                        ->color('success')
//                        ->action(function (Order $record) {
//                            $invoice = (new InvoiceService())
//                                ->filePath('invoices/orders')
//                                ->getInvoice($record->invoice, 0, $record->delivery, [
//                                    'phone' => $record->client->phone,
//                                    'delivery address' => $record->delivery_address,
//                                    'delivery fees' => number_format($record->delivery, 2) . ' SDG',
//                                    'status' => $record->invoice->paid ? "Paid" : "Unpaid",
//                                ]);
//
//                            Notification::make()
//                                ->title('Invoice download completed')
//                                ->success()
//                                ->persistent()
//                                ->actions([
//                                    Action::make('view')
//                                        ->label('View file')
//                                        ->button()
//                                        ->url($invoice->url(), shouldOpenInNewTab: true)
//                                ])
//                                ->send();
//                        }),

                    Tables\Actions\Action::make('change_status')
                        ->label(__('fields.change_status'))
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->disabled(fn($record) => $record->status == Order::$STATUS_COMPLETED)
                        ->modalWidth(MaxWidth::Small)
                        ->form(function (Order $record) {
                            return [
                                Forms\Components\Section::make()->schema([
                                    Select::make('status_id')
                                        ->label(__('fields.status'))
                                        ->options([
                                            Order::$STATUS_READY => __('fields.order_status_'.Order::$STATUS_READY),
                                            Order::$STATUS_DELIVERY_IN_PROGRESS => __('fields.order_status_'.Order::$STATUS_DELIVERY_IN_PROGRESS),
                                            Order::$STATUS_CANCELLED => __('fields.order_status_'.Order::$STATUS_CANCELLED),
                                            Order::$STATUS_COMPLETED => __('fields.order_status_'.Order::$STATUS_COMPLETED),
                                        ])
                                        ->default($record->status)
                                        ->required(),
                                ]),
                            ];
                        })
                        ->action(function (Order $record, array $data) {
                            $record->update(['status' => $data['status_id']]);
                            fns()->saved();
                        }),


                    Tables\Actions\Action::make('view_invoice')
                        ->label(__('fields.view_invoice'))
                        ->icon('heroicon-o-pencil')
                        ->color('gray')
                        ->url(fn(Order $record) => SalesInvoiceResource::getUrl('edit', ['record' => $record->invoice->id]), true),

                    Tables\Actions\Action::make('complete_payment')
                        ->label(__('fields.complete_payment'))
                        ->icon('heroicon-o-pencil')
                        ->color('success')
                        ->visible(function ($record) {
                            return !$record->invoice?->paid;
                        })
                        ->action(function (Order $record) {
                            if ($record->invoice->salesPayments->isEmpty()) {
                                return redirect(ReceiptVoucherResource::getUrl('create', ['invoice_id' => $record->invoice->id, 'order_id' => $record->id]));
                            }

                            $rv = ReceiptVoucher::whereInvoiceId($record->id)->first();

                            if ($rv)
                                return redirect(ReceiptVoucherResource::getUrl('edit', ['rv' => $rv->id]));

                        }),

                ]),
            ])
            ->bulkActions([
            ]);
    }

    protected static function updateTotal($livewire)
    {
        $total = 0;

        foreach ($livewire->data['details'] ?? [] as $index => $item) {
            if (is_number($item['qty']) and is_number($item['unit_price']))
                $total += $item['qty'] * $item['unit_price'];
        }

        $livewire->data['total'] = format_amount($total);

        foreach ($livewire->data['details'] ?? [] as $index => $item) {
            if ($item['display_name'] === null)
                unset($livewire->data['details'][$index]);
        }
    }

    protected static function getVariantFieldsBasedOnOptions($product_id, $livewire): array
    {
        $fields = [];

        $product = Product::with(['variants', 'variantOptions'])->find($product_id);

        if ($product->type !== Product::$TYPE_VARIANTS)
            return [];

//        $library_options = array_filter($product->variants->pluck('variant_library_options_ids')->flatten()->unique()->toArray());
//
        $variantOptions = $product->variantOptions;

//        dd($variantLibs);
//
//        foreach ($library_options as $option) {
//            $item = self::getVariantLibraryFromOption($option);
//            $variantLibs->add($item);
//        }
//
//        $variantLibs = $variantLibs->unique('id');

        foreach ($variantOptions as $variantOption) {
            $lib = $variantOption->library;

            $options = VariantLibraryOption::findMany($variantOption->values);

            $fields[] = Select::make("vo@$lib->id")
                ->required()
                ->label($lib->name)
                ->options($options->pluck('name', 'id'));
        }

        $livewire->mountedFormComponentActionsData[0]['variant_options'] = json_encode($variantOptions->pluck('id')->toArray());

        return $fields;
    }


    protected static function getVariantLibraryFromOption($option_id): VariantLibrary
    {

        $variantLibraries = Cache::remember("variantLibraries@" . \filament()->getTenant()->id, 60, function () {
            return VariantLibrary::with(['options'])->get();
        });

        $vl = $variantLibraries->filter(function ($item) use ($option_id) {
            return in_array($option_id, $item->options->pluck('id')->toArray());
        })->first();

        if (!$vl)
            $vl = VariantLibrary::with(['options'])->whereHas('options', function ($q) use ($option_id) {
                return $q->where('id', $option_id);
            })->first();

        return $vl;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['invoice.items', 'invoice.salesPayments', 'customer', 'user', 'details.item'])->latest();
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
            OrderStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\OrderResource\Pages\ListOrders::route('/'),
            'create' => \App\Filament\Tenant\Resources\OrderResource\Pages\CreateOrder::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\OrderResource\Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
