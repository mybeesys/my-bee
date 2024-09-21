<?php


namespace App\Services;

use App\Models\Acc1;
use App\Models\Acc2;
use App\Models\Acc3;
use App\Models\Acc4;
use App\Models\AdditionalCostType;
use App\Models\Category;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantLibrary;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    public static function instance()
    {
        return new TenantService();
    }

    public function createTenant(Client $client, array $tenantData): ?Tenant
    {
        $client->loadMissing('user');

        $tenantData['client_id'] = $client->id;

        $tenant = Tenant::create($tenantData);

        $tenant->members()->attach($client->user);

        $this->seedData($tenant->id);

        return $tenant;
    }

//    public function deleteTenant(Tenant $tenant): ?Tenant
//    {
//
//
//        return $tenant;
//    }

    public function seedData($tenant_id): void
    {
        //roles

        $roles = [
            'supervisor',
        ];

        $roleService = new RoleService();

        foreach ($roles as $role) {
            $roleService->create($role, "web", $tenant_id);
        }
        //roles

        //settings
        $this->updateOrCreateSettings($tenant_id);
        //settings

        //financeAccounts
        $this->acc1($tenant_id);
        $this->acc2($tenant_id);
        $this->acc3($tenant_id);
        $this->acc4($tenant_id);
        //financeAccounts

        //units

        $data = array(
            array('name' => 'قطعة', 'created_at' => now()),
            array('name' => 'كرتونة', 'created_at' => now()),
            array('name' => 'كيلو جرام', 'created_at' => now()),
            array('name' => 'جرام', 'created_at' => now()),
            array('name' => 'رطل', 'created_at' => now()),
            array('name' => 'جوال', 'created_at' => now()),
            array('name' => 'طن', 'created_at' => now()),
        );

        foreach ($data as $item) {
            $item['tenant_id'] = $tenant_id;
            Unit::firstOrCreate(['name' => $item['name'], 'tenant_id' => $tenant_id], $item);
        }

        //units


        //categories
//        $this->createCategories($tenant_id);
        //categories

        //warehouses
        $this->createWarehouses($tenant_id);
        //warehouses

        //VariantLibraries and options
        $this->createVariantLibraries($tenant_id);
        //VariantLibraries and options

        //products
//        $this->createProducts($tenant_id);
        //products

        //suppliers
//        $this->createSuppliers($tenant_id);
        //suppliers


        //taxProfiles
        $this->createTaxProfiles($tenant_id);
        //taxProfiles

        //AdditionalCostTypes
        $this->createAdditionalCostTypes($tenant_id);
        //iAdditionalCostTypes


        //currencies
        foreach (self::currenciesList() as $item) {
            $item['tenant_id'] = $tenant_id;
            Currency::updateOrCreate(['tenant_id' => $tenant_id, 'iso_code' => $item['iso_code']], $item);
        }
        //currencies


    }

    public function acc1($tenant_id)
    {
        $data = [
            [
                'code' => 1,
                'name' => 'الاصول',
                'normal' => 1,
            ],
            [
                'code' => 2,
                'name' => 'الخصوم',
                'normal' => -1,
            ],
            [
                'code' => 3,
                'name' => 'حقوق الملكية',
                'normal' => 1,
            ],
            [
                'code' => 4,
                'name' => 'الايرادات',
                'normal' => 1,
            ],
            [

                'code' => 5,
                'name' => 'المصروفات',
                'normal' => -1,
            ]
        ];


        foreach ($data as $item) {
            $item['tenant_id'] = $tenant_id;
            Acc1::firstOrCreate(['tenant_id' => $tenant_id, 'code' => $item['code'], 'name' => $item['name']], $item);
        }
    }

    public function acc2($tenant_id)
    {
        $data = array(
            array('code' => '11', 'acc1_code' => '1', 'name' => 'الاصول الثابتة'),
            array('code' => '12', 'acc1_code' => '1', 'name' => 'الاصول المتداولة'),
            array('code' => '21', 'acc1_code' => '2', 'name' => 'خصوم قصيرة الأجل (متداولة)'),
            array('code' => '22', 'acc1_code' => '2', 'name' => 'خصوم طويلة الأجل'),
            array('code' => '41', 'acc1_code' => '4', 'name' => 'إيرادات النشاط'),
            array('code' => '42', 'acc1_code' => '4', 'name' => 'إيرادات أخرى'),
            array('code' => '51', 'acc1_code' => '5', 'name' => 'مصروفات ادارية'),
            array('code' => '52', 'acc1_code' => '5', 'name' => 'مصروفات تشغيلية'),
            array('code' => '53', 'acc1_code' => '5', 'name' => 'المصروفات')
        );

        foreach ($data as $item) {
            $item['tenant_id'] = $tenant_id;
            Acc2::firstOrCreate(
                [
                    'tenant_id' => $tenant_id,
                    'acc1_code' => $item['acc1_code'],
                    'code' => $item['code'],
                    'name' => $item['name']
                ], $item);
        }
    }

    public function acc3($tenant_id)
    {
        $data = array(
            array('code' => '1101', 'acc2_code' => '11', 'name' => 'أثاثات'),
            array('code' => '1102', 'acc2_code' => '11', 'name' => 'الاراضي'),
            array('code' => '1103', 'acc2_code' => '11', 'name' => 'مباني'),
            array('code' => '1104', 'acc2_code' => '11', 'name' => 'سيارات'),
            array('code' => '1201', 'acc2_code' => '12', 'name' => 'نقدية بالخزينة'),
            array('code' => '1202', 'acc2_code' => '12', 'name' => 'نقدية بالبنوك'),
            array('code' => '1203', 'acc2_code' => '12', 'name' => 'المدينون العملاء'),
            array('code' => '1204', 'acc2_code' => '12', 'name' => 'المخزون'),
            array('code' => '1205', 'acc2_code' => '12', 'name' => 'وسيط المخزون'),
            array('code' => '1206', 'acc2_code' => '12', 'name' => 'سندات القبض (شيكات تحت التحصيل)'),
            array('code' => '1207', 'acc2_code' => '12', 'name' => 'المدينون'),
            array('code' => '1208', 'acc2_code' => '12', 'name' => 'بنك الخرطوم - الشركة '),
            array('code' => '1209', 'acc2_code' => '12', 'name' => 'نقدية بالخزنة الرئيسية '),
            array('code' => '1210', 'acc2_code' => '12', 'name' => 'نقدية دولار '),
            array('code' => '1211', 'acc2_code' => '12', 'name' => 'نقدية درهم '),
            array('code' => '1212', 'acc2_code' => '12', 'name' => 'نقدية ريال '),
            array('code' => '1213', 'acc2_code' => '21', 'name' => 'المناديب'),
            array('code' => '1214', 'acc2_code' => '21', 'name' => 'الدائنون (الموردون)'),
            array('code' => '1215', 'acc2_code' => '21', 'name' => 'سندات الصرف (شيكات تحت التحصيل)'),
            array('code' => '1216', 'acc2_code' => '21', 'name' => 'رواتب مستحقة'),
            array('code' => '1217', 'acc2_code' => '21', 'name' => 'دائنون اخرون'),
            array('code' => '1218', 'acc2_code' => '41', 'name' => 'المبيعات'),
            array('code' => '1219', 'acc2_code' => '41', 'name' => 'مردودات المبيعات'),
            array('code' => '1220', 'acc2_code' => '42', 'name' => 'الإستقطاعات'),
            array('code' => '1221', 'acc2_code' => '41', 'name' => 'الخدمات'),
            array('code' => '1223', 'acc2_code' => '53', 'name' => 'المصروفات'),
            array('code' => '1225', 'acc2_code' => '53', 'name' => 'تكلفة المبيعات'),
            array('code' => '1226', 'acc2_code' => '53', 'name' => 'المشتريات'),
            array('code' => '1227', 'acc2_code' => '12', 'name' => 'تحويل بالبنوك'),
            array('code' => '1228', 'acc2_code' => '12', 'name' => 'الضرائب'),
            array('code' => '1229', 'acc2_code' => '12', 'name' => 'مردودات المشتريات'),

        );


        foreach ($data as $item) {
            $item['tenant_id'] = $tenant_id;

            Acc3::firstOrCreate(
                [
                    'tenant_id' => $tenant_id,
                    'code' => $item['code'],
                    'acc2_code' => $item['acc2_code'],
                    'name' => $item['name']
                ], $item);
        }
    }

    public function acc4($tenant_id)
    {
        $data = array(
            array('code' => '120100001', 'acc3_code' => '1201', 'name' => 'الخزينة (ريال)'),

            array('code' => '121800001', 'acc3_code' => '1218', 'name' => 'المبيعات'),
            array('code' => '121900001', 'acc3_code' => '1219', 'name' => 'مردودات المبيعات'),

            array('code' => '122600001', 'acc3_code' => '1226', 'name' => 'المشتريات'),
            array('code' => '121900001', 'acc3_code' => '1229', 'name' => 'مردودات المشتريات'),

            array('code' => '122300001', 'acc3_code' => '1223', 'name' => 'المصروفات'),
            array('code' => '122300002', 'acc3_code' => '1223', 'name' => 'مصروفات المبيعات'),
            array('code' => '122300003', 'acc3_code' => '1223', 'name' => 'مصروفات المشتريات'),

            array('code' => '122500001', 'acc3_code' => '1225', 'name' => 'تكلفة المبيعات'),
            array('code' => '122500002', 'acc3_code' => '1225', 'name' => 'التكاليف الإضافية'),
            array('code' => '122700001', 'acc3_code' => '1227', 'name' => 'تحويل بنكي (الراجحي 1)'),
            array('code' => '122100001', 'acc3_code' => '1221', 'name' => 'الخدمات'),
            array('code' => '122800001', 'acc3_code' => '1228', 'name' => 'ضرائب المصروفات'),
            array('code' => '122800002', 'acc3_code' => '1228', 'name' => 'ضرائب المشتريات'),
            array('code' => '122800003', 'acc3_code' => '1228', 'name' => 'ضرائب المبيعات'),
            array('code' => '122800004', 'acc3_code' => '1228', 'name' => 'ضرائب الخدمات'),


        );

        foreach ($data as $item) {
            $item['tenant_id'] = $tenant_id;

            Acc4::firstOrCreate(
                [
                    'tenant_id' => $tenant_id,
                    'acc3_code' => $item['acc3_code'],
                    'code' => $item['code'],
                ], $item);
        }
    }

    public function currenciesList(): array
    {
        $list = [
            [
                "name" => "US Dollar",
                "name_plural" => "US dollars",
                "symbol" => "$",
                "symbol_native" => "$",
                "iso_code" => "USD",
                "country_code_alpha2" => "US"
            ],
            [
                "name" => "Canadian Dollar",
                "name_plural" => "Canadian dollars",
                "symbol" => "CA$",
                "symbol_native" => "$",
                "iso_code" => "CAD",
                "country_code_alpha2" => "CA"
            ],
            [
                "name" => "Euro",
                "name_plural" => "euros",
                "symbol" => "€",
                "symbol_native" => "€",
                "iso_code" => "EUR",
                "country_code_alpha2" => "AD"
            ],
            [
                "name" => "United Arab Emirates Dirham",
                "name_plural" => "UAE dirhams",
                "symbol" => "AED",
                "symbol_native" => "د.إ.",
                "iso_code" => "AED",
                "country_code_alpha2" => "AE"
            ],
            [
                "name" => "Afghan Afghani",
                "name_plural" => "Afghan Afghanis",
                "symbol" => "Af",
                "symbol_native" => "؋",
                "iso_code" => "AFN",
                "country_code_alpha2" => "AF"
            ],
            [
                "name" => "Albanian Lek",
                "name_plural" => "Albanian lekë",
                "symbol" => "ALL",
                "symbol_native" => "Lek",
                "iso_code" => "ALL",
                "country_code_alpha2" => "AL"
            ],
            [
                "name" => "Armenian Dram",
                "name_plural" => "Armenian drams",
                "symbol" => "AMD",
                "symbol_native" => "դր.",
                "iso_code" => "AMD",
                "country_code_alpha2" => "AM"
            ],
            [
                "name" => "Argentine Peso",
                "name_plural" => "Argentine pesos",
                "symbol" => "AR$",
                "symbol_native" => "$",
                "iso_code" => "ARS",
                "country_code_alpha2" => "AR"
            ],
            [
                "name" => "Australian Dollar",
                "name_plural" => "Australian dollars",
                "symbol" => "AU$",
                "symbol_native" => "$",
                "iso_code" => "AUD",
                "country_code_alpha2" => "AU"
            ],
            [
                "name" => "Azerbaijani Manat",
                "name_plural" => "Azerbaijani manats",
                "symbol" => "man.",
                "symbol_native" => "ман.",
                "iso_code" => "AZN",
                "country_code_alpha2" => "AZ"
            ],
            [
                "name" => "Bosnia-Herzegovina Convertible Mark",
                "name_plural" => "Bosnia-Herzegovina convertible marks",
                "symbol" => "KM",
                "symbol_native" => "KM",
                "iso_code" => "BAM",
                "country_code_alpha2" => "BA"
            ],
            [
                "name" => "Bangladeshi Taka",
                "name_plural" => "Bangladeshi takas",
                "symbol" => "Tk",
                "symbol_native" => "৳",
                "iso_code" => "BDT",
                "country_code_alpha2" => "BD"
            ],
            [
                "name" => "Bulgarian Lev",
                "name_plural" => "Bulgarian leva",
                "symbol" => "BGN",
                "symbol_native" => "лв.",
                "iso_code" => "BGN",
                "country_code_alpha2" => "BG"
            ],
            [
                "name" => "Bahraini Dinar",
                "name_plural" => "Bahraini dinars",
                "symbol" => "BD",
                "symbol_native" => "د.ب.",
                "iso_code" => "BHD",
                "country_code_alpha2" => "BH"
            ],
            [
                "name" => "Burundian Franc",
                "name_plural" => "Burundian francs",
                "symbol" => "FBu",
                "symbol_native" => "FBu",
                "iso_code" => "BIF",
                "country_code_alpha2" => "BI"
            ],
            [
                "name" => "Brunei Dollar",
                "name_plural" => "Brunei dollars",
                "symbol" => "BN$",
                "symbol_native" => "$",
                "iso_code" => "BND",
                "country_code_alpha2" => "BN"
            ],
            [
                "name" => "Bolivian Boliviano",
                "name_plural" => "Bolivian bolivianos",
                "symbol" => "Bs",
                "symbol_native" => "Bs",
                "iso_code" => "BOB",
                "country_code_alpha2" => "BO"
            ],
            [
                "name" => "Brazilian Real",
                "name_plural" => "Brazilian reals",
                "symbol" => "R$",
                "symbol_native" => "R$",
                "iso_code" => "BRL",
                "country_code_alpha2" => "BR"
            ],
            [
                "name" => "Botswanan Pula",
                "name_plural" => "Botswanan pulas",
                "symbol" => "BWP",
                "symbol_native" => "P",
                "iso_code" => "BWP",
                "country_code_alpha2" => "BW"
            ],
            [
                "name" => "Belarusian Ruble",
                "name_plural" => "Belarusian rubles",
                "symbol" => "Br",
                "symbol_native" => "руб.",
                "iso_code" => "BYN",
                "country_code_alpha2" => "BY"
            ],
            [
                "name" => "Belize Dollar",
                "name_plural" => "Belize dollars",
                "symbol" => "BZ$",
                "symbol_native" => "$",
                "iso_code" => "BZD",
                "country_code_alpha2" => "BZ"
            ],
            [
                "name" => "Congolese Franc",
                "name_plural" => "Congolese francs",
                "symbol" => "CDF",
                "symbol_native" => "FrCD",
                "iso_code" => "CDF",
                "country_code_alpha2" => "CD"
            ],
            [
                "name" => "Swiss Franc",
                "name_plural" => "Swiss francs",
                "symbol" => "CHF",
                "symbol_native" => "CHF",
                "iso_code" => "CHF",
                "country_code_alpha2" => "CH"
            ],
            [
                "name" => "Chilean Peso",
                "name_plural" => "Chilean pesos",
                "symbol" => "CL$",
                "symbol_native" => "$",
                "iso_code" => "CLP",
                "country_code_alpha2" => "CL"
            ],
            [
                "name" => "Chinese Yuan",
                "name_plural" => "Chinese yuan",
                "symbol" => "CN¥",
                "symbol_native" => "CN¥",
                "iso_code" => "CNY",
                "country_code_alpha2" => "CN"
            ],
            [
                "name" => "Colombian Peso",
                "name_plural" => "Colombian pesos",
                "symbol" => "CO$",
                "symbol_native" => "$",
                "iso_code" => "COP",
                "country_code_alpha2" => "CO"
            ],
            [
                "name" => "Costa Rican Colón",
                "name_plural" => "Costa Rican colóns",
                "symbol" => "₡",
                "symbol_native" => "₡",
                "iso_code" => "CRC",
                "country_code_alpha2" => "CR"
            ],
            [
                "name" => "Cape Verdean Escudo",
                "name_plural" => "Cape Verdean escudos",
                "symbol" => "CV$",
                "symbol_native" => "CV$",
                "iso_code" => "CVE",
                "country_code_alpha2" => "CV"
            ],
            [
                "name" => "Czech Republic Koruna",
                "name_plural" => "Czech Republic korunas",
                "symbol" => "Kč",
                "symbol_native" => "Kč",
                "iso_code" => "CZK",
                "country_code_alpha2" => "CZ"
            ],
            [
                "name" => "Djiboutian Franc",
                "name_plural" => "Djiboutian francs",
                "symbol" => "Fdj",
                "symbol_native" => "Fdj",
                "iso_code" => "DJF",
                "country_code_alpha2" => "DJ"
            ],
            [
                "name" => "Danish Krone",
                "name_plural" => "Danish kroner",
                "symbol" => "Dkr",
                "symbol_native" => "kr",
                "iso_code" => "DKK",
                "country_code_alpha2" => "DK"
            ],
            [
                "name" => "Dominican Peso",
                "name_plural" => "Dominican pesos",
                "symbol" => "RD$",
                "symbol_native" => "RD$",
                "iso_code" => "DOP",
                "country_code_alpha2" => "DO"
            ],
            [
                "name" => "Algerian Dinar",
                "name_plural" => "Algerian dinars",
                "symbol" => "DA",
                "symbol_native" => "د.ج.",
                "iso_code" => "DZD",
                "country_code_alpha2" => "DZ"
            ],
            [
                "name" => "Estonian Kroon",
                "name_plural" => "Estonian kroons",
                "symbol" => "Ekr",
                "symbol_native" => "kr",
                "iso_code" => "EEK",
                "country_code_alpha2" => "EE"
            ],
            [
                "name" => "Egyptian Pound",
                "name_plural" => "Egyptian pounds",
                "symbol" => "EGP",
                "symbol_native" => "ج.م.",
                "iso_code" => "EGP",
                "country_code_alpha2" => "EG"
            ],
            [
                "name" => "Eritrean Nakfa",
                "name_plural" => "Eritrean nakfas",
                "symbol" => "Nfk",
                "symbol_native" => "Nfk",
                "iso_code" => "ERN",
                "country_code_alpha2" => "ER"
            ],
            [
                "name" => "Ethiopian Birr",
                "name_plural" => "Ethiopian birrs",
                "symbol" => "Br",
                "symbol_native" => "Br",
                "iso_code" => "ETB",
                "country_code_alpha2" => "ET"
            ],
            [
                "name" => "British Pound Sterling",
                "name_plural" => "British pounds sterling",
                "symbol" => "£",
                "symbol_native" => "£",
                "iso_code" => "GBP",
                "country_code_alpha2" => "GB"
            ],
            [
                "name" => "Georgian Lari",
                "name_plural" => "Georgian laris",
                "symbol" => "GEL",
                "symbol_native" => "GEL",
                "iso_code" => "GEL",
                "country_code_alpha2" => "GE"
            ],
            [
                "name" => "Ghanaian Cedi",
                "name_plural" => "Ghanaian cedis",
                "symbol" => "GH₵",
                "symbol_native" => "GH₵",
                "iso_code" => "GHS",
                "country_code_alpha2" => "GH"
            ],
            [
                "name" => "Guinean Franc",
                "name_plural" => "Guinean francs",
                "symbol" => "FG",
                "symbol_native" => "FG",
                "iso_code" => "GNF",
                "country_code_alpha2" => "GN"
            ],
            [
                "name" => "Guatemalan Quetzal",
                "name_plural" => "Guatemalan quetzals",
                "symbol" => "GTQ",
                "symbol_native" => "Q",
                "iso_code" => "GTQ",
                "country_code_alpha2" => "GT"
            ],
            [
                "name" => "Hong Kong Dollar",
                "name_plural" => "Hong Kong dollars",
                "symbol" => "HK$",
                "symbol_native" => "$",
                "iso_code" => "HKD",
                "country_code_alpha2" => "HK"
            ],
            [
                "name" => "Honduran Lempira",
                "name_plural" => "Honduran lempiras",
                "symbol" => "HNL",
                "symbol_native" => "L",
                "iso_code" => "HNL",
                "country_code_alpha2" => "HN"
            ],
            [
                "name" => "Croatian Kuna",
                "name_plural" => "Croatian kunas",
                "symbol" => "kn",
                "symbol_native" => "kn",
                "iso_code" => "HRK",
                "country_code_alpha2" => "HR"
            ],
            [
                "name" => "Hungarian Forint",
                "name_plural" => "Hungarian forints",
                "symbol" => "Ft",
                "symbol_native" => "Ft",
                "iso_code" => "HUF",
                "country_code_alpha2" => "HU"
            ],
            [
                "name" => "Indonesian Rupiah",
                "name_plural" => "Indonesian rupiahs",
                "symbol" => "Rp",
                "symbol_native" => "Rp",
                "iso_code" => "IDR",
                "country_code_alpha2" => "ID"
            ],
            [
                "name" => "Palestinian New Sheqel",
                "name_plural" => "Palestinian new sheqels",
                "symbol" => "₪",
                "symbol_native" => "₪",
                "iso_code" => "PAL",
                "country_code_alpha2" => "PS"
            ],
            [
                "name" => "Indian Rupee",
                "name_plural" => "Indian rupees",
                "symbol" => "Rs",
                "symbol_native" => "টকা",
                "iso_code" => "INR",
                "country_code_alpha2" => "IN"
            ],
            [
                "name" => "Iraqi Dinar",
                "name_plural" => "Iraqi dinars",
                "symbol" => "IQD",
                "symbol_native" => "د.ع.",
                "iso_code" => "IQD",
                "country_code_alpha2" => "IQ"
            ],
            [
                "name" => "Iranian Rial",
                "name_plural" => "Iranian rials",
                "symbol" => "IRR",
                "symbol_native" => "﷼",
                "iso_code" => "IRR",
                "country_code_alpha2" => "IR"
            ],
            [
                "name" => "Icelandic Króna",
                "name_plural" => "Icelandic krónur",
                "symbol" => "Ikr",
                "symbol_native" => "kr",
                "iso_code" => "ISK",
                "country_code_alpha2" => "IS"
            ],
            [
                "name" => "Jamaican Dollar",
                "name_plural" => "Jamaican dollars",
                "symbol" => "J$",
                "symbol_native" => "$",
                "iso_code" => "JMD",
                "country_code_alpha2" => "JM"
            ],
            [
                "name" => "Jordanian Dinar",
                "name_plural" => "Jordanian dinars",
                "symbol" => "JD",
                "symbol_native" => "د.أ.",
                "iso_code" => "JOD",
                "country_code_alpha2" => "JO"
            ],
            [
                "name" => "Japanese Yen",
                "name_plural" => "Japanese yen",
                "symbol" => "¥",
                "symbol_native" => "￥",
                "iso_code" => "JPY",
                "country_code_alpha2" => "JP"
            ],
            [
                "name" => "Kenyan Shilling",
                "name_plural" => "Kenyan shillings",
                "symbol" => "Ksh",
                "symbol_native" => "Ksh",
                "iso_code" => "KES",
                "country_code_alpha2" => "KE"
            ],
            [
                "name" => "Cambodian Riel",
                "name_plural" => "Cambodian riels",
                "symbol" => "KHR",
                "symbol_native" => "៛",
                "iso_code" => "KHR",
                "country_code_alpha2" => "KH"
            ],
            [
                "name" => "Comorian Franc",
                "name_plural" => "Comorian francs",
                "symbol" => "CF",
                "symbol_native" => "FC",
                "iso_code" => "KMF",
                "country_code_alpha2" => "KM"
            ],
            [
                "name" => "South Korean Won",
                "name_plural" => "South Korean won",
                "symbol" => "₩",
                "symbol_native" => "₩",
                "iso_code" => "KRW",
                "country_code_alpha2" => "KR"
            ],
            [
                "name" => "Kuwaiti Dinar",
                "name_plural" => "Kuwaiti dinars",
                "symbol" => "KD",
                "symbol_native" => "د.ك.",
                "iso_code" => "KWD",
                "country_code_alpha2" => "KW"
            ],
            [
                "name" => "Kazakhstani Tenge",
                "name_plural" => "Kazakhstani tenges",
                "symbol" => "KZT",
                "symbol_native" => "тңг.",
                "iso_code" => "KZT",
                "country_code_alpha2" => "KZ"
            ],
            [
                "name" => "Lebanese Pound",
                "name_plural" => "Lebanese pounds",
                "symbol" => "L.L.",
                "symbol_native" => "ل.ل.",
                "iso_code" => "LBP",
                "country_code_alpha2" => "LB"
            ],
            [
                "name" => "Sri Lankan Rupee",
                "name_plural" => "Sri Lankan rupees",
                "symbol" => "SLRs",
                "symbol_native" => "SL Re",
                "iso_code" => "LKR",
                "country_code_alpha2" => "LK"
            ],
            [
                "name" => "Lithuanian Litas",
                "name_plural" => "Lithuanian litai",
                "symbol" => "Lt",
                "symbol_native" => "Lt",
                "iso_code" => "LTL",
                "country_code_alpha2" => "LT"
            ],
            [
                "name" => "Latvian Lats",
                "name_plural" => "Latvian lati",
                "symbol" => "Ls",
                "symbol_native" => "Ls",
                "iso_code" => "LVL",
                "country_code_alpha2" => "LV"
            ],
            [
                "name" => "Libyan Dinar",
                "name_plural" => "Libyan dinars",
                "symbol" => "LD",
                "symbol_native" => "د.ل.",
                "iso_code" => "LYD",
                "country_code_alpha2" => "LY"
            ],
            [
                "name" => "Moroccan Dirham",
                "name_plural" => "Moroccan dirhams",
                "symbol" => "MAD",
                "symbol_native" => "د.م.",
                "iso_code" => "MAD",
                "country_code_alpha2" => "EH"
            ],
            [
                "name" => "Moldovan Leu",
                "name_plural" => "Moldovan lei",
                "symbol" => "MDL",
                "symbol_native" => "MDL",
                "iso_code" => "MDL",
                "country_code_alpha2" => "MD"
            ],
            [
                "name" => "Malagasy Ariary",
                "name_plural" => "Malagasy Ariaries",
                "symbol" => "MGA",
                "symbol_native" => "MGA",
                "iso_code" => "MGA",
                "country_code_alpha2" => "MG"
            ],
            [
                "name" => "Macedonian Denar",
                "name_plural" => "Macedonian denari",
                "symbol" => "MKD",
                "symbol_native" => "MKD",
                "iso_code" => "MKD",
                "country_code_alpha2" => "MK"
            ],
            [
                "name" => "Myanma Kyat",
                "name_plural" => "Myanma kyats",
                "symbol" => "MMK",
                "symbol_native" => "K",
                "iso_code" => "MMK",
                "country_code_alpha2" => "MM"
            ],
            [
                "name" => "Macanese Pataca",
                "name_plural" => "Macanese patacas",
                "symbol" => "MOP$",
                "symbol_native" => "MOP$",
                "iso_code" => "MOP",
                "country_code_alpha2" => "MO"
            ],
            [
                "name" => "Mauritian Rupee",
                "name_plural" => "Mauritian rupees",
                "symbol" => "MURs",
                "symbol_native" => "MURs",
                "iso_code" => "MUR",
                "country_code_alpha2" => "MU"
            ],
            [
                "name" => "Mexican Peso",
                "name_plural" => "Mexican pesos",
                "symbol" => "MX$",
                "symbol_native" => "$",
                "iso_code" => "MXN",
                "country_code_alpha2" => "MX"
            ],
            [
                "name" => "Malaysian Ringgit",
                "name_plural" => "Malaysian ringgits",
                "symbol" => "RM",
                "symbol_native" => "RM",
                "iso_code" => "MYR",
                "country_code_alpha2" => "MY"
            ],
            [
                "name" => "Mozambican Metical",
                "name_plural" => "Mozambican meticals",
                "symbol" => "MTn",
                "symbol_native" => "MTn",
                "iso_code" => "MZN",
                "country_code_alpha2" => "MZ"
            ],
            [
                "name" => "Namibian Dollar",
                "name_plural" => "Namibian dollars",
                "symbol" => "N$",
                "symbol_native" => "N$",
                "iso_code" => "NAD",
                "country_code_alpha2" => "NA"
            ],
            [
                "name" => "Nigerian Naira",
                "name_plural" => "Nigerian nairas",
                "symbol" => "₦",
                "symbol_native" => "₦",
                "iso_code" => "NGN",
                "country_code_alpha2" => "NG"
            ],
            [
                "name" => "Nicaraguan Córdoba",
                "name_plural" => "Nicaraguan córdobas",
                "symbol" => "C$",
                "symbol_native" => "C$",
                "iso_code" => "NIO",
                "country_code_alpha2" => "NI"
            ],
            [
                "name" => "Norwegian Krone",
                "name_plural" => "Norwegian kroner",
                "symbol" => "Nkr",
                "symbol_native" => "kr",
                "iso_code" => "NOK",
                "country_code_alpha2" => "BV"
            ],
            [
                "name" => "Nepalese Rupee",
                "name_plural" => "Nepalese rupees",
                "symbol" => "NPRs",
                "symbol_native" => "नेरू",
                "iso_code" => "NPR",
                "country_code_alpha2" => "NP"
            ],
            [
                "name" => "New Zealand Dollar",
                "name_plural" => "New Zealand dollars",
                "symbol" => "NZ$",
                "symbol_native" => "$",
                "iso_code" => "NZD",
                "country_code_alpha2" => "CK"
            ],
            [
                "name" => "Omani Rial",
                "name_plural" => "Omani rials",
                "symbol" => "OMR",
                "symbol_native" => "ر.ع.",
                "iso_code" => "OMR",
                "country_code_alpha2" => "OM"
            ],
            [
                "name" => "Panamanian Balboa",
                "name_plural" => "Panamanian balboas",
                "symbol" => "B/.",
                "symbol_native" => "B/.",
                "iso_code" => "PAB",
                "country_code_alpha2" => "PA"
            ],
            [
                "name" => "Peruvian Nuevo Sol",
                "name_plural" => "Peruvian nuevos soles",
                "symbol" => "S/.",
                "symbol_native" => "S/.",
                "iso_code" => "PEN",
                "country_code_alpha2" => "PE"
            ],
            [
                "name" => "Philippine Peso",
                "name_plural" => "Philippine pesos",
                "symbol" => "₱",
                "symbol_native" => "₱",
                "iso_code" => "PHP",
                "country_code_alpha2" => "PH"
            ],
            [
                "name" => "Pakistani Rupee",
                "name_plural" => "Pakistani rupees",
                "symbol" => "PKRs",
                "symbol_native" => "₨",
                "iso_code" => "PKR",
                "country_code_alpha2" => "PK"
            ],
            [
                "name" => "Polish Zloty",
                "name_plural" => "Polish zlotys",
                "symbol" => "zł",
                "symbol_native" => "zł",
                "iso_code" => "PLN",
                "country_code_alpha2" => "PL"
            ],
            [
                "name" => "Paraguayan Guarani",
                "name_plural" => "Paraguayan guaranis",
                "symbol" => "₲",
                "symbol_native" => "₲",
                "iso_code" => "PYG",
                "country_code_alpha2" => "PY"
            ],
            [
                "name" => "Qatari Rial",
                "name_plural" => "Qatari rials",
                "symbol" => "QR",
                "symbol_native" => "ر.ق.",
                "iso_code" => "QAR",
                "country_code_alpha2" => "QA"
            ],
            [
                "name" => "Romanian Leu",
                "name_plural" => "Romanian lei",
                "symbol" => "RON",
                "symbol_native" => "RON",
                "iso_code" => "RON",
                "country_code_alpha2" => "RO"
            ],
            [
                "name" => "Serbian Dinar",
                "name_plural" => "Serbian dinars",
                "symbol" => "din.",
                "symbol_native" => "дин.",
                "iso_code" => "RSD",
                "country_code_alpha2" => "RS"
            ],
            [
                "name" => "Russian Ruble",
                "name_plural" => "Russian rubles",
                "symbol" => "RUB",
                "symbol_native" => "₽.",
                "iso_code" => "RUB",
                "country_code_alpha2" => "RU"
            ],
            [
                "name" => "Rwandan Franc",
                "name_plural" => "Rwandan francs",
                "symbol" => "RWF",
                "symbol_native" => "FR",
                "iso_code" => "RWF",
                "country_code_alpha2" => "RW"
            ],
            [
                "name" => "Saudi Riyal",
                "name_plural" => "Saudi riyals",
                "symbol" => "SR",
                "symbol_native" => "ر.س.",
                "iso_code" => "SAR",
                "country_code_alpha2" => "SA"
            ],
            [
                "name" => "Sudanese Pound",
                "name_plural" => "Sudanese pounds",
                "symbol" => "SDG",
                "symbol_native" => "ج.س.",
                "iso_code" => "SDG",
                "country_code_alpha2" => "SD"
            ],
            [
                "name" => "Swedish Krona",
                "name_plural" => "Swedish kronor",
                "symbol" => "Skr",
                "symbol_native" => "kr",
                "iso_code" => "SEK",
                "country_code_alpha2" => "SE"
            ],
            [
                "name" => "Singapore Dollar",
                "name_plural" => "Singapore dollars",
                "symbol" => "S$",
                "symbol_native" => "$",
                "iso_code" => "SGD",
                "country_code_alpha2" => "SG"
            ],
            [
                "name" => "Somali Shilling",
                "name_plural" => "Somali shillings",
                "symbol" => "Ssh",
                "symbol_native" => "Ssh",
                "iso_code" => "SOS",
                "country_code_alpha2" => "SO"
            ],
            [
                "name" => "Syrian Pound",
                "name_plural" => "Syrian pounds",
                "symbol" => "SY£",
                "symbol_native" => "ل.س.",
                "iso_code" => "SYP",
                "country_code_alpha2" => "SY"
            ],
            [
                "name" => "Thai Baht",
                "name_plural" => "Thai baht",
                "symbol" => "฿",
                "symbol_native" => "฿",
                "iso_code" => "THB",
                "country_code_alpha2" => "TH"
            ],
            [
                "name" => "Tunisian Dinar",
                "name_plural" => "Tunisian dinars",
                "symbol" => "DT",
                "symbol_native" => "د.ت.",
                "iso_code" => "TND",
                "country_code_alpha2" => "TN"
            ],
            [
                "name" => "Tongan Paʻanga",
                "name_plural" => "Tongan paʻanga",
                "symbol" => "T$",
                "symbol_native" => "T$",
                "iso_code" => "TOP",
                "country_code_alpha2" => "TO"
            ],
            [
                "name" => "Turkish Lira",
                "name_plural" => "Turkish Lira",
                "symbol" => "TL",
                "symbol_native" => "TL",
                "iso_code" => "TRY",
                "country_code_alpha2" => "TR"
            ],
            [
                "name" => "Trinidad and Tobago Dollar",
                "name_plural" => "Trinidad and Tobago dollars",
                "symbol" => "TT$",
                "symbol_native" => "$",
                "iso_code" => "TTD",
                "country_code_alpha2" => "TT"
            ],
            [
                "name" => "New Taiwan Dollar",
                "name_plural" => "New Taiwan dollars",
                "symbol" => "NT$",
                "symbol_native" => "NT$",
                "iso_code" => "TWD",
                "country_code_alpha2" => "TW"
            ],
            [
                "name" => "Tanzanian Shilling",
                "name_plural" => "Tanzanian shillings",
                "symbol" => "TSh",
                "symbol_native" => "TSh",
                "iso_code" => "TZS",
                "country_code_alpha2" => "TZ"
            ],
            [
                "name" => "Ukrainian Hryvnia",
                "name_plural" => "Ukrainian hryvnias",
                "symbol" => "₴",
                "symbol_native" => "₴",
                "iso_code" => "UAH",
                "country_code_alpha2" => "UA"
            ],
            [
                "name" => "Ugandan Shilling",
                "name_plural" => "Ugandan shillings",
                "symbol" => "USh",
                "symbol_native" => "USh",
                "iso_code" => "UGX",
                "country_code_alpha2" => "UG"
            ],
            [
                "name" => "Uruguayan Peso",
                "name_plural" => "Uruguayan pesos",
                "symbol" => '$U',
                "symbol_native" => "$",
                "iso_code" => "UYU",
                "country_code_alpha2" => "UY"
            ],
            [
                "name" => "Uzbekistan Som",
                "name_plural" => "Uzbekistan som",
                "symbol" => "UZS",
                "symbol_native" => "UZS",
                "iso_code" => "UZS",
                "country_code_alpha2" => "UZ"
            ],
            [
                "name" => "Venezuelan Bolívar",
                "name_plural" => "Venezuelan bolívars",
                "symbol" => "Bs.F.",
                "symbol_native" => "Bs.F.",
                "iso_code" => "VEF",
                "country_code_alpha2" => "VE"
            ],
            [
                "name" => "Vietnamese Dong",
                "name_plural" => "Vietnamese dong",
                "symbol" => "₫",
                "symbol_native" => "₫",
                "iso_code" => "VND",
                "country_code_alpha2" => "VN"
            ],
            [
                "name" => "CFA Franc BEAC",
                "name_plural" => "CFA francs BEAC",
                "symbol" => "FCFA",
                "symbol_native" => "FCFA",
                "iso_code" => "XAF",
                "country_code_alpha2" => "CF"
            ],
            [
                "name" => "CFA Franc BCEAO",
                "name_plural" => "CFA francs BCEAO",
                "symbol" => "CFA",
                "symbol_native" => "CFA",
                "iso_code" => "XOF",
                "country_code_alpha2" => "BF"
            ],
            [
                "name" => "Yemeni Rial",
                "name_plural" => "Yemeni rials",
                "symbol" => "YR",
                "symbol_native" => "ر.ي.",
                "iso_code" => "YER",
                "country_code_alpha2" => "YE"
            ],
            [
                "name" => "South African Rand",
                "name_plural" => "South African rand",
                "symbol" => "R",
                "symbol_native" => "R",
                "iso_code" => "ZAR",
                "country_code_alpha2" => "ZA"
            ],
            [
                "name" => "Zambian Kwacha",
                "name_plural" => "Zambian kwachas",
                "symbol" => "ZK",
                "symbol_native" => "ZK",
                "iso_code" => "ZMK",
                "country_code_alpha2" => "ZM"
            ],
            [
                "name" => "Zimbabwean Dollar",
                "name_plural" => "Zimbabwean Dollar",
                "symbol" => "ZWL$",
                "symbol_native" => "ZWL$",
                "iso_code" => "ZWL",
                "country_code_alpha2" => "ZW"
            ]
        ];

        return $list;
    }


    public function updateOrCreateSettings($tenant_id)
    {
        $service = new SettingService($tenant_id);

//        DB::table('settings')->where('tenant_id', $tenant_id)->delete();

        $service->createOrUpdate('main_currency', ['en' => 'Currency', 'ar' => 'العملة'], "SAR", 'options', false, $service->rulesForString(), 'system', [], false, 'System', null, null, 1, 1, true, "currency_options@$tenant_id"); //cause every tenant has his own options
        $service->createOrUpdate('main_currency_decimals', ['en' => 'Currency decimals', 'ar' => 'الفواصل العشرية في العملة'], "2", 'options', false, $service->rulesForNumber(true, 0, 4), 'system', [0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4], false, 'System', null, null, 1, 2, true); //cause every tenant has his own options

        $service->createOrUpdate('invoice_prefix', ['en' => 'Invoice number prefix', 'ar' => 'نمط ترقيم الفاتورة'], "", 'text', false, $service->rulesForString(false, 5), 'system', [], false, 'System', "INV-", "E.g. INV-000001", 1, 2, true);
        $service->createOrUpdate('invoice_number_digits', ['en' => 'Invoice number digits', 'ar' => 'عدد خانات رقم الفاتورة'], "6", 'text', false, $service->rulesForNumber(true, 1, 20), 'system', [], false, 'System', null, null, 1, 2, true); //cause every tenant has his own options
        $service->createOrUpdate('invoice_starting_number', ['en' => 'Invoice starting number', 'ar' => 'بادئة رقم الفاتورة'], "1", 'text', false, $service->rulesForNumber(true, 1, 100000), 'system', [], false, 'System', null, null, 1, 2, true); //cause every tenant has his own options

//        $service->createOrUpdate('vat', ['en' => 'Value added tax (Vat)', 'ar' => 'ضريبة القيمة المضافة'], "15", 'text', false, $service->rulesForNumber(true, 1, 100), 'system', [], false, 'System', null, null, 1, 2, true); //cause every tenant has his own options

//        $service->createOrUpdate('store.hide_out_of_stock_products', ['en' => 'Hide out of stock products', 'ar' => 'إخفاء المنتجات غير المتوفرة من المتجر'], "0", 'toggle', false, $service->rulesForBoolean(), 'system', [], false, 'System', null, null, 1, 2, true); //cause every tenant has his own options
//
//        $service->createOrUpdate('company.name', ['en' => 'Company name', 'ar' => 'إسم الشركة'], Tenant::find($tenant_id)?->name, 'text', false, $service->rulesForString(false), 'general', [], false, 'General', null, null, 1, 2);
//        $service->createOrUpdate('company.address', ['en' => 'Company address', 'ar' => 'عنوان الشركة'], null, 'text', false, $service->rulesForString(false), 'general', [], false, 'General', null, null, 2, 2);
//        $service->createOrUpdate('company.contact.phone', ['en' => 'Company phone', 'ar' => 'رقم هاتف الشركة'], null, 'text', false, $service->rulesForInternationalPhone(false), 'general', [], false, 'General', null, null, 3, 2);
//        $service->createOrUpdate('company.contact.mobile', ['en' => 'Company mobile', 'ar' => 'رقم موبايل الشركة'], null, 'text', false, $service->rulesForInternationalPhone(false), 'general', [], false, 'General', null, null, 4, 2);
//        $service->createOrUpdate('company.contact.email', ['en' => 'Company email', 'ar' => 'إيميل الشركة'], null, 'text', false, $service->rulesForEmail(false), 'general', [], false, 'General', null, null, 5, 2);

        CacheService::instance()->tenant($tenant_id)->forget('settings');

    }

    public function createCategories($tenant_id, $records = 8): void
    {
        $tenant = Tenant::find($tenant_id);

        for ($i = 0; $i < $records; $i++) {
            Category::create(
                [
                    'tenant_id' => $tenant_id,
                    'name' => [
                        'en' => $tenant->name . " - category " . $i + 1,
                        'ar' => $tenant->name . "- تصنيف" . $i + 1,
                    ],
                    'slug' => custom_slug($tenant->name . " category " . $i + 1),
                ]
            );
        }
    }

    public function createProducts($tenant_id, $basic_products = 5, $units_products = 5): void
    {
        $tenant = Tenant::find($tenant_id);

        $flag = 1;

        for ($i = 0; $i < $basic_products; $i++) {
            $product = Product::create(
                [
                    'tenant_id' => $tenant_id,
                    'name' => $tenant->name . " - product " . $flag,
                    'type' => 'basic',
                    'sku' => random_int(111111111, 999999999),
                    'barcode' => Str::random(8),
                    'warehouse_id' => Warehouse::where('tenant_id', $tenant_id)->get()->random()->id,
                    'category_id' => Category::where('tenant_id', $tenant_id)->get()->random()->id,
                    'main_unit_id' => null,
                    'security_stock' => 10,
                ]
            );

            $flag++;
        }

        for ($i = 0; $i < $units_products; $i++) {
            $product = Product::create(
                [
                    'tenant_id' => $tenant_id,
                    'name' => $tenant->name . " - product " . $flag,
                    'type' => 'units',
                    'sku' => random_int(111111111, 999999999),
                    'barcode' => Str::random(8),
                    'warehouse_id' => Warehouse::where('tenant_id', $tenant_id)->get()->random()->id,
                    'category_id' => Category::where('tenant_id', $tenant_id)->get()->random()->id,
                    'main_unit_id' => Unit::where('tenant_id', $tenant_id)->get()->random()->id,
                    'security_stock' => 10,
                ]
            );

            //create main unit

            ProductUnit::create([
                'tenant_id' => $tenant_id,
                'product_id' => $product->id,
                'unit_id' => $product->main_unit_id,
                'main' => 1,
                'sku' => random_int(111111111, 999999999),
                'barcode' => Str::random(8),
                'unit_count_from_main_unit' => 0,
            ]);
            $flag++;
        }
    }

    public function createWarehouses($tenant_id): void
    {
        Warehouse::create(
            [
                'tenant_id' => $tenant_id,
                'name' => "المخزن الرئيسي",
            ]
        );
    }

    public function createSuppliers($tenant_id, $records = 3): void
    {
        $tenant = Tenant::find($tenant_id);

        for ($i = 0; $i < $records; $i++) {
            $supplier = Supplier::create(
                [
                    'tenant_id' => $tenant_id,
                    'name' => $tenant->name . " - supplier " . $i + 1,
                    'phone' => Str::random(12),
                ]
            );
        }
    }


    public function createTaxProfiles($tenant_id): void
    {
        $purchasesTaxProfile = TaxProfile::create([
            'tenant_id' => $tenant_id,
            'name' => 'ضريبة المشتريات القياسية',
        ]);

        $salesTaxProfile = TaxProfile::create([
            'tenant_id' => $tenant_id,
            'name' => 'ضريبة المبيعات القياسية',
        ]);

        $productTaxProfile = TaxProfile::create([
            'tenant_id' => $tenant_id,
            'name' => 'ضريبة المنتج القياسية',
        ]);

        $purchasesTaxProfile->taxes()->insert([
            [
                'tenant_id' => $tenant_id,
                'tax_profile_id' => $purchasesTaxProfile->id,
                'description' => 'الضريبة 1',
                'percent' => 5,
                'created_at' => now(),
            ],
            [
                'tenant_id' => $tenant_id,
                'tax_profile_id' => $purchasesTaxProfile->id,
                'description' => 'الضريبة 2',
                'percent' => 7,
                'created_at' => now(),
            ],
        ]);

        $salesTaxProfile->taxes()->insert([
            [
                'tenant_id' => $tenant_id,
                'tax_profile_id' => $salesTaxProfile->id,
                'description' => 'الضريبة 1',
                'percent' => 2,
                'created_at' => now(),
            ],
            [
                'tenant_id' => $tenant_id,
                'tax_profile_id' => $salesTaxProfile->id,
                'description' => 'الضريبة 2',
                'percent' => 2.5,
                'created_at' => now(),
            ],
        ]);

        $productTaxProfile->taxes()->insert([
            [
                'tenant_id' => $tenant_id,
                'tax_profile_id' => $productTaxProfile->id,
                'description' => 'ضريبة القيمة المضافة',
                'percent' => 15,
                'created_at' => now(),
            ],
        ]);
    }

    public function createAdditionalCostTypes($tenant_id): void
    {
        AdditionalCostType::create([
            'tenant_id' => $tenant_id,
            'name' => 'توصيل/شحن',
        ]);
    }

    public function createVariantLibraries($tenant_id): void
    {
        $color = VariantLibrary::create([
            'tenant_id' => $tenant_id,
            'name_en' => 'Colors',
            'name_ar' => 'الألوان',
        ]);

        $size = VariantLibrary::create([
            'tenant_id' => $tenant_id,
            'name_en' => 'Sizes',
            'name_ar' => 'المقاسات',
        ]);

        $material = VariantLibrary::create([
            'tenant_id' => $tenant_id,
            'name_en' => 'Materials',
            'name_ar' => 'خامات الملابس',
        ]);

        $unit = VariantLibrary::create([
            'tenant_id' => $tenant_id,
            'name_en' => 'Units',
            'name_ar' => 'الوحدات',
        ]);

        $color->options()->insert([
            [
                'name_ar' => 'أزرق',
                'name_en' => 'Blue',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $color->id,
                'sort' => 1,
            ],
            [
                'name_ar' => 'أحمر',
                'name_en' => 'Red',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $color->id,
                'sort' => 2,
            ],
            [
                'name_ar' => 'أخضر',
                'name_en' => 'Green',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $color->id,
                'sort' => 3,
            ]
        ]);

        $size->options()->insert([
            [
                'name_ar' => 'S',
                'name_en' => 'S',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 1,
            ],
            [
                'name_ar' => 'M',
                'name_en' => 'M',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 2,
            ],
            [
                'name_ar' => 'L',
                'name_en' => 'L',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 3,
            ],
            [
                'name_ar' => 'XL',
                'name_en' => 'XL',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 4,
            ],
            [
                'name_ar' => 'XXL',
                'name_en' => 'XXL',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 5,
            ],
            [
                'name_ar' => '3XL',
                'name_en' => '3XL',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 6,
            ],
            [
                'name_ar' => '4XL',
                'name_en' => '4XL',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 7,
            ],
            [
                'name_ar' => '5XL',
                'name_en' => '5XL',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 8,
            ],
            [
                'name_ar' => '6XL',
                'name_en' => '6XL',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $size->id,
                'sort' => 9,
            ],
        ]);

        $material->options()->insert([
            [
                'name_ar' => 'قطن',
                'name_en' => 'Cotten',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $material->id,
                'sort' => 1,
            ],
            [
                'name_ar' => 'كتان',
                'name_en' => 'Linen',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $material->id,
                'sort' => 2,
            ],
            [
                'name_ar' => 'صوف',
                'name_en' => 'Wool',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $material->id,
                'sort' => 3,
            ]
        ]);

        $unit->options()->insert([
            [
                'name_ar' => 'قطعة',
                'name_en' => 'Piece',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $unit->id,
                'sort' => 1,
            ],
            [
                'name_ar' => 'كرتونة',
                'name_en' => 'Carton',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $unit->id,
                'sort' => 2,
            ],
            [
                'name_ar' => 'كيلو جرام',
                'name_en' => 'Kg',
                'tenant_id' => $tenant_id,
                'variant_library_id' => $unit->id,
                'sort' => 3,
            ]
        ]);
    }

    public function getUsers($tenant_id): Collection
    {
        if (null == $tenant_id)
            throw new \Exception("Tenant id cannot be null");

        return User::where('tenant_id', $tenant_id)->get();
    }
}
