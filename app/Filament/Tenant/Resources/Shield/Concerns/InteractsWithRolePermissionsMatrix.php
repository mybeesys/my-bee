<?php

namespace App\Filament\Tenant\Resources\Shield\Concerns;

use App\Filament\Tenant\Resources\Shield\RoleResource;

trait InteractsWithRolePermissionsMatrix
{
    public int $rolePermissionsMatrixKey = 0;

    public function setAllRolePermissions(bool $checked): void
    {
        foreach (RoleResource::getAllPermissionFieldOptions() as $name => $options) {
            $this->data[$name] = $checked ? array_keys($options) : [];
        }

        $this->data['select_all'] = $checked;
        $this->form->fill($this->data);
        $this->bumpRolePermissionsMatrixKey();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function toggleRolePermissionColumn(string $resourceField, array $permissions, bool $checked): void
    {
        if ($permissions === []) {
            return;
        }

        $state = $this->data[$resourceField] ?? [];

        if (! is_array($state)) {
            $state = [];
        }

        if ($checked) {
            $state = array_values(array_unique([...$state, ...$permissions]));
        } else {
            $state = array_values(array_diff($state, $permissions));
        }

        $this->data[$resourceField] = $state;

        $this->syncRoleSelectAll();
        $this->bumpRolePermissionsMatrixKey();
    }

    public function toggleRoleSimplePermission(string $resourceField, string $permission, bool $checked): void
    {
        $state = $this->data[$resourceField] ?? [];

        if (! is_array($state)) {
            $state = [];
        }

        if ($checked) {
            $state[] = $permission;
        } else {
            $state = array_values(array_diff($state, [$permission]));
        }

        $this->data[$resourceField] = array_values(array_unique($state));

        $this->syncRoleSelectAll();
        $this->bumpRolePermissionsMatrixKey();
    }

    protected function syncRoleSelectAll(): void
    {
        $entitiesStates = collect(RoleResource::getAllPermissionFieldOptions())
            ->map(function (array $options, string $name) {
                $state = collect($this->data[$name] ?? [])->values()->unique()->toArray();

                return count($options) > 0 && count($options) === count($state);
            });

        $this->data['select_all'] = $entitiesStates->isNotEmpty() && ! $entitiesStates->containsStrict(false);
    }

    protected function bumpRolePermissionsMatrixKey(): void
    {
        $this->rolePermissionsMatrixKey++;
    }
}
