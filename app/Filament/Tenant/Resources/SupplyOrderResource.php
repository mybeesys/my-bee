<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\SupplyOrderResource\Pages;
use App\Filament\Tenant\Resources\SupplyOrderResource\RelationManagers;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Rules\UniqueTenantItemRule;
use App\Services\PricingService;
use App\Services\StockService;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SupplyOrderResource extends Resource
{
    protected static ?string $model = SupplyOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.supply_order', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_purchases');
    }

    public static function getLabel(): ?string
    {
        return __('fields.supply_order');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.supply_orders');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([

                    hidden_tenant_id_field(),
                    hidden_user_id_field(),

                    Forms\Components\TextInput::make('no')
                        ->label(__('fields.reference_code'))
                        ->readOnly()
                        ->required()
                        ->default(fn($record) => $record == null ? generate_no(SupplyOrder::class) : $record->no)
                        ->rules([new UniqueTenantItemRule(SupplyOrder::class, 'no', $form->getRecord()?->id)]),

                    Forms\Components\Select::make('supplier_id')
                        ->required()
                        ->label(__('fields.supplier'))
                        ->searchable()
                        ->options(Supplier::pluck('name', 'id'))
                        ->live()
                        ->createOptionForm(SupplierResource::getSchema())
                        ->createOptionUsing(function ($data) {
                            $data['tenant_id'] = filament()->getTenant()->id;
                            $model = Supplier::create($data);
                            return $model->id;
                        }),

                ])->columns(2),

                Forms\Components\Section::make()->schema([
                    Forms\Components\Textarea::make('description')
                        ->required()
                        ->label(__('fields.description'))
                        ->rows(5),
                ]),

                Forms\Components\Section::make()
                    ->key('details-section')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('add_product')
                            ->color(Color::Slate)
                            ->label(__('fields.add_product'))
                            ->modalSubmitActionLabel(__('fields.add'))
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

                                                    if ($product->type == Product::$TYPE_BASIC) {
                                                        $set('type', $product->type);
                                                        $set('name', $product->name);
                                                        $set('model_id', $product->id);
                                                        $set('model_type', Product::class);
                                                        $set('unit_price', number_format(PricingService::instance()->getRetailPrice($product), currency_decimals(), '.', ''));
                                                        $set('max_qty', StockService::instance()->getAvailableStock($product));
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
                                $product = Product::with(['variants'])->findOrFail($data['product_id']);

                                $existingDetails = $livewire->data['details'] ?? [];

                                $max_qty = $data['max_qty'];
                                $unlimited_qty = $data['unlimited_qty'] ?? false;

                                $productExtrasIds = extract_data_from_array_that_has_key_starts_with("px@", $data);

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
                                    $price = PricingService::instance()->getRetailPrice($variant);
                                    $unlimited_qty = $variant->unlimited_qty;
                                    $qty = 1;

                                    $item[Str::uuid()->toString()] = [
                                        'tenant_id' => $tenant_id,
                                        'item_id' => $model_id,
                                        'item_type' => $model_type,
                                        'type' => $type,
                                        'display_name' => $name,
                                        'max_qty' => 100000,
                                        'qty' => $qty,
                                    ];

                                } else {
                                    //basic

                                    $tenant_id = $data['tenant_id'];
                                    $type = $data['type'];
                                    $name = $data['name'];
                                    $model_id = $data['model_id'];
                                    $model_type = $data['model_type'];
                                    $price = $data['unit_price'];
                                    $max_qty = $data['max_qty'];
                                    $qty = 1;

                                    $item[Str::uuid()->toString()] = [
                                        'tenant_id' => $tenant_id,
                                        'item_id' => $model_id,
                                        'item_type' => $model_type,
                                        'type' => $type,
                                        'display_name' => $name,
                                        'max_qty' => 100000,
                                        'qty' => $qty,
                                    ];
                                }

                                $itemExists = collect($existingDetails)->where('item_id', $model_id)->where('item_type', $model_type)->first();

                                if ($itemExists) {
                                    fns()->sendWarning(__('fields.order_details_item_already_exists'));
                                    $action->halt();
                                }


                                foreach ($livewire->data['details'] as $index => $it) {
                                    if ($it['item_id'] == null and $it['item_type'] == null) {
                                        unset($livewire->data['details'][$index]);
                                        unset($existingDetails[$index]);
                                    }
                                }

                                $livewire->data['details'] = array_merge($existingDetails, $item);

                                fns()->saved();

                                $action->halt();
                            }),
                    ])
                    ->schema([
                        TableRepeater::make('details')
                            ->label(__('fields.order_details'))
//                            ->relationship('details')
                            ->emptyLabel(__('fields.no_records_placeholder'))
                            ->headers([
                                Header::make('display_name')
                                    ->width("165px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.name')),

                                Header::make('qty')
                                    ->width("80px")
                                    ->align(fn() => app()->getLocale() == "ar" ? Alignment::Left : Alignment::Right)
                                    ->markAsRequired()
                                    ->label(__('fields.qty')),
                            ])
                            ->addActionLabel(__('fields.add'))
                            ->addable(false)
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->mutateRelationshipDataBeforeFillUsing(function ($data, $record) {
                                $data['price'] = number_format($data['qty'] * $data['unit_price'], currency_decimals(), '.', '');
                                return $data;
                            })
                            ->live()
                            ->schema([

                                hidden_tenant_id_field(),

                                hidden_user_id_field(),

                                Forms\Components\Hidden::make('max_qty')->dehydrated(false),

                                Forms\Components\Hidden::make('type'),
                                Forms\Components\Hidden::make('item_id'),
                                Forms\Components\Hidden::make('item_type'),

                                TextInput::make('display_name')->label(__('fields.product'))->readOnly(),

                                TextInput::make('qty')
                                    ->label(__('fields.qty'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(fn(Forms\Get $get) => $get('max_qty'))
                                    ->live(true)
                                    ->afterStateHydrated(function ($record, Forms\Set $set) {
                                        if ($record) {
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

                            ])
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')->label(__('fields.reference_code'))->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')->label(__('fields.supplier'))->searchable(),
                Tables\Columns\TextColumn::make('description')->label(__('fields.description'))->limit(50)->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('make_purchases_invoice_from_supply_order')
                    ->label(__('fields.make_purchases_invoice_from_supply_order'))
                    ->requiresConfirmation()
                    ->color(Color::Green)
                    ->url(fn(SupplyOrder $record) => PurchaseInvoiceResource::getUrl('create', ['supply_order_id' => $record->id])),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()->action(function ($record){
                    $record->details()->delete();
                    $record->delete();
                    fns()->deleted();
                }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'index' => Pages\ListSupplyOrders::route('/'),
            'create' => Pages\CreateSupplyOrder::route('/create'),
            'edit' => Pages\EditSupplyOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['details.item', 'supplier'])->latest();
    }


    protected static function getVariantFieldsBasedOnOptions($product_id, $livewire): array
    {
        $fields = [];

        $product = Product::with(['variants', 'variantOptions'])->find($product_id);

        if ($product->type !== Product::$TYPE_VARIANTS)
            return [];

        $variantOptions = $product->variantOptions;

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

}
