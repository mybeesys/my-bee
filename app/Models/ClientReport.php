<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;

    class ClientReport extends BaseModel
    {
        use HasFactory;

        protected $guarded = [];

        protected $casts = [
            'files' => 'array',
            'attributes' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

        public function client()
        {
            return $this->belongsTo(Client::class);
        }

        public function user()
        {
            return $this->belongsTo(User::class);
        }

        public function processedBy()
        {
            return $this->belongsTo(User::class, 'processed_by');
        }

    }
