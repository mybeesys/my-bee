<?php

namespace App\Filament\Tenant\Resources\Shield\Support;

use App\Filament\Tenant\Resources\Shield\RoleResource;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;

class RolePermissionsMatrix
{
    /**
     * @return array<int, array{key: string, label: string, prefixes: array<int, string>}>
     */
    public static function columns(bool $full = true): array
    {
        if (! $full) {
            return [
                ['key' => 'access', 'label' => __('fields.permission_col_view'), 'prefixes' => []],
            ];
        }

        return [
            ['key' => 'view', 'label' => __('fields.permission_col_view'), 'prefixes' => ['view_any', 'view']],
            ['key' => 'create', 'label' => __('fields.permission_col_create'), 'prefixes' => ['create']],
            ['key' => 'update', 'label' => __('fields.permission_col_update'), 'prefixes' => ['update', 'update_any']],
            ['key' => 'delete', 'label' => __('fields.permission_col_delete'), 'prefixes' => ['delete', 'delete_any']],
        ];
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     items: array<int, array{field: string, label: string, permissions: array<string, string>, cells: array<string, array<int, string>>}>
     * }>
     */
    public static function resourceGroups(): array
    {
        $grouped = [];

        foreach (collect(FilamentShield::getResources())->sortKeys() as $entity) {
            $fqcn = $entity['fqcn'];
            $groupLabel = __('fields.other');

            if (class_exists($fqcn) && method_exists($fqcn, 'getNavigationGroup')) {
                $groupLabel = $fqcn::getNavigationGroup() ?? $groupLabel;
            }

            $permissions = RoleResource::getResourcePermissionOptions($entity);
            $field = $entity['resource'];

            $grouped[$groupLabel] ??= [
                'label' => $groupLabel,
                'items' => [],
            ];

            $grouped[$groupLabel]['items'][] = [
                'field' => $field,
                'label' => RoleResource::getResourceSectionLabel($entity),
                'permissions' => $permissions,
                'cells' => static::mapCells($permissions),
            ];
        }

        return array_values($grouped);
    }

    /**
     * @param  array<string, string>  $permissions
     * @return array<string, array<int, string>>
     */
    public static function mapCells(array $permissions): array
    {
        $cells = [];

        foreach (static::columns() as $column) {
            $cells[$column['key']] = static::permissionsForColumn(array_keys($permissions), $column['prefixes']);
        }

        return $cells;
    }

    /**
     * @param  array<int, string>  $permissionNames
     * @param  array<int, string>  $prefixes
     * @return array<int, string>
     */
    public static function permissionsForColumn(array $permissionNames, array $prefixes): array
    {
        if ($prefixes === []) {
            return $permissionNames;
        }

        return collect($permissionNames)
            ->filter(function (string $name) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($name, $prefix . '_')) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{field: string, label: string, items: array<int, array{field: string, label: string, permissions: array<string, string>}>}|null
     */
    public static function simpleSection(string $field, string $label, array $options): ?array
    {
        if ($options === []) {
            return null;
        }

        return [
            'field' => $field,
            'label' => $label,
            'items' => collect($options)
                ->map(fn (string $optionLabel, string $permission) => [
                    'field' => $field,
                    'permission' => $permission,
                    'label' => $optionLabel,
                ])
                ->values()
                ->all(),
        ];
    }

    public static function build(): array
    {
        $resourceGroups = static::resourceGroups();
        $pages = static::simpleSection(
            'pages_tab',
            __('fields.permission_tab_pages'),
            RoleResource::getPageOptions(),
        );
        $widgets = static::simpleSection(
            'widgets_tab',
            __('fields.permission_tab_widgets'),
            RoleResource::getWidgetOptions(),
        );
        $custom = static::simpleSection(
            'custom_permissions',
            __('fields.permission_tab_custom'),
            RoleResource::getCustomPermissionOptions(),
        );

        $showResources = (bool) Utils::isResourceEntityEnabled() && $resourceGroups !== [];
        $showPages = (bool) Utils::isPageEntityEnabled() && $pages !== null;
        $showWidgets = (bool) Utils::isWidgetEntityEnabled() && $widgets !== null;
        $showCustom = $custom !== null && (
            (bool) Utils::isCustomPermissionEntityEnabled() || count($custom['items']) > 0
        );

        $resourceCount = collect($resourceGroups)->sum(fn (array $group) => count($group['items']));

        $tabs = collect([
            [
                'id' => 'resources',
                'label' => __('fields.permission_tab_resources'),
                'badge' => $resourceCount,
                'visible' => $showResources,
            ],
            [
                'id' => 'pages',
                'label' => __('fields.permission_tab_pages'),
                'badge' => $pages ? count($pages['items']) : 0,
                'visible' => $showPages,
            ],
            [
                'id' => 'widgets',
                'label' => __('fields.permission_tab_widgets'),
                'badge' => $widgets ? count($widgets['items']) : 0,
                'visible' => $showWidgets,
            ],
            [
                'id' => 'custom',
                'label' => __('fields.permission_tab_custom'),
                'badge' => $custom ? count($custom['items']) : 0,
                'visible' => $showCustom,
            ],
        ])->filter(fn (array $tab) => $tab['visible'])->values()->all();

        return [
            'columns' => static::columns(),
            'simpleColumns' => static::columns(full: false),
            'resourceGroups' => $resourceGroups,
            'pages' => $pages,
            'widgets' => $widgets,
            'custom' => $custom,
            'tabs' => $tabs,
            'defaultTab' => $tabs[0]['id'] ?? 'resources',
            'showResources' => $showResources,
            'showPages' => $showPages,
            'showWidgets' => $showWidgets,
            'showCustom' => $showCustom,
        ];
    }
}
