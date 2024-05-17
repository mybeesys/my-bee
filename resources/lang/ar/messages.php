<?php

return [
    "api" => [
        "created" => "تمت الإضافة",
        "updated" => "تم الحفظ",
        "deleted" => "تم الحذف",
        "retrieved" => "تم جلب البيانات",
        'permission_denied' => 'ليس لديك صلاحية لإجراء هذة العملية',
        "max_records" => "لقد تجاوزت الحد المسموح به",
    ],

    'greeting' => "مرحباَ",
    'complete_registration_to_continue' => 'من فضلك قم بملء البيانات التالية قبل المتابعة',
    'complete_registration' => 'إكمال التسجيل',
    'add_new_activity' => 'إضافة نشاط جديد',
    'activities_limit_reached' => 'لقد تجاوزت عدد الأنشطة المسموح بها!',
    'activities_limit_reached_upgrade_plan_or_contact_support' => 'الرجاء ترقية الإشتراك أو التواصل مع الدعم الفني.',
    'ok' => 'حسناَ',

    'expected_min_words' => 'عدد كلمات حقل :attribute يجب ان تكون على الأقل :count كلمات',
    'max_words_reached' => 'عدد كلمات حقل :attribute يجب ان تكون أقل من :count كلمات',

    'full_name_min_words' => 'حقل الإسم الكامل يجب أن يكون مكون من إسمين على الأقل',
    'full_name_max_words' => 'لقد تجاوزت عدد الأسماء المسموح بها',

    'phone_required' => 'الرجاء إدخال رقم الهاتف',
    'email_required' => 'الرجاء إدخال البريد الإلكتروني',
    'phone_unique' => 'تم تسجيل حساب بهذا الهاتف مسبقاََ, حاول إسترجاع الحساب أو تواصل معنا.',
    'email_unique' => 'تم تسجيل حساب بهذا البريد مسبقا, حاول إسترجاع الحساب أو تواصل معنا.',
    'account_not_found' => 'لم يتم ايجاد الحساب, الرجاء التأكد من رقم الهاتف ',
    'phone_invalid' => 'الرجاء إدخال رقم هاتف صحيح',
    'email_invalid' => 'الرجاء إدخال بريد إلكتروني صحيح',
    'account_deactivated' => 'تم إيقاف الحساب, الرجاء الإتصال بالدعم الفني ',
    'account_activated' => 'تم إعادة تفعيل حسابك بنجاح',

    'first_name_required' => 'الرجاء إدخال الإسم',
    'first_name_min' => 'يجب أن لا يقل الإسم عن حرفان',
    'first_name_max' => 'يجب أن لا يتجاوز الإسم عشرين حرفاَ',


    'second_name_required' => 'الرجاء إدخال الإسم',
    'second_name_min' => 'يجب أن لا يقل الإسم عن حرفان',
    'second_name_max' => 'يجب أن لا يتجاوز الإسم عشرين حرفاَ',

    'third_name_required' => 'Please enter your third name',
    'third_name_min' => 'Third name must be at least 2 characters',
    'third_name_max' => 'Third must not have more than 2 characters',

    'last_name_required' => 'الرجاء إدخال إسم العائلة',
    'last_name_min' => 'يجب أن لا يقل إسم العائلة عن حرفان',
    'last_name_max' => 'يجب أن لا يتجاوز إسم العائلة عشرين حرفاَ',

    'dob_required' => 'الرجاء إدخال تاريخ الميلاد',
    'dob_before' => 'يجب أن لا يقل العمر عن عشر سنوات',

    'dont_share_code_alert' => 'code: is your code. never share this code with anyone, only use it on our app or website.',
    'invalid_otp' => 'الرمز الذي أدخلته غير صالح',
    'valid_otp' => 'تم تأكيد الرمز بنجاح',
    'max_tries_exceeded' => "لقد تجاوزت الحد المسموح به للمحاولات, اتصل بنا او حاول مجدداََ خلال 24 ساعة",

    '401' => "رجاءً قم بتسجيل الدخول",

    'notifications' => [
        'customer' => [
            'order_assigned' => 'Your Order :order Has Been Assigned To Driver',
            'order_batched' => 'Your Order :order Batched Now',
            'order_delivered' => 'Your Order :order Has Been Delivered',
            'order_cancelled' => 'Your Order :order Has Been Cancelled',
            'order_rescheduled' => 'Your Order :order Been Rescheduled To :date',
        ],
        'driver' => [
            'order_assigned' => 'The Order :order Has Been Assigned To You',
            'order_batched' => 'The Order :order Batched Now',
            'order_delivered' => 'The Order :order Has Been Delivered',
            'order_cancelled' => 'The Order :order Has Been Cancelled',
            'order_cancelled_by_admin' => 'The Order :order Has Been Cancelled By Admin',
            'order_rescheduled' => 'The Order :order Has Been Rescheduled To :date',
        ],
        'vendor' => [
            'order_assigned' => 'The Order :order Has Been Assigned To Driver',
            'order_batched' => 'The Order :order Batched Now',
            'order_delivered' => 'The Order :order Has Been Delivered',
            'order_cancelled' => 'The Order :order Has Been Cancelled',
            'order_cancelled_by_admin' => 'The Order :order Has Been Cancelled By Admin',
            'order_cancelled_by_customer' => 'The Order :order Has Been Cancelled By Customer Because Driver did not Responded',
            'order_rescheduled' => 'The Order :order Has Been Rescheduled To :date',
        ],
    ],


    'wallet_insufficient_funds' => 'ليس لديك رصيد كافي , رجاءً قم بشحن المحفظة وحاول مجدداً',

    'welcome_to_nour' => 'أهلاً بك في مجتمع نور!',
    'thanks' => 'شكراً لك لإستخدامك نور!',

    'welcome_consultant' => 'سيادة المستشار( الاسم الاول في خانة التسجيل )أهلاً بك في مجتمع شُورى ، يمكنك تحقيق الفائدة القصوى من خبراتك لصالح عملائك
سوف يتم تفعيل حسابك قريباً',

    'consultant_verified' => 'سيادة المستشار( user ) تم تفعيل حسابك يمكنك الآن تقديم عدد غير محدود من الإستشارات
للحصول على نتائج افضل شارك صفحتك مع عملائك المحتملين',

    'welcome_client' => 'عميلنا العزيز مرحباً بك في عائلة شُورى
يمكنك الآن الحصول على إستشارات الخبراء
لا نعلم ما تبحث عنه ولكن سوف تجد من يقدم لك الرأي السديد',

    'contact_us_thank_you' => 'شكراً لك لإستخدامك نور! سنعاود الاتصال بك في أقرب وقت ممكن.',

    'you_have_new_instant_consultation_request' => 'لديك طلب إستشارة فورية جديد',

    'app_name' => 'نور',
];
