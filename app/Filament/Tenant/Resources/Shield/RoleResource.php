<?php

namespace App\Filament\Tenant\Resources\Shield;

use App\Models\User;
use App\Services\RoleService;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Tenant\Resources\Shield\RoleResource\Pages;
use App\Filament\Tenant\Resources\Shield\Support\RolePermissionLabeler;
use App\Filament\Tenant\Resources\Shield\Support\RolePermissionsMatrix;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RoleResource extends Resource implements HasShieldPermissions
{
    protected static ?string $recordTitleAttribute = 'name';

    protected static $permissionsCollection;

    protected static bool $shouldRegisterNavigation = false;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
//            'delete_any',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('filament-shield::filament-shield.field.name'))
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Hidden::make('guard_name')
                                    ->label(__('filament-shield::filament-shield.field.guard_name'))
                                    ->default(Utils::getFilamentAuthGuard())
                                    ->nullable(),
//                                    ->maxLength(255),
                                Forms\Components\Toggle::make('select_all')
                                    ->onIcon('heroicon-s-shield-check')
                                    ->offIcon('heroicon-s-shield-exclamation')
                                    ->label(__('filament-shield::filament-shield.field.select_all.name'))
                                    ->helperText(fn(): HtmlString => new HtmlString(__('filament-shield::filament-shield.field.select_all.message')))
                                    ->live()
                                    ->afterStateUpdated(function ($livewire, Forms\Set $set, $state) {
                                        if (method_exists($livewire, 'setAllRolePermissions')) {
                                            $livewire->setAllRolePermissions((bool) $state);

                                            return;
                                        }

                                        static::toggleEntitiesViaSelectAll($livewire, $set, $state);
                                    })
                                    ->dehydrated(fn($state): bool => $state),
                            ])
                            ->columns([
                                'sm' => 2,
                                'lg' => 3,
                            ]),
                    ]),
                Forms\Components\Section::make(__('fields.permissions'))
                    ->schema([
                        Forms\Components\View::make('filament.tenant.shield.permissions-matrix')
                            ->viewData([
                                'matrix' => RolePermissionsMatrix::build(),
                            ])
                            ->columnSpanFull(),
                        ...static::getHiddenPermissionCheckboxLists(),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'fi-role-permissions-section']),
            ]);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function getHiddenPermissionCheckboxLists(): array
    {
        $lists = collect(FilamentShield::getResources())
            ->sortKeys()
            ->map(fn (array $entity) => static::makeResourceCheckboxList($entity)->hidden())
            ->all();

        if (Utils::isPageEntityEnabled() && count(FilamentShield::getPages()) > 0) {
            $lists[] = static::makeSimpleCheckboxList(
                'pages_tab',
                static::getPageOptions(),
            )->hidden();
        }

        if (Utils::isWidgetEntityEnabled() && count(FilamentShield::getWidgets()) > 0) {
            $lists[] = static::makeSimpleCheckboxList(
                'widgets_tab',
                static::getWidgetOptions(),
            )->hidden();
        }

        if (static::hasCustomPermissionEntities()) {
            $lists[] = static::makeSimpleCheckboxList(
                'custom_permissions',
                static::getCustomPermissionOptions(),
            )->hidden();
        }

        return $lists;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function getAllPermissionFieldOptions(): array
    {
        $fields = [];

        foreach (collect(FilamentShield::getResources())->sortKeys() as $entity) {
            $fields[$entity['resource']] = static::getResourcePermissionOptions($entity);
        }

        if (Utils::isPageEntityEnabled() && count(FilamentShield::getPages()) > 0) {
            $fields['pages_tab'] = static::getPageOptions();
        }

        if (Utils::isWidgetEntityEnabled() && count(FilamentShield::getWidgets()) > 0) {
            $fields['widgets_tab'] = static::getWidgetOptions();
        }

        if (static::hasCustomPermissionEntities()) {
            $fields['custom_permissions'] = static::getCustomPermissionOptions();
        }

        return $fields;
    }

    protected static function makeResourceCheckboxList(array $entity): Forms\Components\CheckboxList
    {
        return Forms\Components\CheckboxList::make($entity['resource'])
            ->label('')
            ->options(fn (): array => static::getResourcePermissionOptions($entity))
            ->live()
            ->afterStateHydrated(function (Component $component, $livewire, string $operation, ?Model $record, Forms\Set $set) use ($entity) {
                static::setPermissionStateForRecordPermissions(
                    component: $component,
                    operation: $operation,
                    permissions: static::getResourcePermissionOptions($entity),
                    record: $record
                );

                static::toggleSelectAllViaEntities($livewire, $set);
            })
            ->afterStateUpdated(fn ($livewire, Forms\Set $set) => static::toggleSelectAllViaEntities($livewire, $set))
            ->selectAllAction(fn (FormAction $action, Component $component, $livewire, Forms\Set $set) => static::bulkToggleableAction(
                action: $action,
                component: $component,
                livewire: $livewire,
                set: $set
            ))
            ->deselectAllAction(fn (FormAction $action, Component $component, $livewire, Forms\Set $set) => static::bulkToggleableAction(
                action: $action,
                component: $component,
                livewire: $livewire,
                set: $set,
                resetState: true
            ))
            ->dehydrated(fn ($state) => blank($state) ? false : true)
            ->bulkToggleable()
            ->gridDirection('row')
            ->columns(FilamentShieldPlugin::get()->getResourceCheckboxListColumns());
    }

    /**
     * @param  array<string, string>  $options
     */
    protected static function makeSimpleCheckboxList(string $name, array $options): Forms\Components\CheckboxList
    {
        return Forms\Components\CheckboxList::make($name)
            ->label('')
            ->options(fn (): array => $options)
            ->searchable()
            ->live()
            ->afterStateHydrated(function (Component $component, $livewire, string $operation, ?Model $record, Forms\Set $set) use ($options) {
                static::setPermissionStateForRecordPermissions(
                    component: $component,
                    operation: $operation,
                    permissions: $options,
                    record: $record
                );

                static::toggleSelectAllViaEntities($livewire, $set);
            })
            ->afterStateUpdated(fn ($livewire, Forms\Set $set) => static::toggleSelectAllViaEntities($livewire, $set))
            ->selectAllAction(fn (FormAction $action, Component $component, $livewire, Forms\Set $set) => static::bulkToggleableAction(
                action: $action,
                component: $component,
                livewire: $livewire,
                set: $set
            ))
            ->deselectAllAction(fn (FormAction $action, Component $component, $livewire, Forms\Set $set) => static::bulkToggleableAction(
                action: $action,
                component: $component,
                livewire: $livewire,
                set: $set,
                resetState: true
            ))
            ->dehydrated(fn ($state) => blank($state) ? false : true)
            ->bulkToggleable()
            ->gridDirection('row')
            ->columns(FilamentShieldPlugin::get()->getCheckboxListColumns())
            ->columnSpan(FilamentShieldPlugin::get()->getCheckboxListColumnSpan());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.name'))
                    ->formatStateUsing(fn($state): string => Str::headline($state))
                    ->colors(['primary'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->visible(false)
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.guard_name')),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.permissions'))
                    ->counts('permissions')
                    ->colors(['success']),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-shield::filament-shield.column.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(function ($record) {
                    return !in_array($record->name, (new RoleService())->saasSystemRoles('tenant', false));
                }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function ($record) {
                        return $record->users->isEmpty() and !in_array($record->name, (new RoleService())->saasSystemRoles('tenant', false));
                    }),
            ])
            ->bulkActions([
//                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getRecordUrl(Model $record): string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function getModel(): string
    {
        return Utils::getRoleModel();
    }

    public static function getModelLabel(): string
    {
        return __('filament-shield::filament-shield.resource.label.role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-shield::filament-shield.resource.label.roles');
    }

//
//    public static function shouldRegisterNavigation(): bool
//    {
//        return filament()->getTenant() === null;
//    }

//    public static function getNavigationGroup(): ?string
//    {
//        return __('fields.roles_permissions');
//    }

    public static function getNavigationLabel(): string
    {
        return __('filament-shield::filament-shield.nav.role.label');
    }

    public static function getNavigationIcon(): string
    {
        return "heroicon-o-shield-check";
    }
//
//    public static function getNavigationSort(): ?int
//    {
//        return Utils::getResourceNavigationSort();
//    }

    public static function getSlug(): string
    {
        return Utils::getResourceSlug();
    }

    public static function getNavigationBadge(): ?string
    {
        return Utils::isResourceNavigationBadgeEnabled()
            ? static::getModel()::where('guard_name', 'web')->where('tenant_id', filament()->getTenant()->id)->count()
            : null;
    }

    public static function canGloballySearch(): bool
    {
        return Utils::isResourceGloballySearchable() && count(static::getGloballySearchableAttributes()) && static::canViewAny();
    }

    public static function getResourceEntitiesSchema(): ?array
    {
        return [];
    }

    public static function hasCustomPermissionEntities(): bool
    {
        return count(static::getCustomEntities()) > 0;
    }

    public static function getResourceSectionLabel(array $entity): string
    {
        if (str($entity['resource'])->contains('role')) {
            return __('fields.roles');
        }

        $known = [
            'acc1' => 'fields.level_1',
            'acc2' => 'fields.level_2',
            'acc3' => 'fields.level_3',
            'acc4' => 'fields.other_party_accounts',
            'bank::account' => 'fields.bank_accounts',
        ];

        if (isset($known[$entity['resource']])) {
            return __($known[$entity['resource']]);
        }

        if (static::shield()->hasLocalizedPermissionLabels() && class_exists($entity['fqcn'])) {
            return FilamentShield::getLocalizedResourceLabel($entity['fqcn']);
        }

        return str($entity['model'])->headline()->toString();
    }

    public static function getResourcePermissionOptions(array $entity): array
    {
        return collect(Utils::getResourcePermissionPrefixes($entity['fqcn']))
            ->flatMap(function ($permission) use ($entity) {
                $name = $permission . '_' . $entity['resource'];
                $label = static::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedResourcePermissionLabel($permission)
                    : $name;

                return [$name => $label];
            })
            ->toArray();
    }

    public static function setPermissionStateForRecordPermissions(Component $component, string $operation, array $permissions, ?Model $record): void
    {

        if (in_array($operation, ['edit', 'view'])) {

            if (blank($record)) {
                return;
            }
            if (count($permissions) > 0) {
                $component->state(
                    collect($permissions)
                        /** @phpstan-ignore-next-line */
                        ->filter(fn($value, $key) => $record->checkPermissionTo($key))
                        ->keys()
                        ->toArray()
                );
            }
        }
    }

    public static function toggleEntitiesViaSelectAll($livewire, Forms\Set $set, bool $state): void
    {
        foreach (static::getAllPermissionFieldOptions() as $name => $options) {
            $values = $state ? array_keys($options) : [];

            $set($name, $values);
            $livewire->data[$name] = $values;
        }
    }

    public static function toggleSelectAllViaEntities($livewire, Forms\Set $set): void
    {
        $entitiesStates = collect(static::getAllPermissionFieldOptions())
            ->map(function (array $options, string $name) use ($livewire) {
                $selected = collect($livewire->data[$name] ?? [])->values()->unique()->toArray();

                return count($options) > 0 && count($options) === count($selected);
            })
            ->values();

        $set('select_all', $entitiesStates->isNotEmpty() && ! $entitiesStates->containsStrict(false));
    }

    public static function getPageOptions(): array
    {
        return collect(FilamentShield::getPages())
            ->flatMap(fn ($page) => [
                $page['permission'] => static::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedPageLabel($page['class'])
                    : $page['permission'],
            ])
            ->toArray();
    }

    public static function getWidgetOptions(): array
    {
        return collect(FilamentShield::getWidgets())
            ->flatMap(fn ($widget) => [
                $widget['permission'] => static::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedWidgetLabel($widget['class'])
                    : $widget['permission'],
            ])
            ->toArray();
    }

    public static function shield(): FilamentShieldPlugin
    {
        return FilamentShieldPlugin::get();
    }

    public static function getCustomPermissionOptions(): array
    {
        return collect(static::getCustomEntities())
            ->flatMap(fn ($customPermission) => [
                $customPermission => RolePermissionLabeler::label($customPermission),
            ])
            ->toArray();
    }

    protected static function getCustomEntities(): ?Collection
    {
        if (blank(static::$permissionsCollection)) {
            static::$permissionsCollection = Utils::getPermissionModel()::all();
        }

        $resourcePermissions = collect();
        collect(FilamentShield::getResources())->each(function ($entity) use ($resourcePermissions) {
            collect(Utils::getResourcePermissionPrefixes($entity['fqcn']))->map(function ($permission) use ($resourcePermissions, $entity) {
                $resourcePermissions->push((string)Str::of($permission . '_' . $entity['resource']));
            });
        });

        $entitiesPermissions = $resourcePermissions
            ->merge(
                collect(FilamentShield::getPages())->map(
                    fn ($page) => is_array($page) ? $page['permission'] : $page
                )
            )
            ->merge(
                collect(FilamentShield::getWidgets())->map(
                    fn ($widget) => is_array($widget) ? $widget['permission'] : $widget
                )
            )
            ->values();

        return static::$permissionsCollection->whereNotIn('name', $entitiesPermissions)->pluck('name');
    }

    public static function bulkToggleableAction(FormAction $action, Component $component, $livewire, Forms\Set $set, bool $resetState = false): void
    {
        $action
            ->livewireClickHandlerEnabled(true)
            ->action(function () use ($component, $livewire, $set, $resetState) {
                /** @phpstan-ignore-next-line */
                $component->state($resetState ? [] : array_keys($component->getOptions()));
                static::toggleSelectAllViaEntities($livewire, $set);
            });
    }

}
