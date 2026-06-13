<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use Faker;

class DevController extends BaseController
{

    public function seedCustomers(): \Illuminate\Http\JsonResponse
    {
        $tenant = $this->getTenant();

        $records = 150;

        $faker = Faker\Factory::create();

        $phone = "24991251";
        for ($x = 0; $x <= $records; $x++) {
//            $state = \App\Models\State::all()->random();
//            $city = \App\Models\State::all()->where('state_id', $state->id)->random();
//            $area = \App\Models\State::all()->where('city_id', $city->id)->random();

            \App\Models\Customer::create([
                'tenant_id' => $tenant->id,
                'name' => $faker->name,
                'delivery_address' => $faker->address,
                'email' => $faker->email,
                'phone' => $phone . rand(1111, 9999),
                'auto_registered' => false,
                'state_id' => 1,
                'city_id' => 3,
                'area_id' => 1,
            ]);
        }

        return $this->responder("Done seeding $records customers", 200)->respond();
    }
}
