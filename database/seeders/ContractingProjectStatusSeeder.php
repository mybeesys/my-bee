<?php

namespace Database\Seeders;

use App\Models\ContractingProjectStatuses;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContractingProjectStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ContractingProjectStatuses::create(
            [
                'name' => ['en' => 'Projects under study' , 'ar' => 'تحت الدراسة'],
                'color' => '#fc748e',
            ]
        );
        ContractingProjectStatuses::create(
            [
                'name' => ['en' => 'Projects under implementation' , 'ar' => 'تحت التنفيذ'],
                'color' => '#57acf0',
            ]
        );
        ContractingProjectStatuses::create(
            [
                'name' => ['en' => 'Projects implemented' , 'ar' => 'تم تنفيذها'],
                'color' => '#4cf557',
            ]
        );
    }
}
