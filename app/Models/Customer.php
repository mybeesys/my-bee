<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, HasFinancialAccount;

        protected $guarded = [];

        public $finance = ['name' => 'name', 'acc3_code' => 1203]; //المدينون العملاء

        public function scopeHideAnonymousClient($query)
        {
            return $query->where('name', '!=', "Unknown client");
        }

        public function getFinanceNameAttribute()
        {
            return $this->name;
        }

        public function representative()
        {
            return $this->belongsTo(Representative::class);
        }


        public function reports()
        {
            return $this->hasMany(ClientReport::class);
        }

        public function contractingProjects()
        {
            return $this->hasMany(ContractingProject::class);
        }

        public static function dropdown($hide_anonymous = true)
        {
            if ($hide_anonymous)
                return Client::hideAnonymousClient()->pluck('name', 'id');

            return Client::pluck('name', 'id');
        }
}
