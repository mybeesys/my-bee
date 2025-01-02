<?php


namespace App\Services;


use App\Helpers\AccountingTransaction;
use App\Models\Acc4;
use App\Models\CashDet;
use App\Models\Currency;
use App\Models\Op;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AccountingService
{

    public $op_id, $date, $currency_iso_code, $transaction_id, $amount,
        $credit_account_code, $debit_account_code, $exchange_rate, $invoice_id,
        $credit_statement, $debit_statement;

    public $cash_det_credit;
    public $cash_det_debit;

    public $meta;

    public $debug;

    public function debug($debug = true): self
    {
        $this->debug = $debug;
        return $this;
    }
    public function createAcc4AccountForItem(Model $model): mixed
    {
        //create if not exists

        if (!$model->financeAttributesReady())
            throw new \Exception("Model finance attributes not implemented");

        $finance = $model->getFinanceAttributes();

        $name = $model->{$finance['name']};

        $acc3_code = $model->finance['acc3_code'];

        $model->loadMissing('acc4');

        if ($model->acc4)
            return false;

        $last = Acc4::where('code', 'LIKE', "%$acc3_code%")->get()->last();

        $new_code = $last == null ? $acc3_code . "000001" : $last->code + 1;


        return Acc4::create(
            [
                'tenant_id' => $model->tenant_id,
                'item_id' => $model->id,
                'item_type' => get_class($model),
                'acc3_code' => $acc3_code,
                'code' => $new_code,
                'name' => $name,
            ]
        );
    }

    private function reset()
    {
        $this->op_id = null;
        $this->date = null;
        $this->currency_iso_code = null;
        $this->transaction_id = null;
        $this->amount = null;
        $this->credit_account_code = null;
        $this->debit_account_code = null;
        $this->exchange_rate = null;
        $this->invoice_id = null;
        $this->credit_statement = null;
        $this->debit_statement = null;
        $this->cash_det_credit = null;
        $this->cash_det_debit = null;
        $this->meta = null;

        return $this;
    }

    private function validate()
    {

//        optional
//        $this->exchange_rate

        //invoice_id is nullable

        $valid = ($this->date and $this->op_id and $this->currency_iso_code
            and $this->transaction_id and $this->amount and $this->amount > 0
            and $this->credit_account_code and $this->credit_statement and $this->debit_statement);

        if (!$valid) {
            $cause = "unknown";

            if(!$this->date)
                $cause = "date";
            if(!$this->op_id)
                $cause = "op_id";
            if(!$this->currency_iso_code)
                $cause = "currency_iso_code";
            if(!$this->transaction_id)
                $cause = "transaction_id";
            if(!$this->amount > 0)
                $cause = "amount must be greater than 0";
            if(!$this->credit_account_code)
                $cause = "credit_account_code";
            if(!$this->credit_statement)
                $cause = "credit_statement";
            if(!$this->debit_statement)
                $cause = "debit_statement";


//            dd($this->date , $this->op_id , $this->currency_iso_code
//                , $this->transaction_id , $this->amount , $this->amount > 0
//                , $this->credit_account_code , $this->credit_statement , $this->debit_statement);
            throw new \Exception("Invalid transaction: ". $cause.", ".$this->credit_statement);
        }

        if (Acc4::find($this->credit_account_code) == null) {
            throw new \Exception("Account $this->credit_account_code does not exist");
        }

//            if (Acc4::find($this->debit_account_code) == null) {
//                throw new \Exception("Account $this->debit_account_code does not exist");
//            }

        if (Currency::firstWhere('iso_code', $this->currency_iso_code) == null) {
            throw new \Exception("Currency $this->currency_iso_code does not exist");
        }

    }


    public function setUp($op_id, $date, $currency_iso_code, $transaction_id, $amount,
                          $exchange_rate, $credit_statement, $debit_statement, $invoice_id = null, $meta = null)
    {
        $this->op_id = $op_id;
        $this->date = $date;
        $this->currency_iso_code = $currency_iso_code;
        $this->transaction_id = $transaction_id;
        $this->amount = $amount;
        $this->exchange_rate = $exchange_rate;
        $this->credit_statement = $credit_statement;
        $this->debit_statement = $debit_statement;
        $this->invoice_id = $invoice_id;
        $this->meta = $meta;

        return $this;
    }

    public function make($credit_account_code, $debit_account_code)
    {
        $this->credit_account_code = $credit_account_code;
        $this->debit_account_code = $debit_account_code;

        $this->validate();

        $this->cash_det_credit = CashDet::makeTransaction(
            $this->op_id,
            $this->currency_iso_code,
            $this->transaction_id,
            $this->credit_account_code,
            0,
            $this->amount,
            $this->date,
            $this->credit_statement,
            $this->exchange_rate,
            $this->invoice_id,
            $this->meta,
        );

        if ($this->debit_account_code) {
            $this->cash_det_debit = CashDet::makeTransaction(
                $this->op_id,
                $this->currency_iso_code,
                $this->transaction_id,
                $this->debit_account_code,
                $this->amount,
                0,
                $this->date,
                $this->debit_statement,
                $this->exchange_rate,
                $this->invoice_id,
                $this->meta,
            );

        }


        return $this;
    }

    public function makeMultiple(array $accounts)
    {
        //example

        /*
         *
         * [
         *   [
         *     'credit_account_code' => '',
         *     'debit_account_code' => '',
         *      'currency' => '',
         *   ]
         * ]
         *
         *
         *
         *
         */
    }

    public function finish()
    {
        $this->reset();
    }

}
