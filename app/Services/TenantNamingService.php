<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantNamingService
{
    public static function instance(): self
    {
        return new self();
    }

    public function slugFromName(string $name): string
    {
        $name = trim($name);

        if (extension_loaded('intl')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $name);

            if (is_string($transliterated) && filled(trim($transliterated))) {
                $name = $transliterated;
            }
        }

        $slug = custom_slug($name);

        if (blank($slug)) {
            $slug = Str::slug($name, '-', 'en');
        }

        if (blank($slug)) {
            $slug = 'activity';
        }

        return $slug;
    }

    public function uniqueSlug(string $name, ?int $exceptTenantId = null): string
    {
        $base = $this->slugFromName($name);
        $slug = $base;
        $counter = 2;

        while ($this->slugExists($slug, $exceptTenantId)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function suggestUniqueName(string $name): string
    {
        $base = trim($name);
        $suggestion = $base;
        $counter = 2;

        while ($this->nameExists($suggestion)) {
            $suggestion = $base . ' ' . $counter;
            $counter++;
        }

        return $suggestion;
    }

    public function nameExists(string $name, ?int $exceptTenantId = null): bool
    {
        return Tenant::query()
            ->where('name', $name)
            ->when($exceptTenantId, fn ($query) => $query->where('id', '!=', $exceptTenantId))
            ->exists();
    }

    protected function slugExists(string $slug, ?int $exceptTenantId = null): bool
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->when($exceptTenantId, fn ($query) => $query->where('id', '!=', $exceptTenantId))
            ->exists();
    }
}
