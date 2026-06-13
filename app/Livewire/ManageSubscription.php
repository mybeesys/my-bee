<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class ManageSubscription extends Component
{
    public ?int $selectedPlanId = null;

    public function mount(): void
    {
        $this->selectedPlanId = get_subscription()?->plan_id;
    }

    public function getCurrentSubscriptionProperty(): ?Subscription
    {
        return get_subscription();
    }

    public function getCurrentPlanProperty(): ?Plan
    {
        $subscription = $this->currentSubscription;

        return $subscription?->plan;
    }

    /** @return Collection<int, Plan> */
    public function getPlansProperty(): Collection
    {
        return Plan::query()->orderBy('price')->get();
    }

    public function updateSubscription(): void
    {
        $plan = Plan::query()->find($this->selectedPlanId);

        if (! $plan) {
            return;
        }

        $client = get_client();

        if (! $client) {
            return;
        }

        if ($this->currentSubscription?->plan_id === $plan->id) {
            fns()->sendWarning(__('fields.subscription_already_on_plan'));

            return;
        }

        Subscription::subscribe($plan, $client);

        $this->selectedPlanId = $plan->id;

        fns()->sendSuccess(__('fields.subscription_updated'));
    }

    public function planSpanLabel(Plan $plan): string
    {
        if ($plan->span === Plan::SPAN_ONE_TIME) {
            return __('fields.one_time_subscription');
        }

        return match ($plan->span_duration) {
            'monthly' => __('fields.monthly'),
            'yearly' => __('fields.yearly'),
            'unlimited' => __('fields.unlimited'),
            default => __('fields.specified'),
        };
    }

    public function planPriceSuffix(Plan $plan): string
    {
        if ($plan->span === Plan::SPAN_ONE_TIME) {
            return '';
        }

        return match ($plan->span_duration) {
            'monthly' => __('fields.subscription_per_month'),
            'yearly' => __('fields.subscription_per_year'),
            default => '',
        };
    }

    public function planFeatures(Plan $plan): array
    {
        $features = [
            __('fields.max_allowed_companies') . ': ' . $this->formatLimit($plan->max_allowed_companies),
            __('fields.max_allowed_users') . ': ' . $this->formatLimit($plan->max_allowed_users),
            __('fields.max_allowed_sales_invoices') . ': ' . $this->formatLimit($plan->max_allowed_sales_invoices),
            __('fields.max_allowed_purchase_invoices') . ': ' . $this->formatLimit($plan->max_allowed_purchase_invoices),
        ];

        if ($plan->enable_roles) {
            $features[] = __('fields.enable_roles');
        }

        return $features;
    }

    public function nextBillingDate(): ?Carbon
    {
        $subscription = $this->currentSubscription;
        $plan = $this->currentPlan;

        if (! $subscription?->start_date || ! $plan) {
            return null;
        }

        if ($plan->span === Plan::SPAN_ONE_TIME || $plan->span_duration === 'unlimited') {
            return null;
        }

        $start = Carbon::parse($subscription->start_date);

        return match ($plan->span_duration) {
            'monthly' => $start->copy()->addMonth(),
            'yearly' => $start->copy()->addYear(),
            default => null,
        };
    }

    protected function formatLimit(?int $value): string
    {
        if ($value === null || $value < 0) {
            return __('fields.unlimited');
        }

        return (string) $value;
    }

    public function render()
    {
        return view('livewire.manage-subscription');
    }
}
