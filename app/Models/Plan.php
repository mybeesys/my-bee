<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Plan extends BaseModel
{
    use HasFactory, HasTranslations;

    protected $guarded = [];

    public $translatable = ['name'];


    public const SPAN_ONE_TIME = "one-time";
    public const SPAN_SPECIFIED = "specified";

    public function clients(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'subscriptions', 'plan_id', 'client_id');
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
