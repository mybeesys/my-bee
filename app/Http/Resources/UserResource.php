<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\SubscriptionApiService;
use Illuminate\Http\Request;

class UserResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'fullName' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'gender' => $this->gender,
            'settings' => $this->settings,
            'canCreateTenant' => $this->hasRole(User::ROLE_CLIENT),
            'canUpdateTenants' => $this->hasRole(User::ROLE_CLIENT),
            'tenants' => TenantResource::collection($this->whenLoaded('tenants')),
            'subscriptionSummary' => $this->when(
                $this->hasRole(User::ROLE_CLIENT),
                fn () => SubscriptionApiService::instance()->summaryForUser($this->client)
            ),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'canDelete' => false,
        ]);
    }
}
