<div class="tenant-topbar-start">
    @include('filament.tenant.components.collapsed-topbar-logo')

    @if (filament()->auth()->check() && filled(filament()->getTenant()))
        @include('filament.tenant.components.topbar-tenant-context')
    @endif
</div>
