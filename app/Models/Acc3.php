<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acc3 extends BaseModel
{
    use HasFactory;

    protected $guarded = [];
    protected $table = "acc3";
    protected $primaryKey = 'code';

    public function acc2()
    {
        return $this->belongsTo(Acc2::class, 'acc2_code');
    }

}
