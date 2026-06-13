<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Spatie\Translatable\HasTranslations;

    class State extends BaseModel
    {
        use HasFactory, HasTranslations;

        public $translatable = ['name'];

        protected $guarded = [];

        public function country(): \Illuminate\Database\Eloquent\Relations\BelongsTo
        {
            return $this->belongsTo(Country::class);
        }

        public function cities(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            return $this->hasMany(City::class);
        }
    }
