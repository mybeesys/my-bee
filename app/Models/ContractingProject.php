<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractingProject extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function status()
    {
        return $this->belongsTo(ContractingProjectStatuses::class, 'status_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function category()
    {
        return $this->belongsTo(ContractingProjectCategory::class, 'contracting_project_category_id');
    }

    public function subProjects()
    {
        return $this->hasMany(self::class, 'contracting_main_project_id');
    }
}
