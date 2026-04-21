<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالحًا.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'max' => [
        'string' => 'يجب ألا يزيد :attribute عن :max حرفًا.',
        'file' => 'يجب ألا يزيد :attribute عن :max كيلوبايت.',
    ],
    'mimes' => 'يجب أن يكون :attribute من نوع: :values.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'exists' => ':attribute المحدد غير صالح.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عناصر على الأقل.',
    ],
    'in' => ':attribute المحدد غير صالح.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'attributes' => [
        'name' => 'الاسم',
        'name_en' => 'الاسم بالإنجليزية',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الجوال',
        'address' => 'العنوان',
        'avatar' => 'الصورة الشخصية',
        'roles' => 'الأدوار',
        'roles.*' => 'الدور',
        'permissions' => 'الصلاحيات',
        'permissions.*' => 'الصلاحية',
        'id_no' => 'رقم الهوية',
        'contract_type' => 'نوع العقد',
        'region' => 'المنطقة',
        'password' => 'كلمة المرور',
    ],
];
