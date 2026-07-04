<?php

namespace App\Filament\Tenant\Resources\Shield\Concerns;

use App\Filament\Tenant\Resources\Shield\RoleResource;
use Illuminate\Support\Collection;

trait InteractsWithRolePermissionsMatrix
{
    public int $rolePermissionsMatrixKey = 0;

    /**
     * Matrix toggles update Livewire $data directly; hidden checkbox lists are not synced.
     * Always read selected permissions from $this->data when saving.
     */
    protected function selectedRolePermissions(): Collection
    {
        return collect($this->data ?? [])
            ->filter(fn ($value, $key) => ! in_array($key, ['name', 'guard_name', 'select_all'], true))
            ->values()
            ->flatten()
            ->filter(fn ($permission) => is_string($permission) && filled($permission))
            ->unique()
            ->values();
    }

    protected function syncRolePermissionsFormState(): void
    {
        if (! isset($this->form)) {
            return;
        }

        $this->form->fill($this->data);
    }

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
        $this->syncRolePermissionsFormState();
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
        $this->syncRolePermissionsFormState();
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
