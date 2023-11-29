<?php

use App\Models\PurchaseInvoiceStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//dd(User::with('tokens')->find(3)->createToken("test")->plainTextToken);
//Route::get('/', function () {
//    return view('welcome');
//});

//exec('mysqldump --version', $output, $result_code);

//$default_status_id = PurchaseInvoiceStatus::firstWhere('default', 1)?->id;
//
//dd($default_status_id);
