<?php

    namespace Database\Seeders;

    use App\Models\DeductionType;
    use Illuminate\Database\Seeder;

    class DeductionTypeSeeder extends Seeder
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
                    'name' => 'Deduction one',
                    'type' => 'percentage',
                    'value' => 10,
                    'auto_apply' => true,
                    'code' => 'd-1'
                ],
                [
                    'name' => 'Deduction two',
                    'type' => 'amount',
                    'value' => 5000,
                    'auto_apply' => true,
                    'code' => 'd-2'
                ]
            ];

            foreach ($data as $item) {
                DeductionType::firstOrCreate(['name' => $item['name']], $item);
            }
        }
    }
