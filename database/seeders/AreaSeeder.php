<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->saudiArabiaAreas();
    }

    protected function saudiArabiaAreas()
    {
        $listArray = json_decode(File::get(app_path('json/saudi-districts_or_areas.json')), true);

        $this->insertData($listArray);
    }


    protected function insertData($list)
    {
        foreach ($list as $area) {
            $area['name'] = [
                'en' => $area['name_en'],
                'ar' => $area['name_ar'],
            ];
            unset($area['district_id']);
            unset($area['region_id']);
            unset($area['name_en']);
            unset($area['name_ar']);

            Area::create($area);
        }
    }
}
