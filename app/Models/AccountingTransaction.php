<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AccountingTransaction extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function makeTransaction($amount,
                                           $from_account,
                                           $to_account,
                                           $description_credit,
                                           $description_debit,
                                           $credit_owner_id,
                                           $credit_owner_type,
                                           $debit_owner_id,
                                           $debit_owner_type,
                                           $meta_credit = null,
                                           $meta_debit = null)
    {

        $reference = Str::random(6);

        $credit = self::create(
            [
                'reference' => $reference,
                'account_credit' => $from_account,
                'account_debit' => $to_account,
                'credit' => -$amount,
                'debit' => null,
                'description' => $description_credit,
                'meta' => $meta_credit,
                'credit_owner_id' => $credit_owner_id,
                'credit_owner_type' => $credit_owner_type,
                'debit_owner_id' => $debit_owner_id,
                'debit_owner_type' => $debit_owner_type,
            ]
        );

        $debit = self::create(
            [
                'reference' => $reference,
                'account_credit' => $from_account,
                'account_debit' => $to_account,
                'credit' => null,
                'debit' => $amount,
                'description' => $description_debit,
                'meta' => $meta_debit,
                'credit_owner_id' => $credit_owner_id,
                'credit_owner_type' => $credit_owner_type,
                'debit_owner_id' => $debit_owner_id,
                'debit_owner_type' => $debit_owner_type,
            ]
        );

        return [
            'credit' => $credit,
            'debit' => $debit,
        ];
    }
}
