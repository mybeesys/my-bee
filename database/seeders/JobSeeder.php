<?php

    namespace Database\Seeders;

    use App\Models\JobHr;
    use Illuminate\Database\Seeder;

    class JobSeeder extends Seeder
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
                    'name' => 'CEO',
                    'code' => 'job-1'
                ],
                [
                    'name' => 'CFO',
                    'code' => 'job-2'
                ],
                [
                    'name' => 'CTO',
                    'code' => 'job-3'
                ]
            ];

            foreach ($data as $item) {
                JobHr::firstOrCreate(['name' => $item['name']], $item);
            }
        }
    }
