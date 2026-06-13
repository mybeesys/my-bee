<?php


namespace App\Helpers;

use App\Models\User;
use Cache;

class CacheManager
{
    public static $map_key = 'map';

    public const ttl_minute = 60;
    public const ttl_day = 60 * 24;
    public const ttl_ten_minutes = 60 * 10;
    public const ttl_week = 60 * (24 * 7);
    public const ttl_month = 60 * (24 * 30);
    public const ttl_year = 60 * (24 * 365);


    public const key_settings = 'settings';

    public static function get($key, $default = null)
    {
        return Cache::get($key, $default);
    }

    public static function put($key, $data, $ttl = 60 * 60)
    {
        return Cache::put($key, $data, $ttl);
    }

    public static function forget($key)
    {
        $ok = Cache::forget($key);
        return $ok;
    }

    public static function getUser($id, $ttl = 60)
    {
        return Cache::remember('user@' . $id, $ttl, function () use ($id) {
            return User::with('role.permissions')->find($id);
        });
    }

    public static function removeCurrentUser()
    {
        if (\auth()->check()) {
            return Cache::forget('user@' . auth()->id());
        }
    }

    public static function cached($key): bool
    {
        return Cache::has($key);
    }

    public function storeToMap($key, $value)
    {
        $map = Cache::get(self::$map_key, collect());
        $map->put($key, $value);

        Cache::put(self::$map_key, $map, null);
    }

    public static function getMap()
    {
        return Cache::get(self::$map_key, collect());
    }
    public static function load($class, $key, $ttl = 60)
    {
        if (self::cached($key)) {
            return self::get($key);
        }
        $data = ($class)::all();

        self::put($key, $data, $ttl);

        return $data;
    }
}
