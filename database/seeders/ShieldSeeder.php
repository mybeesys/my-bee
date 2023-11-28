<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ShieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_SUPER_VISOR,
            User::ROLE_CLIENT,
        ];

        foreach ($roles as $role) {

            $roleService = new RoleService();

            //tenant_id null
            $r = Role::findOrCreate($role, "web");

            if ($role == User::ROLE_CLIENT) {
                $permissions = $roleService->getTenantAdminDefaultPermissions();
                foreach ($permissions as $permission) {
                    Permission::findOrCreate($permission, 'web');
                }
                $r->syncPermissions((new RoleService())->getTenantAdminDefaultPermissions());
            }
        }
//        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
//
//        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["view_admin","view_any_admin","create_admin","update_admin","delete_admin","view_airline","view_any_airline","create_airline","update_airline","delete_airline","view_app::version","view_any_app::version","create_app::version","update_app::version","delete_app::version","view_client","view_any_client","create_client","update_client","delete_client","view_country","view_any_country","create_country","update_country","delete_country","view_data::consistency","view_any_data::consistency","create_data::consistency","update_data::consistency","delete_data::consistency","view_faq","view_any_faq","create_faq","update_faq","delete_faq","view_hotel","view_any_hotel","create_hotel","update_hotel","delete_hotel","view_program","view_any_program","create_program","update_program","delete_program","view_settings","view_any_settings","create_settings","update_settings","delete_settings","view_shield::role","view_any_shield::role","create_shield::role","update_shield::role","delete_shield::role","delete_any_shield::role","view_umrah::group","view_any_umrah::group","create_umrah::group","update_umrah::group","delete_umrah::group","view_user","view_any_user","create_user","update_user","delete_user","page_Profile","page_HealthCheckResults","widget_StatsOverview"]},{"name":"super_visor","guard_name":"web","permissions":["view_faq",]},{"name":"employee","guard_name":"web","permissions":["view_faq",]},{"name":"client","guard_name":"web","permissions":["view_faq",]},{"name":"corporate","guard_name":"web","permissions":["view_faq",]},{"name":"driver","guard_name":"web","permissions":["view_faq",]},{"name":"accounting","guard_name":"web","permissions":["view_faq",]},]';
//        $directPermissions = '[]';
//
//        static::makeRolesWithPermissions($rolesWithPermissions);
//        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {

            foreach ($rolePlusPermissions ?? [] as $rolePlusPermission) {
                $role = Utils::getRoleModel()::firstOrCreate([
                    'name' => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name']
                ]);

                if (!blank($rolePlusPermission['permissions'])) {

                    $permissionModels = collect();

                    collect($rolePlusPermission['permissions'])
                        ->each(function ($permission) use ($permissionModels) {
                            $permissionModels->push(Utils::getPermissionModel()::firstOrCreate([
                                'name' => $permission,
                                'guard_name' => 'web'
                            ]));
                        });
                    $role->syncPermissions($permissionModels);

                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (!blank($permissions = json_decode($directPermissions, true))) {

            foreach ($permissions as $permission) {

                if (Utils::getPermissionModel()::whereName($permission)->doesntExist()) {
                    Utils::getPermissionModel()::create([
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}
