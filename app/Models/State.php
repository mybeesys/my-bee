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

        protected $casts = [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

        public function localities()
        {
            return $this->hasMany(Locality::class);
        }
    }
