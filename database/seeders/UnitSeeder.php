<?php

    namespace Database\Seeders;

    use App\Models\Unit;
    use Illuminate\Database\Seeder;

    class UnitSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            $data = array(
                array('name' => 'كيلو جرام', 'created_at' => now()),
                array('name' => 'جرام', 'created_at' => now()),
                array('name' => 'رطل', 'created_at' => now()),
                array('name' => 'متر مربع', 'created_at' => now()),
                array('name' => 'متر طولي', 'created_at' => now()),
                array('name' => 'متر مكعب', 'created_at' => now()),
                array('name' => 'جوال', 'created_at' => now()),
                array('name' => 'عدد', 'created_at' => now()),
                array('name' => 'عمليه', 'created_at' => now()),
                array('name' => 'وحده', 'created_at' => now()),
                array('name' => 'قلاب ', 'created_at' => now()),
                array('name' => 'طن', 'created_at' => now()),
                array('name' => 'الف طوب', 'created_at' => now()),
                array('name' => 'لفه', 'created_at' => now()),
                array('name' => 'طوبه', 'created_at' => now()),
            );

            foreach ($data as $item)
            {
                Unit::firstOrCreate(['name' => $item['name']],$item);
            }
        }
    }
