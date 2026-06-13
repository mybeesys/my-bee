<?php

namespace App\Filament\Tenant\Resources\Shield\Support;

use App\Filament\Tenant\Resources\Shield\RoleResource;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class RolePermissionLabeler
{
    /** @var array<string, string>|null */
    protected static ?array $resourceLabelMap = null;

    /**
     * @var array<string, string>
     */
    protected static array $resourceKeyAliases = [
        'expense::category' => 'fields.expense_category',
        'item::pricing' => 'fields.item_price',
        'sales::invoice' => 'fields.sales_invoice',
        'purchase::invoice' => 'fields.purchase_invoice',
        'stock::movement' => 'fields.stock_movement',
        'shield::role' => 'fields.roles',
        'products::movement' => 'fields.products_movement',
        'price::offer' => 'fields.price_offer',
        'supply::order' => 'fields.supply_order',
        'receipt::voucher' => 'fields.receipt_voucher',
        'payment::voucher' => 'fields.payment_voucher',
        'sales::returns' => 'fields.sales_returns',
        'purchases::returns' => 'fields.purchases_returns',
        'tax::profile' => 'fields.tax_profile',
        'tax::report' => 'fields.tax_report',
        'variant::library' => 'fields.variant_library',
        'bank::account::report' => 'fields.bank_account_report',
        'treasury::account::report' => 'fields.treasury_account_report',
        'account::statement' => 'fields.account_statement',
    ];

    public static function label(string $permission): string
    {
        if (Lang::has("permissions.{$permission}")) {
            return __("permissions.{$permission}");
        }

        [$prefix, $resourceKey] = static::parse($permission);

        if ($prefix === null || $resourceKey === null) {
            return str($permission)->replace('::', ' · ')->headline()->toString();
        }

        if ($prefix === 'page') {
            return static::pageLabel($resourceKey);
        }

        if ($prefix === 'widget') {
            return static::widgetLabel($resourceKey);
        }

        $action = RoleResource::shield()->hasLocalizedPermissionLabels()
            ? FilamentShield::getLocalizedResourcePermissionLabel($prefix)
            : str($prefix)->headline()->toString();

        return __('fields.permission_action_resource', [
            'action' => $action,
            'resource' => static::resourceLabel($resourceKey),
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected static function parse(string $permission): array
    {
        $prefixes = [
            'view_any',
            'delete_any',
            'update_any',
            'force_delete_any',
            'restore_any',
            'force_delete',
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'replicate',
            'reorder',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($permission, $prefix . '_')) {
                return [$prefix, substr($permission, strlen($prefix) + 1)];
            }
        }

        if (str_starts_with($permission, 'page_')) {
            return ['page', substr($permission, 5)];
        }

        if (str_starts_with($permission, 'widget_')) {
            return ['widget', substr($permission, 7)];
        }

        return [null, null];
    }

    protected static function resourceLabel(string $key): string
    {
        $map = static::resourceLabelMap();

        if (isset($map[$key])) {
            return $map[$key];
        }

        if (isset(static::$resourceKeyAliases[$key]) && Lang::has(static::$resourceKeyAliases[$key])) {
            return __(static::$resourceKeyAliases[$key]);
        }

        $normalized = str_replace('::', '_', $key);

        foreach ([$normalized, $key, Str::singular($normalized)] as $candidate) {
            if (Lang::has("fields.{$candidate}")) {
                return __("fields.{$candidate}");
            }
        }

        return str($key)->replace('::', ' · ')->replace('_', ' ')->headline()->toString();
    }

    protected static function pageLabel(string $key): string
    {
        foreach (FilamentShield::getPages() ?? [] as $page) {
            if (($page['permission'] ?? null) === 'page_' . $key || ($page['permission'] ?? null) === $key) {
                return RoleResource::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedPageLabel($page['class'])
                    : str($key)->headline()->toString();
            }
        }

        return str($key)->headline()->toString();
    }

    protected static function widgetLabel(string $key): string
    {
        foreach (FilamentShield::getWidgets() ?? [] as $widget) {
            if (($widget['permission'] ?? null) === 'widget_' . $key || ($widget['permission'] ?? null) === $key) {
                return RoleResource::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedWidgetLabel($widget['class'])
                    : str($key)->headline()->toString();
            }
        }

        return str($key)->headline()->toString();
    }

    /**
     * @return array<string, string>
     */
    protected static function resourceLabelMap(): array
    {
        if (static::$resourceLabelMap !== null) {
            return static::$resourceLabelMap;
        }

        static::$resourceLabelMap = [];

        foreach (FilamentShield::getResources() ?? [] as $entity) {
            static::$resourceLabelMap[$entity['resource']] = RoleResource::getResourceSectionLabel($entity);
        }

        return static::$resourceLabelMap;
    }
}
