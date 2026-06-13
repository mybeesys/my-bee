<?php

    namespace Database\Seeders;

    use App\Models\Warehouse;
    use Illuminate\Database\Seeder;

    class WarehouseSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            $data = [
                ['name' => 'المخزن الرئيسي'],
                ['name' => 'مخزن بحري'],
                ['name' => 'مخزن شمبات'],
            ];

            foreach ($data as $item) {
                Warehouse::firstOrCreate(['name' => $item['name']], $item);
            }
        }
    }
