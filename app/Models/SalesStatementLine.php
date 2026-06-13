<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesStatementLine extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;
}
