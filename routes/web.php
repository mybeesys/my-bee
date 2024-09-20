<?php

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
//dd(json_encode(
//    [
//        0 => [
//            1,
//            2,
//            3,
//            4
//        ],
//        1 => [
//            5,
//            6,
//            7,
//            8
//        ]
//    ]
//));
//dd(User::with('tokens')->find(3)->update(['password' => bcrypt(123456)]));
//dd(\App\Models\Acc4::asOptions());
//dd(\App\Models\User::with('tokens')->find(3)->createToken("test")->plainTextToken);
//Route::get('/', function () {
//    return view('welcome');
//});

//exec('mysqldump --version', $output, $result_code);

//$default_status_id = PurchaseInvoiceStatus::firstWhere('default', 1)?->id;
//
//dd($default_status_id);

//$value = \Illuminate\Support\Facades\Crypt::decrypt(App\Services\CookieService::instance()->get('primary_color'), false);

//(new TenantService())->updateOrCreateSettings(1);

//dd(\App\Models\Product::with(['stocks'])->find(1));

//dd(\App\Services\StockService::instance()->canSellProductUnit(\App\Models\ProductUnit::find(2), 1));

//\Illuminate\Support\Facades\Artisan::call('migrate');

//\Rupadana\FilamentAnnounce\Announce::make()
//    ->title('Big News!')
//    ->icon('heroicon-o-megaphone')
//    ->body('Filament can now show very important message to specific users!')
//    ->disableCloseButton() // Optional, if you want ur announcement discloseable
//    ->alignCenter()
//    ->announceTo(User::all());

//dd(City::with('areas')->doesntHave('areas')->first(), City::with('areas')->has('areas')->get());
//foreach (City::with('areas')->doesntHave('areas')->get() as $city)
//{
//    dd($city);
//}

//Route::get('/', function (){
//    dd('x');
//});


//dd(\App\Services\MathService::instance()->getTax(100, 15, true));
if (config('app.env') === "production") {
    Route::get('/', function () {
        return redirect('https://client.mybeesystem.com');
    });
}

Route::get('/login', function () {
    return redirect(route('filament.tenant.auth.login'));
})->name('login');

