@php
    $matrix = $matrix ?? [];
    $columns = $matrix['columns'] ?? [];
    $simpleColumns = $matrix['simpleColumns'] ?? [];
    $resourceGroups = $matrix['resourceGroups'] ?? [];
    $tabs = $matrix['tabs'] ?? [];
    $defaultTab = $matrix['defaultTab'] ?? ($tabs[0]['id'] ?? 'resources');
    $isAr = app()->getLocale() === 'ar';
    $readOnly = $this instanceof \Filament\Resources\Pages\ViewRecord;
    $matrixKey = $this->rolePermissionsMatrixKey ?? 0;

    $isCellChecked = function (string $field, array $permissions) {
        if ($permissions === []) {
            return false;
        }

        $selected = $this->data[$field] ?? [];

        if (! is_array($selected)) {
            return false;
        }

        return count(array_intersect($permissions, $selected)) === count($permissions);
    };

    $isSimpleChecked = function (string $field, string $permission) {
        $selected = $this->data[$field] ?? [];

        return is_array($selected) && in_array($permission, $selected, true);
    };
@endphp

<div
    class="role-permissions-matrix"
    wire:key="role-permissions-matrix-{{ $matrixKey }}"
    x-data="{ tab: @js($defaultTab) }"
>
    @if (count($tabs) > 1)
        <div class="role-permissions-matrix__tabs" role="tablist">
            @foreach ($tabs as $tabItem)
                <button
                    type="button"
                    role="tab"
                    class="role-permissions-matrix__tab"
                    :class="{ 'role-permissions-matrix__tab--active': tab === @js($tabItem['id']) }"
                    @click="tab = @js($tabItem['id'])"
                >
                    <span>{{ $tabItem['label'] }}</span>
                    @if (($tabItem['badge'] ?? 0) > 0)
                        <span class="role-permissions-matrix__tab-badge">{{ $tabItem['badge'] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    @if ($matrix['showResources'] ?? false)
        <div
            class="role-permissions-matrix__panel"
            x-show="tab === 'resources'"
            x-cloak
            @if (count($tabs) <= 1) style="display: block" @endif
        >
            <div class="role-permissions-matrix__scroll">
                <table class="role-permissions-matrix__table">
                    <thead>
                        <tr class="role-permissions-matrix__head-row">
                            <th class="role-permissions-matrix__head-permission {{ $isAr ? 'text-right' : 'text-left' }}">
                                {{ __('fields.permissions') }}
                            </th>
                            @foreach ($columns as $column)
                                <th class="role-permissions-matrix__head-action">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>

                    @foreach ($resourceGroups as $groupIndex => $group)
                        <tbody
                            class="role-permissions-matrix__group"
                            x-data="{ open: true }"
                        >
                            <tr class="role-permissions-matrix__group-row">
                                <td
                                    class="role-permissions-matrix__group-cell"
                                    colspan="{{ count($columns) + 1 }}"
                                >
                                    <button
                                        type="button"
                                        class="role-permissions-matrix__group-toggle"
                                        @click="open = ! open"
                                    >
                                        <span
                                            class="role-permissions-matrix__group-icon"
                                            x-text="open ? '−' : '+'"
                                        ></span>
                                        <span>{{ $group['label'] }}</span>
                                    </button>
                                </td>
                            </tr>

                            @foreach ($group['items'] as $item)
                                <tr
                                    class="role-permissions-matrix__resource-row"
                                    x-show="open"
                                    x-cloak
                                >
                                    <td class="role-permissions-matrix__resource-label {{ $isAr ? 'text-right' : 'text-left' }}">
                                        {{ $item['label'] }}
                                    </td>
                                    @foreach ($columns as $column)
                                        @php
                                            $cellPermissions = $item['cells'][$column['key']] ?? [];
                                            $checked = $isCellChecked($item['field'], $cellPermissions);
                                            $disabled = $cellPermissions === [];
                                        @endphp
                                        <td class="role-permissions-matrix__action-cell">
                                            <label class="role-permissions-matrix__checkbox {{ ($disabled || $readOnly) ? 'role-permissions-matrix__checkbox--disabled' : '' }}">
                                                <input
                                                    type="checkbox"
                                                    @disabled($disabled || $readOnly)
                                                    @checked($checked)
                                                    @unless($readOnly)
                                                        wire:click="toggleRolePermissionColumn(@js($item['field']), @js($cellPermissions), {{ $checked ? 'false' : 'true' }})"
                                                    @endunless
                                                />
                                                <span class="role-permissions-matrix__checkbox-ui"></span>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach

                            @if ($groupIndex < count($resourceGroups) - 1)
                                <tr class="role-permissions-matrix__divider-row" x-show="open" x-cloak>
                                    <td colspan="{{ count($columns) + 1 }}"></td>
                                </tr>
                            @endif
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    @foreach ([
        'pages' => $matrix['pages'] ?? null,
        'widgets' => $matrix['widgets'] ?? null,
        'custom' => $matrix['custom'] ?? null,
    ] as $tabId => $section)
        @if ($section)
            <div
                class="role-permissions-matrix__panel"
                x-show="tab === @js($tabId)"
                x-cloak
            >
                <div class="role-permissions-matrix__scroll">
                    <table class="role-permissions-matrix__table role-permissions-matrix__table--simple">
                        <thead>
                            <tr class="role-permissions-matrix__head-row">
                                <th class="role-permissions-matrix__head-permission {{ $isAr ? 'text-right' : 'text-left' }}">
                                    {{ __('fields.permissions') }}
                                </th>
                                @foreach ($simpleColumns as $column)
                                    <th class="role-permissions-matrix__head-action">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['items'] as $item)
                                @php
                                    $checked = $isSimpleChecked($item['field'], $item['permission']);
                                @endphp
                                <tr class="role-permissions-matrix__resource-row">
                                    <td class="role-permissions-matrix__resource-label {{ $isAr ? 'text-right' : 'text-left' }}">
                                        {{ $item['label'] }}
                                    </td>
                                    <td class="role-permissions-matrix__action-cell">
                                        <label class="role-permissions-matrix__checkbox {{ $readOnly ? 'role-permissions-matrix__checkbox--disabled' : '' }}">
                                            <input
                                                type="checkbox"
                                                @disabled($readOnly)
                                                @checked($checked)
                                                @unless($readOnly)
                                                    wire:click="toggleRoleSimplePermission(@js($item['field']), @js($item['permission']), {{ $checked ? 'false' : 'true' }})"
                                                @endunless
                                            />
                                            <span class="role-permissions-matrix__checkbox-ui"></span>
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach
</div>
