<?php

    namespace App\Models;

    use App\Services\AccountingService;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Support\Facades\DB;

    class SalaryDisbursement extends BaseModel
    {
        use HasFactory;

        protected $guarded = [];

        protected $casts = [
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

        public function details()
        {
            return $this->hasMany(SalaryDisbursementDetail::class);
        }

        public function getTotalAttribute(): bool
        {
            return number_format($this->sub_total + $this->details->entitlements->sum('amount') - $this->details->deductions->sum('amount'), 0);
        }

        public function getApprovedAttribute(): bool
        {
            return $this->approved_at !== null;
        }

        public function approve($salaries_account_code)
        {

            $entitlements_account_code = "122400002";
            $bonuses_account_code = "122400003";
            $deductions_account_code = "122000001";
            $penalties_account_code = "122000002";

            if (!$this->approved) {
                try {
                    DB::beginTransaction();

                    $this->loadMissing(['details.employee.functionalClass', 'details.employee.acc4']);

                    if ($this->details->isEmpty()) {
                        throw new \Exception("الرجاء إختيار موظف واحد علي الأقل");
                    }

                    $this->update(['approved_by' => auth()->id(), 'approved_at' => now()]);

                    $op = make_general_voucher_op();

                    $accService = new AccountingService();

                    $ex_rate = setting('finance.sdg.usd.exchange_rate', null);

                    if ($ex_rate == null) {
                        throw new \Exception('Unable to retrieve exchange rate');
                    }

                    foreach ($this->details as $detail) {

                        //salaries
                        $accService
                            ->setUp(
                                $op->id,
                                now(),
                                1,
                                generate_double_entry_transaction_id(),
                                $detail->employee->functionalClass->salary,
                                $ex_rate,
                                "Employee Basic Salary: {$detail->employee->full_name}",
                                "Employee Basic Salary: {$detail->employee->full_name}",
                                null,
                            )->make($salaries_account_code, $detail->employee->acc4->code)
                            ->finish();

                        //entitlements
                        foreach ($detail->entitlements as $ent) {

                            $accService
                                ->setUp(
                                    $op->id,
                                    now(),
                                    1,
                                    generate_double_entry_transaction_id(),
                                    $ent->amount,
                                    $ex_rate,
                                    "Employee Entitlement: {$ent->name}, {$detail->employee->full_name}",
                                    "Employee Entitlement: {$ent->name}, {$detail->employee->full_name}",
                                    null,
                                )->make($entitlements_account_code, $detail->employee->acc4->code)
                                ->finish();
                        }

                        //bonuses
                        foreach ($detail->bonuses as $bonus) {

                            $accService
                                ->setUp(
                                    $op->id,
                                    now(),
                                    1,
                                    generate_double_entry_transaction_id(),
                                    $bonus->amount,
                                    $ex_rate,
                                    "Employee Bonus: {$bonus->name}, {$detail->employee->full_name}",
                                    "Employee Bonus: {$bonus->name}, {$detail->employee->full_name}",
                                    null,
                                )->make($bonuses_account_code, $detail->employee->acc4->code)
                                ->finish();

                        }

                        //penalties
                        foreach ($detail->penalties as $penalty) {

                            $accService
                                ->setUp(
                                    $op->id,
                                    now(),
                                    1,
                                    generate_double_entry_transaction_id(),
                                    $penalty->amount,
                                    $ex_rate,
                                    "Employee Penalty: {$penalty->description}, {$detail->employee->full_name}",
                                    "Employee Penalty: {$penalty->description}, {$detail->employee->full_name}",
                                    null,
                                )->make($penalties_account_code, $detail->employee->acc4->code)
                                ->finish();
                        }

                        //deductions
                        foreach ($detail->deductions as $deduction) {

                            $accService
                                ->setUp(
                                    $op->id,
                                    now(),
                                    1,
                                    generate_double_entry_transaction_id(),
                                    $deduction->amount,
                                    $ex_rate,
                                    "Employee Deduction: {$deduction->description}, {$detail->employee->full_name}",
                                    "Employee Deduction: {$deduction->description}, {$detail->employee->full_name}",
                                    null,
                                )->make($deductions_account_code, $detail->employee->acc4->code)
                                ->finish();
                        }
                    };
                    DB::commit();
                } catch (\Exception $exception) {
                    DB::rollBack();
                    report($exception);
                    throw new \Exception($exception->getMessage());
                }
            }

        }
    }
