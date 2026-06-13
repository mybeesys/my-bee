<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use phpDocumentor\Reflection\Types\Self_;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (self::list() as $item) {
            Currency::updateOrCreate(['iso_code' => $item['iso_code']], $item);
        }
    }

    public function list(): array
    {
        $list = [
            [
                "id" => 1,
                "name" => "US Dollar",
                "name_plural" => "US dollars",
                "symbol" => "$",
                "symbol_native" => "$",
                "iso_code" => "USD",
                "country_code_alpha2" => "US"
            ],
            [
                "id" => 2,
                "name" => "Canadian Dollar",
                "name_plural" => "Canadian dollars",
                "symbol" => "CA$",
                "symbol_native" => "$",
                "iso_code" => "CAD",
                "country_code_alpha2" => "CA"
            ],
            [
                "id" => 3,
                "name" => "Euro",
                "name_plural" => "euros",
                "symbol" => "€",
                "symbol_native" => "€",
                "iso_code" => "EUR",
                "country_code_alpha2" => "AD"
            ],
            [
                "id" => 4,
                "name" => "United Arab Emirates Dirham",
                "name_plural" => "UAE dirhams",
                "symbol" => "AED",
                "symbol_native" => "د.إ.",
                "iso_code" => "AED",
                "country_code_alpha2" => "AE"
            ],
            [
                "id" => 5,
                "name" => "Afghan Afghani",
                "name_plural" => "Afghan Afghanis",
                "symbol" => "Af",
                "symbol_native" => "؋",
                "iso_code" => "AFN",
                "country_code_alpha2" => "AF"
            ],
            [
                "id" => 6,
                "name" => "Albanian Lek",
                "name_plural" => "Albanian lekë",
                "symbol" => "ALL",
                "symbol_native" => "Lek",
                "iso_code" => "ALL",
                "country_code_alpha2" => "AL"
            ],
            [
                "id" => 7,
                "name" => "Armenian Dram",
                "name_plural" => "Armenian drams",
                "symbol" => "AMD",
                "symbol_native" => "դր.",
                "iso_code" => "AMD",
                "country_code_alpha2" => "AM"
            ],
            [
                "id" => 8,
                "name" => "Argentine Peso",
                "name_plural" => "Argentine pesos",
                "symbol" => "AR$",
                "symbol_native" => "$",
                "iso_code" => "ARS",
                "country_code_alpha2" => "AR"
            ],
            [
                "id" => 9,
                "name" => "Australian Dollar",
                "name_plural" => "Australian dollars",
                "symbol" => "AU$",
                "symbol_native" => "$",
                "iso_code" => "AUD",
                "country_code_alpha2" => "AU"
            ],
            [
                "id" => 10,
                "name" => "Azerbaijani Manat",
                "name_plural" => "Azerbaijani manats",
                "symbol" => "man.",
                "symbol_native" => "ман.",
                "iso_code" => "AZN",
                "country_code_alpha2" => "AZ"
            ],
            [
                "id" => 11,
                "name" => "Bosnia-Herzegovina Convertible Mark",
                "name_plural" => "Bosnia-Herzegovina convertible marks",
                "symbol" => "KM",
                "symbol_native" => "KM",
                "iso_code" => "BAM",
                "country_code_alpha2" => "BA"
            ],
            [
                "id" => 12,
                "name" => "Bangladeshi Taka",
                "name_plural" => "Bangladeshi takas",
                "symbol" => "Tk",
                "symbol_native" => "৳",
                "iso_code" => "BDT",
                "country_code_alpha2" => "BD"
            ],
            [
                "id" => 13,
                "name" => "Bulgarian Lev",
                "name_plural" => "Bulgarian leva",
                "symbol" => "BGN",
                "symbol_native" => "лв.",
                "iso_code" => "BGN",
                "country_code_alpha2" => "BG"
            ],
            [
                "id" => 14,
                "name" => "Bahraini Dinar",
                "name_plural" => "Bahraini dinars",
                "symbol" => "BD",
                "symbol_native" => "د.ب.",
                "iso_code" => "BHD",
                "country_code_alpha2" => "BH"
            ],
            [
                "id" => 15,
                "name" => "Burundian Franc",
                "name_plural" => "Burundian francs",
                "symbol" => "FBu",
                "symbol_native" => "FBu",
                "iso_code" => "BIF",
                "country_code_alpha2" => "BI"
            ],
            [
                "id" => 16,
                "name" => "Brunei Dollar",
                "name_plural" => "Brunei dollars",
                "symbol" => "BN$",
                "symbol_native" => "$",
                "iso_code" => "BND",
                "country_code_alpha2" => "BN"
            ],
            [
                "id" => 17,
                "name" => "Bolivian Boliviano",
                "name_plural" => "Bolivian bolivianos",
                "symbol" => "Bs",
                "symbol_native" => "Bs",
                "iso_code" => "BOB",
                "country_code_alpha2" => "BO"
            ],
            [
                "id" => 18,
                "name" => "Brazilian Real",
                "name_plural" => "Brazilian reals",
                "symbol" => "R$",
                "symbol_native" => "R$",
                "iso_code" => "BRL",
                "country_code_alpha2" => "BR"
            ],
            [
                "id" => 19,
                "name" => "Botswanan Pula",
                "name_plural" => "Botswanan pulas",
                "symbol" => "BWP",
                "symbol_native" => "P",
                "iso_code" => "BWP",
                "country_code_alpha2" => "BW"
            ],
            [
                "id" => 20,
                "name" => "Belarusian Ruble",
                "name_plural" => "Belarusian rubles",
                "symbol" => "Br",
                "symbol_native" => "руб.",
                "iso_code" => "BYN",
                "country_code_alpha2" => "BY"
            ],
            [
                "id" => 21,
                "name" => "Belize Dollar",
                "name_plural" => "Belize dollars",
                "symbol" => "BZ$",
                "symbol_native" => "$",
                "iso_code" => "BZD",
                "country_code_alpha2" => "BZ"
            ],
            [
                "id" => 22,
                "name" => "Congolese Franc",
                "name_plural" => "Congolese francs",
                "symbol" => "CDF",
                "symbol_native" => "FrCD",
                "iso_code" => "CDF",
                "country_code_alpha2" => "CD"
            ],
            [
                "id" => 23,
                "name" => "Swiss Franc",
                "name_plural" => "Swiss francs",
                "symbol" => "CHF",
                "symbol_native" => "CHF",
                "iso_code" => "CHF",
                "country_code_alpha2" => "CH"
            ],
            [
                "id" => 24,
                "name" => "Chilean Peso",
                "name_plural" => "Chilean pesos",
                "symbol" => "CL$",
                "symbol_native" => "$",
                "iso_code" => "CLP",
                "country_code_alpha2" => "CL"
            ],
            [
                "id" => 25,
                "name" => "Chinese Yuan",
                "name_plural" => "Chinese yuan",
                "symbol" => "CN¥",
                "symbol_native" => "CN¥",
                "iso_code" => "CNY",
                "country_code_alpha2" => "CN"
            ],
            [
                "id" => 26,
                "name" => "Colombian Peso",
                "name_plural" => "Colombian pesos",
                "symbol" => "CO$",
                "symbol_native" => "$",
                "iso_code" => "COP",
                "country_code_alpha2" => "CO"
            ],
            [
                "id" => 27,
                "name" => "Costa Rican Colón",
                "name_plural" => "Costa Rican colóns",
                "symbol" => "₡",
                "symbol_native" => "₡",
                "iso_code" => "CRC",
                "country_code_alpha2" => "CR"
            ],
            [
                "id" => 28,
                "name" => "Cape Verdean Escudo",
                "name_plural" => "Cape Verdean escudos",
                "symbol" => "CV$",
                "symbol_native" => "CV$",
                "iso_code" => "CVE",
                "country_code_alpha2" => "CV"
            ],
            [
                "id" => 29,
                "name" => "Czech Republic Koruna",
                "name_plural" => "Czech Republic korunas",
                "symbol" => "Kč",
                "symbol_native" => "Kč",
                "iso_code" => "CZK",
                "country_code_alpha2" => "CZ"
            ],
            [
                "id" => 30,
                "name" => "Djiboutian Franc",
                "name_plural" => "Djiboutian francs",
                "symbol" => "Fdj",
                "symbol_native" => "Fdj",
                "iso_code" => "DJF",
                "country_code_alpha2" => "DJ"
            ],
            [
                "id" => 31,
                "name" => "Danish Krone",
                "name_plural" => "Danish kroner",
                "symbol" => "Dkr",
                "symbol_native" => "kr",
                "iso_code" => "DKK",
                "country_code_alpha2" => "DK"
            ],
            [
                "id" => 32,
                "name" => "Dominican Peso",
                "name_plural" => "Dominican pesos",
                "symbol" => "RD$",
                "symbol_native" => "RD$",
                "iso_code" => "DOP",
                "country_code_alpha2" => "DO"
            ],
            [
                "id" => 33,
                "name" => "Algerian Dinar",
                "name_plural" => "Algerian dinars",
                "symbol" => "DA",
                "symbol_native" => "د.ج.",
                "iso_code" => "DZD",
                "country_code_alpha2" => "DZ"
            ],
            [
                "id" => 34,
                "name" => "Estonian Kroon",
                "name_plural" => "Estonian kroons",
                "symbol" => "Ekr",
                "symbol_native" => "kr",
                "iso_code" => "EEK",
                "country_code_alpha2" => "EE"
            ],
            [
                "id" => 35,
                "name" => "Egyptian Pound",
                "name_plural" => "Egyptian pounds",
                "symbol" => "EGP",
                "symbol_native" => "ج.م.",
                "iso_code" => "EGP",
                "country_code_alpha2" => "EG"
            ],
            [
                "id" => 36,
                "name" => "Eritrean Nakfa",
                "name_plural" => "Eritrean nakfas",
                "symbol" => "Nfk",
                "symbol_native" => "Nfk",
                "iso_code" => "ERN",
                "country_code_alpha2" => "ER"
            ],
            [
                "id" => 37,
                "name" => "Ethiopian Birr",
                "name_plural" => "Ethiopian birrs",
                "symbol" => "Br",
                "symbol_native" => "Br",
                "iso_code" => "ETB",
                "country_code_alpha2" => "ET"
            ],
            [
                "id" => 38,
                "name" => "British Pound Sterling",
                "name_plural" => "British pounds sterling",
                "symbol" => "£",
                "symbol_native" => "£",
                "iso_code" => "GBP",
                "country_code_alpha2" => "GB"
            ],
            [
                "id" => 39,
                "name" => "Georgian Lari",
                "name_plural" => "Georgian laris",
                "symbol" => "GEL",
                "symbol_native" => "GEL",
                "iso_code" => "GEL",
                "country_code_alpha2" => "GE"
            ],
            [
                "id" => 40,
                "name" => "Ghanaian Cedi",
                "name_plural" => "Ghanaian cedis",
                "symbol" => "GH₵",
                "symbol_native" => "GH₵",
                "iso_code" => "GHS",
                "country_code_alpha2" => "GH"
            ],
            [
                "id" => 41,
                "name" => "Guinean Franc",
                "name_plural" => "Guinean francs",
                "symbol" => "FG",
                "symbol_native" => "FG",
                "iso_code" => "GNF",
                "country_code_alpha2" => "GN"
            ],
            [
                "id" => 42,
                "name" => "Guatemalan Quetzal",
                "name_plural" => "Guatemalan quetzals",
                "symbol" => "GTQ",
                "symbol_native" => "Q",
                "iso_code" => "GTQ",
                "country_code_alpha2" => "GT"
            ],
            [
                "id" => 43,
                "name" => "Hong Kong Dollar",
                "name_plural" => "Hong Kong dollars",
                "symbol" => "HK$",
                "symbol_native" => "$",
                "iso_code" => "HKD",
                "country_code_alpha2" => "HK"
            ],
            [
                "id" => 44,
                "name" => "Honduran Lempira",
                "name_plural" => "Honduran lempiras",
                "symbol" => "HNL",
                "symbol_native" => "L",
                "iso_code" => "HNL",
                "country_code_alpha2" => "HN"
            ],
            [
                "id" => 45,
                "name" => "Croatian Kuna",
                "name_plural" => "Croatian kunas",
                "symbol" => "kn",
                "symbol_native" => "kn",
                "iso_code" => "HRK",
                "country_code_alpha2" => "HR"
            ],
            [
                "id" => 46,
                "name" => "Hungarian Forint",
                "name_plural" => "Hungarian forints",
                "symbol" => "Ft",
                "symbol_native" => "Ft",
                "iso_code" => "HUF",
                "country_code_alpha2" => "HU"
            ],
            [
                "id" => 47,
                "name" => "Indonesian Rupiah",
                "name_plural" => "Indonesian rupiahs",
                "symbol" => "Rp",
                "symbol_native" => "Rp",
                "iso_code" => "IDR",
                "country_code_alpha2" => "ID"
            ],
            [
                "id" => 48,
                "name" => "Palestinian New Sheqel",
                "name_plural" => "Palestinian new sheqels",
                "symbol" => "₪",
                "symbol_native" => "₪",
                "iso_code" => "PAL",
                "country_code_alpha2" => "PS"
            ],
            [
                "id" => 49,
                "name" => "Indian Rupee",
                "name_plural" => "Indian rupees",
                "symbol" => "Rs",
                "symbol_native" => "টকা",
                "iso_code" => "INR",
                "country_code_alpha2" => "IN"
            ],
            [
                "id" => 50,
                "name" => "Iraqi Dinar",
                "name_plural" => "Iraqi dinars",
                "symbol" => "IQD",
                "symbol_native" => "د.ع.",
                "iso_code" => "IQD",
                "country_code_alpha2" => "IQ"
            ],
            [
                "id" => 51,
                "name" => "Iranian Rial",
                "name_plural" => "Iranian rials",
                "symbol" => "IRR",
                "symbol_native" => "﷼",
                "iso_code" => "IRR",
                "country_code_alpha2" => "IR"
            ],
            [
                "id" => 52,
                "name" => "Icelandic Króna",
                "name_plural" => "Icelandic krónur",
                "symbol" => "Ikr",
                "symbol_native" => "kr",
                "iso_code" => "ISK",
                "country_code_alpha2" => "IS"
            ],
            [
                "id" => 53,
                "name" => "Jamaican Dollar",
                "name_plural" => "Jamaican dollars",
                "symbol" => "J$",
                "symbol_native" => "$",
                "iso_code" => "JMD",
                "country_code_alpha2" => "JM"
            ],
            [
                "id" => 54,
                "name" => "Jordanian Dinar",
                "name_plural" => "Jordanian dinars",
                "symbol" => "JD",
                "symbol_native" => "د.أ.",
                "iso_code" => "JOD",
                "country_code_alpha2" => "JO"
            ],
            [
                "id" => 55,
                "name" => "Japanese Yen",
                "name_plural" => "Japanese yen",
                "symbol" => "¥",
                "symbol_native" => "￥",
                "iso_code" => "JPY",
                "country_code_alpha2" => "JP"
            ],
            [
                "id" => 56,
                "name" => "Kenyan Shilling",
                "name_plural" => "Kenyan shillings",
                "symbol" => "Ksh",
                "symbol_native" => "Ksh",
                "iso_code" => "KES",
                "country_code_alpha2" => "KE"
            ],
            [
                "id" => 57,
                "name" => "Cambodian Riel",
                "name_plural" => "Cambodian riels",
                "symbol" => "KHR",
                "symbol_native" => "៛",
                "iso_code" => "KHR",
                "country_code_alpha2" => "KH"
            ],
            [
                "id" => 58,
                "name" => "Comorian Franc",
                "name_plural" => "Comorian francs",
                "symbol" => "CF",
                "symbol_native" => "FC",
                "iso_code" => "KMF",
                "country_code_alpha2" => "KM"
            ],
            [
                "id" => 59,
                "name" => "South Korean Won",
                "name_plural" => "South Korean won",
                "symbol" => "₩",
                "symbol_native" => "₩",
                "iso_code" => "KRW",
                "country_code_alpha2" => "KR"
            ],
            [
                "id" => 60,
                "name" => "Kuwaiti Dinar",
                "name_plural" => "Kuwaiti dinars",
                "symbol" => "KD",
                "symbol_native" => "د.ك.",
                "iso_code" => "KWD",
                "country_code_alpha2" => "KW"
            ],
            [
                "id" => 61,
                "name" => "Kazakhstani Tenge",
                "name_plural" => "Kazakhstani tenges",
                "symbol" => "KZT",
                "symbol_native" => "тңг.",
                "iso_code" => "KZT",
                "country_code_alpha2" => "KZ"
            ],
            [
                "id" => 62,
                "name" => "Lebanese Pound",
                "name_plural" => "Lebanese pounds",
                "symbol" => "L.L.",
                "symbol_native" => "ل.ل.",
                "iso_code" => "LBP",
                "country_code_alpha2" => "LB"
            ],
            [
                "id" => 63,
                "name" => "Sri Lankan Rupee",
                "name_plural" => "Sri Lankan rupees",
                "symbol" => "SLRs",
                "symbol_native" => "SL Re",
                "iso_code" => "LKR",
                "country_code_alpha2" => "LK"
            ],
            [
                "id" => 64,
                "name" => "Lithuanian Litas",
                "name_plural" => "Lithuanian litai",
                "symbol" => "Lt",
                "symbol_native" => "Lt",
                "iso_code" => "LTL",
                "country_code_alpha2" => "LT"
            ],
            [
                "id" => 65,
                "name" => "Latvian Lats",
                "name_plural" => "Latvian lati",
                "symbol" => "Ls",
                "symbol_native" => "Ls",
                "iso_code" => "LVL",
                "country_code_alpha2" => "LV"
            ],
            [
                "id" => 66,
                "name" => "Libyan Dinar",
                "name_plural" => "Libyan dinars",
                "symbol" => "LD",
                "symbol_native" => "د.ل.",
                "iso_code" => "LYD",
                "country_code_alpha2" => "LY"
            ],
            [
                "id" => 67,
                "name" => "Moroccan Dirham",
                "name_plural" => "Moroccan dirhams",
                "symbol" => "MAD",
                "symbol_native" => "د.م.",
                "iso_code" => "MAD",
                "country_code_alpha2" => "EH"
            ],
            [
                "id" => 68,
                "name" => "Moldovan Leu",
                "name_plural" => "Moldovan lei",
                "symbol" => "MDL",
                "symbol_native" => "MDL",
                "iso_code" => "MDL",
                "country_code_alpha2" => "MD"
            ],
            [
                "id" => 69,
                "name" => "Malagasy Ariary",
                "name_plural" => "Malagasy Ariaries",
                "symbol" => "MGA",
                "symbol_native" => "MGA",
                "iso_code" => "MGA",
                "country_code_alpha2" => "MG"
            ],
            [
                "id" => 70,
                "name" => "Macedonian Denar",
                "name_plural" => "Macedonian denari",
                "symbol" => "MKD",
                "symbol_native" => "MKD",
                "iso_code" => "MKD",
                "country_code_alpha2" => "MK"
            ],
            [
                "id" => 71,
                "name" => "Myanma Kyat",
                "name_plural" => "Myanma kyats",
                "symbol" => "MMK",
                "symbol_native" => "K",
                "iso_code" => "MMK",
                "country_code_alpha2" => "MM"
            ],
            [
                "id" => 72,
                "name" => "Macanese Pataca",
                "name_plural" => "Macanese patacas",
                "symbol" => "MOP$",
                "symbol_native" => "MOP$",
                "iso_code" => "MOP",
                "country_code_alpha2" => "MO"
            ],
            [
                "id" => 73,
                "name" => "Mauritian Rupee",
                "name_plural" => "Mauritian rupees",
                "symbol" => "MURs",
                "symbol_native" => "MURs",
                "iso_code" => "MUR",
                "country_code_alpha2" => "MU"
            ],
            [
                "id" => 74,
                "name" => "Mexican Peso",
                "name_plural" => "Mexican pesos",
                "symbol" => "MX$",
                "symbol_native" => "$",
                "iso_code" => "MXN",
                "country_code_alpha2" => "MX"
            ],
            [
                "id" => 75,
                "name" => "Malaysian Ringgit",
                "name_plural" => "Malaysian ringgits",
                "symbol" => "RM",
                "symbol_native" => "RM",
                "iso_code" => "MYR",
                "country_code_alpha2" => "MY"
            ],
            [
                "id" => 76,
                "name" => "Mozambican Metical",
                "name_plural" => "Mozambican meticals",
                "symbol" => "MTn",
                "symbol_native" => "MTn",
                "iso_code" => "MZN",
                "country_code_alpha2" => "MZ"
            ],
            [
                "id" => 77,
                "name" => "Namibian Dollar",
                "name_plural" => "Namibian dollars",
                "symbol" => "N$",
                "symbol_native" => "N$",
                "iso_code" => "NAD",
                "country_code_alpha2" => "NA"
            ],
            [
                "id" => 78,
                "name" => "Nigerian Naira",
                "name_plural" => "Nigerian nairas",
                "symbol" => "₦",
                "symbol_native" => "₦",
                "iso_code" => "NGN",
                "country_code_alpha2" => "NG"
            ],
            [
                "id" => 79,
                "name" => "Nicaraguan Córdoba",
                "name_plural" => "Nicaraguan córdobas",
                "symbol" => "C$",
                "symbol_native" => "C$",
                "iso_code" => "NIO",
                "country_code_alpha2" => "NI"
            ],
            [
                "id" => 80,
                "name" => "Norwegian Krone",
                "name_plural" => "Norwegian kroner",
                "symbol" => "Nkr",
                "symbol_native" => "kr",
                "iso_code" => "NOK",
                "country_code_alpha2" => "BV"
            ],
            [
                "id" => 81,
                "name" => "Nepalese Rupee",
                "name_plural" => "Nepalese rupees",
                "symbol" => "NPRs",
                "symbol_native" => "नेरू",
                "iso_code" => "NPR",
                "country_code_alpha2" => "NP"
            ],
            [
                "id" => 82,
                "name" => "New Zealand Dollar",
                "name_plural" => "New Zealand dollars",
                "symbol" => "NZ$",
                "symbol_native" => "$",
                "iso_code" => "NZD",
                "country_code_alpha2" => "CK"
            ],
            [
                "id" => 83,
                "name" => "Omani Rial",
                "name_plural" => "Omani rials",
                "symbol" => "OMR",
                "symbol_native" => "ر.ع.",
                "iso_code" => "OMR",
                "country_code_alpha2" => "OM"
            ],
            [
                "id" => 84,
                "name" => "Panamanian Balboa",
                "name_plural" => "Panamanian balboas",
                "symbol" => "B/.",
                "symbol_native" => "B/.",
                "iso_code" => "PAB",
                "country_code_alpha2" => "PA"
            ],
            [
                "id" => 85,
                "name" => "Peruvian Nuevo Sol",
                "name_plural" => "Peruvian nuevos soles",
                "symbol" => "S/.",
                "symbol_native" => "S/.",
                "iso_code" => "PEN",
                "country_code_alpha2" => "PE"
            ],
            [
                "id" => 86,
                "name" => "Philippine Peso",
                "name_plural" => "Philippine pesos",
                "symbol" => "₱",
                "symbol_native" => "₱",
                "iso_code" => "PHP",
                "country_code_alpha2" => "PH"
            ],
            [
                "id" => 87,
                "name" => "Pakistani Rupee",
                "name_plural" => "Pakistani rupees",
                "symbol" => "PKRs",
                "symbol_native" => "₨",
                "iso_code" => "PKR",
                "country_code_alpha2" => "PK"
            ],
            [
                "id" => 88,
                "name" => "Polish Zloty",
                "name_plural" => "Polish zlotys",
                "symbol" => "zł",
                "symbol_native" => "zł",
                "iso_code" => "PLN",
                "country_code_alpha2" => "PL"
            ],
            [
                "id" => 89,
                "name" => "Paraguayan Guarani",
                "name_plural" => "Paraguayan guaranis",
                "symbol" => "₲",
                "symbol_native" => "₲",
                "iso_code" => "PYG",
                "country_code_alpha2" => "PY"
            ],
            [
                "id" => 90,
                "name" => "Qatari Rial",
                "name_plural" => "Qatari rials",
                "symbol" => "QR",
                "symbol_native" => "ر.ق.",
                "iso_code" => "QAR",
                "country_code_alpha2" => "QA"
            ],
            [
                "id" => 91,
                "name" => "Romanian Leu",
                "name_plural" => "Romanian lei",
                "symbol" => "RON",
                "symbol_native" => "RON",
                "iso_code" => "RON",
                "country_code_alpha2" => "RO"
            ],
            [
                "id" => 92,
                "name" => "Serbian Dinar",
                "name_plural" => "Serbian dinars",
                "symbol" => "din.",
                "symbol_native" => "дин.",
                "iso_code" => "RSD",
                "country_code_alpha2" => "RS"
            ],
            [
                "id" => 93,
                "name" => "Russian Ruble",
                "name_plural" => "Russian rubles",
                "symbol" => "RUB",
                "symbol_native" => "₽.",
                "iso_code" => "RUB",
                "country_code_alpha2" => "RU"
            ],
            [
                "id" => 94,
                "name" => "Rwandan Franc",
                "name_plural" => "Rwandan francs",
                "symbol" => "RWF",
                "symbol_native" => "FR",
                "iso_code" => "RWF",
                "country_code_alpha2" => "RW"
            ],
            [
                "id" => 95,
                "name" => "Saudi Riyal",
                "name_plural" => "Saudi riyals",
                "symbol" => "SR",
                "symbol_native" => "ر.س.",
                "iso_code" => "SAR",
                "country_code_alpha2" => "SA"
            ],
            [
                "id" => 96,
                "name" => "Sudanese Pound",
                "name_plural" => "Sudanese pounds",
                "symbol" => "SDG",
                "symbol_native" => "ج.س.",
                "iso_code" => "SDG",
                "country_code_alpha2" => "SD"
            ],
            [
                "id" => 97,
                "name" => "Swedish Krona",
                "name_plural" => "Swedish kronor",
                "symbol" => "Skr",
                "symbol_native" => "kr",
                "iso_code" => "SEK",
                "country_code_alpha2" => "SE"
            ],
            [
                "id" => 98,
                "name" => "Singapore Dollar",
                "name_plural" => "Singapore dollars",
                "symbol" => "S$",
                "symbol_native" => "$",
                "iso_code" => "SGD",
                "country_code_alpha2" => "SG"
            ],
            [
                "id" => 99,
                "name" => "Somali Shilling",
                "name_plural" => "Somali shillings",
                "symbol" => "Ssh",
                "symbol_native" => "Ssh",
                "iso_code" => "SOS",
                "country_code_alpha2" => "SO"
            ],
            [
                "id" => 100,
                "name" => "Syrian Pound",
                "name_plural" => "Syrian pounds",
                "symbol" => "SY£",
                "symbol_native" => "ل.س.",
                "iso_code" => "SYP",
                "country_code_alpha2" => "SY"
            ],
            [
                "id" => 101,
                "name" => "Thai Baht",
                "name_plural" => "Thai baht",
                "symbol" => "฿",
                "symbol_native" => "฿",
                "iso_code" => "THB",
                "country_code_alpha2" => "TH"
            ],
            [
                "id" => 102,
                "name" => "Tunisian Dinar",
                "name_plural" => "Tunisian dinars",
                "symbol" => "DT",
                "symbol_native" => "د.ت.",
                "iso_code" => "TND",
                "country_code_alpha2" => "TN"
            ],
            [
                "id" => 103,
                "name" => "Tongan Paʻanga",
                "name_plural" => "Tongan paʻanga",
                "symbol" => "T$",
                "symbol_native" => "T$",
                "iso_code" => "TOP",
                "country_code_alpha2" => "TO"
            ],
            [
                "id" => 104,
                "name" => "Turkish Lira",
                "name_plural" => "Turkish Lira",
                "symbol" => "TL",
                "symbol_native" => "TL",
                "iso_code" => "TRY",
                "country_code_alpha2" => "TR"
            ],
            [
                "id" => 105,
                "name" => "Trinidad and Tobago Dollar",
                "name_plural" => "Trinidad and Tobago dollars",
                "symbol" => "TT$",
                "symbol_native" => "$",
                "iso_code" => "TTD",
                "country_code_alpha2" => "TT"
            ],
            [
                "id" => 106,
                "name" => "New Taiwan Dollar",
                "name_plural" => "New Taiwan dollars",
                "symbol" => "NT$",
                "symbol_native" => "NT$",
                "iso_code" => "TWD",
                "country_code_alpha2" => "TW"
            ],
            [
                "id" => 107,
                "name" => "Tanzanian Shilling",
                "name_plural" => "Tanzanian shillings",
                "symbol" => "TSh",
                "symbol_native" => "TSh",
                "iso_code" => "TZS",
                "country_code_alpha2" => "TZ"
            ],
            [
                "id" => 108,
                "name" => "Ukrainian Hryvnia",
                "name_plural" => "Ukrainian hryvnias",
                "symbol" => "₴",
                "symbol_native" => "₴",
                "iso_code" => "UAH",
                "country_code_alpha2" => "UA"
            ],
            [
                "id" => 109,
                "name" => "Ugandan Shilling",
                "name_plural" => "Ugandan shillings",
                "symbol" => "USh",
                "symbol_native" => "USh",
                "iso_code" => "UGX",
                "country_code_alpha2" => "UG"
            ],
            [
                "id" => 110,
                "name" => "Uruguayan Peso",
                "name_plural" => "Uruguayan pesos",
                "symbol" => '$U',
                "symbol_native" => "$",
                "iso_code" => "UYU",
                "country_code_alpha2" => "UY"
            ],
            [
                "id" => 111,
                "name" => "Uzbekistan Som",
                "name_plural" => "Uzbekistan som",
                "symbol" => "UZS",
                "symbol_native" => "UZS",
                "iso_code" => "UZS",
                "country_code_alpha2" => "UZ"
            ],
            [
                "id" => 112,
                "name" => "Venezuelan Bolívar",
                "name_plural" => "Venezuelan bolívars",
                "symbol" => "Bs.F.",
                "symbol_native" => "Bs.F.",
                "iso_code" => "VEF",
                "country_code_alpha2" => "VE"
            ],
            [
                "id" => 113,
                "name" => "Vietnamese Dong",
                "name_plural" => "Vietnamese dong",
                "symbol" => "₫",
                "symbol_native" => "₫",
                "iso_code" => "VND",
                "country_code_alpha2" => "VN"
            ],
            [
                "id" => 114,
                "name" => "CFA Franc BEAC",
                "name_plural" => "CFA francs BEAC",
                "symbol" => "FCFA",
                "symbol_native" => "FCFA",
                "iso_code" => "XAF",
                "country_code_alpha2" => "CF"
            ],
            [
                "id" => 115,
                "name" => "CFA Franc BCEAO",
                "name_plural" => "CFA francs BCEAO",
                "symbol" => "CFA",
                "symbol_native" => "CFA",
                "iso_code" => "XOF",
                "country_code_alpha2" => "BF"
            ],
            [
                "id" => 116,
                "name" => "Yemeni Rial",
                "name_plural" => "Yemeni rials",
                "symbol" => "YR",
                "symbol_native" => "ر.ي.",
                "iso_code" => "YER",
                "country_code_alpha2" => "YE"
            ],
            [
                "id" => 117,
                "name" => "South African Rand",
                "name_plural" => "South African rand",
                "symbol" => "R",
                "symbol_native" => "R",
                "iso_code" => "ZAR",
                "country_code_alpha2" => "ZA"
            ],
            [
                "id" => 118,
                "name" => "Zambian Kwacha",
                "name_plural" => "Zambian kwachas",
                "symbol" => "ZK",
                "symbol_native" => "ZK",
                "iso_code" => "ZMK",
                "country_code_alpha2" => "ZM"
            ],
            [
                "id" => 119,
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

}
