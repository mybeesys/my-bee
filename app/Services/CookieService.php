<?php


namespace App\Services;


use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;

class CookieService
{

    public static function instance(): self
    {
        return new self();
    }

    public function set($key, $value, ): bool
    {
        Cookie::queue(Cookie::make($key, $value));
        return true;
    }

    public function get($key, $default = null): string|array|null
    {
        try {
            return Crypt::decrypt(Cookie::get($key, $default), false);
        }catch (\Throwable){}
        return null;
//        return Cookie::get($key, $default);
//        return $_COOKIE[$name] ?? $default;
    }
}
