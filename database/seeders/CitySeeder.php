<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        self::saudiArabiaCities();
    }

    protected function saudiArabiaCities()
    {
       $listArray = json_decode(File::get(app_path('json/saudi-cities.json')), true);

       $this->insertData($listArray);
    }


    protected function insertData($list){
        foreach ($list as $city) {
            $city['id'] = $city['city_id'];
            $city['state_id'] = $city['region_id'];
            $city['name'] = [
              'en' => $city['name_en'],
              'ar' => $city['name_ar'],
            ];
            unset($city['region_id']);
            unset($city['city_id']);
            unset($city['name_en']);
            unset($city['name_ar']);

            City::create($city);
        }
    }
}
