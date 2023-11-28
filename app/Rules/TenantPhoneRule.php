<?php

namespace App\Rules;

use App\Models\Client;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TenantPhoneRule implements ValidationRule
{

    protected ?Client $client;

    public function __construct(Client $client = null)
    {
        $this->client = $client;
    }

    public function getClient(): ?Client
    {
        if (filament()->getCurrentPanel()->getId() == "admin" and $this->client == null)
            throw new \Exception("Client instance not provided correctly.");

        return $this->client ?? tenant_client();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phoneExists = Tenant::wherePhone($value)->first();
        $phoneBelongsToCurrentClient = in_array($value, $this->getClient()->tenants->pluck('phone')->toArray());

        if ($phoneExists and !$phoneBelongsToCurrentClient) {
            $fail('validation.unique')->translate();
        }
    }

}
