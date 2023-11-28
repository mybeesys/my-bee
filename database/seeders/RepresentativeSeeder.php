<?php

    namespace Database\Seeders;

    use App\Models\Representative;
    use Illuminate\Database\Seeder;

    class RepresentativeSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            Representative::firstOrCreate(
                [
                    'name' => 'Representative one',
                    'phone' => '111111111111',
                ],
                [
                    'name' => 'Representative one',
                    'phone' => '111111111111',
                ]
            );
        }
    }
