<?php

namespace App\Http\Middleware;

use App\Models\Acc1;
use App\Models\Acc2;
use App\Models\Acc3;
use App\Models\Acc4;
use App\Models\AccountingTransaction;
use App\Models\CashDet;
use App\Models\Category;
use App\Models\ContactUS;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceAdditionalCost;
use App\Models\InvoiceAdditionalCostType;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceStatus;
use App\Models\ItemPrice;
use App\Models\ItemStock;
use App\Models\Op;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PaymentVoucher;
use App\Models\PaymentVoucherPayment;
use App\Models\Product;
use App\Models\PurchaseInvoiceStatus;
use App\Models\ReceiptVoucher;
use App\Models\ReceiptVoucherPayment;
use App\Models\SaleInvoiceStatus;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Models\VariantOption;
use App\Models\Warehouse;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantScopes
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $tenant = Filament::getTenant();

        Acc1::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Acc2::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Acc3::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Acc4::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        AccountingTransaction::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        CashDet::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        User::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Category::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        ContactUS::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Currency::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Customer::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Driver::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        ExpenseCategory::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Expense::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Product::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Invoice::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        InvoiceAdditionalCostType::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        InvoiceAdditionalCost::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        InvoiceItem::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        InvoicePayment::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        ReceiptVoucher::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        ReceiptVoucherPayment::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        InvoiceStatus::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        PurchaseInvoiceStatus::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        SaleInvoiceStatus::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        ItemPrice::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        ItemStock::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Op::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Order::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        OrderDetail::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        PaymentVoucher::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        PaymentVoucherPayment::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Role::resolveRelationUsing('tenant', function ($model) {
            return $model->belongsTo(Tenant::class, 'tenant_id');
        });

        Role::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Setting::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        StockMovement::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Supplier::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Unit::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Warehouse::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        TaxProfile::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        Tax::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        VariantLibrary::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        VariantLibraryOption::addGlobalScope(
            fn(Builder $query) => $query->whereBelongsTo($tenant),
        );

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        return $next($request);
    }
}
