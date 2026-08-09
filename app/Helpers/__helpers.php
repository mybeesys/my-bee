<?php

use App\Http\Requests\CreateSectionRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Models\Location;
use App\Models\Section;
use App\Models\Tenant;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InfyOm\Generator\Utils\ResponseUtil;
use Laracasts\Flash\Flash;


if (!function_exists('user_send_email_verification')) {
    function user_send_email_verification(Model $user)
    {
        $user->sendEmailVerificationNotification();
    }

}

if (!function_exists('store_translations')) {
    function store_translations($model, $column, $en, $ar)
    {
        $translations = [
            'en' => $en,
            'ar' => $ar
        ];

        $model->setTranslations($column, $translations);

        $model->save();
    }
}

if (!function_exists('model_uses_soft_delete')) {
    function model_uses_soft_delete($model_class)
    {
        return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model_class));
    }
}


if (!function_exists('model_uses_media')) {
    function model_uses_media($model_class)
    {
        return in_array('Spatie\MediaLibrary\InteractsWithMedia', class_uses($model_class));
    }
}

if (!function_exists('arr_rand')) {
    function arr_rand($array, $number)
    {
        $keys = array_rand($array, $number);

        return $array[$keys];
    }
}

if (!function_exists('is_admin_panel')) {
    function is_admin_panel(): bool
    {
        if (! class_exists(\Filament\Facades\Filament::class)) {
            return false;
        }

        try {
            return filament()->getCurrentPanel()?->getId() === 'admin';
        } catch (\Throwable) {
            return false;
        }
    }
}

if (!function_exists('settings')) {
    function settings(array $type = []): Collection
    {
        if (is_admin_panel()) {
            $settings = platform_settings();
        } else {
            $settings = \App\Services\CacheService::instance()
                ->remember('settings', \App\Services\CacheService::TTL_DAY, function () {
                    return \App\Models\Setting::where('tenant_id', filament()->getTenant()?->id)->orderBy('sort')->get();
                });
        }

        if (count($type) > 0) {
            $settings = $settings->whereIn('type', $type);
        }

        return $settings;
    }
}

if (!function_exists('setting')) {
    function setting($key, $default = ''): ?string
    {
        if (is_admin_panel()) {
            return platform_setting($key, $default);
        }

        $item = settings()->firstWhere('key', $key);

        return $item == null ? $default : $item->value;
    }
}

if (!function_exists('platform_settings')) {
    function platform_settings(): \Illuminate\Support\Collection
    {
        return \App\Services\CacheService::instance()->remember(
            'platform_settings',
            \App\Services\CacheService::TTL_DAY,
            fn () => \App\Models\Setting::query()
                ->whereNull('tenant_id')
                ->orderBy('sort')
                ->get(),
        );
    }
}

if (!function_exists('platform_setting')) {
    function platform_setting(string $key, mixed $default = ''): ?string
    {
        $item = platform_settings()->firstWhere('key', $key);

        return $item === null ? $default : $item->value;
    }
}

if (!function_exists('platform_company_profile')) {
    /** @return array{name: string, address: string, phone: string, mobile: string, email: string, trn: string} */
    function platform_company_profile(): array
    {
        return [
            'name' => trim((string) platform_setting('company.name', 'MyBee System')),
            'address' => trim((string) platform_setting('company.address', '')),
            'phone' => trim((string) platform_setting('company.contact.phone', '')),
            'mobile' => trim((string) platform_setting('company.contact.mobile', '')),
            'email' => trim((string) platform_setting('company.contact.email', '')),
            'trn' => trim((string) platform_setting('company.trn', '')),
        ];
    }
}

if (!function_exists('settings_tab_icon')) {
    function settings_tab_icon(string $tab, string $default = 'heroicon-o-cog-6-tooth'): string
    {
        $key = 'settings.tabs.' . strtolower(str_replace(' ', '-', $tab)) . '.icon';
        $icon = setting($key, $default);

        if (! is_string($icon) || $icon === '') {
            return $default;
        }

        // Reject encrypted leftovers / corrupt values used as Blade icon names.
        if (str_starts_with($icon, 'eyJ') || strlen($icon) > 80 || ! preg_match('/^[a-zA-Z0-9:_-]+$/', $icon)) {
            return $default;
        }

        return $icon;
    }
}

if (!function_exists('settings_by_tag')) {
    function settings_by_tag(array $tags = []): Collection
    {
        return settings()->where('tags', $tags);
    }
}

if (!function_exists('settings_by_group')) {
    function settings_by_group(array $groups, array $type = []): Collection
    {
        return settings($type)->whereIn('group', $groups);
    }
}

if (!function_exists('settings_by_tab')) {
    function settings_by_tab(string $tab, array $type = []): Collection
    {
        return settings($type)
            ->where('tab', $tab)
            ->sortBy('sort')
            ->values();
    }
}

if (!function_exists('obfuscate_email')) {
    function obfuscate_email(string $email): string
    {
        $is_email = filter_var('bob@example.com', FILTER_VALIDATE_EMAIL);
        if ($is_email) {
            $em = explode("@", $email);
            $name = implode('@', array_slice($em, 0, count($em) - 1));
            $len = floor(strlen($name) / 2);

            return substr($name, 0, $len) . str_repeat('*', $len) . "@" . end($em);
        }
        return '';
    }
}


if (!function_exists('get_client')) {
    /**
     * Get the current client
     * @param boolean $throwError <p>
     * Default value: false,
     * Throw Error(true) if user not logged in,
     * if false returns null
     * </p>
     */
    function get_client(): ?\App\Models\Client
    {
        if ($user = get_user()) {
            return $user->client;
        }
        throw new Exception("User not logged in");
    }
}

if (!function_exists('get_unread_notifications')) {
    /**
     * Get Unread notifications of (current or specified user) or empty
     * collection if user not logged in
     * @param \App\Models\User $user
     * <p>
     * Default value: null,
     * Get unread notifications of specified user
     * </p>
     */
    function get_unread_notifications(\App\Models\User $user = null): \Illuminate\Notifications\DatabaseNotificationCollection
    {
        if ($user) {
            return $user->unreadNotifications;
        } else {
            if (auth()->check()) {
                return auth()->user()->unreadNotifications;
            }
        }
        return new \Illuminate\Notifications\DatabaseNotificationCollection([]);
    }
}

if (!function_exists('get_read_notifications')) {
    /**
     * Get read notifications of (current or specified user) or empty
     * collection if user not logged in
     * @param \App\Models\User $user
     * <p>
     * Default value: null,
     * Get read notifications of specified user
     * </p>
     */
    function get_read_notifications(\App\Models\User $user = null): Collection
    {
        if ($user) {
            return $user->notifications->filter(function ($item) {
                return $item->read_at != null;
            });
        } else {
            if (auth()->check()) {
                return auth()->user()->notifications->filter(function ($item) {
                    return $item->read_at != null;
                });
            }
        }
        return collect();
    }
}
if (!function_exists('get_notifications')) {
    /**
     * Get notifications of (current or specified user) or empty
     * collection if user not logged in
     * @param \App\Models\User $user
     * <p>
     * Default value: null,
     * Get notifications of specified user
     * </p>
     */
    function get_notifications(\App\Models\User $user = null): \Illuminate\Notifications\DatabaseNotificationCollection
    {
        if ($user) {
            return $user->notifications;
        } else {
            if (auth()->check()) {
                return auth()->user()->notifications;
            }
        }
        return new \Illuminate\Notifications\DatabaseNotificationCollection([]);
    }
}


function send_notification_FCM_via_tokens(array $tokens, $title, $body, $icon = null)
{


    if (!$icon)
        $icon = "https://pinkstore.sd/images/PinkStoreLogo.png";

    $serverKey = 'AAAA_OIz0vs:APA91bGg6XIaZrHcndgtm1RQHCH1VBA2dg6cZ1emTnf7BJy_vxc0REk0NhHXGQfVfw_lN3p7_RqhJAztuj_B3vN0Vs1hncZZHfwJiIEPQDhJmUEtUxppg-WXrxytQBOnFVH23TlMUlor';
    $url = "https://fcm.googleapis.com/fcm/send";
    $notification = array('title' => $title, 'body' => $body, 'sound' => 'default', 'badge' => '1', 'image' => $icon);


    // MONZER REPLACE /TOPICS/ALL with $token -> This mean device token to send to spac device
    //to does not accept array
    $arrayToSend = array("registration_ids" => $tokens, 'notification' => $notification, 'priority' => 'high');
    $json = json_encode($arrayToSend);
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: key=' . $serverKey;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    //Send the request
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function send_notification_FCM_via_topic($topic, $title, $body, $icon = null)
{

    if (!in_array($topic, ['/topics/all', '/topics/customers', '/topics/drivers', '/topics/admins']))
        abort(400, 'invalid fcm topic');

    if (!$icon)
        $icon = "https://pinkstore.sd/images/PinkStoreLogo.png";

    $serverKey = env('FCM_KEY');
    $url = "https://fcm.googleapis.com/fcm/send";
    $notification = array('title' => $title, 'body' => $body, 'sound' => 'default', 'badge' => '1', 'icon' => $icon);

    // MONZER REPLACE /TOPICS/ALL with $token -> This mean device token to send to spac device
    //to does not accept array
    $arrayToSend = array("to" => $topic, 'notification' => $notification, 'priority' => 'high');
    $json = json_encode($arrayToSend);
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: key=' . $serverKey;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    //Send the request
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function sanitize_sdn_phone($phone, $withPayload = true)
{
    $phone = Str::replace(' ', '', $phone);

    $payload = ['phone' => $phone, 'sanitized' => false, 'valid' => false];

    $validator = Validator::make([$phone], ['phone:SD']);

    if ($validator->passes()) {
        switch ($phone) {
            case Str::startsWith($phone, '249'): //already sanitized
            {
                $payload = ['phone' => $phone, 'sanitized' => true, 'valid' => true];
                break;
            }
            case Str::startsWith($phone, ['010', '011', '012']): //sudani
            {
                $payload = ['phone' => "249" . ltrim($phone, '0'), 'sanitized' => true, 'valid' => true];
                break;
            }
            case Str::startsWith($phone, ['090', '091', '096']): //zain
            {
                $payload = ['phone' => "249" . ltrim($phone, '0'), 'sanitized' => true, 'valid' => true];
                break;
            }
            case Str::startsWith($phone, ['092', '093', '095', '099']): //mtn
            {
                $payload = ['phone' => "249" . ltrim($phone, '0'), 'sanitized' => true, 'valid' => true];
                break;
            }
            default :
                $payload = ['phone' => $phone, 'sanitized' => false, 'valid' => false];

        }
    }

    return $withPayload ? $payload : $payload['phone'];
}


function generate_no($model_class)
{
    $item = ($model_class)::orderByDesc('id')->get()->first();
    return str_pad($item ? $item->id + 1 : 1, 6, '0', STR_PAD_LEFT);
}

function generate_op()
{
    $op = \App\Models\Op::orderByDesc('id')->get()->first();
    return str_pad($op != null ? (int)$op->no + 1 : 1, 6, '0', STR_PAD_LEFT);
}

function generate_receipt_voucher()
{
    $item = \App\Models\ReceiptVoucher::orderByDesc('id')->get()->first();
    return str_pad($item ? (int)$item->no + 1 : 1, 6, '0', STR_PAD_LEFT);
}

function generate_payment_voucher()
{
    $item = \App\Models\PaymentVoucher::orderByDesc('id')->get()->first();
    return str_pad($item ? (int)$item->no + 1 : 1, 6, '0', STR_PAD_LEFT);
}

function generate_invoice_no()
{
    $item = \App\Models\Invoice::orderByDesc('no')->first();
    $startingNumber = intval(setting('invoice_starting_number', 1));
    $length = intval(setting('invoice_number_digits', 6));
    $prefix = setting('invoice_prefix', null);

    if ($item) {
        $no = intval(preg_replace('/\D/', '', $item->no)) + 1;
    } else {
        $no = $startingNumber;
    }

    return $prefix . str_pad($no, $length, '0', STR_PAD_LEFT);
}

//    function str($string = null)
//    {
//        if (is_null($string)) return new class {
//            public function __call($method, $params)
//            {
//                return Str::$method(...$params);
//            }
//        };
//
//        return Str::of($string);
//    }

function ex_rate()
{
    return setting('finance.sdg.usd.exchange_rate', 0);
}

function current_user_attribute($attribute = "full_name")
{
    return auth()->user()->{$attribute};
}

function user_attribute(\App\Models\User $user, $attribute = "full_name")
{
    return $user->{$attribute};
}

function send_filament_notification($title, $body, $type = "success", $persist = false, Collection $db_recipients = null)
{
    if ($db_recipients == null)
        $db_recipients = collect();

    $service = new \App\Services\FilamentNotificationService();
    switch ($type) {
        case "success":
        {
            $service->success($title, $body, $persist, $db_recipients);
            break;
        }
        case "warning":
        {
            $service->warning($title, $body, $persist, $db_recipients);
            break;
        }
        case "danger":
        {
            $service->danger($title, $body, $persist, $db_recipients);
            break;
        }
        default :
        {
            throw new Exception('Unsupported notification type');
        }
    }
}

function filament_db_notification($message, Collection $db_recipients)
{
    $service = new \App\Services\FilamentNotificationService();

    return $service->message($message)->recipients($db_recipients)->sendDatabaseNotification();
}

function filament_notification_service(): \App\Services\FilamentNotificationService
{
    return new \App\Services\FilamentNotificationService();
}

function module_enabled(string $module): bool
{
    $module = strtolower(str_replace(' ', '_', $module));
    $modules = collect(config('system.modules', []));
    return $modules->where('code', $module)->where('active', true)->count() > 0;
}

function generate_double_entry_transaction_id(): int
{
    $last_transaction = \App\Models\CashDet::max('transaction_id');
    return $last_transaction + 1;
}

function make_general_voucher_op(): \App\Models\Op
{
    return \App\Models\Op::create(
        [
            'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
            'type' => "general-voucher", //قيد عام
            'user_id' => auth()->id(),
            'no' => generate_op(),
            'payment_voucher_no' => null,
            'date' => now(),
            'locked_at' => null,
            'submitted_at' => null,
            'files' => null,
        ]
    );
}

function make_cash_receipt_voucher_op(): \App\Models\Op
{
    return \App\Models\Op::create(
        [
            'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
            'type' => "cash-receipt-voucher", //قيد عام
            'user_id' => auth()->id(),
            'no' => generate_op(),
            'payment_voucher_no' => null,
            'date' => now(),
            'locked_at' => null,
            'submitted_at' => null,
            'files' => null,
        ]
    );
}

function make_bank_transfer_receipt_voucher_op(): \App\Models\Op
{
    return \App\Models\Op::create(
        [
            'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
            'type' => "bank-transfer-receipt-voucher",
            'user_id' => auth()->id(),
            'no' => generate_op(),
            'payment_voucher_no' => null,
            'date' => now(),
            'locked_at' => null,
            'submitted_at' => null,
            'files' => null,
        ]
    );
}

function make_cash_payment_voucher_op(): \App\Models\Op
{
    return \App\Models\Op::create(
        [
            'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
            'type' => "cash-payment-voucher",
            'user_id' => auth()->id(),
            'no' => generate_op(),
            'payment_voucher_no' => null,
            'date' => now(),
            'locked_at' => null,
            'submitted_at' => null,
            'files' => null,
        ]
    );
}

function make_bank_transfer_payment_voucher_op(): \App\Models\Op
{
    return \App\Models\Op::create(
        [
            'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
            'type' => "bank-transfer-payment-voucher",
            'user_id' => auth()->id(),
            'no' => generate_op(),
            'payment_voucher_no' => null,
            'date' => now(),
            'locked_at' => null,
            'submitted_at' => null,
            'files' => null,
        ]
    );
}

function make_taxes_op(): \App\Models\Op
{
    return \App\Models\Op::create(
        [
            'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
            'type' => "taxes",
            'user_id' => auth()->id(),
            'no' => generate_op(),
            'payment_voucher_no' => null,
            'date' => now(),
            'locked_at' => null,
            'submitted_at' => null,
            'files' => null,
        ]
    );
}

function make_voucher_op($type): \App\Models\Op
{
    return \App\Models\Op::create(
        [
            'type' => $type, //قيد عام
            'user_id' => auth()->id(),
            'no' => generate_op(),
            'payment_voucher_no' => null,
            'date' => now(),
            'locked_at' => null,
            'submitted_at' => null,
            'files' => null,
        ]
    );
}

function purchase_invoice_status_pending(): int
{
    return 1;
}

function sales_invoice_status_initial(): int
{
    return 4;
}

function sales_invoice_status_pending_payment(): int
{
    return 5;
}

function sales_invoice_status_payment_completed(): int
{
    return 6;
}

function can_lock_invoice(): bool
{
    return auth()->user()->isClient();
}

function can_lock_journal_entry(): bool
{
    return auth()->user()->isSuperAdmin();
}


function is_number($value): bool
{
    return is_int($value) or is_float($value) or is_numeric($value) or is_double($value);
}

function tafqeet($amount, $currency, $withNotes = false)
{
    if (!$amount)
        return "";

    $amount = Str::replace(',', '', $amount);

    if (!is_number($amount))
        return "";

    if ($withNotes) {
        return \Alkoumi\LaravelArabicTafqeet\Tafqeet::inArabic($amount, $currency);
    }

    return Str::replace(['فقط', 'لا غير'], '', \Alkoumi\LaravelArabicTafqeet\Tafqeet::inArabic($amount, $currency));
}


function profit_percent($retail_price, $unit_cost): float|int|string
{
    if (!is_number($retail_price) or !is_number($unit_cost) or
        !$retail_price or $retail_price == 0 or !$unit_cost or $unit_cost == 0)
        return 0;

    return (($retail_price - $unit_cost) / $unit_cost) * 100;
}

function percent($amount, $total): float|int|string
{
    if ($total == 0)
        return 0;
    return ($amount / $total) * 100;
}

function format_account_statement_text(?string $statement): string
{
    $text = strip_tags((string) $statement);

    return str_replace('تسوية', 'سداد', $text);
}

function can_delete_product(\App\Models\Product $product): bool
{
    return false;
    $product->loadMissing('acc4');

    $invoices = \App\Models\InvoiceItem::where('product_id', $product->id)->count();
    $paymentVouchers = \App\Models\PaymentVoucher::where('debit_acc4_code', $product->acc4->code)
        ->orWhere('credit_acc4_code', $product->acc4->code)->count();

//    $receiptVouchers = \App\Models\ReceiptVoucher::where('acc4_code', $product->acc4->code)->count();

    return $invoices == 0 and $paymentVouchers == 0;// and $receiptVouchers == 0;
}

function can_delete_contractor(\App\Models\Contractor $product): bool
{
    return false;
}

function can_delete_worker(\App\Models\Worker $product): bool
{
    return false;
}

function admin_panel_url()
{
    return env('APP_URL') . '' . Str::endsWith(config('filament.path'), '/') ? config('filament.path') : config('filament.path') . '/';
//        return env('APP_URL');
//        return "/".config('filament.path');
}


function fns(): \App\Services\FilamentNotificationService
{
    return new \App\Services\FilamentNotificationService();
}

if (!function_exists('tenant_client')) {
    function tenant_client(): ?\App\Models\Client
    {
        return filament()->auth()->user()->client;
    }
}

if (!function_exists('custom_slug')) {
    function custom_slug($string, $separator = '-')
    {
        if (is_null($string)) {
            return "";
        }

        $string = trim($string);

        $string = mb_strtolower($string, "UTF-8");;

        $string = str($string)->replace('@', ' at')->value();

        $string = preg_replace("/[^a-z0-9_\sءاأإآؤئبتثجحخدذرزسشصضطظعغفقكلمنهويةى]#u/", "", $string);

        $string = preg_replace("/[\s-]+/", " ", $string);

        $string = preg_replace("/[\s_]/", $separator, $string);

        $string = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', strtolower($string));

        return $string;
    }
}
/**
 * (reject)/Check if collection has the given columns translated based on the current locale
 *
 * @param Collection $data collection
 * @return array columns to be checked.
 */
function collection_has_trans(Collection $data, array $columns): Collection
{
    return $data->reject(function ($item) use ($columns) {
        $result = false;
        foreach ($columns as $column) {
            $result = $item->{$column} == "";
            if ($result == true)
                return true;
        }
        return $result;
    });
}

if (!function_exists('str_contains_with_results')) {
    /**
     * str_contains_with_results
     * returns the found needles
     */
    function str_contains_with_results($haystack, $needles, $ignoreCase = false): array
    {
        $match = [];

        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        if (!is_iterable($needles)) {
            $needles = (array)$needles;
        }

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                $match[] = $needle;
            }
        }

        return $match;
    }

    function numbers_to_words($amount, $locale = null): ?string
    {

        if (null == $locale)
            $locale = app()->getLocale();

        $amount = Str::replace(',', '', $amount);

//            $amount = filter_var($amount, FILTER_SANITIZE_NUMBER_FLOAT);

        if (blank($amount) or !is_number($amount))
            return null;

        $formatter = new NumberFormatter($locale, NumberFormatter::SPELLOUT);

        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, currency_decimals());

        return $formatter->format($amount);
    }

    function format_amount($amount, $style = NumberFormatter::DECIMAL, $locale = "en", $includeCurrencyCode = false): ?string
    {
        $amount = Str::replace(',', '', $amount);

        if (blank($amount) or !is_number($amount))
            return null;

        $formatter = new NumberFormatter($locale, $style);

        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, currency_decimals());

        if ($includeCurrencyCode)
            return main_currency_iso_code() . " " . $formatter->format($amount);

        return $formatter->format($amount);
    }


    function user_setting(string $name, $default = null, $guard = "web"): mixed
    {
        $user = auth($guard)->user();

        return $user ? $user->setting($name, $default) : $default;
    }
}

if (!function_exists('hidden_tenant_id_field')) {
    function hidden_tenant_id_field(): \Filament\Forms\Components\Field
    {
        return \Filament\Forms\Components\Hidden::make('tenant_id')->default(filament()->getTenant()->id ?? request()->header('Tenant-Id'));
    }
}

if (!function_exists('hidden_main_currency_field')) {
    function hidden_main_currency_field($name = "currency_iso_code", $default = "SAR"): \Filament\Forms\Components\Field
    {
        return \Filament\Forms\Components\Hidden::make($name)->default(setting('main_currency', $default));
    }
}

if (!function_exists('hidden_user_id_field')) {
    function hidden_user_id_field($name = "user_id", $default = null): \Filament\Forms\Components\Field
    {
        return \Filament\Forms\Components\Hidden::make($name)
            ->default(fn () => $default ?? filament()->auth()->id() ?? auth()->id());
    }
}

if (!function_exists('hidden_invoice_no_field')) {
    function hidden_invoice_no_field($name = "no", $default = null): \Filament\Forms\Components\Field
    {
        return \Filament\Forms\Components\Hidden::make($name)->default($default ?? generate_invoice_no());
    }
}


if (!function_exists('main_currency_iso_code')) {
    function main_currency_iso_code($default = "SAR"): ?string
    {
        return setting('main_currency', $default);
    }
}

if (!function_exists('main_currency_native_symbol')) {
    function main_currency_native_symbol($default = "ر.س."): ?string
    {
        return \App\Services\CacheService::instance()->remember('currencies', \App\Services\CacheService::TTL_DAY, function () {
            return \App\Models\Currency::select('iso_code', 'symbol_native')->get();
        })->firstWhere('iso_code', main_currency_iso_code())->symbol_native ?? $default;
    }
}


if (!function_exists('currency_decimals')) {
    function currency_decimals($default = 2): int
    {
        return setting('main_currency_decimals', $default);
    }
}

if (!function_exists('format_currency_decimals')) {
    function format_currency_decimals(): callable
    {
        return function () {

        };
    }
}

if (!function_exists('extract_values_from_array_that_has_key_starts_with')) {
    function extract_values_from_array_that_has_key_starts_with($startWith, array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (str($key)->startsWith($startWith)) {
                $result[] = $value;
            }
        }
        return $result;
    }
}

if (!function_exists('extract_data_from_array_that_has_key_starts_with')) {
    function extract_data_from_array_that_has_key_starts_with($startWith, array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (str($key)->startsWith($startWith)) {
                $arr = explode($startWith, $key);
                if ($value)
                    $result[] = $arr[1];
            }
        }
        return $result;
    }
}

if (!function_exists('extract_dynamic_product_extras_from_array_that_has_key_starts_with')) {
    function extract_dynamic_product_extras_from_array_that_has_key_starts_with($startWith, array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (str($key)->startsWith($startWith)) {
                $arr = explode($startWith, $key);
                if ($value)
                    $result[] = $arr[1];
            }
        }
        return $result;
    }
}

if (!function_exists('arabic_for_pdf')) {
    function arabic_for_pdf(?string $text): string
    {
        if (blank($text)) {
            return '';
        }

        static $arabic = null;
        $arabic ??= new \ArPHP\I18N\Arabic();

        return $arabic->utf8Glyphs($text, 200, true, true);
    }
}

if (!function_exists('get_tenant')) {
    function get_tenant(): ?Tenant
    {
        if(\Filament\Facades\Filament::getTenant() != null)
            return \Filament\Facades\Filament::getTenant();

        if(request()->header('Tenant-Id'))
            return Tenant::findOrFail(request()->header('Tenant-Id'));

        if(request()->header('Store-Slug'))
            return Tenant::firstWhere('slug', request()->header('Store-Slug'));

        return null;
    }
}

if (!function_exists('get_user')) {
    function get_user(): \Illuminate\Contracts\Auth\Authenticatable
    {
        return auth()->user() ?? auth('sanctum')->user();
    }
}

if (!function_exists('public_asset_if_exists')) {
    function public_asset_if_exists(string $path): ?string
    {
        return is_file(public_path($path)) ? asset($path) : null;
    }
}

if (!function_exists('system_logo_icon_url')) {
    function system_logo_icon_url(): ?string
    {
        if (is_file(public_path('logo-icon.svg'))) {
            return asset('logo-icon.svg');
        }

        foreach ([
            'logo-icon.png' => IMAGETYPE_PNG,
            'logo.jpg' => IMAGETYPE_JPEG,
            'logo.webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : null,
        ] as $filename => $expectedType) {
            $path = public_path($filename);

            if (! is_file($path) || $expectedType === null) {
                continue;
            }

            if (@exif_imagetype($path) === $expectedType) {
                return asset($filename);
            }
        }

        return null;
    }
}

if (!function_exists('system_brand_logo_url')) {
    function system_brand_logo_url(): ?string
    {
        if (is_file(public_path('brand-logo.svg'))) {
            return asset('brand-logo.svg');
        }

        foreach (['brand-logo.png', 'brand-logo.webp', 'brand-logo.jpg'] as $filename) {
            $path = public_path($filename);

            if (! is_file($path)) {
                continue;
            }

            $type = @exif_imagetype($path);

            if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_JPEG || (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP)) {
                return asset($filename);
            }
        }

        return system_logo_icon_url();
    }
}

if (!function_exists('system_logo_url')) {
    function system_logo_url(): ?string
    {
        return system_brand_logo_url();
    }
}


