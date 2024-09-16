<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdminResource\Pages;
use App\Filament\Admin\Resources\AdminResource\RelationManagers;
use App\Models\User;
use App\Rules\PasswordStrengthRule;
use App\Services\RoleService;
use App\Tables\Columns\DateColumn;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role;

class AdminResource extends Resource
{

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $recordTitleAttribute = "phone";

    protected static ?string $slug = "administrative/admins";

    protected static ?int $navigationSort = 3;

//    public static function getNavigationGroup(): ?string
//    {
//        return __('fields.roles_permissions');
//    }

    public static function getLabel(): ?string
    {
        return __('fields.admin');
    }

    public static function getPluralLabel(): ?string
    {
        return __('fields.administrators');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record->full_name;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::with(['roles'])->whereHas('roles', function ($q) {
            return $q->whereIn('name', (new RoleService())->listForAdmin(asSelect: false, except: [User::ROLE_SUPER_ADMIN, User::ROLE_CLIENT]));
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
                                ->placeholder('966xxxxxxxxx')
                                ->unique(ignorable: fn(?Model $record): ?Model => $record)
                                ->required()
                                ->type('tel'),
//                                    ->rules(['required', 'phone:SD', 'unique:users,phone']),
                            Forms\Components\TextInput::make('email')
                                ->label(__('fields.email'))
                                ->disabled(fn($context) => $context == "edit")
                                ->unique(ignorable: fn(?Model $record): ?Model => $record)
                                ->required()
                                ->email()
                                ->type('email'),
                            Forms\Components\TextInput::make('address')
                                ->label(__('fields.address')),
                            Forms\Components\Select::make('gender')
                                ->label(__('fields.gender'))
                                ->options([
                                    'male' => __('fields.male'),
                                    'female' => __('fields.female'),
                                ])
                                ->required(),

                            Forms\Components\DatePicker::make('dob')
                                ->label(__('fields.dob'))
                                ->seconds(false)
                                ->minDate(now()->subYears(60))
                                ->maxDate(now()->subYears(15))
                                ->default(now()->subYear(32))
                                ->displayFormat('d/m/Y'),
                        ])->columns([
                            'sm' => 2,
                        ]),

                    $layout::make()
                        ->schema([
                            Forms\Components\Select::make('roles')
                                ->label(__('fields.roles_permissions'))
                                ->relationship('roles', 'name')
                                ->required()
                                ->multiple()
                                ->options((new RoleService())->listForAdmin(except: [User::ROLE_SUPER_ADMIN, User::ROLE_CLIENT]))
                                ->searchable(),
                        ])
                        ->columns(1),

                    $layout::make()
                        ->visible(fn(Page $livewire) => $livewire instanceof Pages\CreateAdmin)
                        ->schema([

                            Forms\Components\TextInput::make('password')
                                ->label(__('fields.password'))
                                ->required()
                                ->reactive()
                                ->password()
                                ->revealable()
                                ->rules(['required', new PasswordStrengthRule(8)]),

                            Forms\Components\TextInput::make('password_confirmation')
                                ->label(__('fields.password_confirmation'))
                                ->required()
                                ->password()
                                ->revealable()
                                ->same('password'),
                        ])
                        ->columns(1),
                    $layout::make()
                        ->visible(fn(Page $livewire) => $livewire instanceof Pages\EditAdmin)
                        ->schema([

                            Forms\Components\Checkbox::make('change_password')
                                ->label(__('fields.change_password'))
                                ->reactive()
                                ->inLine(),
                            Forms\Components\TextInput::make('password')
                                ->label(__('fields.password'))
                                ->reactive()
                                ->password()
                                ->revealable()
                                ->rules(['required', new PasswordStrengthRule(8)])
                                ->visible(fn(Forms\Get $get) => $get('change_password') === true),

                            Forms\Components\TextInput::make('password_confirmation')
                                ->label(__('fields.password_confirmation'))
                                ->required()
                                ->password()
                                ->revealable()
                                ->same('password')
                                ->visible(fn(Forms\Get $get) => $get('change_password') === true)
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
                                ->dehydrateStateUsing(fn($state) => $state ? now() : null)
                                ->label(__('fields.email_verified?'))
                                ->dehydrated(fn($record, $state) => $record->email_verified_at != $state)
                                ->hint(function ($record) {
                                    if ($record)
                                        return $record->email_verified_at ? $record->email_verified_at->diffForHumans() : null;
                                }),
//                                ->disabled(1),
                            Forms\Components\Toggle::make('phone_verified_at')
                                ->dehydrateStateUsing(fn($state) => $state ? now() : null)
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
                    ->label(__('fields.reference_code'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->searchable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('fields.full_name'))
                    ->searchable()
                    ->toggleable()
                    ->description(function ($record) {
                        return "Role: " . implode(',', $record->roles->pluck('name')->toArray());
                    })
                    ->getStateUsing(function ($record) {
                        return ucwords($record->full_name);
                    })
                    ->color(function (User $record) {
                        if ($record->roles->count() == 0)
                            return "danger";
                        return "primary";
                    }),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('fields.email'))
                    ->toggleable()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('fields.phone'))
                    ->toggleable()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label(__('fields.gender'))
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('address')
                    ->label(__('fields.address'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('age')
                    ->label(__('fields.age'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(function ($record) {
                        if ($record->dob) {
                            return $record->dob->age;
                        }
                        return "N/A";
                    }),

                Tables\Columns\TextColumn::make('dob')
                    ->label(__('fields.dob'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label(__('fields.email_verified_at'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->dateTime('M j, Y'),
                Tables\Columns\TextColumn::make('phone_verified_at')
                    ->label(__('fields.phone_verified_at'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->dateTime('M j, Y'),


                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('fields.updated_at'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->label(__('fields.active'))
                    ->boolean()
                    ->toggleable()
                    ->sortable(),

            ])->filters([

                Tables\Filters\SelectFilter::make('gender')
                    ->label(__('fields.gender'))
                    ->options([
                        'male' => __('fields.male'),
                        'female' => __('fields.female'),
                    ]),
            ])->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                ]),
            ])->deferLoading();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'media'])->whereHas('roles', function ($q) {
            return $q->whereIn('name', (new RoleService())->listForAdmin(asSelect: false, except: [User::ROLE_SUPER_ADMIN, User::ROLE_CLIENT]));
        })->whereNull('tenant_id')->latest();
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
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
