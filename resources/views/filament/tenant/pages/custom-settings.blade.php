{{--<x-filament-panels::page>--}}

{{--</x-filament-panels::page>--}}

<x-filament-panels::page>

    <!--
// v0 by Vercel.
// https://v0.dev/t/u2eWtZNVFRZ
-->

    <style>
        .custom-cols-6 {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    </style>
    <style>:root {
            --background: 0 0% 100%;
            --foreground: 222.2 84% 4.9%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --popover: 0 0% 100%;
            --popover-foreground: 222.2 84% 4.9%;
            --primary: 221.2 83.2% 53.3%;
            --primary-foreground: 210 40% 98%;
            --secondary: 210 40% 96.1%;
            --secondary-foreground: 222.2 47.4% 11.2%;
            --muted: 210 40% 96.1%;
            --muted-foreground: 215.4 16.3% 44%;
            --accent: 210 40% 96.1%;
            --accent-foreground: 222.2 47.4% 11.2%;
            --destructive: 0 72% 51%;
            --destructive-foreground: 210 40% 98%;
            --border: 214.3 31.8% 91.4%;
            --input: 214.3 31.8% 91.4%;
            --ring: 221.2 83.2% 53.3%;
            --chart-1: 221.2 83.2% 53.3%;
            --chart-2: 216 92% 60%;
            --chart-3: 212 95% 68%;
            --chart-4: 210 98% 78%;
            --chart-5: 212 97% 87%;
            --radius: 0.5rem;
        }

        img[src="/placeholder.svg"], img[src="/placeholder-user.jpg"] {
            filter: sepia(.3) hue-rotate(-60deg) saturate(.5) opacity(0.8)
        }</style>
    <style>h1, h2, h3, h4, h5, h6 {
            font-family: 'Inter', sans-serif;
            --font-sans-serif: 'Inter';
        }
    </style>
    <style>body {
            font-family: 'Inter', sans-serif;
            --font-sans-serif: 'Inter';
        }
    </style>
    <div class="w-full">
        <div class="container px-4 md:px-6">
            <div class="space-y-6">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 custom-cols-6 gap-6">
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Pages\Subscription::getUrl(), 'title' => __('fields.subscription'), 'icon' => "heroicon-o-rectangle-stack"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Pages\InvConfig::getUrl(), 'title' => __('fields.invoices'), 'icon' => "heroicon-o-document-text"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Pages\Store::getUrl(), 'title' => __('fields.shop'), 'icon' => "heroicon-o-shopping-bag"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\TaxProfileResource::getUrl(), 'title' => __('fields.tax_profiles'), 'icon' => "heroicon-o-banknotes"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\CouponResource::getUrl(), 'title' => __('fields.coupons'), 'icon' => "heroicon-o-user-group"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\Shield\RoleResource::getUrl(), 'title' => __('fields.roles'), 'icon' => "heroicon-o-user-circle"])

                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\UserResource::getUrl(), 'title' => __('fields.user_management'), 'icon' => "heroicon-o-user-group"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\CategoryResource::getUrl(), 'title' => __('fields.products_categories'), 'icon' => "heroicon-o-bolt"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\WarehouseResource::getUrl(), 'title' => __('fields.warehouses'), 'icon' => "heroicon-o-building-storefront"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\SupplierResource::getUrl(), 'title' => __('fields.suppliers'), 'icon' => "heroicon-o-user"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\ExpenseCategoryResource::getUrl(), 'title' => __('fields.expense_categories'), 'icon' => "heroicon-o-rectangle-stack"])
                    @include('filament.pages.settings-item', ['url' => \App\Filament\Tenant\Resources\Acc4Resource::getUrl(), 'title' => __('fields.level_4'), 'icon' => "heroicon-o-credit-card"])

                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>


