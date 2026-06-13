<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;

    class CashDet extends BaseModel
    {
        use HasFactory;

        protected $table = "cash_det";

        protected $guarded = [];

        protected $casts = [
            'date' => 'datetime',
            'meta' => 'array',
        ];

        public function currency()
        {
            return $this->belongsTo(Currency::class);
        }

        public function operation()
        {
            return $this->belongsTo(Op::class, 'op_id');
        }

        public function invoice()
        {
            return $this->belongsTo(Invoice::class);
        }

        public function account()
        {
            return $this->belongsTo(Acc4::class, 'account_code', 'code');
        }

        public static function makeTransaction($op_id, $currency_iso_code, $transaction_id, $account_code, $amount_in,
                                               $amount_out, $date, $statement, $exchange_rate, $invoice_id = null, $meta = null): CashDet
        {
            if($amount_in > 0 and $amount_out > 0)
                throw new \Exception("Invalid transaction amount");

            $balance_pre_transaction = null;
            $balance_post_transaction = null;
            $cashDets = CashDet::where('account_code', $account_code)->get();
            //calc balance
            if($amount_in > 0){ //debit
                $balance_pre_transaction = $cashDets->sum('amount_in') - $cashDets->sum('amount_out');
                $balance_post_transaction = $balance_pre_transaction + $amount_in;
            }else{ //credit
                $balance_pre_transaction = $cashDets->sum('amount_in') - $cashDets->sum('amount_out');
                $balance_post_transaction = $balance_pre_transaction - $amount_out;
            }

            $transaction = CashDet::create(
                [
                    'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
                    'op_id' => $op_id,
                    'currency_iso_code' => $currency_iso_code,
                    'transaction_id' => $transaction_id,
                    'account_code' => $account_code,
                    'amount_in' => $amount_in,
                    'amount_out' => $amount_out,
                    'balance_pre_transaction' => $balance_pre_transaction,
                    'balance_post_transaction' => $balance_post_transaction,
                    'date' => $date,
                    'statement' => $statement,
                    'exchange_rate' => $exchange_rate,
                    'invoice_id' => $invoice_id,
                    'meta' => $meta
                ]
            );
            return $transaction;
        }
    }
