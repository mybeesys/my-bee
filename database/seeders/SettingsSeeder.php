<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\CacheService;
use App\Services\SettingService;
use App\Services\SMSService;
use App\Traits\SeederHelper;
use App\Traits\SettingTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Tags\Tag;

class SettingsSeeder extends Seeder
{

    /**
     * Run the database seeders.
     *
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $service = new SettingService(null);

        DB::table('settings')->delete();
        DB::table('settings')->truncate();

        CacheService::instance()->forget('settings');

        try {


//        $service->createOrUpdate('passport.auto_reject_passport_expiration_less_than_days', ['en' => 'Auto reject passport that expires in less than (days)', 'ar' => 'الرفض التلقائي لجواز السفر'], "190", 'text', false, $service->rulesForNumber(1, 365 * 2), 'general', [], false, "System",  "Duration in days","الرفض التلقائي لجواز السفر الذي تنتهي صلاحيته في أقل من(عدد الأيام)", 1, 1);

        $service->createOrUpdate('company.name', ['en' => 'Company name', 'ar' => 'إسم الشركة'],"My bee", 'text', false, $service->rulesForString(false), 'general', [], false, 'General', null, null, 1, 2);
        $service->createOrUpdate('company.address', ['en' => 'Company address', 'ar' => 'عنوان الشركة'], null, 'text', false, $service->rulesForString(false), 'general', [], false, 'General', null, null, 2, 2);
        $service->createOrUpdate('company.contact.phone', ['en' => 'Company phone', 'ar' => 'رقم هاتف الشركة'], null, 'text', false, $service->rulesForInternationalPhone(false), 'general', [], false, 'General', null, null, 3, 2);
        $service->createOrUpdate('company.contact.mobile', ['en' => 'Company mobile', 'ar' => 'رقم موبايل الشركة'], null, 'text', false, $service->rulesForInternationalPhone(false), 'general', [], false, 'General', null, null, 4, 2);
        $service->createOrUpdate('company.contact.email', ['en' => 'Company email', 'ar' => 'إيميل الشركة'], null, 'text', false, $service->rulesForEmail(false), 'general', [], false, 'General', null, null, 5, 2);

//        $service->createOrUpdate('company.about_ar', ['en' => 'About company (ar)', 'ar' => 'عن الشركة (عربي)'], "", 'rich-text', false, $service->rulesForString(), 'general', [], false, 'General', null, null, 6, 2);
//        $service->createOrUpdate('company.about_en', ['en' => 'About company (en)', 'ar' => 'عن الشركة (إنجليزي)'], "", 'rich-text', false, $service->rulesForString(), 'general', [], false, 'General', null, null, 7, 2);
//        $service->createOrUpdate('company.mission_ar', ['en' => 'Company mission (ar)', 'ar' => 'مهمة الشركة (عربي)'], "", 'rich-text', false, $service->rulesForString(),'general', [], false, 'General', null, null, 8, 2);
//        $service->createOrUpdate('company.mission_en', ['en' => 'Company mission (en)', 'ar' => 'مهمة الشركة (إنجليزي)'], "", 'rich-text', false, $service->rulesForString(),'general', [], false, 'General', null, null, 9, 2);
//        $service->createOrUpdate('company.vision_ar', ['en' => 'Company vision (ar)', 'ar' => 'نظرة الشركة (عربي)'], "", 'rich-text', false, $service->rulesForString(), 'general', [], false, 'General', null, null, 10, 2);
//        $service->createOrUpdate('company.vision_en', ['en' => 'Company vision (en)', 'ar' => 'نظرة الشركة (إنجليزي)'], "", 'rich-text', false, $service->rulesForString(), 'general', [], false, 'General', null, null, 11, 2);

        $service->createOrUpdate('social.facebook', ['en' => 'Facebook Url', 'ar' => 'رابط فيسبوك'], null, 'text', false, $service->rulesForURL(false), 'social', [], false, 'Social', null, null, 1, 3);
        $service->createOrUpdate('social.youtube', ['en' => 'Youtube Url', 'ar' => 'رابط يوتوب'], null, 'text', false, $service->rulesForURL(false), 'social', [], false, 'Social', null, null, 2, 3);
        $service->createOrUpdate('social.instagram', ['en' => 'Instagram Url', 'ar' => 'رابط إنستقرام'], null, 'text', false, $service->rulesForURL(false), 'social', [], false, 'Social', null, null, 3, 3);
        $service->createOrUpdate('social.twitter', ['en' => 'Twitter Url', 'ar' => 'رابط تويتر'], null, 'text', false, $service->rulesForURL(false), 'social', [], false, 'Social', null, null, 4, 3);
        $service->createOrUpdate('social.pinterest', ['en' => 'Pinterest Url', 'ar' => 'رابط بنترست'], null, 'text', false, $service->rulesForURL(false), 'social', [], false, 'Social', null, null, 5, 3);

//        $service->createOrUpdate('app.theme.color', ['en' => 'Theme color', 'ar' => 'Theme color'], "#F20C90", 'text', false, $service->rulesForString(), 'app', [], false, 'App', null, null, 1, 4);
//        $service->createOrUpdate('app.title.color', ['en' => 'Title color', 'ar' => 'Title color'], "#222222", 'text', false, $service->rulesForString(), 'app', [], false, 'App', null, null, 2, 4);
//        $service->createOrUpdate('app.content.color', ['en' => 'Content color', 'ar' => 'Content color'], "#777777", 'text', false, $service->rulesForString(), 'app', [], false, 'App', null, null, 3, 4);
//        $service->createOrUpdate('app.border.color', ['en' => 'Border color', 'ar' => 'Border color'], "#DDDDDD", 'text', false, $service->rulesForString(), 'app', [], false, 'App', null, null, 4, 4);
//        $service->createOrUpdate('app.grey.light', ['en' => 'Grey light', 'ar' => 'Grey light'], "#EDEFF4", 'text', false, $service->rulesForString(),'app', [], false, 'App', null, null, 5, 4);
//        $service->createOrUpdate('app.version', ['en' => 'Version', 'ar' => 'Version'], "1.0.0", 'text', false, $service->rulesForString(), 'app', [], false, 'App', null, null, 6, 4);
//        $service->createOrUpdate('app.version.code', ['en' => 'Version code', 'ar' => 'Version code'], 1, 'text', false, $service->rulesForNumber(), 'app', [], false, 'App', null, null, 7, 4);
//
////        $service->createOrUpdate('service.terms_and_conditions_ar', ['en' => 'Service terms & conditions (ar)', 'ar' => 'أحكام وشروط الخمة (عربي)'], "", 'rich-text', false, $service->rulesForString(), 'service');
////        $service->createOrUpdate('service.terms_and_conditions_en', ['en' => 'Service terms & conditions (en)', 'ar' => 'أحكام وشروط الخمة (إنجليزي)'], "", 'rich-text', false, $service->rulesForString(), 'service');
////        $service->createOrUpdate('service.privacy_policy_ar', ['en' => 'Privacy policy (ar)', 'ar' => 'سياسة الخصوصية (عربي)'], "", 'rich-text', false, $service->rulesForString(), 'service');
////        $service->createOrUpdate('service.privacy_policy_en', ['en' => 'Privacy policy (en)', 'ar' => 'سياسة الخصوصية (إنجليزي)'], "", 'rich-text', false, $service->rulesForString(), 'service');
//
//        $service->createOrUpdate('sms.provider', ['en' => 'Sms provider', 'ar' => 'مزود خدمة الرسائل'], "App\Services\InfobipService", 'options', false, $service->rulesForString(), 'sms-provider', array_flip((new SMSService())->listSmsProviders()), false, 'Sms provider', null, null, 1, 5);
////        $service->createOrUpdate('sms.provider_website', ['en' => 'SmsProvider provider website', 'ar' => 'رابط مزود الخدمة'], "https://mazinhost.com", 'text', false, [], 'sms-server', null, false, 'SmsProvider Server', null, null, 2, 5);
////        $service->createOrUpdate('sms.alias', ['en' => 'SmsProvider alias', 'ar' => 'إسم الخدمة'], "PinkStore", 'text', false, $service->rulesForString(), 'sms-server', null, false, 'SmsProvider Server', null, null, 3, 5);
////        $service->createOrUpdate('sms.url', ['en' => 'SmsProvider URL', 'ar' => 'رابط الخدمة'], "https://mazinhost.com/smsv1/sms/api?action=send-sms&api_key=Y2FyZGhlcm8yNDlAZ21haWwuY29tOko4WklzTyNRMm4=&to=:phone&from=PinkStore&sms=:msg&unicode=1", 'text', false, $service->rulesForString(), 'sms-server', null, false, 'SmsProvider Server', null, null, 4, 5);
//
//        $service->createOrUpdate('mail.mailer', ['en' => 'Mailer', 'ar' => 'المراسل'], "", 'text', false, $service->rulesForString(), 'mail-server', [], false, 'Mail Server', null, null, 1, 6);
//        $service->createOrUpdate('mail.host', ['en' => 'Mail host', 'ar' => 'المضيف'], "", 'text', false, [], 'mail-server', [], false, 'Mail Server', null, null, 2, 5);
//        $service->createOrUpdate('mail.port', ['en' => 'Mail port', 'ar' => 'المنفذ'], "", 'text', false, $service->rulesForString(), 'mail-server', [], false, 'Mail Server', null, null, 3, 6);
//        $service->createOrUpdate('mail.username', ['en' => 'Mail username', 'ar' => 'إسم المستخدم'], "", 'text', false, $service->rulesForString(), 'mail-server', [], false, 'Mail Server', null, null, 4, 6);
//        $service->createOrUpdate('mail.password', ['en' => 'Mail password', 'ar' => 'كلمة المرور'], "", 'text', false, $service->rulesForString(), 'mail-server', [], true, 'Mail Server', null, null, 5, 6);
//        $service->createOrUpdate('mail.encryption', ['en' => 'Mail encryption', 'ar' => 'نوع التشفير'], "", 'text', false, $service->rulesForString(), 'mail-server', [], false, 'Mail Server', null, null, 6, 6);
//        $service->createOrUpdate('mail.from_address', ['en' => 'Mail from address', 'ar' => 'من العنوان'], "", 'text', false, $service->rulesForString(), 'mail-server', [], false, 'Mail Server', null, null, 7, 6);
//        $service->createOrUpdate('mail.from_name', ['en' => 'Mail from name', 'ar' => 'من الإسم'], "", 'text', false, $service->rulesForString(), 'mail-server', [], false, 'Mail Server', null, null, 8, 6);
//
//        //ehab cpanel api token:34A0RVYLCQDAU8KD3SI1SQX4JBT3NR0T
//        //ehab cpanel username/pass;  test:hUYu(tPptPbU
//        $service->createOrUpdate('cpanel.user', ['en' => 'Cpanel user', 'ar' => 'إسم مستخدم سي بانيل'], "nourappg", 'text', false, $service->rulesForString(), 'cpanel', [], false, 'cPanel', null, null, 1, 7);
//        $service->createOrUpdate('cpanel.password', ['en' => 'Cpanel password', 'ar' => 'كلمة مرور سي بانيل'], "d)7yROKM1y]0p7", 'text', false, $service->rulesForString(), 'cpanel', [], true, 'cPanel', null, null, 2, 7);
//        $service->createOrUpdate('cpanel.api_token', ['en' => 'Cpanel api token', 'ar' => 'رمز سي بانيل'], "14GD1YJOSVG9O5DMABALUM3LFGE4V4SE", 'text', false, $service->rulesForString(), 'cpanel', [], true, 'cPanel', null, null, 3, 7);
//        $service->createOrUpdate('cpanel.server', ['en' => 'Cpanel server', 'ar' => 'خادم سي بانيل'], "nourappglobal.com", 'text', false, $service->rulesForString(), 'cpanel', [], false, 'cPanel', null, false, 4, 7);
//        $service->createOrUpdate('cpanel.port', ['en' => 'Cpanel port', 'ar' => 'منفذ سي بانيل'], "2083", 'text', false, $service->rulesForString(), 'cpanel', [], false, 'cPanel', null, false, 5, 7);
//
//        $service->createOrUpdate('firebase.server_key', ['en' => 'Firebase server key', 'ar' => 'Firebase server key'], "AAAAu9xtOhs:APA91bGJMckyPm4dIviK6SM1vV7Uf8AgxX8_bBLhDaIymzQ3o-EYHWdyY4ImRKfF1hfj7kkhGoYzWDlJPOMIx8ZZHvyRyOXPaUmZKQXI-hxBBtZ9QedU3YT4mrclwbgEAEadDsPYEmj6", 'text', false, $service->rulesForString(), 'firebase', [], true, 'Firebase', null, null, 1, 8);
//
//        $service->createOrUpdate('pusher.app_id', ['en' => 'Pusher app id', 'ar' => 'Pusher app id'], "1692093", 'text', false, $service->rulesForString(), 'pusher', [], false, 'Pusher', null, null, 1, 9);
//        $service->createOrUpdate('pusher.app_key', ['en' => 'Pusher app key', 'ar' => 'Pusher app key'], "275b9ba6a99d4a8439e4", 'text', false, $service->rulesForString(), 'pusher', [], false, 'Pusher', null, null, 2, 9);
//        $service->createOrUpdate('pusher.app_secret', ['en' => 'Pusher app secret', 'ar' => 'Pusher app secret'], "82bbc28ebd0444b209ec", 'text', false, $service->rulesForString(), 'pusher', [], true, 'Pusher', null, null, 3, 9);
//        $service->createOrUpdate('pusher.app_cluster', ['en' => 'Pusher app cluster', 'ar' => 'Pusher app cluster'], "eu", 'text', false, $service->rulesForString(), 'pusher', [], false, 'Pusher', null, null, 4, 9);
//

        $this->setupTabs();

        }catch (\Throwable $exception)
        {
            dd($exception);
        }
    }

    public function setupTabs()
    {
        $service = new SettingService(null);

        $tabs = Setting::get()->pluck('tab')->unique()->toArray();

        $icons = [
//            'system' => 'heroicon-o-home',
            'general' => 'heroicon-o-building-storefront',
            'social' => 'heroicon-o-globe-alt',
//            'app' => 'heroicon-o-device-phone-mobile',
//            'sms-provider' => 'heroicon-o-bell',
//            'mail-server' => 'heroicon-o-at-symbol',
//            'cpanel' => 'heroicon-o-cpu-chip',
//            'firebase' => 'heroicon-o-inbox',
//            'pusher' => 'heroicon-o-bell',
        ];
        foreach ($tabs as $tab) {
            $tab = strtolower(Str::replace(' ', '-', $tab));

            $service->createOrUpdate("settings.tabs.$tab.icon", ['en' => ucwords($tab) . " tab icon", 'ar' => ucwords($tab) . " tab icon"], $icons[$tab] ?? "", 'text', false, $service->rulesForString(), 'settings tabs', [], false, null, null, null, 111, 111, false);

            $bool = in_array($tab, ['app', 'sms-server', 'mail-server', 'cpanel', 'firebase', 'pusher']);
            $service->createOrUpdate("settings.tabs.$tab.requires_special_access", ['en' => ucwords($tab) . " tab special access", 'ar' => ucwords($tab) . " tab special access"], $bool, 'bool', false, $service->rulesForBoolean(), 'settings tabs', [], false, null, null, null, 110, 1010, false);

        }
    }
}
