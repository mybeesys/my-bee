<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acc1 extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $primaryKey = 'code';

    protected $table = "acc1";
}
