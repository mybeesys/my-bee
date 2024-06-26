<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService
{
    const ROLE_SUPER_ADMIN = "super_admin";
    const ROLE_SUPER_VISOR = "super_visor";
    const ROLE_CLIENT = "client";

    public static function instance()
    {
        return new RoleService();
    }

    public function listForAdmin($asSelect = true, $except = []): array
    {
        if ($asSelect) {
            $roles = [];
            $roles = Role::where('tenant_id', null)
                ->whereNotIn('name', $except)
                ->pluck('name', 'id')
                ->toArray();

            foreach ($roles as $index => $role) {
                $roles[$index] = $this->translateRole($role, app()->getLocale());
            }

            return $roles;
        }

        return Role::where('tenant_id', null)->whereNotIn('name', $except)->pluck('name')->toArray();
    }


    public function listForTenant($asSelect = true): array
    {
        if ($asSelect) {
            $roles = [];
            $roles = Role::where('tenant_id', filament()->getTenant()->id)
                ->pluck('name', 'id')
                ->toArray();

            foreach ($roles as $index => $role) {
                $roles[$index] = $this->translateRole($role, app()->getLocale());
            }

            return $roles;
        }

        return Role::where('tenant_id', filament()->getTenant()->id)->pluck('name')->toArray();
    }

    public function create($role_name, $guard = "web", $tenant_id = null): \Spatie\Permission\Contracts\Role|Role
    {
        $role = DB::table("roles")
            ->where('name', $role_name)
            ->where('guard_name', $guard)
            ->where('tenant_id', $tenant_id)
            ->first();

        if (!$role) {
            DB::table("roles")->insert([
                'name' => $role_name,
                'guard_name' => $guard,
                'tenant_id' => $tenant_id,
            ]);
        }

        return Role::findById(
            DB::table("roles")
                ->where('name', $role_name)
                ->where('guard_name', $guard)
                ->where('tenant_id', $tenant_id)
                ->first()
                ->id
            , $guard);
    }

    public function assignRole($user, $role)
    {
        return $user->assignRole($role);
    }

    public function assignRoles($user, array $roles): User
    {
        foreach ($roles as $role) {
            $user->assignRole($role);
        }
        return $user;
    }

    public function syncRoles($user, array $roles): User
    {
        $user->roles()->detach();

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    public function getSuperAdminPermissions()
    {
        return [
            'view_shield::role',
            'view_any_shield::role',
            'create_shield::role',
            'update_shield::role',
            'delete_shield::role',
            'delete_any_shield::role',

            'view_app::version',
            'view_any_app::version',
            'create_app::version',
            'update_app::version',
            'delete_app::version',

            'view_client',
            'view_any_client',
            'create_client',
            'update_client',
            'delete_client',

            'view_plan',
            'view_any_plan',
            'create_plan',
            'update_plan',
            'delete_plan',

            'view_role',
            'view_any_role',
            'create_role',
            'update_role',
            'delete_role',
            'delete_any_role',

            'view_admin',
            'view_any_admin',
            'create_admin',
            'update_admin',
            'delete_admin',

            'view_user',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_user',

        ];
    }

    public function getTenantAdminDefaultPermissions(): array
    {
        return [
            'view_shield::role',
            'view_any_shield::role',
            'create_shield::role',
            'update_shield::role',
            'delete_shield::role',
            'delete_any_shield::role',

            'view_user',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_any_user',

            'view_category',
            'view_any_category',
            'create_category',
            'update_category',
            'delete_category',

            'view_product',
            'view_any_product',
            'create_product',
            'update_product',
            'delete_any_product',

            'view_item::pricing',
            'view_any_item::pricing',
            'create_item::pricing',
            'update_item::pricing',
            'delete_any_item::pricing',

            'view_warehouse',
            'view_any_warehouse',
            'create_warehouse',
            'update_warehouse',
            'delete_any_warehouse',

            'view_supplier',
            'view_any_supplier',
            'create_supplier',
            'update_supplier',
            'delete_any_supplier',

            'view_unit',
            'view_any_unit',
            'create_unit',
            'update_unit',
            'delete_unit',

            'view_stock::movement',
            'view_any_stock::movement',
            'create_stock::movement',
            'update_stock::movement',
            'delete_stock::movement',

            'view_purchase::invoice',
            'view_any_purchase::invoice',
            'create_purchase::invoice',
            'update_purchase::invoice',
            'delete_purchase::invoice',

            'view_sales::invoice',
            'view_any_sales::invoice',
            'create_sales::invoice',
            'update_sales::invoice',
            'delete_sales::invoice',

            'view_expense',
            'view_any_expense',
            'create_expense',
            'update_expense',
            'delete_expense',

            'view_expense::category',
            'view_any_expense::category',
            'create_expense::category',
            'update_expense::category',
            'delete_expense::category',

        ];
    }

    public function saasSystemRoles($panel, $asSelect = true): array
    {
        throw_if(!in_array($panel, ['admin', 'tenant']), 'Invalid panel');

        $locale = app()->getLocale();

        $roles = [];

        if ($panel === "admin") {
            if (!$asSelect) {
                $roles = [
                    "super_admin",
                    "super_visor",
                    "client",
//                    "client-supervisor",
                ];
            } else {
                $roles = [
                    "super_admin" => self::translateRole("super_admin", $locale),
                    "super_visor" => self::translateRole("super_visor", $locale),
                    "client" => self::translateRole("client", $locale),
//                    "client-supervisor" => self::translateRole("client-supervisor", $locale),
                ];
            }

        } else {

            if (!$asSelect) {
                $roles = [
//                    "Supervisor",
                ];
            } else {
                $roles = [
//                    "Supervisor" => self::translateRole("super_visor", $locale),
                ];
            }

        }

        return $roles;
    }

    public function translateRole($role, $local = "en"): string
    {
        if ($local == "ar") {
            if ($role === User::ROLE_SUPER_ADMIN)
                return "مدير النظام";
            if ($role === User::ROLE_SUPER_VISOR)
                return "مشرف النظام";
            if ($role === User::ROLE_CLIENT)
                return "عميل";
        }
        return str($role)->title()->value();
    }

    public function getRoles($users_ids): array
    {
        return User::with('roles')->whereIn('id', $users_ids ?? [])->get()->pluck('roles')->flatten()->pluck('name')->toArray() ?? [];
    }
}
