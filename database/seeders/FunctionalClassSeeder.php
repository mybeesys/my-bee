<?php

namespace Database\Seeders;

use App\Models\FunctionalClass;
use Illuminate\Database\Seeder;

class FunctionalClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'name' => 'Class 1',
                'salary' => 50000,
                'code' => 'c-1'
            ],
            [
                'name' => 'Class 2',
                'salary' => 35000,
                'code' => 'c-2'
            ],
            [
                'name' => 'Class 3',
                'salary' => 30000,
                'code' => 'c-3'
            ],
        ];

        foreach ($data as $item) {
            FunctionalClass::firstOrCreate(['name' => $item['name']], $item);
        }
    }
}
