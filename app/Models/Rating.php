<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends BaseModel
{
    public $fillable = ['rating','comment', 'rateable_type', 'rateable_id'];


    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rateable()
    {
        return $this->morphTo();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
