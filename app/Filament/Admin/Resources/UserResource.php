<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Filament\Admin\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Rules\PasswordStrengthRule;
use App\Services\RoleService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $label = 'User';
    protected static ?string $pluralLabel = 'Users';
    protected static ?string $model = User::class;

    protected static ?string $slug = "administrative/users";

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationIcon = 'heroicon-o-users';


    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
//    public static function getNavigationGroup(): ?string
//    {
//        return __('fields.roles_permissions');
//    }

    public static function getLabel(): ?string
    {
        return __('fields.user');    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.users');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::with('roles')->whereHas('roles', function ($q) {
            return $q->whereNotIn('name', [User::ROLE_SUPER_ADMIN, User::ROLE_SUPER_VISOR]);
        })->whereNull('tenant_id')->count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema(static::getFormSchema(Forms\Components\Card::class))
            ->columns([
                'sm' => 3,
                'lg' => null,
            ]);
    }

    public static function getFormSchema(string $layout = Forms\Components\Grid::class): array
    {
        return [
            Forms\Components\Group::make()
                ->schema([
                    $layout::make()
                        ->schema([

                            Forms\Components\TextInput::make('first_name')
                                ->label(__('fields.first_name'))
                                ->autofocus()
                                ->required(),
                            Forms\Components\TextInput::make('second_name')
                                ->label(__('fields.second_name'))
                                ->required(),
                            Forms\Components\TextInput::make('third_name')
                                ->label(__('fields.third_name')),
                            Forms\Components\TextInput::make('fourth_name')
                                ->label(__('fields.fourth_name')),

                            Forms\Components\TextInput::make('phone')
                                ->label(__('fields.phone'))
                                ->unique(ignorable: fn(?Model $record): ?Model => $record)
                                ->required()
                                ->type('tel'),
//                                    ->rules(['required', 'phone:SD', 'unique:users,phone']),
                            Forms\Components\TextInput::make('email')
                                ->label(__('fields.email'))
                                ->disabled(fn($context) => $context == "edit")
                                ->unique(ignorable: fn(?Model $record): ?Model => $record)
                                ->required()
                                ->type('email'),

                            Forms\Components\TextInput::make('address')
                                ->label(__('fields.address')),

                            Forms\Components\Select::make('gender')

                                ->label(__('fields.gender'))
                                ->options([
                                    'male' => 'Male',
                                    'female' => 'Female',
                                ])
                                ->required(),

                            Forms\Components\DatePicker::make('dob')
                                ->label(__('fields.dob'))
                                ->seconds()
                                ->minDate(now()->subYears(60))
                                ->maxDate(now()->subYears(15))
                                ->default(now()->subYear(32))
                                ->displayFormat('d/m/Y'),
                        ])->columns([
                            'sm' => 2,
                        ]),

                    $layout::make()
                        ->visible(fn(Page $livewire) => $livewire instanceof Pages\CreateUser)
                        ->schema([
                            Forms\Components\Select::make('roles')
                                ->label(__('fields.roles_permissions'))
                                ->required()
                                ->multiple()
                                ->options((new RoleService())->listForAdmin())
                        ])
                        ->columns(1),

                    $layout::make()
                        ->visible(fn(Page $livewire) => $livewire instanceof Pages\CreateUser)
                        ->schema([
                            Forms\Components\TextInput::make('password')
                                ->label(__('fields.password'))
                                ->required()
                                ->reactive()
                                ->password()
                                ->rules(['required', new PasswordStrengthRule(8)]),

                            Forms\Components\TextInput::make('password_confirmation')
                                ->label(__('fields.password_confirmation'))
                                ->required()
                                ->password()
                                ->same('password')
                                ->visible(fn(Forms\Get $get) => $get('password') !== null || $get('password') != "")
                        ])
                        ->columns(1),


                    $layout::make()
                        ->visible(fn(Page $livewire) => $livewire instanceof Pages\EditUser)
                        ->schema([

                            Forms\Components\Checkbox::make('change_password')
                                ->label(__('fields.change_password'))
                                ->reactive()
                                ->inLine(),
                            Forms\Components\TextInput::make('password')
                                ->label(__('fields.password'))
                                ->reactive()
                                ->password()
                                ->rules(['required', new PasswordStrengthRule(8)])
                                ->visible(fn(Forms\Get $get) => $get('change_password') === true),

                            Forms\Components\TextInput::make('password_confirmation')
                                ->label(__('fields.password_confirmation'))
                                ->required()
                                ->password()
                                ->same('password')
                                ->visible(fn(Forms\Get $get) => $get('password') !== null && $get('change_password') === true)
                        ])
                        ->columns(1),
                ])->columnSpan([
                    'sm' => 2,
                ]),

            Forms\Components\Group::make()
                ->visible(fn($context) => $context == "edit")
                ->schema([
                    Forms\Components\Section::make(__('fields.status'))
                        ->schema([
                            Forms\Components\Toggle::make('active')
                                ->label(__('fields.active')),
//                                ->dehydrated(false)
//                                ->disabled(1),
                            Forms\Components\Toggle::make('email_verified_at')
                                ->dehydrateStateUsing(fn($state) =>  $state ? now() : null)
                                ->label(__('fields.email_verified?'))
                                ->dehydrated(fn($record, $state) => $record->email_verified_at != $state)
                                ->hint(function ($record) {
                                    if ($record)
                                        return $record->email_verified_at ? $record->email_verified_at->diffForHumans() : null;
                                }),
//                                ->disabled(1),
                            Forms\Components\Toggle::make('phone_verified_at')
                                ->dehydrateStateUsing(fn($state) =>  $state ? now() : null)
                                ->label(__('fields.phone_verified?'))
                                ->dehydrated(fn($record, $state) => $record->phone_verified_at != $state)
                                ->hint(function ($record) {
                                    if ($record)
                                        return $record->phone_verified_at ? $record->phone_verified_at->diffForHumans() : null;
                                }),
//                                ->disabled(1),
                        ]),

                    Forms\Components\Section::make(__('fields.roles_permissions'))
                        ->schema([
                            Forms\Components\TextInput::make('roles_list')
                                ->label(__('fields.roles_list'))
                                ->disabled(1)
                                ->dehydrated(0),
                        ]),
                ])
                ->columnSpan(['lg' => 1]),
        ];
    }

    public static function table(Tables\Table $table): Tables\Table
    {

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label('Reference')
                    ->searchable(),

                Tables\Columns\TextColumn::make('first_name')
                    ->label('Name')
                    ->searchable()
                    ->toggleable()
                    ->description(function ($record) {
                        return "Role: " . implode(',', $record->roles->pluck('name')->toArray());
                    })
                    ->getStateUsing(function ($record) {
                        return ucwords($record->full_name);
                    })->extraAttributes(function (User $record) {
                        if ($record->roles->count() == 0)
                            return ['class' => 'text-danger-700'];
                        return [];
                    }),
                Tables\Columns\TextColumn::make('email')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('address')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('age')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(function ($record) {
                        if ($record->dob) {
                            return $record->dob->age;
                        }
                        return "N/A";
                    }),

                Tables\Columns\TextColumn::make('dob')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->dateTime('M j, Y'),
                Tables\Columns\TextColumn::make('phone_verified_at')
                    ->label('Phone Verified at')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->dateTime('M j, Y'),
                Tables\Columns\BooleanColumn::make('active')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->dateTime('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])->filters([

                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),

                Tables\Filters\Filter::make('roles')
                    ->visible(fn() => auth()->user()->isSuperAdminOrSuperVisor())
                    ->form([
                        Forms\Components\Select::make('role')
                            ->multiple()
                            ->options([
                                User::ROLE_SUPER_VISOR => User::ROLE_SUPER_VISOR,
                            ]),

                        Forms\Components\Checkbox::make('missing_roles')
                            ->default(0),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['role'],
                                fn(Builder $query, $roles): Builder => $query->whereHas('roles', function (Builder $q) use ($roles) {
                                    return $q->whereIn('name', $roles);
                                }),
                            )->when(
                                $data['missing_roles'],
                                fn(Builder $query, $missing): Builder => $query->whereDoesntHave('roles'),
                            );
                    }),

            ])->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                ])
            ])->deferLoading();
    }

    public static function getEloquentQuery(): Builder
    {
        $admins_ids = User::with('roles')->whereHas('roles', function ($q) {
            return $q->whereIn('name', [User::ROLE_SUPER_ADMIN, User::ROLE_SUPER_VISOR]);
        })->pluck('id')->toArray();

        return parent::getEloquentQuery()->with(['roles', 'media'])
            ->whereNull('tenant_id')
            ->whereNotIn('id', $admins_ids)->latest();
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
