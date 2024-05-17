<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use function Laravel\Prompts\select;

class CacheService
{
    public const MASTER_KEYS_LIST = "CACHED_DATA_KEYS";

    public const HIDDEN_KEYS_LIST = "HIDDEN_CACHED_DATA_KEYS";

    public const TTL_MINUTE = 60;
    public const TTL_DAY = 86400;
    public const TTL_TEN_MINUTES = 600;
    public const TTL_HOUR = 60 * 60;
    public const TTL_WEEK = 60 * (24 * 7);
    public const TTL_MONTH = 60 * (24 * 30);
    public const TTL_YEAR = 60 * (24 * 365);

    protected $tenant_id;

    public static function instance(): self
    {
        return new self();
    }

    public function tenant($tenant_id): self
    {
        $this->tenant_id = $tenant_id;
        return $this;
    }

    protected function getTenantId()
    {
        return $this->tenant_id ?? filament()->getTenant()?->id;
    }

    //collection
//    [
//        'key' => 'announcements',
//    'ttl' => 1800,
//    'stored_at' => 23233233233233 , //timestamp
//        ]
    public function put(string $key, $value, $ttl = null, bool $forever = false, $hide = false): bool
    {
        $key = self::evaluateKeyForTenant($key);

        if ($forever) {
            $result = Cache::forever($key, $value);
        } else {
            $result = Cache::put($key, $value, $ttl);
        }

        //ooh boy deadlock right there!
        if ($key !== self::evaluateKeyForTenant(self::getMasterKey()))
            self::syncPutObserver($key, $ttl, $forever, $hide);

        return $result;
    }

    public function get(string $key, $default = null)
    {
        $key = self::evaluateKeyForTenant($key);

        return Cache::get($key, $default);
    }

    public function has(string $key): bool
    {
        $key = self::evaluateKeyForTenant($key);

        return Cache::has($key);
    }

    public function forget(string $key): bool
    {
        $key = self::evaluateKeyForTenant($key);

        $result = Cache::forget($key);

        if ($result)
            $this->syncForgetObserver($key);

        return $result;
    }

    private function syncPutObserver($key, $ttl, bool $forever, $hide = false): void
    {
        $listArray = self::get(self::getMasterKey(), []);

        $list = collect($listArray);

        if ($list->firstWhere('key', $key)) {
            $list = $list->reject(function ($item) use ($key) {
                return $item['key'] == $key;
            });
        }

        $date = now();
        $list->add([
            'key' => $key,
            'ttl' => $ttl,
            'stored_at' => $date->timestamp,
            'forever' => $forever,
            'hidden' => $hide,
        ]);

        self::put(self::getMasterKey(), $list->toArray(), null, true);
    }

    private function syncForgetObserver($key): void
    {
        $listArray = self::get($this->getMasterKey(), []);

        $list = collect($listArray);

        if ($list->firstWhere('key', $key)) {
            $list = $list->reject(function ($item) use ($key) {
                return $item['key'] == $key;
            });
        }

        self::put(self::getMasterKey(), $list->toArray(), null, true);

    }

    public function remember($key, $ttl, $callback, $hide = false)
    {
        $key = self::evaluateKeyForTenant($key);

        $shouldSync = false;
        if (Cache::get($key) == null)
            $shouldSync = true;

        $data = Cache::remember($key, $ttl, $callback);

        if ($shouldSync)
            self::syncPutObserver($key, $ttl, false, $hide);

        return $data;
    }

    public static function keysList(): array
    {
        $service = self::instance();
        return (array)$service->get($service->getMasterKey(), []);
    }


    public function getMasterKey(): string
    {
        return $this->evaluateKeyForTenant(self::MASTER_KEYS_LIST);
    }


    protected function evaluateKeyForTenant($key): string
    {
        $tenant_id = $this->getTenantId();

        if ($tenant_id)
            return $key . "@" . $tenant_id;
//        $tenant = filament()->getTenant();
//
//        $tenant_id = $tenant ? "@$tenant->id" : "";
//
//        return $key . $tenant_id;

        return $key;
    }
}
