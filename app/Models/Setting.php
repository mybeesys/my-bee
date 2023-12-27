<?php

namespace App\Models;

use App\Casts\EncryptCast;
use App\Helpers\CacheManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Setting extends BaseModel implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia;

    protected $guarded = [];

    public $translatable = ['display_name'];

    protected $casts = [
        'is_system' => 'string',
        'rules' => 'array',
        'options' => 'array',
        'value' => EncryptCast::class,
        'def_value' => EncryptCast::class,
    ];

    public static function mail()
    {
        $settings = CacheManager::load(Setting::class, CacheManager::key_settings, 10);

        return $settings->filter(function ($setting) {
            return in_array('Mail', $setting->tags ?? [], true);
        })->pluck('value', 'display_name')->toArray();
    }

    public function getFileTestAttribute()
    {
        return $this->getAttribute('value');
    }

    public function getIsNumericAttribute()
    {
        return in_array('numeric', $this->rules ?? []);
    }

    public function getIsRequiredAttribute()
    {
        return in_array('required', $this->rules ?? []);
    }
}
