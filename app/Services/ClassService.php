<?php

namespace App\Services;

use App\Contracts\SmsProvider;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;

class ClassService
{
    public static function instance(): self
    {
        return new self();
    }

    public function listModelsThatImplements($interface): array
    {
        $classes = [];
        foreach (scandir(app_path() . "/Models") as $file) {
            if (Str::endsWith($file, '.php')) {
                $class = "App/Models/$file";
                $class = Str::replace(['../', '.php'], '', $class);
                $class = Str::replace(['/'], "\\", $class);
                if (in_array($interface, class_implements($class))) {
                    $classes[] = $class;
                }
            }
        }
        return $classes;
    }

    public function listModelsThatUses($trait):array
    {
        $classes = [];
        foreach (scandir(app_path() . "/Models") as $file) {
            if (Str::endsWith($file, '.php')) {
                $class = "App/Models/$file";
                $class = Str::replace(['../', '.php'], '', $class);
                $class = Str::replace(['/'], "\\", $class);
                if (in_array($trait, class_uses_recursive($class))) {
                    $classes[] = $class;
                }
            }
        }
        return $classes;
    }

    public function classUses($trait, $class):bool
    {
        return in_array($trait, class_uses_recursive($class));
    }

    public function listHttpResources(): array
    {
        $classes = [];
        foreach (scandir(app_path() . "/Http/Resources") as $file) {
            if (Str::endsWith($file, '.php')) {
                $full_path = str("App/Http/Resources/$file")->remove('.php')->value();
                $short_name = str($full_path)->afterLast("/")->value();

                $classes[$full_path] = $short_name;
            }
        }
        return $classes;
    }
}
