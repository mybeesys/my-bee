<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
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
            ],
            [
                'id' => 'products',
                'name' => __('fields.products'),
                'count' => Product::count(),
            ],
            [
                'id' => 'categories',
                'name' => __('fields.categories'),
                'count' => Category::count(),
            ],
            [
                'id' => 'clients',
                'name' => __('fields.clients'),
                'count' => Customer::count()
            ],
            [
                'id' => 'suppliers',
                'name' => __('fields.suppliers'),
                'count' => Supplier::count()
            ],
        ];

        return $this->responder(__('messages_data_retrieved'), 200, $data)->respond();

    }

    public function expenses(): \Illuminate\Http\JsonResponse
    {
        $defaultStats = [
            [
                'id' => 'expense_categories',
                'name' => __('fields.expense_categories'),
                'count' => ExpenseCategory::count(),
            ],
            [
                'id' => 'expenses',
                'name' => __('fields.expenses'),
                'count' => Expense::count(),
            ],
        ];

        $expCats = ExpenseCategory::with(['expenses'])->whereHas('expenses')->get();

        $extraStats = [];

        foreach ($expCats as $expCat) {
            $extraStats[] = [
                'id' => $expCat->id,
                'name' => $expCat->name,
                'totalExpenses' => $expCat->expenses_total_formatted . " " . main_currency_iso_code(),
            ];
        }

        $extraStats[] = [
            'id' => null,
            'name' => __('fields.total'),
            'totalExpenses' => format_amount($expCats->pluck('expenses')->flatten()->sum('amount')) . " " . main_currency_iso_code(),
        ];

        return $this->responder(__('messages_data_retrieved'), 200, [
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
                'id' => 'units',
                'name' => __('fields.units'),
                'count' => Unit::count(),
            ],
            [
                'id' => 'warehouses',
                'name' => __('fields.warehouses'),
                'count' => Warehouse::count(),
            ],
            [
                'id' => 'suppliers',
                'name' => __('fields.suppliers'),
                'count' => Supplier::count(),
            ],
        ];

        return $this->responder(__('messages_data_retrieved'), 200, $data)->respond();
    }
}
