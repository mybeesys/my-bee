<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteMediaByFileNameRequest;
use App\Http\Requests\GenerateNoRequest;
use App\Http\Requests\GetStoreCartRequest;
use App\Http\Requests\GetStoreCategoriesRequest;
use App\Http\Requests\GetStoreProductsRequest;
use App\Http\Requests\GetVariantInfoRequest;
use App\Http\Requests\ListProductsForAdvancedCreationRequest;
use App\Http\Requests\StoreApplyCouponRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\StoreCheckoutRequest;
use App\Http\Requests\StoreDeleteCartItemRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateStoreCartRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ListProductsForAdvancedCreationResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PriceOfferResource;
use App\Http\Resources\ProductExtraResource;
use App\Http\Resources\StoreCategoryResource;
use App\Http\Resources\StoreProductResource;
use App\Http\Resources\StoreProductVariantResource;
use App\Http\Resources\SupplyOrderResource;
use App\Models\AdditionalCost;
use App\Models\AdditionalCostType;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\SupplyOrder;
use App\Models\Tenant;
use App\Services\CacheService;
use App\Services\CouponService;
use App\Services\MathService;
use App\Services\MediaService;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StoreController extends BaseController
{

    /**
     * Display a listing of the resource.
     */
    public function info(Request $request): \Illuminate\Http\JsonResponse
    {
        $store_slug = $request->header('Store-Slug');

        $tenant = Tenant::where('slug', $store_slug)->firstOrFail();

        $data = [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo' => MediaService::mediaUrls($tenant->getMedia('logos'), true),
            'cover' => $tenant->cover,
            'heroTitle' => $tenant->store_title ?? "My bee- ماي بي",
            'bio' => $tenant->store_bio ?? "",
            'trn' => $tenant->trn ?? "",
            'address' => $tenant->store_address ?? "",
            'phone' => $tenant->phone ?? "",
            'email' => $tenant->email ?? "",
            'stockTrackingEnabled' => $tenant->store_enable_stock_tracking == true,
            'ordersTrackingEnabled' => $tenant->store_orders_tracking_mode != null,
            'workingHours' => $tenant->store_working_hours ?? "",
            'social' => [
                'facebook' => $tenant->store_social_media_links['facebook'] ?? "",
                'instagram' => $tenant->store_social_media_links['instagram'] ?? "",
                'twitter' => $tenant->store_social_media_links['twitter'] ?? "",
                'youtube' => $tenant->store_social_media_links['youtube'] ?? "",
                'snapchat' => $tenant->store_social_media_links['snapchat'] ?? "",
                'whatsapp' => $tenant->store_social_media_links['whatsapp'] ?? "",
            ]
        ];

        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }

    /**
     * Display a listing of the resource.
     */
    public function categories(GetStoreCategoriesRequest $request): \Illuminate\Http\JsonResponse
    {
        $store_slug = $request->header('Store-Slug');
        $categories_ids = $request->categories_ids;
        $query = Category::query()->with(['tenant', 'products.variantOptions.library', 'products.extras.extra']);

        $query->whereHas('tenant', function (Builder $q) use ($store_slug) {
            $q->where('slug', $store_slug);
        });

        $query->when($categories_ids, function (Builder $q) use ($categories_ids) {
            $q->whereIn('id', $categories_ids);
        });

        $data = $query->orderBy('sort')->get();

        return $this->responder(__('messages.api.retrieved'), 200, StoreCategoryResource::collection($data))->respond();
    }

    /**
     * Display a listing of the resource.
     */
    public function products(GetStoreProductsRequest $request): \Illuminate\Http\JsonResponse
    {
        $store_slug = $request->header('Store-Slug');

        $categories_ids = $request->categories_ids;
        $query = Product::query()->with(['tenant', 'category', 'extras.extra', 'variantOptions.library']);

        $query->whereHas('tenant', function (Builder $q) use ($store_slug) {
            $q->where('slug', $store_slug);
        });

        $query->when($categories_ids, function (Builder $q) use ($categories_ids) {
            $q->whereIn('category_id', $categories_ids);
        });

        $data = $query->orderBy('sort')->get();

        return $this->responder(__('messages.api.retrieved'), 200, StoreProductResource::collection($data))->respond();
    }

    public function getVariantInfo(GetVariantInfoRequest $request)
    {
        $store_slug = $request->header('Store-Slug');

        $product = Product::with(['tenant', 'variantOptions.library', 'variants.product', 'extras.extra'])
            ->whereHas('tenant', function (Builder $builder) use ($store_slug) {
                $builder->where('slug', $store_slug);
            })
            ->where('id', $request->product_id)->firstOrFail();

        $variants_options_id = $request->variants_options_id;

        $productVariant = self::getExistingVariantByCombination($product, $variants_options_id);

        if ($productVariant)
            return $this->responder(__('messages.api.retrieved'), 200, new StoreProductVariantResource($productVariant))->respond();
        else
            return $this->errorNotFound()->respond();

    }


    public function cart(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, self::getCart())->respond();
    }

    public function addToCart(StoreCartItemRequest $request): \Illuminate\Http\JsonResponse
    {
        $uuid = \request()->header('Store-UUID');
        $store_slug = \request()->header('Store-Slug');

        $cart = self::getCart();

        $product = Product::with(['extras.extra'])->findOrFail($request->product_id);

        $extras = $product->extras->whereIn('id', $request->product_extras_ids ?? []);

        if ($extras->count() != count($request->product_extras_ids ?? []))
            return $this->responder("Some or all products extra does not belong to this product.", 400, [])->respond();

        $new_item = [
            'uuid' => $uuid,
            'id' => Str::random(8),
            'storeSlug' => $store_slug,
            'image' => null,
            'name' => null,
            'type' => null,
            'productId' => $product->id,
            'productVariantId' => null,
            'qty' => 0,
            'maxQty' => 1,
            'unitPrice' => 0,
            'unitPriceFormatted' => 0,
            'price' => 0,
            'priceFormatted' => 0,
            'extras' => $this->convertExtrasToArray($extras),
            'createdAt' => now()->toString(),
            'updatedAt' => null,
        ];

        if ($product->type == Product::$TYPE_BASIC) {

            $exists = collect($cart['items'] ?? [])->where('productId', $product->id)->isNotEmpty();
            if ($exists)
                return $this->responder(__('fields.order_details_item_already_exists'), 400, [])
                    ->code(self::$CODE_CART_ITEM_ALREADY_EXISTS)
                    ->respond();

            $available_stock = StockService::instance()->getAvailableStock($product);
            $new_item['image'] = null;
            $new_item['name'] = $product->name;
            $new_item['type'] = 'basic';
            $new_item['unitPrice'] = number_format(PricingService::instance()->getRetailPrice($product), currency_decimals(), '.', '');
            $new_item['unitPriceFormatted'] = number_format(PricingService::instance()->getRetailPrice($product), currency_decimals(), '.', ',') . " " . main_currency_native_symbol();
            $new_item['price'] = number_format(PricingService::instance()->getRetailPrice($product), currency_decimals(), '.', '');
            $new_item['priceFormatted'] = number_format(PricingService::instance()->getRetailPrice($product), currency_decimals(), '.', ',') . " " . main_currency_native_symbol();
            $new_item['maxQty'] = $available_stock;
//            $new_item['qty'] = ($request->qty and $available_stock >= $request->qty) ? $request->qty : 0;
            $new_item['qty'] = $request->input('qty', "1");

        } elseif ($product->type == Product::$TYPE_VARIANTS) {
            $productVariant = self::getExistingVariantByCombination($product, $request->variants_options_ids ?? []);

            if (!$productVariant)
                return $this->errorNotFound("Variant not found")->respond();

            $exists = collect($cart['items'] ?? [])
                ->where('productId', $product->id)
                ->where('productVariantId', $productVariant->id)
                ->isNotEmpty();

            if ($exists)
                return $this->responder(__('fields.order_details_item_already_exists'), 400, [])
                    ->code(self::$CODE_CART_ITEM_ALREADY_EXISTS)
                    ->respond();

            $available_stock = StockService::instance()->getAvailableStock($productVariant);

            $new_item['image'] = null;
            $new_item['name'] = $productVariant->name;
            $new_item['type'] = 'variants';
            $new_item['productVariantId'] = $productVariant->id;
            $new_item['unitPrice'] = number_format(PricingService::instance()->getRetailPrice($productVariant), currency_decimals(), '.', '');
            $new_item['unitPriceFormatted'] = number_format(PricingService::instance()->getRetailPrice($productVariant), currency_decimals(), '.', ',') . " " . main_currency_native_symbol();
            $new_item['price'] = number_format(PricingService::instance()->getRetailPrice($productVariant), currency_decimals(), '.', '');
            $new_item['priceFormatted'] = number_format(PricingService::instance()->getRetailPrice($productVariant), currency_decimals(), '.', ',') . " " . main_currency_native_symbol();
            $new_item['maxQty'] = $available_stock;
//            $new_item['qty'] = ($request->qty and $available_stock >= $request->qty) ? $request->qty : 0;
            $new_item['qty'] = $request->input('qty', "1");

        } else {
            throw new \Exception("Unknown product type");
        }

        if ($new_item['qty'] > 0) {
            $items = array_merge($cart['items'] ?? [], [$new_item]);
            $newCart = $this->generateCartData($items);
            CacheService::instance()->put("cart@$uuid", $newCart);
        }

        return $this->responder(__('messages.api.retrieved'), 200, self::getCart())->respond();
    }

    public function updateCart(UpdateStoreCartRequest $request): \Illuminate\Http\JsonResponse
    {
        $uuid = \request()->header('Store-UUID');

        $cartItems = $this->getCart()['items'];
        $id = $request->id;
        $qty = $request->qty;
        $product_extras_ids_to_add = $request->product_extras_ids_to_add ?? [];

        if ($qty <= 0)
            return $this->responder(__('messages.api.retrieved'), 200, self::getCart())->respond();

        $item = collect($cartItems)->filter(fn($item) => $item['id'] == $id)->first();
        if (count($product_extras_ids_to_add) > 0) {
            if ($item) {
                $product = Product::with('extras.extra')->findOrFail($item['productId']);
                $extras = $product->extras->whereIn('id', $product_extras_ids_to_add);

                if ($extras->count() != count($product_extras_ids_to_add))
                    return $this->responder("Some or all products extra does not belong to this product.", 400, [])->respond();

                $cartItems = collect($cartItems)->map(function ($item, $key) use ($id, $extras) {
                    if ($item['id'] == $id) {
                        $existingExtra = $item['extras'];
                        $newExtras = [];
                        foreach ($extras as $productExtra) {
                            if (collect($existingExtra)->where('id', $productExtra->id)->first() == null) {
                                $newExtras[] = $this->convertExtraToArray($productExtra);
                            }
                        }

                        $data = array_merge($existingExtra, $newExtras);
                        $item['extras'] = array_values($data);
                    }
                    return $item;
                })->toArray();

                $newCart = $this->generateCartData($cartItems);

                CacheService::instance()->put("cart@$uuid", $newCart);

            } else {
                return $this->errorNotFound()->respond();
            }
        }
        if ($qty) {
            if ($item) {
                $cartItems = collect($cartItems)->map(function ($item, $key) use ($id, $qty) {
                    if ($item['id'] == $id) {
                        $item['qty'] = $qty;// < $item['maxQty'] ? intval($qty) : $item['maxQty'];
                        $item['price'] = number_format($item['unitPrice'] * $qty, currency_decimals(), '.', '');
                        $item['priceFormatted'] = number_format($item['unitPrice'] * $qty, currency_decimals(), '.', ',') . " " . main_currency_native_symbol();
                        $item['updatedAt'] = now();
                    }
                    return $item;
                })->toArray();

                $newCart = $this->generateCartData($cartItems);

                CacheService::instance()->put("cart@$uuid", $newCart);
            } else {
                return $this->errorNotFound()->respond();
            }
        }
        return $this->responder(__('messages.api.retrieved'), 200, self::getCart())->respond();
    }

    public function deleteCartItem(StoreDeleteCartItemRequest $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->id;
        $product_extras_ids_to_remove = $request->product_extras_ids_to_remove ?? [];

        $uuid = \request()->header('Store-UUID');

        $cartItems = $this->getCart()['items'] ?? [];

        if (count($product_extras_ids_to_remove) == 0) {
            //remove item by id
            $cartItems = collect($cartItems)->reject(fn($item) => $item['id'] == $id)->toArray();
        }

        //remove product_extra by id and product_extra_id
        $cartItems = collect($cartItems)->map(function ($item, $key) use ($id, $product_extras_ids_to_remove, $cartItems) {
            if ($item['id'] == $id) {
                foreach ($product_extras_ids_to_remove as $id_to_remove) {
                    foreach ($item['extras'] as $index => $extra) {
                        if ($id_to_remove == $extra['id']) {
                            unset($item['extras'][$index]);
                        }
                    }
                }
            }
            return $item;
        })->toArray();

        $cartItems = array_values($cartItems);

        $newCart = $this->generateCartData($cartItems);

        CacheService::instance()->put("cart@$uuid", $newCart);
        return $this->responder(__('messages.api.retrieved'), 200, self::getCart())->respond();
    }

    public function clearCart(): \Illuminate\Http\JsonResponse
    {
        $uuid = \request()->header('Store-UUID');
        Cache::put("coupon_code@$uuid", null);

        CacheService::instance()->put("cart@$uuid", $this->getDefaultCartData());
        return $this->responder(__('messages.api.retrieved'), 200, self::getCart())->respond();
    }

    public function checkout(StoreCheckoutRequest $request): \Illuminate\Http\JsonResponse
    {

        $tenant = Tenant::firstWhere('slug', \request()->header('Store-Slug'));

        $data = $request->validated();

        $cart = self::getCart();

        $items = $cart['items'] ?? [];

        if (count($items) == 0)
            return $this->errorBadRequest()->code(self::$CODE_CART_IS_EMPTY)->respond();

        //steps
//        1- create or get customer by phone
//        2- create order
//        3 - create order details,
//        4- create invoice
//        5- create invoice items
//        6- clear cart

        try {
            DB::beginTransaction();

            $customer = Customer::firstOrCreate([
                'phone' => $data['phone'],
            ], [
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'state_id' => $data['state_id'],
                'city_id' => $data['city_id'],
                'area_id' => $data['area_id'] ?? null,
                'delivery_address' => $data['delivery_address'],
                'email' => $data['email'] ?? null
            ]);

            $order = Order::create([
                'tenant_id' => $tenant->id,
                'source' => 'shop',
                'delivery_type' => 'delivery',
                'discount' => $cart['coupon']['amount'] ?? 0,
                'delivery' => 0,
                'customer_id' => $customer->id,
                'invoice_id' => null,
                'state_id' => $data['state_id'],
                'city_id' => $data['city_id'],
                'area_id' => $data['area_id'] ?? null,
                'delivery_address' => $data['delivery_address'],
                'payment_method' => $data['payment_method'],
                'coupon_id' => $cart['coupon']['id'],
                'coupon_data' => Coupon::firstWhere('code', $cart['coupon']['code'] ?? null)?->toArray(),
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice = Invoice::create([
                'no' => generate_invoice_no(),
                'tenant_id' => $tenant->id,
                'type' => 'sales',
                'status' => 'sale_order',
                'for' => 'customer',
                'customer_id' => $customer->id,
                'user_id' => null,
                'date' => now(),
                'notes' => 'فاتورية آلية عن طريق المتجر',
                'discount_option' => $cart['coupon']['valid'] ? 'overall' : 'none',
                'discount_method' => $cart['coupon']['percent'] == null ? 'amount' : 'percent',
                'discount_amount' => $cart['coupon']['percent'] == null ? $cart['coupon']['amount'] : null,
                'discount_percent' => $cart['coupon']['percent'] ?? null,
            ]);

            $order->update(['invoice_id' => $invoice->id]);

            foreach ($items as $item) {
                $product = Product::with('taxProfile.taxes')->find($item['productId']);

                $itemModel = null;
                $item_id = null;
                $product_variant_id = null;
                $tax_profile_id = $product->tax_profile_id;
                $tax_profile_data = $product->taxProfile?->toArray();
                $item_type = null;
                $unit_price = 0;
                $tax = 0;
                $extrasModels = ProductExtra::with(['lastPrice', 'extra'])->findMany(collect($item['extras'])->pluck('id')->toArray());

                $discount = $cart['coupon']['valid'] ? ($cart['coupon']['amount'] / count($items)) : 0;

                if ($item['type'] == "basic") {
                    $itemModel = $product;
                    $item_id = $product->id;
                    $item_type = get_class($product);
                    $unit_price = PricingService::instance()->getRetailPrice($product);

                    $subTotal = $unit_price * $item['qty'];
                    $subTotal -= $discount;

                    $subTotal += PricingService::instance()->getRetailItemsPrices($extrasModels) * $item['qty'];

                    if($product->taxProfile){
                        $tax = MathService::instance()->getTaxFromTaxProfile($subTotal, $product->taxProfile, true);
                    }

                } else if ($item['type'] == "variants") {
                    $productVariant = ProductVariant::find($item['productVariantId']);
                    $itemModel = $productVariant;
                    $item_id = $productVariant->id;
                    $item_type = get_class($productVariant);
                    $unit_price = PricingService::instance()->getRetailPrice($productVariant);

                    $subTotal = $unit_price * $item['qty'];
                    $subTotal -= $discount;

                    $subTotal += PricingService::instance()->getRetailItemsPrices($extrasModels) * $item['qty'];

                    if($productVariant->product->taxProfile){
                        $tax = MathService::instance()->getTaxFromTaxProfile($subTotal, $productVariant->product->taxProfile, true);
                    }
                } else {
                    throw new \Exception("Unknown product type");
                }

//                $od = OrderDetail::create(
//                    [
//                        'tenant_id' => $tenant->id,
//                        'order_id' => $order->id,
//                        'item_id' => $item_id,
//                        'item_type' => $item_type,
//                        'qty' => $item['qty'],
//                        'discount' => $discount,
//                        'tax' => $tax,
//                        'unit_price' => $unit_price,
//                        'tax_profile_data' => $tax_profile_data,
//                    ]
//                );

                $invoiceItem = $invoice->items()->create(
                    [
                        'tenant_id' => $tenant->id,
                        'invoice_id' => $invoice->id,
                        'product_id' => $item['productId'],
                        'name' => $itemModel->name,
                        'product_variant_id' => $product_variant_id,
                        'order_details_id' => null,//$od->id,
                        'tax_profile_id' => $tax_profile_id,
                        'tax_profile_data' => $tax_profile_data,
                        'discount' => $discount, //handled as overall discount
                        'tax' => $tax,
                        'qty' => $item['qty'],
                        'price' => $unit_price,
                        'created_at' => now(),
                    ]);

                foreach ($item['extras'] ?? [] as $extra) {
                    $productExtra = ProductExtra::with(['lastPrice', 'extra'])->findOrFail($extra['id']);

//                    $od->orderDetailsExtras()->create([
//                        'tenant_id' => $tenant->id,
//                        'order_details_id' => $od->id,
//                        'product_extra_id' => $productExtra->id,
//                        'unit_price' => PricingService::instance()->getRetailPrice($productExtra),
//                        'display_name' => $productExtra->name,
//                        'qty' => 1,
//                    ]);

                    $invoiceItem->extras()->create([
                        'tenant_id' => $tenant->id,
                        'invoice_item_id' => $invoiceItem->id,
                        'product_extra_id' => $productExtra->id,
                        'unit_price' => PricingService::instance()->getRetailPrice($productExtra),
                        'display_name' => $productExtra->extra->name,
                        'qty' => 1,
                    ]);
                }
            }

            $costTypeDelivery = AdditionalCostType::firstOrCreate([
                'name' => "توصيل/شحن"
            ], [
                'name' => "توصيل/شحن",
                'tenant_id' => $tenant->id,
            ]);

            $statement_en = "Delivery fees, order no #$order->no";
            $statement_ar = "رسوم توصيل الطلب:#$order->no";

            AdditionalCost::create([
                'tenant_id' => $tenant->id,
                'item_id' => $invoice->id,
                'item_type' => Invoice::class,
                'additional_cost_type_id' => $costTypeDelivery->id,
                'statement' => $statement_ar . " - " . $statement_en,
                'cost' => 0,
                'meta' => ['type' => 'delivery_fees', 'client' => $order->customer->name, 'client_id' => $order->customer_id]
            ]);

            DB::commit();

            $this->clearCart();

            return $this->responder(__('messages.api.retrieved'), 200,
                [
                    'orderNo' => $order->no,
                    'cart' => $this->getCart(),
                ]
            )->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);
            return $this->error($exception)->message("حدث خطأ أثناء عمل الطلب, قم بإفراغ السلة وحاول مجددآ")->respond();
        }
    }

    public function applyCoupon(StoreApplyCouponRequest $request): \Illuminate\Http\JsonResponse
    {
        $uuid = \request()->header('Store-UUID');

        Cache::put("coupon_code@$uuid", $request->input('coupon'));

        return $this->responder(__('messages.api.retrieved'), 200, $this->getCart())->respond();
    }

    public function clearCoupon(Request $request): \Illuminate\Http\JsonResponse
    {
        $uuid = \request()->header('Store-UUID');

        Cache::forget("coupon_code@$uuid");

        return $this->responder(__('messages.api.retrieved'), 200, $this->getCart())->respond();
    }

    public function trackOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::with(['tenant', 'details.item', 'details.orderDetailsExtras', 'customer', 'invoice'])
            ->whereRelation('tenant', 'slug', $request->header('Store-Slug'))
            ->whereRelation('customer', 'phone', $request->phone)
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, OrderResource::collection($orders))->respond();
    }

    public function electronicInvoice(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderNo = $request->input('order_no');
        $invoiceUID = $request->input('invoice_uid');

        $inv = null;
        if ($orderNo) {
            $inv = Invoice::with(['tenant', 'items.product', 'customer', 'additionalCosts'])->where('id', Order::firstWhere('no', $orderNo)?->invoice_id)->where('type', 'sales')->firstOrFail();
        } else {
            $inv = Invoice::with(['tenant', 'items.product', 'customer', 'additionalCosts'])->where('uid', $invoiceUID)->where('type', 'sales')->firstOrFail();
        }

        $tenant = $inv->tenant;

        return $this->responder(__('messages.api.retrieved'), 200, [
            'store' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'cover' => $tenant->cover,
                'logo' => $tenant->logo,
                'heroTitle' => $tenant->store_title ?? "My bee",
                'bio' => $tenant->store_bio ?? "",
                'trn' => $tenant->trn ?? "",
                'address' => $tenant->store_address ?? "",
                'phone' => $tenant->phone ?? "",
                'email' => $tenant->email ?? "",
                'ordersTrackingEnabled' => $tenant->store_orders_tracking_mode != null,
                'workingHours' => $tenant->store_working_hours ?? "",
                'social' => [
                    'facebook' => $tenant->store_social_media_links['facebook'] ?? "",
                    'instagram' => $tenant->store_social_media_links['instagram'] ?? "",
                    'twitter' => $tenant->store_social_media_links['twitter'] ?? "",
                    'youtube' => $tenant->store_social_media_links['youtube'] ?? "",
                    'snapchat' => $tenant->store_social_media_links['snapchat'] ?? "",
                    'whatsapp' => $tenant->store_social_media_links['whatsapp'] ?? "",
                ]
            ],
            'termsAndConditions' => $tenant->store_terms_and_conditions,
            'invoice' => new InvoiceResource($inv),
        ])->respond();
    }

    public function priceOffer($no): \Illuminate\Http\JsonResponse
    {
        $priceOffer = PriceOffer::with(['tenant', 'details.item', 'details.offerDetailsExtras', 'customer', 'services.type', 'additionalCosts.type'])
            ->where('no', $no)
            ->firstOrFail();

        $tenant = $priceOffer->tenant;

        return $this->responder(__('messages.api.retrieved'), 200, [
            'store' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'cover' => $tenant->cover,
                'logo' => $tenant->logo,
                'heroTitle' => $tenant->store_title ?? "My bee",
                'bio' => $tenant->store_bio ?? "",
                'trn' => $tenant->trn ?? "",
                'address' => $tenant->store_address ?? "",
                'phone' => $tenant->phone ?? "",
                'email' => $tenant->email ?? "",
                'ordersTrackingEnabled' => $tenant->store_orders_tracking_mode != null,
                'workingHours' => $tenant->store_working_hours ?? "",
                'social' => [
                    'facebook' => $tenant->store_social_media_links['facebook'] ?? "",
                    'instagram' => $tenant->store_social_media_links['instagram'] ?? "",
                    'twitter' => $tenant->store_social_media_links['twitter'] ?? "",
                    'youtube' => $tenant->store_social_media_links['youtube'] ?? "",
                    'snapchat' => $tenant->store_social_media_links['snapchat'] ?? "",
                    'whatsapp' => $tenant->store_social_media_links['whatsapp'] ?? "",
                ]
            ],
            'termsAndConditions' => $tenant->store_terms_and_conditions,
            'priceOffer' => new PriceOfferResource($priceOffer),
        ])->respond();
    }

    public function supplyOrder($no): \Illuminate\Http\JsonResponse
    {
        $supplyOrder = SupplyOrder::with(['tenant', 'details.item', 'supplier'])
            ->where('no', $no)
            ->firstOrFail();

        $tenant = $supplyOrder->tenant;

        return $this->responder(__('messages.api.retrieved'), 200, [
            'store' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'cover' => $tenant->cover,
                'logo' => $tenant->logo,
                'heroTitle' => $tenant->store_title ?? "My bee",
                'bio' => $tenant->store_bio ?? "",
                'trn' => $tenant->trn ?? "",
                'address' => $tenant->store_address ?? "",
                'phone' => $tenant->phone ?? "",
                'email' => $tenant->email ?? "",
                'ordersTrackingEnabled' => $tenant->store_orders_tracking_mode != null,
                'workingHours' => $tenant->store_working_hours ?? "",
                'social' => [
                    'facebook' => $tenant->store_social_media_links['facebook'] ?? "",
                    'instagram' => $tenant->store_social_media_links['instagram'] ?? "",
                    'twitter' => $tenant->store_social_media_links['twitter'] ?? "",
                    'youtube' => $tenant->store_social_media_links['youtube'] ?? "",
                    'snapchat' => $tenant->store_social_media_links['snapchat'] ?? "",
                    'whatsapp' => $tenant->store_social_media_links['whatsapp'] ?? "",
                ]
            ],
            'termsAndConditions' => $tenant->store_terms_and_conditions,
            'supplyOrder' => new SupplyOrderResource($supplyOrder),
        ])->respond();
    }

    protected function getCart(): array
    {
        $uuid = \request()->header('Store-UUID');
        $cart = CacheService::instance()->get("cart@$uuid", $this->getDefaultCartData());

        //remove missing products or variants or product extras
        foreach ($cart['items'] as $itemIndex => $item) {

            $product = Product::find($item['productId']);

            if (!$product)
                unset($cart['items'][$itemIndex]);

            if ($item['productVariantId']) {
                $productVariant = ProductVariant::find($item['productVariantId']);

                if (!$productVariant)
                    unset($cart['items'][$itemIndex]);
            }

            foreach ($item['extras'] ?? [] as $extraIndex => $extraArray) {
                $productExtra = ProductExtra::find($extraArray['id']);
                if (!$productExtra)
                    unset($cart['items'][$itemIndex][$extraIndex]);
            }

            $cart['items'][$itemIndex]['extras'] = array_values($item['extras']);
        }

        return $this->generateCartData($cart['items']);
    }

    protected function getDefaultCartData(): array
    {
        return [
            "subTotal" => 0.0,
            "subTotalFormatted" => "0.0 SAR",
            "subTotalAfterDiscount" => 0.0,
            "subTotalFormattedAfterDiscount" => "0.0 SAR",
            'tax' => 0,
            'taxFormatted' => "0.0 SAR",
            'deliveryFees' => app()->getLocale() == "ar" ? "سيتم إحتساب رسوم التوصيل بناءً على موقعك" : "Delivery fees will be calculated based on your location",
            'coupon' => $this->getCouponInfo(0.0),
            "items" => []
        ];
    }

    protected function generateCartData(array $items): array
    {
        $extrasTotal = collect($items)->sum(function ($item) {
            return collect($item['extras'])->sum('price');
        });

        $subTotal = $this->calculateSubTotal($items) + $extrasTotal;

        $couponData = $this->getCouponInfo($subTotal);

        $taxes = $this->calculateTaxes(array_values($items), $couponData);

        return [
            'subTotal' => $subTotal,
            'subTotalFormatted' => number_format($subTotal, currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'subTotalAfterDiscount' => $subTotal - $couponData['amount'],
            'subTotalFormattedAfterDiscount' => number_format($subTotal - $couponData['amount'], currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'tax' => number_format($taxes, currency_decimals(), '.', ''),
            'taxFormatted' => number_format($taxes, currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'total' => number_format($subTotal - $couponData['amount'], currency_decimals(), '.', ''),
            'totalFormated' => number_format($subTotal - $couponData['amount'], currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'deliveryFees' => app()->getLocale() == "ar" ? "سيتم إحتساب رسوم التوصيل بناءً على موقعك" : "Delivery fees will be calculated based on your location",
            'coupon' => $couponData,
            "items" => array_values($items)
        ];
    }

    protected function getCouponInfo($subTotal = 0): array
    {
        $uuid = \request()->header('Store-UUID');
        $code = Cache::get("coupon_code@$uuid", null);
        $coupon = Coupon::firstWhere('code', $code);

        return [
            'id' => $coupon?->id,
            'code' => $code,
            'valid' => CouponService::instance()->isValid($code),
            'percent' => $coupon?->type == "percent" ? $coupon?->value : null,
            'amount' => CouponService::instance()->amount($code, $subTotal),
        ];
    }

    protected function calculateTaxes(array $items, $couponData)
    {
        $taxes = 0;
        foreach ($items as $item) {
            $extrasModels = ProductExtra::with(['lastPrice', 'extra'])->findMany(collect($item['extras'])->pluck('id')->toArray());

            $discount = $couponData['valid'] ? ($couponData['amount'] / count($items)) : 0;

            $product = Product::with(['taxProfile', 'lastPrice'])->find($item['productId']);
            if($product->taxProfile){
                $unit_price = PricingService::instance()->getRetailPrice($product);
                $subTotal = $unit_price * $item['qty'];
                $subTotal -= $discount;
                $subTotal += PricingService::instance()->getRetailItemsPrices($extrasModels) * $item['qty'];

                $taxes += MathService::instance()->getTaxFromTaxProfile($subTotal, $product->taxProfile, true);
            }
        }
        return $taxes;
    }

    protected function calculateSubTotal(array $items)
    {
        $subTotal = 0;
        foreach ($items as $item) {
            $extrasModels = ProductExtra::with(['lastPrice', 'extra'])->findMany(collect($item['extras'])->pluck('id')->toArray());

            if ($item['type'] == "basic") {
                $product = Product::find($item['productId']);
                $subTotal += PricingService::instance()->getRetailPrice($product) * $item['qty'];
                $subTotal += PricingService::instance()->getRetailItemsPrices($extrasModels) * $item['qty'];
            } else if ($item['type'] == "variants") {
                $productVariant = ProductVariant::find($item['productVariantId']);
                $subTotal += PricingService::instance()->getRetailPrice($productVariant) * $item['qty'];
                $subTotal += PricingService::instance()->getRetailItemsPrices($extrasModels) * $item['qty'];
            } else {
                throw new \Exception("Unknown product type");
            }
        }
        return $subTotal;
    }

    protected function convertExtrasToArray(Collection $collection): array
    {
        $data = [];
        foreach ($collection as $item) {
            $data[] = [
                'id' => $item->id,
                'name' => $item->extra->name,
                'hasDiscount' => PricingService::instance()->hasDiscount($item),
                'originalPrice' => number_format(PricingService::instance()->getOriginalPrice($item), currency_decimals(), '.', ''),
                'price' => number_format(PricingService::instance()->getRetailPrice($item), currency_decimals(), '.', ''),
                'originalPriceFormatted' => number_format(PricingService::instance()->getOriginalPrice($item), currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
                'priceFormatted' => number_format(PricingService::instance()->getRetailPrice($item), currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
                'inStock' => true,
            ];
        }
        return $data;
    }

    protected function convertExtraToArray(ProductExtra $productExtra): array
    {
        return [
            'id' => $productExtra->id,
            'name' => $productExtra->extra->name,
            'hasDiscount' => PricingService::instance()->hasDiscount($productExtra),
            'originalPrice' => number_format(PricingService::instance()->getOriginalPrice($productExtra), currency_decimals(), '.', ''),
            'price' => number_format(PricingService::instance()->getRetailPrice($productExtra), currency_decimals(), '.', ''),
            'originalPriceFormatted' => number_format(PricingService::instance()->getOriginalPrice($productExtra), currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'priceFormatted' => number_format(PricingService::instance()->getRetailPrice($productExtra), currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'inStock' => true,
        ];
    }

    protected function getExistingVariantByCombination($product, $combinations): ?ProductVariant
    {
        return $product->variants->filter(function ($existItem) use ($combinations) {
            $array1 = $existItem->variant_library_options_ids;
            $array2 = $combinations;
            return array_diff($array1, $array2) == array_diff($array2, $array1);
        })->first();

    }

    public function generateNo(GenerateNoRequest $request)
    {
        switch ($request->type) {
            case "sales_invoice_no":
            {
                return $this->responder(__('messages.api.retrieved'), 200, ['no' => generate_invoice_no()])->respond();
            }
            case "purchases_invoice_no":
            {
                return $this->responder(__('messages.api.retrieved'), 200, ['no' => generate_invoice_no()])->respond();
            }
            case "receipt_voucher_no":
            {
                return $this->responder(__('messages.api.retrieved'), 200, ['no' => generate_receipt_voucher()])->respond();
            }
            case "payment_voucher_no":
            {
                return $this->responder(__('messages.api.retrieved'), 200, ['no' => generate_payment_voucher()])->respond();
            }

            default:
            {
                return $this->errorBadRequest()->respond();
            }
        }
    }

    public function listProductsForAdvancedCreation(ListProductsForAdvancedCreationRequest $request)
    {
        switch ($request->for) {
            case "sales":
            {
                $products = Product::with(['extras.extra', 'lastPrice', 'variantOptions.library'])->has('lastPrice')->get();
                return $this->responder(__('messages.api.retrieved'), 200, ListProductsForAdvancedCreationResource::collection($products))->respond();
            }
            case "purchases":
            {
                $products = Product::with(['extras.extra', 'lastPrice', 'variantOptions.library'])->get();
                return $this->responder(__('messages.api.retrieved'), 200, ListProductsForAdvancedCreationResource::collection($products))->respond();
            }
            case "supply_orders":
            {
                $products = Product::with(['extras.extra', 'lastPrice', 'variantOptions.library'])->get();
                return $this->responder(__('messages.api.retrieved'), 200, ListProductsForAdvancedCreationResource::collection($products))->respond();
            }
            case "price_offers":
            {
                $products = Product::with(['extras.extra', 'lastPrice', 'variantOptions.library'])->get();
                return $this->responder(__('messages.api.retrieved'), 200, ListProductsForAdvancedCreationResource::collection($products))->respond();
            }

            default:
            {
                return $this->errorBadRequest()->respond();
            }
        }
    }

    public function deleteMediaByFileName(DeleteMediaByFileNameRequest $request): \Illuminate\Http\JsonResponse
    {
        $file_name = $request->input('file_name');
        $deleted = Media::where('file_name', $file_name)->first()?->delete();

        return $this
            ->responder(__('messages.api.deleted'), 200, ['deleted' => boolval($deleted)])
            ->respond();
    }
}
