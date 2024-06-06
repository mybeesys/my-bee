<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\AdditionalCostType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\ItemExtra;
use App\Models\Order;
use App\Models\PaymentVoucher;
use App\Models\Product;
use App\Models\PurchasesReturns;
use App\Models\ReceiptVoucher;
use App\Models\SalesReturns;
use App\Models\ServiceType;
use App\Models\Supplier;
use App\Models\TaxProfile;
use App\Models\Unit;
use App\Models\VariantLibrary;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class StatsController extends BaseController
{
    public function shop(): \Illuminate\Http\JsonResponse
    {
        $data = [
            [
                'id' => 'orders',
                'name' => __('fields.new_orders'),
                'count' => Order::new()->count(),
                'description' => Order::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'products',
                'name' => __('fields.products'),
                'count' => Product::count(),
                'description' => Product::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'categories',
                'name' => __('fields.categories'),
                'count' => Category::count(),
                'description' => Category::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'clients',
                'name' => __('fields.clients'),
                'count' => Customer::count(),
                'description' => Customer::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'suppliers',
                'name' => __('fields.suppliers'),
                'count' => Supplier::count(),
                'description' => Supplier::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'purchases',
                'name' => __('fields.purchases'),
                'count' => Invoice::purchases()->count(),
                'description' => Invoice::purchases()->latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'sales',
                'name' => __('fields.sales'),
                'count' => Invoice::sales()->count(),
                'description' => Invoice::sales()->latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'purchases_returns',
                'name' => __('fields.purchases_returns'),
//                'count' => PurchasesReturns::with('details')->get()->pluck('details')->sum('qty'),
               'count' => PurchasesReturns::count(),
                'description' => PurchasesReturns::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'sales_returns',
                'name' => __('fields.sales_returns'),
//                'count' => SalesReturns::with('details')->get()->pluck('details')->sum('qty'),
                'count' => SalesReturns::count(),
                'description' => SalesReturns::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'receipt_vouchers',
                'name' => __('fields.receipt_vouchers'),
                'count' => ReceiptVoucher::count(),
                'description' => ReceiptVoucher::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'payment_vouchers',
                'name' => __('fields.payment_vouchers'),
                'count' => PaymentVoucher::count(),
                'description' => PaymentVoucher::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
        ];

        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();

    }

    public function expenses(): \Illuminate\Http\JsonResponse
    {
        $defaultStats = [
            [
                'id' => 'expenses_categories',
                'name' => __('fields.expense_categories'),
                'count' => ExpenseCategory::count(),
                'description' => ExpenseCategory::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
            [
                'id' => 'expenses',
                'name' => __('fields.expenses'),
                'count' => Expense::count(),
                'description' => ExpenseCategory::latest()->first()?->created_at?->diffForHumans() ?? "",
            ],
        ];

        $expCats = ExpenseCategory::with(['expenses'])->whereHas('expenses')->get();

        $extraStats = [];

        foreach ($expCats as $expCat) {
            $extraStats[] = [
                'id' => $expCat->id,
                'name' => $expCat->name,
                'totalExpenses' => $expCat->expenses_total_formatted . " " . main_currency_iso_code(),
                'description' => numbers_to_words($expCat->expenses_total),
            ];
        }

        $total_expenses = $expCats->pluck('expenses')->flatten()->sum('total');

        $extraStats[] = [
            'id' => null,
            'name' => __('fields.total'),
            'totalExpenses' => format_amount($total_expenses) . " " . main_currency_iso_code(),
            'description' => numbers_to_words($total_expenses),
        ];

        return $this->responder(__('messages.api.retrieved'), 200, [
            'stats' => $defaultStats,
            'expensesCategories' => $extraStats
        ])->respond();
    }

//    public function orders(): \Illuminate\Http\JsonResponse
//    {
//        $data = [
//            [
//                'id' => 'new_orders',
//                'name' => __('fields.new_orders'),
//                'count' => Order::new()->count(),
//            ],
//            [
//                'id' => 'units',
//                'name' => __('fields.units'),
//                'count' => Unit::count(),
//            ],
//            [
//                'id' => 'suppliers',
//                'name' => __('fields.suppliers'),
//                'count' => Supplier::count(),
//            ],
//        ];
//
//        return $this->responder(__('messages_data_retrieved'), 200, $data)->respond();
//    }

    public function settings(): \Illuminate\Http\JsonResponse
    {
        $data = [
            [
                'id' => 'clients',
                'name' => __('fields.clients'),
                'count' => Customer::count(),
            ],
            [
                'id' => 'services_types',
                'name' => __('fields.services_types'),
                'count' => ServiceType::count(),
            ],
            [
                'id' => 'additional_costs_types',
                'name' => __('fields.additional_costs_types'),
                'count' => AdditionalCostType::count(),
            ],
            [
                'id' => 'variants_libraries',
                'name' => __('fields.variant_libraries'),
                'count' => VariantLibrary::count(),
            ],
            [
                'id' => 'wherehouses',
                'name' => __('fields.warehouses'),
                'count' => Warehouse::count(),
            ],
            [
                'id' => 'suppliers',
                'name' => __('fields.suppliers'),
                'count' => Supplier::count(),
            ],
            [
                'id' => 'products_extras',
                'name' => __('fields.products_extras'),
                'count' => ItemExtra::count(),
            ],
            [
                'id' => 'tax_profiles',
                'name' => __('fields.tax_profiles'),
                'count' => TaxProfile::count(),
            ],
        ];

        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }
}
