<?php

    namespace Database\Seeders;

    use App\Models\VacationType;
    use Illuminate\Database\Seeder;

    class VacationTypeSeeder extends Seeder
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
                    'name' => 'With salary',
                    'value' => 0,
                    'code' => 'v-1'
                ],
                [
                    'name' => 'Without salary',
                    'value' => 10,
                    'code' => 'v-2'
                ]
            ];

            foreach ($data as $item) {
                VacationType::firstOrCreate(['name' => $item['name']], $item);
            }
        }
    }
