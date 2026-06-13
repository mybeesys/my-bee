<?php

namespace App\Rules;

use App\Models\Client;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ApiTenantAttributeRule implements ValidationRule
{

    protected $client;

    protected $attr, $ignore_id;

    public function __construct($attribute, $ignore_id = null)
    {
        $this->ignore_id = $ignore_id;

        $this->client = Client::firstWhere('user_id', auth('sanctum')->id());

        if (!$this->client)
            abort(404, 'Failed to retrieve client info');
        $this->attr = $attribute;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Tenant::where($this->attr, $value)->where('id', '!=', $this->ignore_id)->first();

        $attributeBelongsToCurrentClient = in_array($value, $this->client->tenants->pluck($this->attr)->toArray());

        if ($exists and !$attributeBelongsToCurrentClient) {
            $fail('validation.unique')->translate();
        }
    }
}
