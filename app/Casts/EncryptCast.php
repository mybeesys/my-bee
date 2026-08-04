<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class EncryptCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Legacy values were stored with encrypt() (serialized payload).
        try {
            return Crypt::decrypt($value);
        } catch (DecryptException) {
            // Newer values may use encryptString() (non-serialized).
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                // Unreadable encrypted payload (wrong APP_KEY) — never return ciphertext.
                if (is_string($value) && str_starts_with($value, 'eyJ')) {
                    return null;
                }

                // Plaintext legacy rows.
                return $value;
            }
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return [$key => null];
        }

        // Keep encrypt() for compatibility with existing decrypt() readers.
        return [$key => encrypt($value)];
    }
}
