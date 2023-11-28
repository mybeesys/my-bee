<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Client::firstOrCreate(
            [
                'name' => 'Unknown client',
                'phone' => '0000000000000',
            ],
            [
                'name' => 'Unknown client',
                'phone' => '0000000000000',
            ],
        );

        Client::firstOrCreate(
            [
                'name' => 'Client one',
                'phone' => '111111111111',
            ],
            [
                'name' => 'Client one',
                'phone' => '111111111111',
            ]
        );
    }
}
