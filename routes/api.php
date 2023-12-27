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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//V1
Route::group(['prefix' => "v1", 'middleware' => ['force_json_response', 'localization', 'restrict_requests_by_cors']], function () {
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


    Route::group(['prefix' => "tenant", 'middleware' =>
        [
            'force_json_response',
            'localization',
            'restrict_requests_by_cors',
            'auth:sanctum',
            'ensure_tenant_id_in_header',
            'ensure_user_can_access_tenant',
            'apply_api_tenant_scopes',
        ]
    ], function () {


        Route::group(['prefix' => 'shop'], function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\StatsController::class, 'shop']);
            Route::apiResources([
                'categories' => \App\Http\Controllers\API\V1\CategoryController::class,
                'suppliers' => \App\Http\Controllers\API\V1\SupplierController::class,
//                'products' => \App\Http\Controllers\API\V1\ProductController::class,
                  //'clients' => \App\Http\Controllers\API\V1\CustomerController::class
            ]);
        });

        Route::group(['prefix' => 'warehouses'], function () {
            Route::apiResources([
                'warehouses' => \App\Http\Controllers\API\V1\WarehouseController::class,
                'units' => \App\Http\Controllers\API\V1\UnitController::class,
            ]);
        });

        Route::group(['prefix' => 'expenses'], function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\StatsController::class, 'expenses']);
            Route::apiResources([
                'categories' => \App\Http\Controllers\API\V1\ExpenseCategoryController::class,
                'expenses' => \App\Http\Controllers\API\V1\ExpenseController::class,
            ]);
        });

        Route::group(['prefix' => 'settings'], function () {
            Route::get('stats', [\App\Http\Controllers\API\V1\StatsController::class, 'settings']);
            Route::apiResources([
                'units' => \App\Http\Controllers\API\V1\UnitController::class,
                'warehouses' => \App\Http\Controllers\API\V1\WarehouseController::class,
                'suppliers' => \App\Http\Controllers\API\V1\SupplierController::class,
            ]);
        });

    });
});
