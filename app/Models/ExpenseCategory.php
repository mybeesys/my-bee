<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getExpensesTotalAttribute(): string
    {
        return $this->expenses->sum('amount');
    }

    public function getExpensesTotalFormattedAttribute(): string
    {
        return format_amount($this->expenses->sum('amount'));
    }
}
