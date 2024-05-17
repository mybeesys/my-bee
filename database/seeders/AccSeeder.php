<?php

    namespace Database\Seeders;

    use App\Models\Acc1;
    use App\Models\Acc2;
    use App\Models\Acc3;
    use App\Models\Acc4;
    use Illuminate\Database\Seeder;

    class AccSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            $this->acc1();
            $this->acc2();
            $this->acc3();
            $this->acc4();
        }

        public function acc1()
        {
            $data = [
                [
                    'code' => 1,
                    'name' => 'الاصول',
                    'normal' => 1,
                ],
                [
                    'code' => 2,
                    'name' => 'الخصوم',
                    'normal' => -1,
                ],
                [
                    'code' => 3,
                    'name' => 'حقوق الملكية',
                    'normal' => 1,
                ],
                [
                    'code' => 4,
                    'name' => 'الايرادات',
                    'normal' => 1,
                ],
                [

                    'code' => 5,
                    'name' => 'المصروفات',
                    'normal' => -1,
                ]
            ];


            foreach ($data as $item) {
                Acc1::firstOrCreate(['code' => $item['code'], 'name' => $item['name']], $item);
            }
        }

        public function acc2()
        {
            $data = array(
                array('code' => '11', 'acc1_code' => '1', 'name' => 'الاصول الثابتة'),
                array('code' => '12', 'acc1_code' => '1', 'name' => 'الاصول المتداولة'),
                array('code' => '21', 'acc1_code' => '2', 'name' => 'خصوم قصيرة الأجل (متداولة)'),
                array('code' => '22', 'acc1_code' => '2', 'name' => 'خصوم طويلة الأجل'),
                array('code' => '41', 'acc1_code' => '4', 'name' => 'إيرادات النشاط'),
                array('code' => '42', 'acc1_code' => '4', 'name' => 'إيرادات أخرى'),
                array('code' => '51', 'acc1_code' => '5', 'name' => 'مصروفات ادارية'),
                array('code' => '52', 'acc1_code' => '5', 'name' => 'مصروفات تشغيلية'),
                array('code' => '53', 'acc1_code' => '5', 'name' => 'مصروفات اخري')
            );

            foreach ($data as $item) {
                Acc2::firstOrCreate(
                    [
                        'acc1_code' => $item['acc1_code'],
                        'code' => $item['code'],
                        'name' => $item['name']
                    ], $item);
            }
        }

        public function acc3()
        {
            $data = array(
                array('code' => '1101', 'acc2_code' => '11', 'name' => 'أثاثات'),
                array('code' => '1102', 'acc2_code' => '11', 'name' => 'الاراضي'),
                array('code' => '1103', 'acc2_code' => '11', 'name' => 'مباني'),
                array('code' => '1104', 'acc2_code' => '11', 'name' => 'سيارات'),
                array('code' => '1201', 'acc2_code' => '12', 'name' => 'نقدية بالخزينة'),
                array('code' => '1202', 'acc2_code' => '12', 'name' => 'نقدية بالبنوك'),
                array('code' => '1203', 'acc2_code' => '12', 'name' => 'المدينون العملاء'),
                array('code' => '1204', 'acc2_code' => '12', 'name' => 'المخزون'),
                array('code' => '1205', 'acc2_code' => '12', 'name' => 'وسيط المخزون'),
                array('code' => '1206', 'acc2_code' => '12', 'name' => 'سندات القبض (شيكات تحت التحصيل)'),
                array('code' => '1207', 'acc2_code' => '12', 'name' => 'المدينون'),
                array('code' => '1208', 'acc2_code' => '12', 'name' => 'بنك الخرطوم - الشركة '),
                array('code' => '1209', 'acc2_code' => '12', 'name' => 'نقدية بالخزنة الرئيسية '),
                array('code' => '1210', 'acc2_code' => '12', 'name' => 'نقدية دولار '),
                array('code' => '1211', 'acc2_code' => '12', 'name' => 'نقدية درهم '),
                array('code' => '1212', 'acc2_code' => '12', 'name' => 'نقدية ريال '),
                array('code' => '1213', 'acc2_code' => '21', 'name' => 'المناديب'),
                array('code' => '1214', 'acc2_code' => '21', 'name' => 'الدائنون (الموردون)'),
                array('code' => '1215', 'acc2_code' => '21', 'name' => 'سندات الصرف (شيكات تحت التحصيل)'),
                array('code' => '1216', 'acc2_code' => '21', 'name' => 'رواتب مستحقة'),
                array('code' => '1217', 'acc2_code' => '21', 'name' => 'دائنون اخرون'),
                array('code' => '1218', 'acc2_code' => '41', 'name' => 'المبيعات'),
                array('code' => '1219', 'acc2_code' => '41', 'name' => 'مردودات المبيعات'),
                array('code' => '1220', 'acc2_code' => '42', 'name' => 'الإستقطاعات'),
                array('code' => '1221', 'acc2_code' => '41', 'name' => 'الخدمات'),
                array('code' => '1222', 'acc2_code' => '51', 'name' => 'مرتبات الموظفين والعمال'),
                array('code' => '1223', 'acc2_code' => '51', 'name' => 'مصروفات إدارية أخرى'),
                array('code' => '1224', 'acc2_code' => '52', 'name' => 'مصروفات تشغيلية أخرى'),
                array('code' => '1225', 'acc2_code' => '53', 'name' => 'تكلفة المبيعات'),
                array('code' => '1226', 'acc2_code' => '53', 'name' => 'المشتريات'),
                array('code' => '1227', 'acc2_code' => '12', 'name' => 'تحويل بالبنوك'),
            );


            foreach ($data as $item) {
                Acc3::firstOrCreate(
                    [
                        'code' => $item['code'],
                        'acc2_code' => $item['acc2_code'],
                        'name' => $item['name']
                    ], $item);
            }
        }

        public function acc4()
        {
            $data = array(
                array('code' => '120100001', 'acc3_code' => '1201', 'name' => 'الخزينة (ريال)'),
//                array('code' => '120100002', 'acc3_code' => '1201', 'name' => 'الخزينة (دولار)'),
                array('code' => '121800001', 'acc3_code' => '1218', 'name' => 'المبيعات'),
                array('code' => '121900001', 'acc3_code' => '1219', 'name' => 'مردودات المبيعات'),
//                array('code' => '122200001', 'acc3_code' => '1222', 'name' => 'مرتبات مستحقة'),
//                array('code' => '122300001', 'acc3_code' => '1223', 'name' => 'مصروفات المشاريع الإدارية'),
//                array('code' => '122400001', 'acc3_code' => '1224', 'name' => 'مصروفات المشاريع التشغيلية'),
//                array('code' => '122400002', 'acc3_code' => '1224', 'name' => 'مستحقات الموظفين'),
//                array('code' => '122400003', 'acc3_code' => '1224', 'name' => 'حوافز الموظفين'),
                array('code' => '122400004', 'acc3_code' => '1224', 'name' => 'مصروفات توصيل الطلبات'),
                array('code' => '122400005', 'acc3_code' => '1224', 'name' => 'مصروفات توصيل إضافية للطلبات'),
//                array('code' => '122000001', 'acc3_code' => '1220', 'name' => 'خصومات الموظفين'),
//                array('code' => '122000002', 'acc3_code' => '1220', 'name' => 'جزاءات الموظفين'),
                array('code' => '122500001', 'acc3_code' => '1225', 'name' => 'تكلفة المبيعات'),
                array('code' => '122500002', 'acc3_code' => '1225', 'name' => 'التكاليف الإضافية'),
                array('code' => '122600001', 'acc3_code' => '1226', 'name' => 'مشتريات'),
                array('code' => '122600002', 'acc3_code' => '1226', 'name' => 'تكاليف المشتريات'),
                array('code' => '122700001', 'acc3_code' => '1227', 'name' => 'تحويل بنكي (الراجحي 1)'),
            );

            foreach ($data as $item) {
                Acc4::firstOrCreate(
                    [
                        'acc3_code' => $item['acc3_code'],
                        'code' => $item['code'],
                    ], $item);
            }
        }
    }
