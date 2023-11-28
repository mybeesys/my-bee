<?php

    namespace Database\Seeders;

    use App\Models\Entitlement;
    use Illuminate\Database\Seeder;

    class EntitlementSeeder extends Seeder
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
                    'name' => 'Housing',
                    'value' => 15000,
                    'auto_apply' => true,
                    'code' => 'e-1'
                ],
                [
                    'name' => 'Transportation',
                    'value' => 7000,
                    'auto_apply' => true,
                    'code' => 'e-2'
                ]
            ];

            foreach ($data as $item) {
                Entitlement::firstOrCreate(['name' => $item['name']], $item);
            }
        }
    }
