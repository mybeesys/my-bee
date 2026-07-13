<x-filament-panels::page>
    @php
        $sections = [
            [
                'title' => __('fields.settings_section_account'),
                'items' => [
                    ['url' => \App\Filament\Tenant\Pages\Subscription::getUrl(), 'title' => __('fields.subscription'), 'icon' => 'heroicon-o-rectangle-stack', 'tone' => 'sky'],
                    ['url' => \App\Filament\Tenant\Pages\InvConfig::getUrl(), 'title' => __('fields.currency_and_invoices'), 'icon' => 'heroicon-o-document-text', 'tone' => 'violet'],
                ],
            ],
            [
                'title' => __('fields.settings_section_commerce'),
                'items' => [
                    ['url' => \App\Filament\Tenant\Pages\Store::getUrl(), 'title' => __('fields.shop'), 'icon' => 'heroicon-o-shopping-bag', 'tone' => 'emerald'],
                    ['url' => \App\Filament\Tenant\Resources\TaxProfileResource::getUrl(), 'title' => __('fields.tax_profiles'), 'icon' => 'heroicon-o-banknotes', 'tone' => 'amber'],
                    ['url' => \App\Filament\Tenant\Resources\CouponResource::getUrl(), 'title' => __('fields.coupons'), 'icon' => 'heroicon-o-user-group', 'tone' => 'rose'],
                ],
            ],
            [
                'title' => __('fields.settings_section_team'),
                'items' => [
                    ['url' => \App\Filament\Tenant\Resources\Shield\RoleResource::getUrl(), 'title' => __('fields.roles'), 'icon' => 'heroicon-o-user-circle', 'tone' => 'indigo'],
                    ['url' => \App\Filament\Tenant\Resources\UserResource::getUrl(), 'title' => __('fields.user_management'), 'icon' => 'heroicon-o-user-group', 'tone' => 'teal'],
                ],
            ],
            [
                'title' => __('fields.settings_section_operations'),
                'items' => [
                    ['url' => \App\Filament\Tenant\Resources\CategoryResource::getUrl(), 'title' => __('fields.products_categories'), 'icon' => 'heroicon-o-bolt', 'tone' => 'sky'],
                    ['url' => \App\Filament\Tenant\Resources\WarehouseResource::getUrl(), 'title' => __('fields.warehouses'), 'icon' => 'heroicon-o-building-storefront', 'tone' => 'violet'],
                    ['url' => \App\Filament\Tenant\Resources\SupplierResource::getUrl(), 'title' => __('fields.suppliers'), 'icon' => 'heroicon-o-user', 'tone' => 'emerald'],
                    ['url' => \App\Filament\Tenant\Resources\ExpenseCategoryResource::getUrl(), 'title' => __('fields.expense_categories'), 'icon' => 'heroicon-o-rectangle-stack', 'tone' => 'amber'],
                    ['url' => \App\Filament\Tenant\Resources\Acc4Resource::getUrl(), 'title' => __('fields.other_party_accounts'), 'icon' => 'heroicon-o-credit-card', 'tone' => 'slate'],
                    ['url' => \App\Filament\Tenant\Resources\BankAccountResource::getUrl(), 'title' => __('fields.bank_accounts'), 'icon' => 'heroicon-o-building-library', 'tone' => 'sky'],
                ],
            ],
        ];
    @endphp

    <div class="settings-hub">
        <div class="settings-hub__sections">
            @foreach ($sections as $section)
                <section class="settings-hub__section">
                    <h2 class="settings-hub__section-title">{{ $section['title'] }}</h2>

                    <div class="settings-hub__grid">
                        @foreach ($section['items'] as $item)
                            @include('filament.pages.settings-item', $item)
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
