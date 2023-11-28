<?php

    namespace App\Models;

    use App\Traits\HasFinancialAccount;
    use App\Traits\HasPrefixedId;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;

    class Employee extends BaseModel
    {
        use HasFactory, HasPrefixedId, HasFinancialAccount;

        public $finance = ['name' => 'full_name', 'acc3_code' => 1216]; //رواتب مستحقة

        protected $guarded = [];

        protected $casts = [
            'dob' => 'datetime',
            'join_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];

        public function getFinanceNameAttribute()
        {
            return $this->full_name;
        }

        public function scopeActive($q)
        {
            return $q->where('active', 1);
        }

        public function functionalClass()
        {
            return $this->belongsTo(FunctionalClass::class);
        }

        public function departments()
        {
            return $this->hasManyThrough(Department::class, EmployeeDepartment::class, 'employee_id', 'id');
        }

        public function jobs()
        {
            return $this->hasManyThrough(JobHr::class, EmployeeJobs::class, 'employee_id', 'id');
        }

        public function diseases()
        {
            return $this->hasManyThrough(Disease::class, EmployeeDiseases::class, 'employee_id', 'id');
        }


        public function qualifications()
        {
            return $this->hasMany(Qualification::class);
        }

        public function deductions()
        {
            return $this->hasMany(EmployeeDeductions::class);
        }

        public function entitlements()
        {
            return $this->hasMany(EmployeeEntitlements::class);
        }

        public function getNetSalaryAttribute()
        {
            $deductedPercent = $this->deductions->sum('percentage');

            $deductedAmount = 0;
            if ($deductedPercent > 0) {
                $deductedAmount = $this->functionalClass->salary * $deductedPercent / 100;
            }
            return $this->functionalClass->salary - $deductedAmount;
        }
    }
    //php artisan make:filament-relation-manager EmployeeResource qualifications name