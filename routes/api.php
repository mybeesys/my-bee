<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


//V1
Route::group(['prefix' => "v1", 'middleware' => ['force_json_response', 'localization', 'restrict_requests_by_cors']], function () {
    Route::post('delete-media-by-file-name', [\App\Http\Controllers\API\V1\StoreController::class, 'deleteMediaByFileName'])->middleware('auth:sanctum');
    Route::post('seed-customers', [App\Http\Controllers\API\V1\DevController::class, 'seedCustomers'])->middleware('auth:sanctum');
    Route::post('app-status', [App\Http\Controllers\API\V1\MobileAppStatusController::class, 'status']);
    Route::get('app-status/versions', [App\Http\Controllers\API\V1\AppVersionController::class, 'versions']);
    Route::get('app-status/versions/latest', [App\Http\Controllers\API\V1\AppVersionController::class, 'latestVersion']);

    Route::post('register', [\App\Http\Controllers\API\V1\ClientController::class, 'register']);
    Route::post('login', [\App\Http\Controllers\API\V1\ClientController::class, 'login']);

    Route::post('create-tenant', [\App\Http\Controllers\API\V1\ClientController::class, 'createTenant'])
        ->middleware('auth:sanctum');

    Route::post('list-tenants', [\App\Http\Controllers\API\V1\ClientController::class, 'listTenants'])
        ->middleware(['auth:sanctum']);

    Route::post('update-tenant', [\App\Http\Controllers\API\V1\ClientController::class, 'updateTenant'])
        ->middleware(['auth:sanctum', 'ensure_tenant_id_in_header', 'ensure_user_can_access_tenant']);

    Route::post('me', [\App\Http\Controllers\API\V1\ClientController::class, 'me'])
        ->middleware(['auth:sanctum']);

    Route::group(['prefix' => 'client', 'middleware' => ['auth:sanctum']], function () {
        Route::get('subscription', [\App\Http\Controllers\API\V1\ClientSubscriptionController::class, 'show']);
        Route::get('plans', [\App\Http\Controllers\API\V1\ClientSubscriptionController::class, 'plans']);
        Route::post('subscription/quote', [\App\Http\Controllers\API\V1\ClientSubscriptionController::class, 'quote']);
        Route::post('subscription/coupon/validate', [\App\Http\Controllers\API\V1\ClientSubscriptionController::class, 'validateCoupon']);
        Route::post('subscription/subscribe', [\App\Http\Controllers\API\V1\ClientSubscriptionController::class, 'subscribe']);
        Route::get('subscription/usage', [\App\Http\Controllers\API\V1\ClientSubscriptionController::class, 'usage']);
        Route::get('subscription/coupons/available', [\App\Http\Controllers\API\V1\ClientSubscriptionController::class, 'couponsAvailable']);
    });

    Route::group(['prefix' => 'store', 'middleware' => ['ensure_tenant_slug_in_header', 'ensure_store_uuid_in_header', 'ensure_tenant_store_enabled']], function () {
        Route::get('info', [\App\Http\Controllers\API\V1\StoreController::class, 'info']);
        Route::get('categories', [\App\Http\Controllers\API\V1\StoreController::class, 'categories']);
        Route::get('products', [\App\Http\Controllers\API\V1\StoreController::class, 'products']);
        Route::post('get-variant-info', [\App\Http\Controllers\API\V1\StoreController::class, 'getVariantInfo']);
        Route::get('cart', [\App\Http\Controllers\API\V1\StoreController::class, 'cart']);
        Route::post('cart/add', [\App\Http\Controllers\API\V1\StoreController::class, 'addToCart']);
        Route::post('cart/update', [\App\Http\Controllers\API\V1\StoreController::class, 'updateCart']);
        Route::post('cart/delete', [\App\Http\Controllers\API\V1\StoreController::class, 'deleteCartItem']);
        Route::get('cart/clear', [\App\Http\Controllers\API\V1\StoreController::class, 'clearCart']);
        Route::post('apply-coupon', [\App\Http\Controllers\API\V1\StoreController::class, 'applyCoupon']);
        Route::post('clear-coupon', [\App\Http\Controllers\API\V1\StoreController::class, 'clearCoupon']);
        Route::post('checkout', [\App\Http\Controllers\API\V1\StoreController::class, 'checkout']);
        Route::get('track-orders', [\App\Http\Controllers\API\V1\StoreController::class, 'trackOrders']);

        Route::get('e-invoice', [\App\Http\Controllers\API\V1\StoreController::class, 'electronicInvoice'])
            ->withoutMiddleware(['ensure_tenant_slug_in_header', 'ensure_store_uuid_in_header']);

        Route::get('price-offers/{no}', [\App\Http\Controllers\API\V1\StoreController::class, 'priceOffer'])
            ->withoutMiddleware(['ensure_tenant_slug_in_header', 'ensure_store_uuid_in_header']);

        Route::get('supply-orders/{no}', [\App\Http\Controllers\API\V1\StoreController::class, 'supplyOrder'])
            ->withoutMiddleware(['ensure_tenant_slug_in_header', 'ensure_store_uuid_in_header']);

        Route::group(['prefix' => 'location'], function () {
            Route::get('countries', [\App\Http\Controllers\API\V1\LocationController::class, 'countries'])
                ->withoutMiddleware(['ensure_tenant_slug_in_header', 'ensure_store_uuid_in_header']);
            Route::get('states', [\App\Http\Controllers\API\V1\LocationController::class, 'states'])
                ->withoutMiddleware(['ensure_tenant_slug_in_header', 'ensure_store_uuid_in_header']);
            Route::get('cities', [\App\Http\Controllers\API\V1\LocationController::class, 'cities'])
                ->withoutMiddleware(['ensure_tenant_slug_in_header', 'ensure_store_uuid_in_header']);
            Route::get('areas', [\App\Http\Controllers\API\V1\LocationController::class, 'areas'])
                ->withoutMiddleware(['ensure_tenant_slug_in_header', 'ensure_store_uuid_in_header']);
        });
    });


    Route::group(['prefix' => "tenant", 'middleware' =>
        [
            'force_json_response',
            'localization',
            'restrict_requests_by_cors',
            'auth:sanctum',
            'ensure_tenant_id_in_header',
            'ensure_user_can_access_tenant',
            'apply_api_tenant_scopes',
            'ensure_client_subscription_active_api',
        ]
    ], function () {


        Route::group(['prefix' => 'shop'], function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\StatsController::class, 'shop']);
            Route::post('generate-no', [\App\Http\Controllers\API\V1\StoreController::class, 'generateNo']);
            Route::post('list-products-for-advanced-creation', [\App\Http\Controllers\API\V1\StoreController::class, 'listProductsForAdvancedCreation']);

            Route::get('price-offers/{id}/sales-prefill', [\App\Http\Controllers\API\V1\PriceOfferController::class, 'salesPrefill']);

            Route::get('orders/stats', [\App\Http\Controllers\API\V1\OrderController::class, 'stats']);
            Route::get('coupons/prefill', [\App\Http\Controllers\API\V1\CouponController::class, 'prefill']);
            Route::get('coupons/form-options', [\App\Http\Controllers\API\V1\CouponController::class, 'formOptions']);
            Route::apiResource('coupons', \App\Http\Controllers\API\V1\CouponController::class);
            Route::post('orders/{id}/confirm-invoice', [\App\Http\Controllers\API\V1\OrderController::class, 'confirmInvoice']);
            Route::put('orders/{id}', [\App\Http\Controllers\API\V1\OrderController::class, 'replace']);
            Route::patch('orders/{id}', [\App\Http\Controllers\API\V1\OrderController::class, 'update']);
            Route::apiResource('orders', \App\Http\Controllers\API\V1\OrderController::class)->except(['update']);

            Route::apiResources([
                'categories' => \App\Http\Controllers\API\V1\CategoryController::class,
                'suppliers' => \App\Http\Controllers\API\V1\SupplierController::class,
                'products' => \App\Http\Controllers\API\V1\ProductController::class,
                'clients' => \App\Http\Controllers\API\V1\CustomerController::class,
                'purchases' => \App\Http\Controllers\API\V1\PurchaseInvoiceController::class,
                'sales' => \App\Http\Controllers\API\V1\SaleInvoiceController::class,
                'purchases-returns' => \App\Http\Controllers\API\V1\PurchasesReturnsController::class,
                'sales-returns' => \App\Http\Controllers\API\V1\SalesReturnsController::class,
                'payment-vouchers' => \App\Http\Controllers\API\V1\PaymentVoucherController::class,
                'receipt-vouchers' => \App\Http\Controllers\API\V1\ReceiptVoucherController::class,
                'supply-orders' => \App\Http\Controllers\API\V1\SupplyOrderController::class,
                'price-offers' => \App\Http\Controllers\API\V1\PriceOfferController::class,
            ]);

            Route::get('clients/{id}/account-statement', [\App\Http\Controllers\API\V1\CustomerController::class, 'accountStatement']);
            Route::get('clients/{id}/invoices', [\App\Http\Controllers\API\V1\CustomerController::class, 'invoices']);
            Route::get('clients/{id}/orders', [\App\Http\Controllers\API\V1\CustomerController::class, 'orders']);

            Route::get('suppliers/{id}/account-statement', [\App\Http\Controllers\API\V1\SupplierController::class, 'accountStatement']);
            Route::get('suppliers/{id}/purchase-invoices', [\App\Http\Controllers\API\V1\SupplierController::class, 'purchaseInvoices']);
            Route::get('suppliers/{id}/supply-orders', [\App\Http\Controllers\API\V1\SupplierController::class, 'supplyOrders']);

            Route::post('supply-orders/{id}/start-purchase-invoice', [\App\Http\Controllers\API\V1\SupplyOrderController::class, 'startPurchaseInvoice']);
            Route::get('supply-orders/{id}/purchase-prefill', [\App\Http\Controllers\API\V1\SupplyOrderController::class, 'purchasePrefill']);

            Route::get('location/states', [\App\Http\Controllers\API\V1\LocationController::class, 'tenantStates']);
            Route::get('location/cities', [\App\Http\Controllers\API\V1\LocationController::class, 'tenantCities']);
            Route::get('location/areas', [\App\Http\Controllers\API\V1\LocationController::class, 'tenantAreas']);

            //clear temp invoices
            Route::post('purchases-clear-temp-invoices', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'clearTempInvoices']);
            Route::post('sales-clear-temp-invoices', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'clearTempInvoices']);

            //returns
            Route::get('sales-returns-get-available-invoices', [\App\Http\Controllers\API\V1\SalesReturnsController::class, 'getAvailableInvoices']);
            Route::get('sales-returns-list-invoice-items-for-create/{no}', [\App\Http\Controllers\API\V1\SalesReturnsController::class, 'listInvoiceItemsForCreate']);
            Route::get('sales-returns-returnable-products/{customerId}', [\App\Http\Controllers\API\V1\SalesReturnsController::class, 'returnableProductsForCustomer']);
            Route::get('purchases-returns-get-available-invoices', [\App\Http\Controllers\API\V1\PurchasesReturnsController::class, 'getAvailableInvoices']);
            Route::get('purchases-returns-list-invoice-items-for-create/{no}', [\App\Http\Controllers\API\V1\PurchasesReturnsController::class, 'listInvoiceItemsForCreate']);
            Route::get('purchases-returns-returnable-products/{supplierId}', [\App\Http\Controllers\API\V1\PurchasesReturnsController::class, 'returnableProductsForSupplier']);


            //purchases
            Route::post('purchases/add-item', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'addItem']);
            Route::post('purchases/update-item', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'updateItem']);
            Route::post('purchases/delete-item', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'deleteItem']);

            Route::post('purchases/add-additional-cost', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'addAdditionalCost']);
            Route::post('purchases/update-additional-cost', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'updateAdditionalCost']);
            Route::post('purchases/delete-additional-cost', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'deleteAdditionalCost']);

            Route::post('purchases/apply-overall-discount', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'applyOverallDiscount']);
            Route::post('purchases/remove-overall-discount', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'removeOverallDiscount']);

            Route::post('purchases/update-status', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'updateStatus']);

            Route::post('purchases/save', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'save']);
            Route::post('purchases/commit', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'commit']);
            Route::post('purchases/{id}/credit-payment', [\App\Http\Controllers\API\V1\PurchaseInvoiceController::class, 'creditPayment']);


            //sales
            Route::post('sales/add-item', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'addItem']);
            Route::post('sales/update-item', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'updateItem']);
            Route::post('sales/delete-item', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'deleteItem']);

            Route::post('sales/add-additional-cost', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'addAdditionalCost']);
            Route::post('sales/update-additional-cost', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'updateAdditionalCost']);
            Route::post('sales/delete-additional-cost', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'deleteAdditionalCost']);

            Route::post('sales/add-service', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'addService']);
            Route::post('sales/update-service', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'updateService']);
            Route::post('sales/delete-service', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'deleteService']);


            Route::post('sales/apply-overall-discount', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'applyOverallDiscount']);
            Route::post('sales/remove-overall-discount', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'removeOverallDiscount']);

            Route::post('sales/update-status', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'updateStatus']);

            Route::post('sales/save', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'save']);
            Route::post('sales/commit', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'commit']);
            Route::post('sales/{id}/credit-payment', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'creditPayment']);
            Route::get('price-offers-for-sales', [\App\Http\Controllers\API\V1\SaleInvoiceController::class, 'priceOffers']);


            Route::post('payment-vouchers/payments/add', [\App\Http\Controllers\API\V1\PaymentVoucherController::class, 'addPayment']);
            Route::post('payment-vouchers/utils/allocation-preview', [\App\Http\Controllers\API\V1\PaymentVoucherController::class, 'allocationPreview']);
            Route::get('payment-vouchers/prefill', [\App\Http\Controllers\API\V1\PaymentVoucherController::class, 'prefill']);
            Route::get('payment-vouchers/utils/get-supplier-invoices/{supplier_acc4_code}', [\App\Http\Controllers\API\V1\PaymentVoucherController::class, 'getSupplierInvoices']);
            Route::get('payment-vouchers/utils/get-other-entities', [\App\Http\Controllers\API\V1\PaymentVoucherController::class, 'getOtherEntities']);
            Route::get('payment-vouchers/utils/get-invoice-info/{id}', [\App\Http\Controllers\API\V1\PaymentVoucherController::class, 'getInvoiceInfo']);
            Route::get('payment-vouchers/utils/get-credit-accounts', [\App\Http\Controllers\API\V1\PaymentVoucherController::class, 'getCreditAccounts']);
            Route::get('payment-vouchers/utils/voucher-payment-accounts', [\App\Http\Controllers\API\V1\PaymentVoucherController::class, 'getVoucherPaymentAccounts']);

            Route::post('receipt-vouchers/payments/add', [\App\Http\Controllers\API\V1\ReceiptVoucherController::class, 'addPayment']);
            Route::post('receipt-vouchers/utils/allocation-preview', [\App\Http\Controllers\API\V1\ReceiptVoucherController::class, 'allocationPreview']);
            Route::get('receipt-vouchers/prefill', [\App\Http\Controllers\API\V1\ReceiptVoucherController::class, 'prefill']);
            Route::get('receipt-vouchers/utils/get-customer-invoices/{customer_acc4_code}', [\App\Http\Controllers\API\V1\ReceiptVoucherController::class, 'getCustomerInvoices']);
            Route::get('receipt-vouchers/utils/get-other-entities', [\App\Http\Controllers\API\V1\ReceiptVoucherController::class, 'getOtherEntities']);
            Route::get('receipt-vouchers/utils/get-invoice-info/{id}', [\App\Http\Controllers\API\V1\ReceiptVoucherController::class, 'getInvoiceInfo']);
            Route::get('receipt-vouchers/utils/get-credit-accounts', [\App\Http\Controllers\API\V1\ReceiptVoucherController::class, 'getCreditAccounts']);
            Route::get('receipt-vouchers/utils/voucher-payment-accounts', [\App\Http\Controllers\API\V1\ReceiptVoucherController::class, 'getVoucherPaymentAccounts']);


            Route::get('orders/{id}/payments', [\App\Http\Controllers\API\V1\OrderController::class, 'payments']);

            Route::get('products/variants/pre-create-get-variants-library', [\App\Http\Controllers\API\V1\ProductController::class, 'variantsLibrary']);
            Route::post('products/variants/on-variants-libs-change-generate-variants', [\App\Http\Controllers\API\V1\ProductController::class, 'generateVariants']);
        });

        Route::group(['prefix' => 'expenses'], function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\StatsController::class, 'expenses']);
            Route::get('overview', [\App\Http\Controllers\API\V1\ExpenseController::class, 'overview']);
            Route::get('prefill', [\App\Http\Controllers\API\V1\ExpenseController::class, 'prefill']);
            Route::post('utils/tax-preview', [\App\Http\Controllers\API\V1\ExpenseController::class, 'taxPreview']);

            Route::apiResource('categories', \App\Http\Controllers\API\V1\ExpenseCategoryController::class, [
                'names' => [
                    'index' => 'exp-categories.index',
                    'show' => 'exp-categories.view',
                    'update' => 'exp-categories.update',
                    'store' => 'exp-categories.save',
                    'destroy' => 'exp-categories.delete',
                ]
            ]);
            Route::apiResource('expenses', \App\Http\Controllers\API\V1\ExpenseController::class);

            Route::get('acc4-treasury-accounts', [\App\Http\Controllers\API\V1\ExpenseController::class, 'treasuryAccounts']);
        });

        Route::group(['prefix' => 'reports'], function () {
            Route::get('catalog', [\App\Http\Controllers\API\V1\ReportController::class, 'catalog']);

            Route::prefix('filters')->group(function () {
                Route::get('account-statement', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersAccountStatement']);
                Route::get('treasury', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersTreasury']);
                Route::get('bank', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersBank']);
                Route::get('tax', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersTax']);
                Route::get('income-statement', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersIncomeStatement']);
                Route::get('products-movements', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersProductsMovements']);
                Route::get('sales-statement', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersSalesStatement']);
                Route::get('inventory-detail', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersInventoryDetail']);
                Route::get('inventory-summary', [\App\Http\Controllers\API\V1\ReportController::class, 'filtersInventorySummary']);
            });

            Route::get('account/statement/all', [\App\Http\Controllers\API\V1\ReportController::class, 'allAccounts']);
            Route::get('account/statement/bank', [\App\Http\Controllers\API\V1\ReportController::class, 'bankAccount']);
            Route::get('account/statement/treasury', [\App\Http\Controllers\API\V1\ReportController::class, 'treasuryAccount']);
            Route::get('account/statement/tax', [\App\Http\Controllers\API\V1\ReportController::class, 'taxAccount']);
            Route::get('account/statement/products-movements', [\App\Http\Controllers\API\V1\ReportController::class, 'productsMovements']);

            Route::get('income-statement', [\App\Http\Controllers\API\V1\ReportController::class, 'incomeStatement']);
            Route::get('sales-statement', [\App\Http\Controllers\API\V1\ReportController::class, 'salesStatement']);
            Route::get('inventory/detail', [\App\Http\Controllers\API\V1\ReportController::class, 'inventoryDetail']);
            Route::get('inventory/summary', [\App\Http\Controllers\API\V1\ReportController::class, 'inventorySummary']);
        });

        Route::group(['prefix' => 'settings'], function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\StatsController::class, 'settings']);
            Route::get('additional-settings', [\App\Http\Controllers\API\V1\SettingController::class, 'listSettings']);
            Route::apiResource('suppliers', \App\Http\Controllers\API\V1\SupplierController::class, [
                'names' => [
                    'index' => 'settings-suppliers.index',
                    'show' => 'settings-suppliers.view',
                    'update' => 'settings-suppliers.update',
                    'store' => 'settings-suppliers.save',
                    'destroy' => 'settings-suppliers.delete',
                ]
            ]);
            Route::apiResource('clients', \App\Http\Controllers\API\V1\ClientController::class, [
                'names' => [
                    'index' => 'settings-clients.index',
                    'show' => 'settings-clients.view',
                    'update' => 'settings-clients.update',
                    'store' => 'settings-clients.save',
                    'destroy' => 'settings-clients.delete',
                ]
            ]);
            Route::get('acc4/other-parties', [\App\Http\Controllers\API\V1\Acc4Controller::class, 'otherPartiesIndex']);
            Route::post('acc4/other-parties', [\App\Http\Controllers\API\V1\Acc4Controller::class, 'otherPartiesStore']);
            Route::patch('acc4/other-parties/{code}', [\App\Http\Controllers\API\V1\Acc4Controller::class, 'otherPartiesUpdate']);
            Route::delete('acc4/other-parties/{code}', [\App\Http\Controllers\API\V1\Acc4Controller::class, 'otherPartiesDestroy']);

            Route::prefix('utils/accounts')->group(function () {
                Route::get('collection', [\App\Http\Controllers\API\V1\Acc4Controller::class, 'accountOptionsCollection']);
                Route::get('other-parties', [\App\Http\Controllers\API\V1\Acc4Controller::class, 'accountOptionsOtherParties']);
                Route::get('voucher-payments', [\App\Http\Controllers\API\V1\Acc4Controller::class, 'accountOptionsVoucherPayments']);
                Route::get('statement', [\App\Http\Controllers\API\V1\Acc4Controller::class, 'accountOptionsStatement']);
            });

            Route::apiResources([
                'acc3' => \App\Http\Controllers\API\V1\Acc3Controller::class,
                'acc4' => \App\Http\Controllers\API\V1\Acc4Controller::class,
                'services-types' => \App\Http\Controllers\API\V1\ServiceTypeController::class,
                'additional-costs-types' => \App\Http\Controllers\API\V1\AdditionalCostTypeController::class,
                'warehouses' => \App\Http\Controllers\API\V1\WarehouseController::class,
                'variants-libraries' => \App\Http\Controllers\API\V1\VariantLibraryController::class,
                'extras' => \App\Http\Controllers\API\V1\ExtraController::class,
                'tax-profiles' => \App\Http\Controllers\API\V1\TaxProfileController::class,
            ]);
        });

    });
});
