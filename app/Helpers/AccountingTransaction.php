<?php


    namespace App\Helpers;


    class AccountingTransaction
    {
        // credit amount out, debit amount in

        public $currency_id = null, $credit_account_id = null,
            $debit_account_id = null, $date = null, $statement = null;

        public function __construct(int $currency_id, $credit_account_id, $debit_account_id, $date, $statement)
        {
            $this->currency_id = $currency_id;
            $this->credit_account_id = $credit_account_id; //amount out مدين
            $this->debit_account_id = $debit_account_id; //amount in    داين
            $this->date = $date;
            $this->statement = $statement;

            $valid = ($this->currency_id and $this->debit_account_id and $this->credit_account_id and $this->date and $this->statement);

            if (!$valid)
                throw new \Exception("Invalid accounting transaction");
        }


        /* lets test it.
          buy a new laptop for your company: price 1000 usd.

        transaction: credit account = cash  -1000   debit account = assets +10000

        that`s it.
         */

    }