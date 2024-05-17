<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $saudi_id = Country::firstWhere('dial_code', '966')->id;

        $list = [
            ["name" => ["en" => "Riyadh", "ar" => "الرياض"], "country_id" => $saudi_id],
            ["name" => ["en" => "Makkah", "ar" => "مكة المكرمة"], "country_id" => $saudi_id],
            ["name" => ["en" => "Al Madinah", "ar" => "المدينة المنورة"], "country_id" => $saudi_id],
            ["name" => ["en" => "Al Qassim", "ar" => "القصيم"], "country_id" => $saudi_id],
            ["name" => ["en" => "Eastern Province", "ar" => "الشرقية"], "country_id" => $saudi_id],
            ["name" => ["en" => "Asir", "ar" => "عسير"], "country_id" => $saudi_id],
            ["name" => ["en" => "Tabuk", "ar" => "تبوك"], "country_id" => $saudi_id],
            ["name" => ["en" => "Hail", "ar" => "حائل"], "country_id" => $saudi_id],
            ["name" => ["en" => "Northern Borders", "ar" => "الحدود الشمالية"], "country_id" => $saudi_id],
            ["name" => ["en" => "Jazan", "ar" => "جازان"], "country_id" => $saudi_id],
            ["name" => ["en" => "Najran", "ar" => "نجران"], "country_id" => $saudi_id],
            ["name" => ["en" => "Al Bahah", "ar" => "الباحة"], "country_id" => $saudi_id],
            ["name" => ["en" => "Al Jawf", "ar" => "الجوف"], "country_id" => $saudi_id],
        ];

        foreach ($list as $country) {
            State::updateOrCreate(['name->en' => $country['name']['en']], $country);
        }
    }
}
