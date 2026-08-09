@php
    use App\Filament\Tenant\Pages\Subscription;

    $tenant = filament()->getTenant();

    if (! $tenant) {
        return;
    }

    try {
        $plan = get_plan();
        $isFreePlan = subscription_on_free_plan();
        $trialDaysRemaining = subscription_trial_days_remaining();
        $trialExpired = subscription_trial_expired();
    } catch (\Throwable) {
        $plan = null;
        $isFreePlan = false;
        $trialDaysRemaining = null;
        $trialExpired = false;
    }

    $subscriptionUrl = Subscription::getUrl();
@endphp

<div class="tenant-topbar-context">
    <div class="tenant-topbar-context__tenant" title="{{ $tenant->name }}">
        <span class="tenant-topbar-context__icon-wrap" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-building-storefront" class="tenant-topbar-context__icon" />
        </span>
        <span class="tenant-topbar-context__name">{{ $tenant->name }}</span>
    </div>

    @if ($isFreePlan)
        <a
            href="{{ $subscriptionUrl }}"
            @class([
                'tenant-topbar-context__hint',
                'tenant-topbar-context__hint--warning' => ! $trialExpired && $trialDaysRemaining !== null && $trialDaysRemaining <= 5,
                'tenant-topbar-context__hint--expired' => $trialExpired,
            ])
        >
            <span class="tenant-topbar-context__hint-text">
                @if ($trialExpired)
                    {{ __('fields.topbar_free_plan_expired') }}
                @elseif ($trialDaysRemaining !== null)
                    {{ __('fields.topbar_free_plan_ends_in', ['days' => $trialDaysRemaining]) }}
                @else
                    {{ __('fields.topbar_free_plan_active') }}
                @endif
            </span>
            <span class="tenant-topbar-context__hint-sep" aria-hidden="true">·</span>
            <span class="tenant-topbar-context__hint-action">{{ __('fields.topbar_free_plan_upgrade') }}</span>
        </a>
    @elseif ($plan)
        <span class="tenant-topbar-context__plan-pill">
            {{ $plan->name }}
        </span>
    @endif
</div>
