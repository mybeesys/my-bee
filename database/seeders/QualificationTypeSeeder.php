<?php

    namespace Database\Seeders;

    use App\Models\QualificationType;
    use Illuminate\Database\Seeder;

    class QualificationTypeSeeder extends Seeder
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
                    'name' => 'High school',
                    'code' => 'q-1'
                ],
                [
                    'name' => 'University',
                    'code' => 'q-2'
                ]
            ];

            foreach ($data as $item) {
                QualificationType::firstOrCreate(['name' => $item['name']], $item);
            }
        }
    }
