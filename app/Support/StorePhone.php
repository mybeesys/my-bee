<?php

namespace App\Support;

class StorePhone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = self::toEnglishDigits(trim($phone));
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';
        $phone = ltrim($phone, '+');

        if ($phone === '') {
            return null;
        }

        if (preg_match('/^0(5\d{8})$/', $phone, $matches)) {
            return '966' . $matches[1];
        }

        if (preg_match('/^5\d{8}$/', $phone)) {
            return '966' . $phone;
        }

        return $phone;
    }

    /** @return array<int, string> */
    public static function variants(?string $phone): array
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return [];
        }

        $variants = [$normalized];

        if (str_starts_with($normalized, '966') && strlen($normalized) >= 12) {
            $local = substr($normalized, 3);
            $variants[] = $local;
            $variants[] = '0' . $local;
        }

        $raw = preg_replace('/[^\d+]/', '', (string) $phone) ?? '';
        $raw = ltrim(self::toEnglishDigits($raw), '+');

        if ($raw !== '') {
            $variants[] = $raw;
        }

        return array_values(array_unique($variants));
    }

    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return false;
        }

        $length = strlen($normalized);

        return ctype_digit($normalized) && $length >= 9 && $length <= 15;
    }

    protected static function toEnglishDigits(string $value): string
    {
        return str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );
    }
}
