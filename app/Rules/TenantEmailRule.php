<?php

namespace App\Rules;

use App\Models\Client;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TenantEmailRule implements ValidationRule
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
        $emailExists = Tenant::whereEmail($value)->first();
        $emailBelongsToCurrentClient = in_array($value, $this->getClient()->tenants->pluck('email')->toArray());

        if ($emailExists and !$emailBelongsToCurrentClient) {
            $fail('validation.unique')->translate();
        }
    }
}
