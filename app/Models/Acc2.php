<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acc2 extends BaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = "acc2";
    protected $primaryKey = 'code';


    public function acc1Code()
    {
        return $this->belongsTo(Acc1::class, 'acc1_code');
    }
}
