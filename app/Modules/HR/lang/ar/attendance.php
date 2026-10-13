<?php

return ['statuses' => [
    'unscheduled' => 'لم يحدد جدول',
    'off_day' => 'يوم راحة',
    'outside_employment' => 'خارج فترة العمل',
    'pending' => 'بانتظار نهاية الوردية',
    'absence_candidate' => 'غياب محتمل — يحتاج مراجعة',
    'present' => 'حضور مكتمل',
    'working' => 'على رأس العمل',
    'missing_checkout' => 'انصراف مفقود',
    'missing_checkin' => 'حضور مفقود',
    'missing_session' => 'وردية غير مكتملة',
    'legacy_present' => 'حاضر — السجل القديم',
    'legacy_absent' => 'غائب — السجل القديم',
    'conflict' => 'تعارض المصادر',
    'holiday' => 'عطلة رسمية',
    'leave' => 'إجازة معتمدة للحضور',
    'excused_absence' => 'غياب بعذر معتمد',
    'off_day_worked' => 'عمل في يوم راحة أو عطلة',
    'approved_present' => 'حضور مصحح ومعتمد — الأوقات غير معلومة',
    'approved_absent' => 'غياب مصحح ومعتمد',
], 'actions' => [
    'employee' => ['mapped' => 'إنشاء هوية الحضور'],
    'schedule' => ['created' => 'إنشاء إصدار جدول العمل', 'exception_recorded' => 'تسجيل استثناء معتمد'],
    'shift' => ['assigned' => 'إسناد وردية', 'retired' => 'تسجيل إنهاء تكليف الوردية'],
    'holiday' => ['created' => 'تسجيل عطلة رسمية', 'cancelled' => 'تسجيل إلغاء عطلة'],
    'attendance' => [
        'simulated' => 'حدث حضور في بيئة الاختبار', 'correction_requested' => 'طلب تصحيح الحضور',
        'correction_approved' => 'اعتماد تصحيح الحضور', 'correction_rejected' => 'رفض تصحيح الحضور',
        'reconciled' => 'تسوية ملخص الحضور', 'view_permission_granted' => 'منح صلاحية عرض الحضور',
    ],
]];
