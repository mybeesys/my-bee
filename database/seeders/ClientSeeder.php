<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use App\Services\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::beginTransaction();

        try {
            $monzerUser = $this->makeUser([
                    'email' => 'monzerosman@live.com',
                    'password' => bcrypt('123456'),
                    'first_name' => 'Monzer',
                    'second_name' => 'Osman',
                    'phone' => '249913513235',
                ]
            );

            $jedoUser = $this->makeUser([
                    'email' => 'jedo@live.com',
                    'password' => bcrypt('123456'),
                    'first_name' => 'Abody',
                    'second_name' => 'Soma',
                    'phone' => '249913513236',
                ]
            );

            $monzerClient = Client::firstOrCreate([
                'email' => 'monzerosman@live.com',
            ],
                [
                    'name' => 'Monzer Osman',
                    'phone' => '249913513235',
                    'user_id' => $monzerUser->id,
                ],
            );

            $jedoClient = Client::firstOrCreate([
                'email' => 'jedo@live.com',
            ],
                [
                    'name' => 'Abody Soma',
                    'phone' => '2499135132356',
                    'user_id' => $jedoUser->id,
                ],
            );

            $muntassirUser = $this->makeUser(
                [
                    'email' => 'muntassir2008@gmail.com',
                    'password' => bcrypt('123456'),
                    'first_name' => 'Muntassir',
                    'second_name' => 'seddig',
                    'phone' => '0000000000000',
                ]
            );

            $muntassirClient = Client::firstOrCreate(
                [
                    'email' => 'muntassir2008@gmail.com',
                ],
                [
                    'name' => 'Muntassir seddig',
                    'phone' => '0000000000000',
                    'user_id' => $muntassirUser->id,
                ],
            );

            $karamUser = $this->makeUser(
                [
                    'email' => 'karam_m3@hotmail.com',
                    'password' => bcrypt('123456'),
                    'first_name' => 'Karam',
                    'second_name' => 'Karam',
                    'phone' => '0000000000001',
                ]
            );

            $karamClient = Client::firstOrCreate(
                [
                    'email' => 'karam_m3@hotmail.com',
                ],
                [
                    'name' => 'Karam Karam',
                    'phone' => '0000000000001',
                    'user_id' => $karamUser->id,
                ],
            );

            RoleService::instance()->assignRole($monzerUser, User::ROLE_CLIENT);
            RoleService::instance()->assignRole($muntassirUser, User::ROLE_CLIENT);
            RoleService::instance()->assignRole($karamUser, User::ROLE_CLIENT);
            RoleService::instance()->assignRole($jedoUser, User::ROLE_CLIENT);


            $freePlan = Plan::first();

            if (!Subscription::isSubscribedTo($freePlan->id, $monzerClient))
                Subscription::subscribe($freePlan, $monzerClient);

            if (!Subscription::isSubscribedTo($freePlan->id, $muntassirClient))
                Subscription::subscribe($freePlan, $muntassirClient);

            if (!Subscription::isSubscribedTo($freePlan->id, $karamClient))
                Subscription::subscribe($freePlan, $karamClient);

            if (!Subscription::isSubscribedTo($freePlan->id, $jedoClient))
                Subscription::subscribe($freePlan, $jedoClient);

            $apple = $this->makeCompanyTenant($monzerClient, "Apple", '249913513235',
                'monzerosman@live.com', 'Monzer', '234234234234234');

            $actOfMuntassir = $this->makeIndividualTenant($muntassirClient, "Muntassir", '0000000000000',
                'muntassir2008@gmail.com');

            $actOfKaram = $this->makeIndividualTenant($karamClient, "Karam", '0000000000001',
                'karam_m3@hotmail.com');

            $actOfJedo = $this->makeIndividualTenant($jedoClient, "Jedo", '0000000000002',
                'jedo@live.com');

            $tenantService = TenantService::instance();

            if ($apple->wasRecentlyCreated)
                $tenantService->seedData($apple->id);

            if ($actOfMuntassir->wasRecentlyCreated)
                $tenantService->seedData($actOfMuntassir->id);

            if ($actOfKaram->wasRecentlyCreated)
                $tenantService->seedData($actOfKaram->id);

            if ($actOfJedo->wasRecentlyCreated)
                $tenantService->seedData($actOfJedo->id);

            DB::commit();

        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            dd($exception);
        }
    }

    public function makeUser($data)
    {
        return User::firstOrCreate([
            'email' => $data['email']
        ], $data);
    }

    public function makeIndividualTenant(Client $client, $name, $phone, $email): Tenant
    {
        $tenant = Tenant::firstOrCreate([
            'client_id' => $client->id,
        ],
            [
                'client_id' => $client->id,
                'type' => 'individual',
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'slug' => custom_slug($name)
            ]);

        if (!$tenant->members->contains($client->user)) {
            $tenant->members()->attach($client->user);
        }
        return $tenant;
    }

    public function makeCompanyTenant(Client $client, $name, $phone, $email, $company_person, $trn): Tenant
    {
        $tenant = Tenant::firstOrCreate([
            'client_id' => $client->id,
        ],
            [
                'client_id' => $client->id,
                'type' => 'company',
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'company_person' => $company_person,
                'trn' => $trn,
                'slug' => custom_slug($name),
            ]);

        if (!$tenant->members->contains($client->user)) {
            $tenant->members()->attach($client->user);
        }

        return $tenant;
    }
}
