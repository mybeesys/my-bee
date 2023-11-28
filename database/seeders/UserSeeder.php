<?php

    namespace Database\Seeders;

    use App\Helpers\CacheManager;
    use App\Helpers\Permissions;
    use App\Models\User;
    use Illuminate\Database\Seeder;
    use Illuminate\Support\Str;
    use Spatie\Permission\Models\Permission;
    use Spatie\Permission\Models\Role;

    class UserSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {

            $this->createRoles();

            $superAdmin = $this->createUser([
                'first_name' => 'Super',
                'second_name' => 'Admin',
                'email' => 'super_admin@test.com',
                'password' => bcrypt('123456'),
                'email_verified_at' => now(),
                'active' => 1,
            ]);
            $this->assignRole($superAdmin, User::ROLE_SUPER_ADMIN);

            $this->createSuperVisors();
        }

        function createRoles()
        {
            $roles = [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_SUPER_VISOR,
                User::ROLE_CLIENT,
            ];
            foreach ($roles as $role) {
//                $this->command->info('User Seeder: Creating Role: ' . $role);
                Role::findOrCreate($role, "web");
            }
            //$role === 'super_admin' || $role == 'super_visor' ? "web" : "api"
        }

        function createSuperVisors()
        {
            $u = $this->createUser([
                'first_name' => 'Super',
                'second_name' => 'Visor',
                'email' => 'super_visor@test.com',
                'password' => bcrypt(123456),
                'active' => 1,
            ]);
            $this->assignRole($u, User::ROLE_SUPER_VISOR);
        }


        public function assignRole(User $user, $role)
        {
            if (!$user->hasRole($role))
                $user->assignRole($role);
        }

        public function createUser($data)
        {
            $msg = "User Seeder: Creating user: ". $data['email'];

            if(User::where('email', $data['email'])->first() != null) {
                $msg = "User Seeder: skipping " . $data['email'];
            }

            $this->command->info($msg);


            return User::firstOrCreate(['email' => $data['email']], $data);
        }

        function randDigits(int $length = 9): int
        {
            return rand(pow(10, $length - 1), pow(10, $length) - 1);
        }
    }
