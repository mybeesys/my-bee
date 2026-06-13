@if(\Filament\Facades\Filament::getTenant() !== null)
    <x-filament::button
        color="danger"
        icon="gmdi-logout"
        :href="config('app.url') . '/admin/tenancy/tenants'"
        tag="a"
    >
        Exit Tenant
    </x-filament::button>
@endif
