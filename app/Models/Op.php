<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;

    class Op extends BaseModel
    {
        use HasFactory;

        protected $table = 'op';

        protected $guarded = [];

        protected $casts = [
            'files' => 'array',
        ];

        public static function generate($op_type_id)
        {
            return Op::create(
                [
                    'op_type_id' => $op_type_id,
                    'user_id' => auth()->id(),
                    'no' => generate_op(),
                    'payment_voucher_no' => null,
                    'date' => now(),
                    'locked_at' => null,
                    'submitted_at' => null,
                    'files' => null,
                ]);
        }
    }
