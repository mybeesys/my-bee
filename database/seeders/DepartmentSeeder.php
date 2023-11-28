<?php

    namespace Database\Seeders;

    use App\Models\Department;
    use Illuminate\Database\Seeder;

    class DepartmentSeeder extends Seeder
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
                    'name' => 'IT',
                    'code' => 'it'
                ],
                [
                    'name' => 'Accounting',
                    'code' => 'acc'
                ],
                [
                    'name' => 'Sales',
                    'code' => 'sales'
                ]
            ];

            foreach ($data as $item) {
                Department::firstOrCreate(['name' => $item['name']], $item);
            }
        }
    }
