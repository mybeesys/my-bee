<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenceType extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public const type_item = 1;
    public const type_employee = 2;
    public const type_customer = 3;
    public const type_supplier = 4;
    public const type_representative = 5;

}
