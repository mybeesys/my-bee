<?php

    namespace Database\Seeders;

    use App\Models\InvoiceStatus;
    use Illuminate\Database\Seeder;

    class InvoiceStatusSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            $data = [
                [
                    'type' => 'purchases',
                    'name' => [
                        'en' => 'Pending',
                        'ar' => 'تحت الإنشاء',
                    ],
                ],
                [
                    'type' => 'purchases',
                    'name' => [
                        'en' => 'No Receipt permission was created',
                        'ar' => 'لم يتم إنشاء إذن الإستلام',
                    ],
                ],
                [
                    'type' => 'purchases',
                    'name' => [
                        'en' => 'Receipt permission created',
                        'ar' => 'تم إنشاء إذن الإستلام',
                    ],
                ],
                [
                    'type' => 'sales',
                    'name' => [
                        'en' => 'Initial invoice',
                        'ar' => 'فاتورة مبدئية',
                    ],
                ],

                [
                    'type' => 'sales',
                    'name' => [
                        'en' => 'Pending payment',
                        'ar' => 'في إنتظار عملية السداد',
                    ],
                ],

                [
                    'type' => 'sales',
                    'name' => [
                        'en' => 'Payment has been confirmed',
                        'ar' => 'تم تأكيد عملية السداد',
                    ],
                ]
            ];

            foreach ($data as $item) {
                InvoiceStatus::firstOrCreate(['name->en' => $item['name']['en']], $item);
            }
        }
    }
