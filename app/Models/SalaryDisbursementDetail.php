<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryDisbursementDetail extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function deductions()
    {
        return $this->hasMany(SalaryDisbursementDetailDeductions::class, 'detail_id');
    }

    public function entitlements()
    {
        return $this->hasMany(SalaryDisbursementDetailEntitlements::class, 'detail_id');
    }

    public function penalties()
    {
        return $this->hasMany(SalaryDisbursementDetailPenalties::class, 'detail_id');
    }

    public function bonuses()
    {
        return $this->hasMany(SalaryDisbursementDetailBonuses::class, 'detail_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getCalculatedSalary()
    {
        $this->loadMissing(['deductions', 'entitlements', 'penalties', 'bonuses']);

        $salary = $this->employee->functionalClass->salary;

        $entitlementsAmount = $this->entitlements->sum('amount');
        $bonusesAmount = $this->penalties->sum('amount');
        $deductionsAmount = $this->deductions->sum('amount');
        $penaltiesAmount = $this->penalties->sum('amount');

        $salary = $salary + $entitlementsAmount + $bonusesAmount - $deductionsAmount - $penaltiesAmount;

        return $salary;
    }

    public function getMetaAttribute()
    {
        $this->loadMissing(['deductions', 'entitlements', 'penalties', 'bonuses']);

        $salary = $this->employee->functionalClass->salary;

        $entitlementsAmount = $this->entitlements->sum('amount');
        $bonusesAmount = $this->penalties->sum('amount');
        $deductionsAmount = $this->deductions->sum('amount');
        $penaltiesAmount = $this->penalties->sum('amount');

        $reservedSalary = $salary + $entitlementsAmount + $bonusesAmount - $deductionsAmount - $penaltiesAmount;

        return [
            'employeeID' => $this->employee->id,
            'employee' => $this->employee->full_name,
            'entitlements' => $entitlementsAmount,
            'bonuses' => $bonusesAmount,
            'deductions' => $deductionsAmount,
            'penalties' => $penaltiesAmount,
            'salary' => $salary,
            'reservedSalary' => $reservedSalary,
        ];
    }
}
