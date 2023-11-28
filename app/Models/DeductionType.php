<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeductionType extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];


    public function getFullNameAttribute($value)
    {
        if($this->type == "percentage")
        {
            return $value . ' ('.$this->value.'%)';
        }
        return $value . ' ('.number_format($this->value, 0).')';
    }

    public function employees()
    {
        return $this->hasMany(EmployeeDeductions::class);
    }
}
