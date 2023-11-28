<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Supplier::firstOrCreate(
            [
                'name' => 'Supplier one',
                'phone' => '111111111111',
            ],
            [
                'name' => 'Supplier one',
                'phone' => '111111111111',
            ]
        );
    }
}
