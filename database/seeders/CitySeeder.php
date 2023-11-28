<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        City::create([
            "name" => [
                "en" => "Khartoum",
                "ar" => "الخرطوم",
            ],
        ]);

        City::create([
            "name" => [
                "en" => "Bahry",
                "ar" => "بحري",
            ],
        ]);
    }
}
