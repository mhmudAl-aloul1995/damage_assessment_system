DROP TABLE IF EXISTS `assessments`;

CREATE TABLE
    `assessments` (
        id INT PRIMARY KEY AUTO_INCREMENT,
        `name` VARCHAR(512),
        `label` VARCHAR(512),
        `hint` VARCHAR(512)
    );

INSERT INTO
    `assessments` (`name`, `label`, `hint`)
VALUES
    ('location', ' حدود المبنى', ' '),
    ('field_status', 'Assessment Status', 'حالة الإستبيان'),
    (
        'building_committee_status',
       'حالة لجنة المبنى',
        'حالة لجنة المبنى'
    ),
    (
        'unit_committee_status',
        'حالة لجنة الوحدة السكانية',
        'حالة لجنة الوحدة السكانية'
    ),
    (
        'unit_committee_count',
         'عدد الوحدات السكانية',
        'عدد الوحدات السكانية'
    ),
    ('parcel_no1', 'رقم القسيمة', ' '),
    ('block_no1', 'رقم القطعة', ' '),
    ('owner_na', 'القطعة/ قسيمة', ' '),
    ('units_count', 'عدد الوحدات', ' '),
    ('assignedto', 'اسم الباحث', ' '),
    ('groupnumber', 'رقم المجموعة', ' '),
    ('zone_code', 'رقم الزون', ' '),
    ('objectid', 'objectid', 'رقم المبنى'),
    ('start', 'start', ' '),
    ('end', 'end', ' '),
    ('today', 'today', ' '),
    ('username', 'username', ' '),
    ('simserial', 'simserial', ' '),
    ('subscriberid', 'subscriberid', ' '),
    ('deviceid', 'deviceid', ' '),
    ('phonenumber', 'phonenumber', ' '),
    ('audit', 'audit', ' '),
    ('instructions', 'التعليمات', ' '),
    (
        'note01',
        'عزيزي المهندس: برجاء قراءة التعليمات التالية بعناية والتقيد بها.',
        ' '
    ),
    (
        'note02',
        '1) الاستمارة الواحدة تمثل بيانات مبنى واحد متضرر جزئيا مع بيانات وحداته السكنية والغير سكنية.',
        ' '
    ),
    (
        'note03',
        '2) في حال كان المبنى يحتوي على عدد كبير من الوحدات مثل الأبراج، ينصح بفتح استمارة جديدة لتكملة الوحدات المتبقية.',
        ' '
    ),
    (
        'note04',
        '3) في حال فتحت استمارة جديدة لتكملة الوحدات المتبقية لنفس المبنى، يجب عليك استخدام **نفس رقم المبنى واسم المبنى** كما في الاستمارات السابقة، حتى يكون من السهل الربط بين الاستمارتين.',
        ' '
    ),
    (
        'note05',
        '4) يجب التأكد أن دقة gps أقل أو يساوي 7 م، وإلا يجب استخدام المرئية الفضائية لتعليم الإحداثيات عليها يدويا.',
        ' '
    ),
    (
        'note06',
        '5) يجب التقاط صور معبرة توضح الأضرار أو أي معلومات أخرى.',
        ' '
    ),
    (
        'note07',
        '6) ينصح استخدام انترنت سريع لإرسال الاستمارات، كونها تحتوي على صور، وبالتالي ربما تأخذ بعض الوقت للإرسال.',
        ' '
    ),
    (
        'note08',
        '7) يوصى بإرسال استمارة واحدة في المرة الواحدة. ',
        ' '
    ),
    (
        'note09',
        '8) إدخال البيانات يجب أن يكون باللغة العربية',
        ' '
    ),
    ('g0', 'introduction', ' '),
    ('weather', 'weather', 'حال الطقس '),
    (
        'security_situation',
        'security situation',
        'هل يوجد عائق يمنع عملية الحصر  '
    ),
    (
        'security_info',
        'security information',
        'ما هو العائق '
    ),
    ('g1', 'current damage status', ' '),
    (
        'building_damage_status',
        'what is the current damage status of the building?',
        'ما هي حالة الضرر الحالية للمبنى؟ '
    ),
    (
        'building_information',
        'building information',
        'معلومات المبنى '
    ),
    (
        'bldng_introduction',
        '1. introduction',
        '1. مقدمة '
    ),
    (
        'building_type',
        '1.1 building type',
        'نوع المبنى '
    ),
    (
        'building_type_other',
        '1.1.1  building type',
        'اكتب نوع المبنى '
    ),
    (
        'building_use',
        '1.2 building use',
        'نوع استخدام المبنى '
    ),
    (
        'building_name',
        '1.3 building name',
        'اسم المبنى. مثال: برج فلسطين '
    ),
    (
        'date_of_damage',
        '1.4 date of damage',
        'تاريخ القصف/الضرر '
    ),
    (
        'building_material',
        '1.5 type/material of building',
        'نوع مادة المبنى '
    ),
    (
        'other_material',
        '1.5.1 if other, specify:',
        'إذا تم اختيار آخر/أخرى في السؤال السابق، وضح الإجابة '
    ),
    (
        'building_age',
        '1.6 how old is  the building?',
        'كم هو عمر المبنى؟ '
    ),
    (
        'floor_nos',
        '1.7 number of floors',
        'عدد الطوابق '
    ),
    (
        'ground_floor_area__m2',
        '1.8 ground floor area (m2)',
        'مساحة الطابق الأرضي (م2)  '
    ),
    (
        'floor_area_m2',
        '1.9 floor area (m2)',
        'مساحة الطابق المتكرر '
    ),
    (
        'units_nos',
        '1.10 number of units',
        'عدد الوحدات '
    ),
    (
        'damaged_units_nos',
        '1.11 number of targeted damaged units',
        'عدد الوحدات المتضررة '
    ),
    (
        'occupied_units_nos',
        '1.12 number of occupied units',
        'عدد الوحدات المأهولة '
    ),
    (
        'vacant_units_nos',
        '1.13 number of vacant units',
        'عدد الوحدات الفارغة '
    ),
    (
        'is_damaged_before',
        '1.14 prior to the event, did the building have some damage due to previous conflicts or other reasons?',
        'هل تضرر المبنى قبل الأحداث الحالية بسبب صراع آخر أو أسباب أخرى؟ '
    ),
    (
        'if_damaged',
        '1.14.1 if yes, specify when and how?',
        'إذا تضرر المبنى قبل ذلك، حدد متى وكيف؟ '
    ),
    (
        'building_debris_exist',
        '1.15 is there debris generated at the building level?',
        'هل يوجد ركام ناتج عن الضرر في المبنى؟ '
    ),
    (
        'building_debris_qty',
        '1.15.1 estimated amount of debris at building level',
        'تقدير كمية الركام في المبنى '
    ),
    (
        'building_debris_blocking',
        '1.15.2 does debris obstruct access or use of the building?',
        'هل الركام يعيق الوصول أو الاستخدام؟ '
    ),
    (
        'uxo_present',
        '1.16 are there any uxo present?',
        'هل يوجد مخلفات حربية / ذخائر غير منفجرة داخل المبنى؟ '
    ),
    (
        'bodies_present',
        '1.17 are there any bodies in the building?',
        'هل يوجد جثث داخل المبنى؟ '
    ),
    (
        'estimated_number_of_bodies',
        '1.17.1 estimated number of bodies',
        'عدد الجثث (تقديري) '
    ),
    (
        'building_status_visit',
        '1.18 building status at the time of visit',
        'حالة المبنى وقت المعاينة '
    ),
    (
        'building_roof_type',
        '1.19 building roof type',
        'نوع سطح المبنى '
    ),
    (
        'clay_tile_area',
        '1.19.1 clay tile roof area (m2)',
        'مساحة القرميد (م2) '
    ),
    (
        'concrete_area',
        '1.19.2 concrete roof area (m2)',
        'مساحة الباطون (م2) '
    ),
    (
        'aspestos_area',
        '1.19.3 aspestos roof area (m2)',
        'مساحة الاسبست (م2) '
    ),
    (
        'scorite_area',
        '1.19.4 scorite roof area (m2)',
        'مساحة الصاج (م2) '
    ),
    (
        'other_roof',
        '1.19.5 if other, specify roof type',
        'في حال أخرى، حدد نوع السقف '
    ),
    (
        'other_roof_area',
        '1.19.6 specify other roof area (m2)',
        'في حال أخرى،  حدد المساحة (م2) '
    ),
    ('', '', ' '),
    (
        'ownweship_information',
        '2. ownership information',
        ' 2. معلومات عن الملكية '
    ),
    (
        'ownweshipbldng_introduction',
        '2.1 introduction',
        '2.1 معلومات عن ملكية المبنى '
    ),
    (
        'building_ownership',
        '2.1.1 building ownership',
        'اختر نوع الملكية '
    ),
    (
        'owner_status',
        '2.1.2 what is the status of the property owner?',
        'ما هو وضع مالك العقار؟ '
    ),
    (
        'building_responsible',
        '2.1.3 who is responsible for the building?',
        'من المسؤول عن البناية؟ '
    ),
    (
        'building_authorization',
        '2.1.4 do you have authorization/delegation to manage the building and request compensation (e.g., power of attorney)?',
        'هل لديك تفويض بإدارة المبنى وطلب التعويضات؟ '
    ),
    (
        'land_fully_owned',
        '2.1.5 is the land fully owned?',
        'هل الأرض مملوكة بالكامل؟ '
    ),
    (
        'owner_name',
        '2.1.6 owner full name ',
        'اسم المالك '
    ),
    (
        'owner_id',
        '2.1.7 owner id number',
        'رقم هوية المالك '
    ),
    (
        'owner_mobile',
        '2.1.8 owner mobile number',
        'رقم جوال المالك  '
    ),
    (
        'board1_name',
        '2.1.9 board member 1 full name  ',
        'إسم رئيس مجلس الإادارة  '
    ),
    (
        'board1_id',
        '2.1.10 board member 1 id number',
        'رقم الهاتف المحمول '
    ),
    (
        'board1_number',
        '2.1.11 board member 2 id number',
        'رقم الهوية '
    ),
    (
        'board2_name',
        '2.1.12 board member 2 full name  ',
        'إسم نائب مجلس الإادارة  '
    ),
    (
        'board2_id',
        '2.1.13 board member 2 id number',
        'رقم الهاتف المحمول '
    ),
    (
        'board2_number',
        '2.1.14 board member 2 id number',
        'رقم الهوية '
    ),
    (
        'has_authorization_if_not_owner',
        '2.1.15 if not fully owned, do you have authorization/delegation to manage the property (e.g., power of attorney)?',
        'إذا لم تكن الملكية كاملة، هل لديك تفويض/وكالة لإدارة العقار؟ '
    ),
    (
        'authorization_details',
        '2.1.16 please specify the authorization (optional)',
        'يرجى توضيح نوع التفويض (اختياري) '
    ),
    (
        'is_rented',
        '2.1.17 is the property rented?',
        'هل العقار مستأجر بالكامل؟ '
    ),
    (
        'tenant_names',
        '2.1.18 tenant names (optional)',
        'أسماء المستأجرين (اختياري) '
    ),
    (
        'agreement_type',
        '2.1.19 type of agreement',
        'نوع الاتفاق '
    ),
    (
        'agreement_duration',
        '2.1.20 agreement duration',
        'مدة الاتفاق '
    ),
    ('documents', '2.2 documents', '2.2 الوثائق '),
    (
        'has_documents',
        '2.2.1 are ownership documents available?',
        'هل تتوفر مستندات تثبت الملكية؟ '
    ),
    (
        'doc_types_available',
        '2.2.2 which documents are available?',
        'ما هي المستندات المتوفرة؟ '
    ),
    (
        'doc_types_other',
        '2.2.3 please specify other documents',
        'يرجى تحديد مستندات أخرى '
    ),
    (
        'no_documents_reason',
        '2.2.4 if no, briefly explain why (optional)',
        'إذا لا، وضّح السبب (اختياري) '
    ),
    (
        'need_renew_docs',
        '2.2.5 do you need to renew or obtain documents?',
        'هل تحتاج إلى تجديد أو استخراج مستندات؟ '
    ),
    (
        'doc_challenges',
        '2.2.6 main challenges (if any)',
        'التحديات الرئيسية (إن وجدت) '
    ),
    (
        'doc_challenges_other',
        '2.2.7 please specify (other)',
        'يرجى التحديد (أخرى) '
    ),
    ('disputes', '2.3 disputes', '2.3 النزاعات '),
    (
        'has_dispute',
        '2.3.1 are there any known disputes?',
        'هل توجد نزاعات معروفة على العقار أو الأرض؟ '
    ),
    (
        'dispute_types',
        '2.3.2 type of dispute',
        'نوع النزاع '
    ),
    (
        'dispute_other',
        '2.3.3 please specify other dispute',
        'يرجى تحديد نزاع آخر '
    ),
    (
        'building_attachment',
        '3. building attachments',
        '3. مرفقات المبنى '
    ),
    (
        'attach_one_photo_for_each_of_the_following_documents',
        '410',
        'یرفق ما أمكن صورة عن كل من المستندات التالیة '
    ),
    (
        'select_document',
        '3.3 select one or more of the following documents to be attached',
        'اختر المستندات المرفقة '
    ),
    (
        'id_number_photo',
        '3.4 id number',
        'صورة الهوية '
    ),
    (
        'land_ownership_photo',
        '3.5 land ownership',
        'صورة إثبات ملكية الأرض  '
    ),
    (
        'municipal_permit_photo',
        '3.6 municipal permit',
        'صورة رخصة البلدية '
    ),
    (
        'other_documents_photo',
        '3.7 other documents',
        'صورة مستندات أخرى '
    ),
    (
        'building_services',
        '4. building services',
        '4. خدمات العمارة '
    ),
    (
        'has_elevator',
        '4.1 is there an elevator?',
        'هل يوجد مصعد في العمارة/ البناية؟ '
    ),
    (
        'elevator_number',
        '4.2 number of elevators',
        'عدد المصاعد '
    ),
    (
        'elevator_status',
        '4.2.1 elevator condition',
        'حالة المصعد '
    ),
    (
        'elevator_box',
        '4.2.2 cabin condition',
        'حالة الكابينة '
    ),
    (
        'elevator_motor',
        '4.2.3 motor condition',
        'حالة الماتور '
    ),
    (
        'has_solar',
        '4.3 is there a solar energy system?',
        'هل يوجد نظام طاقة شمسية في البناية؟ '
    ),
    (
        'solar_damage_status',
        '4.3.1 solar system condition',
        'حالة نظام الطاقة الشمسية '
    ),
    (
        'has_well',
        '4.4 is there a well and submersible pump system?',
        'هل يوجد بئر مياه ونظام غاطس؟ '
    ),
    (
        'well_damage_status',
        '4.4.1 well system condition',
        'حالة نظام البئر/الغاطس '
    ),
    (
        'has_fence',
        '4.5 is there a building fence?',
        'هل يوجد سور للبناية؟ '
    ),
    (
        'fence_damage_status',
        '4.5.1 fence condition',
        'حالة السور '
    ),
    (
        'fence_length',
        '4.5.2 fence length',
        'طول السور (متر) '
    ),
    (
        'has_electric_room',
        '4.6 is there an electrical room?',
        'هل توجد غرفة كهرباء؟ '
    ),
    (
        'electric_room_damage_status',
        '4.6.1 electrical room condition',
        'حالة غرفة الكهرباء '
    ),
    (
        'has_sewage',
        '4.7 is there a sewage system?',
        'هل يوجد نظام صرف صحي؟ '
    ),
    (
        'sewage_damage_status',
        '4.7.1 sewage system condition',
        'حالة الصرف الصحي '
    ),
    (
        'service_ownership',
        '4.8 service ownership',
        'اختر ملكية الخدامات '
    ),
    (
        'service_ownership_name',
        '4.8.1 service ownership name',
        'ما اسم مالك الخدمات '
    ),
    (
        'has_other_service',
        '4.9 other building service?',
        'خدمة أخرى خاصة بالعمارة؟ '
    ),
    (
        'other_service_details',
        '4.9.1 specify the service and damage (if any)',
        'حدّد المرفق ونوع الضرر إن وجد '
    ),
    (
        'building_services_notes',
        '4.10 comments on building services',
        'ملاحظات حول خدمات العمارة '
    ),
    (
        'bldng_accessories',
        '5. building accessories',
        '5. ملحقات المبنى '
    ),
    (
        'staircase_status',
        '5.1 staircase condition',
        'حالة الدرج '
    ),
    (
        'staircase_widt',
        '5.1.1 staircase width (meters)',
        'عرض الدرج (متر) '
    ),
    (
        'has_parking',
        '5.2 is there a garage/parking?',
        'هل يوجد كراج؟ '
    ),
    (
        'parking_status',
        '5.2.1 garage condition',
        'حالة الكراج '
    ),
    (
        'garage_area',
        '5.2.2 garage area (m²)',
        'مساحة الكراج (م²) '
    ),
    ('garage_type', '5.2.3 garage type', 'نوع الكراج '),
    (
        'has_canopy',
        '5.3 is there a canopy?',
        'هل توجد مظلة؟ '
    ),
    (
        'canopy_status',
        '5.3.1 canopy condition',
        'حالة المظلة '
    ),
    (
        'carport_length',
        '5.3.2 canopy length (m)',
        'طول المظلة (م) '
    ),
    (
        'carport_width',
        '5.3.3 canopy width (m)',
        'عرض المظلة (م) '
    ),
    (
        'carport_height',
        '5.3.4 canopy height (m)',
        'ارتفاع المظلة (م) '
    ),
    (
        'has_basement',
        '5.4 is there a basement?',
        'هل يوجد بدروم؟ '
    ),
    (
        'basement_status',
        '5.4.1 basement condition',
        'حالة البدروم '
    ),
    (
        'basement_area',
        '5.4.2 basement area (m²)',
        'مساحة البدروم (م²) '
    ),
    (
        'has_mezzanine',
        '5.5 is there a mezzanine?',
        'هل توجد سدة؟ '
    ),
    (
        'mezzanine_status',
        '5.5.1 mezzanine condition',
        'حالة السدة '
    ),
    (
        'roof_terrace_area',
        '5.5.2 roof terrace area (m²)',
        'مساحة السدة (م²) '
    ),
    (
        'bldng_engineer_comments',
        '6. engineer comments',
        '6. ملاحظات المهندس '
    ),
    (
        'comments_recommendations',
        '6.1  comments & recommendations',
        'اشرح بشكل عام مواصفات المبنى وسجل أي ملاحظات أخرى '
    ),
    (
        'building_image',
        '6.2  take picture for the whole building',
        'خذ صورة عامة لكل المبنى '
    ),
    (
        'building_image2',
        '6.3  take picture for the whole building',
        'خذ صورة عامة أخرى لكل المبنى '
    ),
    (
        'break01_note',
        'the following page will ask you if you want to add a damaged unit. please click "add" to add information for a new damaged unit in this building.',
        'تنويه: في الخانة التالية، قم بتعبئة بيانات الوحدات المتضررة في هذا المبنى. عندما تنتهي من إضافة بيانات الوحدة ، قم بالضغط على إشارة +  لإضافة وحدة سكنية. كرر العملية عند اللزوم وذلك حسب عدد الوحدات المتضررة في المبنى. '
    ),
    ('housing_unit', 'housing unit وحدة سكنية', ' '),
    (
        'housing_unit_group',
        '7. unit introduction',
        '7. الوحدة السكنية '
    ),
    (
        'housing_unit_type',
        '7.1 housing unit type',
        'نوع الوحدة السكنية '
    ),
    (
        'unit_damage_status',
        '7.2 unit damage status',
        'حالة الضرر '
    ),
    (
        'page8',
        '8. unit information',
        '8. معلومات الوحدة السكنية '
    ),
    (
        'floor_number',
        '8.1  floor number',
        'حدد رقم الطابق الذي يوجد فيها الوحدة المتضررة '
    ),
    (
        'housing_unit_number',
        '8.2 housing unit number',
        ' رقم الوحدة السكنية '
    ),
    (
        'unit_direction',
        '8.3 specify the direction of the damaged unit',
        ' حدد اتجاه الوحدة المتضررة '
    ),
    (
        'damaged_area_m2',
        '8.4 damaged unit area - m2',
        'مساحة الوحدة المتضررة  '
    ),
    (
        'infra_type2',
        '8.5 unit use',
        'نوع استخدام الوحدة المتضررة '
    ),
    (
        'house_unit_ownership',
        '8.6  house unit ownership',
        'نوع ملكية الوحدة المتضررة '
    ),
    (
        'other_ownership',
        '8.7  if other, specify:',
        'إذا تم اختيار آخر/أخرى في السؤال السابق، وضح الإجابة '
    ),
    (
        'occupied',
        '8.8  occupied before conflict?',
        'هل كانت الوحدة المتضررة مشغولة قبل الحدث/القصف؟ '
    ),
    (
        'number_of_rooms',
        '8.9 number of rooms',
        ' عدد الغرف '
    ),
    (
        'page9',
        '9. household and unit information',
        '9. معلومات  الأسرة والوحدة السكنية '
    ),
    (
        'identity_type1',
        '9.0 identity type',
        'نوع الوثيقة '
    ),
    ('id_number1', '9.1 id number', 'رقم الهوية '),
    ('passport1', '9.2 passport number', 'رقم الجواز '),
    (
        'other_id1',
        '9.2.1 if other, specify',
        'في حال اخترت أخرى، حدد نوع الوثيقة ورقمها '
    ),
    (
        'unit_owner',
        '9.3 unit owner',
        'اسم مالك الوحدة '
    ),
    (
        'q_9_3_1_first_name',
        '9.3.1 first name',
        'الاسم الأول '
    ),
    (
        'q_9_3_2_second_name__father',
        '9.3.2 second name (father)',
        'اسم الأب '
    ),
    (
        'q_9_3_3_third_name__grandfather',
        '9.3.3 third name (grandfather)',
        'اسم الجد '
    ),
    (
        'q_9_3_4_last_name',
        '9.3.4 last name',
        'اسم العائلة '
    ),
    ('sex', '9.4 gender', 'جنس المالك '),
    (
        'mobile_number',
        '9.5  mobile number',
        'رقم الموبايل. اكتب رقم الموبايل بالشكل التالي: 0592797072 '
    ),
    (
        'additional_mobile',
        '9.5.1 additional mobile number',
        'رقم موبايل اضافي. اكتب رقم الموبايل بالشكل التالي: 0592797073 '
    ),
    ('owner_job', '9.6 job', 'وظيفة المالك '),
    (
        'other_job',
        '9.6.1 if other, specify',
        'إذا تم اختيار آخر/أخرى في السؤال السابق، وضح الإجابة '
    ),
    ('age', '9.7  age', 'عمر المالك '),
    (
        'marital_status',
        '9.8 marital status',
        'الحالة الاجتماعية للمالك '
    ),
    (
        'ownership_image',
        '9.9 take picture for the id and other documents',
        'خذ صورة للهوية وإثبات الملكية '
    ),
    (
        'page10',
        '10. spouses and disability information',
        '10. معلومات الأزواج وذوي الإعاقة '
    ),
    (
        'no_spouses',
        '10.1 number of spouses',
        'عدد الزوجات '
    ),
    (
        'spouse1',
        '10.2 spouse name',
        ' اسم الزوج/الزوجة '
    ),
    (
        'spouse1_id',
        '10.3 spouse id',
        'رقم هوية الزوج/الزوجة '
    ),
    (
        'spouse2',
        '10.4 second spouse name',
        ' الزوجة الثانية '
    ),
    (
        'spouse2_id',
        '10.5 second spouse id',
        'رقم هوية الزوجة الثانية '
    ),
    (
        'spouse3',
        '10.6 third spouse name',
        'الزوجة الثالثة '
    ),
    (
        'spouse3_id',
        '10.7 third spouse id',
        'رقم هوية الزوجة الثالثة '
    ),
    (
        'spouse4',
        '10.8 fourth spouse name',
        'الزوجة الرابعة '
    ),
    (
        'spouse4_id',
        '10.9 fourth spouse id',
        'رقم هوية الزوجة الرابعة '
    ),
    (
        'are_there_people_with_disability',
        '10.10 are there people with disability?',
        'هل يوجد أحدا من ذوي الإعاقة؟ '
    ),
    (
        'number_of_people_with_disability',
        '10.11 number of people with disability',
        'عدد الأشخاص من ذوي الإعاقة '
    ),
    (
        'handicapped_type',
        '10.12 type of disability',
        'نوع الإعاقة '
    ),
    (
        'other_handicapped',
        '10.13 if other, specify:',
        'إذا تم اختيار آخر/أخرى في السؤال السابق، وضح الإجابة '
    ),
    (
        'is_refugee',
        '10.14 is the family registered as refugee?',
        'العائلة مسجلة كلاجئة مع الأونروا '
    ),
    (
        'unrwa_registration_number',
        '10.15  write the unrwa registration number',
        'رقم بطاقة الاونروا الجدید/القدیم '
    ),
    (
        'page11',
        '11. family size',
        '11.عدد أفراد الأسرة '
    ),
    (
        'number_of_nuclear_families',
        '11.1 number of nuclear families',
        'عدد الأسر النووية '
    ),
    (
        'mchildren_001',
        '11.2 no. males (<18 years old)',
        'عدد الذكور ممن هم أقل من 18 عاما. '
    ),
    (
        'myoung',
        '11.3 no. males (>=18 and < 60 years old)',
        'عدد الذكور ممن هم 18 عاما أو أكبر وأقل من 60 عاما. '
    ),
    (
        'melderly',
        '11.4 no. males (>=60 years old)',
        'عدد الذكور ممن هم أكبر من 60 عاما. '
    ),
    (
        'fchildren',
        '11.5 no. females (<18 years old)',
        'عدد الإناث ممن هم أقل من 18 عاما. '
    ),
    (
        'fyoung_001',
        '11.6 no. females (>=18 and < 60 years old)',
        'عدد الإناث ممن هم 18 عاما أو أكبر وأقل من 60 عاما. '
    ),
    (
        'felderly',
        '11.7 no. females (>=60 years old)',
        'عدد الإناث ممن هم أكبر من 60 عاما. '
    ),
    (
        'pregnant',
        '11.8 no. pregnant women',
        'عدد النساء الحوامل '
    ),
    (
        'lactating',
        '11.9 no. lactating women',
        'عدد النساء المرضعات '
    ),
    (
        'page12',
        '12. current residence and refugee status',
        '12. مكان الإقامة الحالي وحالة اللجوء '
    ),
    (
        'the_unit_resident',
        '12.0 the unit resident at the time of damage is',
        ' المقیم في المنزل وقت الضرر '
    ),
    (
        'current_address',
        '12.1 is the current address as same as the original address?',
        'هل العنوان الحالي هو نفس العنوان الأصلي (عنوان الهدم) '
    ),
    (
        'current_residence',
        '12.2 current place of residence',
        'مكان الإقامة الحالية '
    ),
    (
        'current_residence_other',
        '12.2.1 please specify (other)',
        'يرجى التحديد (أخرى) '
    ),
    (
        'shelter_name',
        '12.3 shelter name',
        'اسم مركز الإيواء '
    ),
    (
        'shelter_type',
        '12.4 shelter type',
        'نوع مركز الإيواء '
    ),
    (
        'shelter_type_other',
        '12.4.1 please specify (other)',
        'يرجى التحديد (أخرى) '
    ),
    ('governorate', '12.5 governorate', 'المحافظة '),
    ('locality', '12.6 locality', 'البلدية '),
    ('neighborhood', '12.7 neighborhood', 'الحي '),
    ('street', '12.8 street', 'اسم الشارع '),
    (
        'closest_facility2',
        '12.9 closest facility',
        'أقرب مرفق '
    ),
    (
        'page13',
        '13. household and rentee',
        '13. معلومات المستأجر '
    ),
    (
        'identity_type2',
        '13.1 identity type',
        'نوع الوثيقة '
    ),
    (
        'rentee_id_passport_number',
        '13.2 rentee id/passport number',
        'رقم هوية المستأجر/جواز السفر '
    ),
    (
        'rentee_resident_full_name',
        '13.3 rentee/resident full name',
        'اسم المستأجر/المقيم '
    ),
    (
        'q_13_3_1_first_name',
        '13.3.1 first name',
        'الاسم الأول '
    ),
    (
        'q_13_3_2_second_name__father',
        '13.3.2 second name (father)',
        'اسم الأب '
    ),
    (
        'q_13_3_3_third_name__grandfather',
        '13.3.3 third name (grandfather)',
        'اسم الجد '
    ),
    (
        'q_13_3_4_last_name__family',
        '13.3.4 last name (family)',
        'اسم العائلة '
    ),
    (
        'rentee_mobile_number',
        '13.4 rentee mobile number',
        'رقم جوال/هاتف المستأجر '
    ),
    ('work_type', '13.5 work type', 'طبيعة العمل '),
    (
        'other_work',
        '13.5.1 if other, specify',
        'في حال أخرى، حدد طبيعة العمل الآخر '
    ),
    ('', '', ' '),
    (
        'page14',
        '14. unit finishing and internal damaged',
        '14. تقييم تشطيب الوحدة والأضرار الداخلية '
    ),
    (
        'external_finishing_of_the_unit',
        '14.1 external finishing of the unit',
        'تشطيب الوحدة من الخارج '
    ),
    (
        'other_external_finishing',
        '14.1.1 if other, specify',
        'في حال أخرى، حدد نوع التشطيب الآخر '
    ),
    (
        'is_finished',
        '14.2 is the apartment finished?',
        'هل الشقة مشطبة؟ '
    ),
    (
        'finishing_extent',
        '14.2.2 is the finishing total or partial?',
        'هل التشطيب كلي أم جزئي؟ '
    ),
    (
        'internal_finishing_of_the_unit',
        '14.2.1 internal finishing of the unit',
        'تشطيب الوحدة من الداخل '
    ),
    (
        'finishing_partial_types',
        '14.2.4 partial finishing types',
        'نوع التشطيب الجزئي '
    ),
    (
        'has_fire',
        '14.3 is there a fire in the housing unit?',
        'هل يوجد حريق في الوحدة؟ '
    ),
    (
        'fire_extent',
        '14.3.1 is the fire total or partial?',
        'هل الحريق كلي أم جزئي؟ '
    ),
    (
        'fire_severity',
        '14.3.2 fire severity level',
        'ما هي درجة الحريق '
    ),
    (
        'fire_locations',
        '14.3.3 areas affected by fire',
        'الأماكن التي تعرضت للحريق '
    ),
    (
        'fire_rooms_count',
        '14.3.4 number of rooms affected by fire',
        'عدد الغرف التي تعرضت للحريق '
    ),
    (
        'fire_area',
        '14.3.5 fire affected area (m²)',
        'مساحة الحريق (م²) '
    ),
    (
        'furniture_ownership',
        '14.4 who owns the furniture?',
        'ملكية الأثاث تعود إلى من؟ '
    ),
    (
        'percentage_of_damaged_furniture',
        '14.4.1 percentage of damaged furniture',
        'نسبة تدمير الآثاث (%) '
    ),
    (
        'unit_stripping',
        '14.5 was there stripping inside the housing unit?',
        'هل تم التفريغ في الوحدة السكنية؟ '
    ),
    (
        'unit_stripping_details',
        '14.5.1 stripping: total or partial?',
        'التفريغ: كلي أم جزئي؟ '
    ),
    (
        'stripping_area',
        '14.5.2 stripping area (m²)',
        'مساحة التفريغ (م²) '
    ),
    (
        'stripping_locations',
        '14.5.3 areas affected by stripping',
        'حدد الأماكن التي تم التفريغ فيها '
    ),
    (
        'rubble_removal_is_needed',
        '9.3  debris removal is needed?',
        'هل هناك حاجة لإزالة الركام؟ '
    ),
    (
        'activation_of_uxo_ha_d_material_clearance',
        '9.4  activation of uxo/hazard material clearance?',
        'هل هناك حاجة لفحص وتنظيف المواد الخطرة/المتفجرة؟ '
    ),
    (
        'unit_support_needed',
        '9.6 housing unit shoring / structural support needed',
        'هل تحتاج الوحدة السكنية إلى تدعيم؟ '
    ),
    (
        'is_the_housing_unit_or_living_habitable',
        '9.7 is the damaged unit suitable for living (habitable)/work?',
        'هل الوحدة المتضررة ملائمة للسكن/العمل؟ '
    ),
    (
        'mhpss',
        '15. mental health and psychosocial support (mhpss)',
        'القسم (5): الصحة النفسية والدعم النفسي الاجتماعي '
    ),
    (
        'mhpss_experinced',
        '15.1 since the war, have you or any family members experienced increased stress, anxiety, emotional challenges, and/or mental and psychological issues? if so',
        'منذ بداية الحرب، هل واجهت أنت أو أي من أفراد عائلتك زيادة في التوتر، أو القلق، أو التحديات العاطفية، أو المشاكل العقلية والنفسية، أو الارق او أي مشاكل نفسية أخرى؟ إذا كان الأمر كذلك (نعم)، رجاء حدد؟ '
    ),
    (
        'other_mhpss_exp',
        '15.1.1 if other, specify:',
        'إذا تم اختيار آخر/أخرى في السؤال السابق، وضح الإجابة '
    ),
    (
        'mhpss_support',
        '15.2 what kind of support do you think would help you cope better (e.g., individual counseling, group therapy, family support, or community activities)?',
        'ما نوع الدعم الذي تعتقد أنه سيساعدك على التكيف مع الوضع الحالي بشكل أفضل (مثل، الاستشارة الفردية، أو العلاج الجماعي، أو الدعم الأسري، أو الأنشطة المجتمعية، او غيرها)؟ '
    ),
    (
        'other_mhpss_support',
        '15.2.1 if other, specify:',
        'إذا تم اختيار آخر/أخرى في السؤال السابق، وضح الإجابة '
    ),
    (
        'community_participation',
        '15.3 would you be interested in participating in community-based interventions aimed at enhancing resilience and coping mechanisms?',
        'هل أنت مهتم بالمشاركة في أية تدخلات مجتمعية و/أو نفسية تهدف إلى تعزيز المرونة والصمود وآليات التكيف؟ '
    ),
    ('', '', ' '),
    (
        'ce',
        '16. community needs and preferences survey',
        '16. استطلاع احتياجات المجتمع '
    ),
    (
        'ce1',
        '16.1 prefab housing needs',
        'احتياجات المنازل الجاهزة '
    ),
    (
        'prefab_moving',
        '16.1.1 would you be interested in moving to a prefab house as a temporary solution?',
        'هل ستكون مهتماً بالانتقال إلى منزل جاهز كحل مؤقت؟ '
    ),
    (
        'prefab_moving_maybe',
        '16.1.2 if maybe, please specify:',
        'إذا تم اختيار ربما في السؤال السابق، وضح الإجابة '
    ),
    (
        'prefab_types',
        '16.1.3 what features are most important to you in a prefab house?',
        'ما هي الميزات الأكثر أهمية بالنسبة لك في المنزل الجاهز؟ '
    ),
    (
        'other_prefab_types',
        '16.1.4 if other, specify:',
        'إذا تم اختيار آخر/أخرى في السؤال السابق، وضح الإجابة '
    ),
    (
        'prefab_pref',
        '16.1.5 do you have any specific preferences or requirements for the prefab house? (please describe)',
        'هل لديك أي تفضيلات أو متطلبات محددة للمنزل الجاهز (يرجى التوضيح) ؟ '
    ),
    (
        'ce2',
        '16.2 rehabilitation needs',
        'احتياجات إعادة التأهيل '
    ),
    (
        'reh_kitchen',
        '16.2.1 do you require rehabilitation of your kitchen?',
        'هل يحتاج إلى إعادة تأهيل المطبخ؟ '
    ),
    (
        'reh_bathroom',
        '16.2.2 do you require rehabilitation of your bathroom?',
        'هل يحتاج إلى إعادة تأهيل الحمام؟ '
    ),
    (
        'reh_type',
        '16.2.3 what type of rehabilitation is needed for your kitchen or bathroom? (please describe)',
        'ما نوع إعادة التأهيل المطلوبة للمطبخ أو الحمام (يرجى الوصف)؟ '
    ),
    (
        'ce3',
        '16.3 additional comments',
        'تعليقات إضافية '
    ),
    (
        'additional_comments',
        '16.3.1 do you have any additional comments or concerns regarding temporary shelter?',
        'هل لديك أي تعليقات أو مخاوف إضافية بشأن الإسكان المؤقت؟ '
    ),
    ('techncial_boq', '17. techncial-boq', ' '),
    ('tech_boq', 'techncial-boq', ' '),
    ('p11', 'demolishing works', ' '),
    ('dm1', 'dm1-demolish walls ', 'إزالة حوائط (m2) '),
    (
        'dm2',
        'dm2-remove concrete from slabs',
        'أزالة أسقف (m2) '
    ),
    (
        'dm3',
        'dm3-remove concrete from existing damaged columns',
        'إزالة أعمدة (no.) '
    ),
    (
        'dm4',
        'dm4-carefully remove concrete from stair cases ',
        'إزالة درج (item) '
    ),
    (
        'dm5',
        'dm5-clean the site including removing debris out of site to approved damping areas.',
        'تنظيف الموقع و إزالة الركام  من الموقع (item) '
    ),
    (
        'dm6',
        'dm6-demolish concrete ground slabs',
        'إزالة مدة أرضية (m2) '
    ),
    (
        'dm7',
        'dm7-demolish concrete beams or foundation including removing debris out of site to approved damping areas.',
        'إزالة حزامات أو قواعد وإزالة ركامها من الموقع (m3) '
    ),
    (
        'dm8',
        'dm8-carfully remove the terazzo floor tiles',
        'إزالة بلاط (m2) '
    ),
    (
        'dm9',
        'dm9-carefully dismantle & refix existing aluminum or wooden window/door, repair, fix new fittings in place of missing parts where needed & any other needed.',
        'فك وإعادة تركيب أبواب أو شبابيك ألمنيوم أو خشب شاملاً الصيانة والقطع الجديدة (no.) '
    ),
    (
        'dm10',
        'dm10-remove marble worktop',
        'إزالة المجلى كاملا ( الوجه والأرفف ... إلخ) (rm) '
    ),
    (
        'dm11',
        'dm11-hacking and remove old plaster including removing debris out of site to approved damping areas.',
        'إزالة قصارة قديمة شاملاً تنظيف الموقع (m2) '
    ),
    (
        'dm12',
        'dm12-backfilling with clean sand',
        'ردم باستخدام رمل نظيف (m3) '
    ),
    ('p12', 'blocks works', ' '),
    (
        'bl2',
        'bl2-hollow block walling 10 - 12cm thick ',
        'بلوك 12 - 10 سم (m2) '
    ),
    (
        'bl3',
        'bl3-hollow block walling 15cm thick',
        'بلوك 15 سم (m2) '
    ),
    (
        'bl4',
        'bl4-hollow block walling 20cm thick',
        'بلوك 20 سم (m2) '
    ),
    (
        'bl5',
        'bl5-solid block walling 20cm thick ',
        'بلوك بلدي 20 سم (m2) '
    ),
    ('p13', 'concrete works', ' '),
    (
        'co2',
        'co2-supply and cast reinforced concrete (b250/20) for ground slab with [10cm] thick, ',
        'توريد وصب خرسانة مسلحة بقوة (b250/20) لأعمال مدة أرضية بسماكة 10سم (m2) '
    ),
    (
        'co3',
        'co3-supply and cast reinforced concrete (b250/20) in door jambs, canopies, secondary tie columns,secondary beams, lintel, sills, infil and topping to walls ',
        'توريد وصب خرسانة مسلحة بقوة (b250/20) لأعمال الجلسات والكشفات والحزامات (m3) '
    ),
    (
        'co4',
        'co4-supply and cast reinforced concrete (b250/20) for ground beams or foundation',
        'توريد وصب خرسانة مسلحة بقوة (b250/20) لأعمال الأحزمة الأرضية والقواعد (m3) '
    ),
    (
        'co5',
        'co5-supply and cast reinforced concrete (b300/20) for new columns ',
        'توريد وصب خرسانة مسلحة بقوة (b300/20) لأعمال الأعمدة الجديدة (m3) '
    ),
    (
        'co6',
        'co6-supply and cast reinforced concrete (b300/20) as protective sleeve cover around existing damaged columns. ',
        'توريد وصب خرسانة مسلحة بقوة (b300/20) لأعمال غطاء خرساني (قمصان) حول الأعمدة المتضررة (m3) '
    ),
    (
        'co7',
        'co7-supply and cast reinforced concrete (b250/20) for solid slab [12cm] average thick',
        'توريد وصب خرسانة مسلحة بقوة (b250/20) لأعمال السقف المصمت بسماكة 12سم  (m2) '
    ),
    (
        'co8',
        'co8-supply and cast reinforced concrete (b250/20) for suspended hollow block slab [25cm average thick]',
        'توريد وصب خرسانة مسلحة بقوة (b250/20) لأعمال الأسقف باستخدام بلوك مفرغ (ريبس) بسماكة 30 - 35 سم  (m2) '
    ),
    (
        'co9',
        'co9-supply & cast reinforced concrete (b250/20) for stair case (landing, flights, steps & beams)',
        'توريد وصب خرسانة مسلحة بقوة (b250/20) لأعمال الدرج (الشواحط والدرجات والأحزمة) (m3) '
    ),
    (
        'co10',
        'co10-provide wooden shuttering and steel support for existing roof slab or any structural element as directed by supervising engineer.',
        'تدعيم بدعائم خشبية ومعدنية لسقف أو أي عنصر إنشائي يحتاج لتدعيم (m2) '
    ),
    (
        'p14_1',
        'internal finishings works',
        'أعمال التشطيبات الداخلية '
    ),
    ('p14_1_1', 'painting works', 'أعمال الدهان '),
    (
        'fn1',
        'fn1-priming and painting with at least two coats of high quality acrylic emulsion paint (supercryle) for walls and ceiling surfaces. ',
        'تأسيس ودهان على الأقل طبقتين (سوبركريل) للحوائط والاسقف (m2) '
    ),
    (
        'fn2',
        'fn2-priming and painting with at least two coats of high quality oil paint for walls. ',
        'تأسيس ودهان على الأقل طبقتين (دهان زيت) للحوائط (m2) '
    ),
    ('p14_1_2', 'tiling works', 'أعمال البلاط '),
    (
        'fn3',
        'fn3-supply and install precast terrazzo sills',
        'توريد وتركيب جلسات كسر رخام (rm) '
    ),
    (
        'fn4',
        'fn9-supply and install precast terrazzo for stair case or marble',
        'توريد وتركيب درج (كسر رخام) أو رخام  (rm) '
    ),
    (
        'fn5',
        'fn4-supply and install terrazzo with marble chips floor tiles and skirting (7cm high) ',
        'توريد وتركيب بلاط كسر رخام شاملاً بلاط البانيل بسمك 7 سم (m2) '
    ),
    (
        'fn6',
        'fn6-supply and install white glazed ceramic wall tiles ',
        'توريد وتركيب بلاط حوائط سراميك (m2) '
    ),
    (
        'fn7',
        'fn7-supply and install unglazed ceramic floor tiles ',
        'توريد وتركيب بلاط أرضي سراميك  (m2) '
    ),
    (
        'fn8',
        'fn8-supply and install multi-purpose marble (m2.)',
        'توريد وتركيب  بلاط خشب بركيه '
    ),
    (
        'fn10',
        'fn10-supply and installation of granolithic finish for floors or walls',
        'توريد وتركيب جرانيوليت للأرضيات أو الحوائط (m2) '
    ),
    ('p14_1_3', 'marble works', 'أعمال الرخام '),
    (
        'fn11',
        'fn11-supply and install marble sills (local khalily type) 250mm wide',
        'تويد وتركيب جلسات رخام خليلي أو كسر رخام بعرض 25 سم (rm) '
    ),
    (
        'fn12',
        'fn12-marble worktop  ( complete including top, shelves, walls, … etc)',
        'توريد وتركيب رخام مجلى (كامل يشمل الوجه والرفوف والضلفات ... إلخ) (rm) '
    ),
    (
        'fn13',
        'fn13-supply and fix granite marble only',
        'توريد وتركيب وجه جرانيت (m2) '
    ),
    (
        'fn14',
        'fn14-maintenance of kitchen marble',
        'صيانة رخام مطبخ (rm) '
    ),
    (
        'fn15',
        'fn15- supply and install multi-purpose marble (m2.)',
        'توريد و تريكيب رخام متعدد الإستخدامات (m2) '
    ),
    (
        'p14_1_4',
        'plastering works (gypsum / plaster)',
        'أعمال القصارة (لياسة / جبس) '
    ),
    (
        'fn22',
        'fn22-supply & fix gypsum decorated panel (wide) include material & workmanship',
        'توريد وتركيب جبس للديكور الداخلي (عريض) شاملاً المواد والعمالة (rm) '
    ),
    (
        'fn23',
        'fn23-supply & fix gypsum decorated panel (thin) include material & workmanship',
        'توريد وتركيب جبس للديكور الداخلي (كرانيش) شاملاً المواد والعمالة (rm) '
    ),
    (
        'fn24',
        'fn24-supply & fix gypsum decorated panel  include material & workmanship',
        'توريد وتركيب جبس (ألواح) للديكور الداخلي شاملاً المواد والعمالة (m2) '
    ),
    (
        'fn25',
        'fn25-supply and install gypsum board walls, including all materials and workmanship',
        'توريد وتركيب حوائط جبس بورد، شاملاً المواد والعمالة (m2) '
    ),
    (
        'fn26',
        'fn26-supply and installation of false ceiling (gypsum board), including all materials and workmanship.',
        'توريد وتركيب سقف مستعار (جبس بورد) شاملاً المواد والعمالة (m2) '
    ),
    (
        'fn16',
        'fn16-internal plastering, 13mm thick to walls and ceilings.',
        'قصارة داخلية بسماكة 13 مم للحوائط والسقف (m2) '
    ),
    (
        'fn17',
        'fn17-external rendering, 15mm thick with approved dampproof admixture to walls.',
        'قصارة خارجية بسماكة 15 مم (m2) '
    ),
    (
        'fn18',
        'fn18-ditto but lime free external rendering.',
        'قصارة خارجية بدون استخدام الشيد (m2) '
    ),
    (
        'fn19',
        'fn19-external tyrolean finish with white cement ad fine sand (kfars) mix (1:3) to walls.',
        'رشقة خارجية باستخدام رمل ناعم و أسمنت أبيض بنسبة (1/3) (m2) '
    ),
    (
        'fn20',
        'fn20-external italian plaster finish.',
        'شلختة إيطالية (m2) '
    ),
    (
        'fn21',
        'fn21-plastering, rendering and repairing cracks',
        'قصارة ومعالجة وإصلاح الشقوق (rm) '
    ),
    ('', '', ' '),
    ('', '', ' '),
    (
        'p14_2',
        'external finishings works',
        'أعمال التشطيبات الخارجية '
    ),
    (
        'fn27',
        'fn27-supply and install exterior natural stone. ',
        'توريد وتركيب حجر قدسي (m2) '
    ),
    (
        'fn28',
        'fn28-supply, install  and fix  clay roof tiles (karmeed قرميد) complete with all needed items, materials  and works',
        'توريك وتركيب كرميد شاملا كل ما يحتاجه من مواد وأعمال (m2) '
    ),
    (
        'fn29',
        'fn29-supply and installation of interlock tiles, 6 cm thick',
        'توريد وتركيب بلاط إنترلوك سماكة 6 سم '
    ),
    (
        'fn30',
        'fn30-supply and install interlocking paving tiles, 8 cm thickness',
        'توريد وتركيب بلاط إنترلوك سماكة8 سم '
    ),
    (
        'fn31',
        'fn31-external italian plaster finish.',
        'شلختة إيطالية (m2) '
    ),
    ('', '', ' '),
    ('p15', 'aluminum works', ' '),
    (
        'al1',
        'al1-supply and fix aluminum window of 4-6mm thick waved glazed sliding leaves ',
        'توريد وتركيب نوافذ ألمنيوم كاملة بسماكة 4-6 مم (منزلقة) (m2) '
    ),
    (
        'al2',
        'al2-supply and fix aluminum window of 4-6mm thick waved glazed moving leaves ',
        'توريد وتركيب نوافذ ألمنيوم كاملة بسماكة 4-6 مم (متحركة) (m2) '
    ),
    (
        'al3',
        'al3-supply and fix aluminum window of 4-6mm thick waved glazed fixed leaves ',
        'توريد وتركيب نوافذ ألمنيوم كاملة بسماكة 4-6 مم (ثابتة) (m2) '
    ),
    (
        'al4',
        'al4-supply and fix aluminum window of plastic panel louvered sliding leaves.',
        'توريد وتركيب نوافذ ألومنيوم قلّاب (لوفر) بلاستيك (منزلقة) (m2) '
    ),
    (
        'al5',
        'al5-supply and fix aluminum window of plastic panel louvered moving leaves. ',
        'توريد وتركيب نوافذ ألومنيوم قلّاب (لوفر) بلاستيك (متحركة) (m2) '
    ),
    (
        'al6',
        'al6-supply and fix aluminum window of plastic panel louvered fixed leaves. ',
        'توريد وتركيب نوافذ ألومنيوم قلّاب (لوفر) بلاستيك (ثابتة) (m2) '
    ),
    (
        'al7',
        'al7-supply and install leaves for aluminum window or door to match the existing ',
        'توريد وتركيب ضلفات لشبابيك أو أبواب ألمنيوم (m2) '
    ),
    (
        'al8',
        'al8-supply and install  aluminum frame ',
        'توريد وتركيب إطار ألمنيوم (rm) '
    ),
    (
        'al9',
        'al9-maintenance of aluminum window or door ( excluding glass).',
        'صيانة شبابيك أو أبواب ألمنيوم (m2) '
    ),
    (
        'al10',
        'al10-supply and install curtain wall system',
        'توريد وتركيب كيرتن وول (متر مربع) (m2) '
    ),
    ('', '', ' '),
    ('p16', 'wood works', ' '),
    (
        'wd1',
        'wd1-type d1, v-jointed, size (90-80x220x4.5)cm ',
        'توريد وتركيب أبواب خشبية يشمل الحلق، حجم (80-90 *220*4.5) سم (no.) '
    ),
    (
        'wd3',
        'wd3-maintenance of wooden window or door',
        'صيانة أبواب أو نوافذ خشبية شامل الحلق (no.) '
    ),
    (
        'wd4',
        'wd4-supply and fix wooden window.',
        'توريد وتركيب نوافذ خشبية (no.) '
    ),
    (
        'wd5',
        'wd5-supply and install timber leaves for wooden window or door.',
        'توريد وتركيب ضلفة باب أو نافذة خشبية (no.) (80-90 *220*4.5) سم '
    ),
    (
        'wd6',
        'supply and install wooden door frame (no.) 15 cm',
        'توريد وتركيب حلق باب خشب (no.) 15 سم '
    ),
    (
        'wd7',
        'wd–supply and install wooden door frame, 18 cm (no.)',
        'توريد وتركيب حلق باب خشب (no.) 18 سم '
    ),
    (
        'wd8',
        'wd6-supply and install timber frame for wooden window or door to match the existing. ',
        'توريد وتركيب حلق باب خشب (no.) 23 سم '
    ),
    (
        'wd9',
        'supply and install wooden staircase (mr.)',
        'توريد وتركيب  درج خشبي (mr.)  '
    ),
    (
        'wd10',
        'supply and install wooden mezzanine (m2.)',
        'توريد وتركيب   (m2.) سدة خشبية '
    ),
    (
        'wd11',
        'supply and install wooden cabinet (mr.)',
        'توريد وتركيب   خزانة خشب mr '
    ),
    (
        'wd12',
        'cm8-supply and install hardware with switch (wally type).',
        'توريد وتركيب زرفيل باب  (no.) '
    ),
    ('p17', 'metal works', ' '),
    (
        'mt1',
        'mt1-supply and install galvanized steel  sheets (type skourit) 0.5mm thick .',
        'توريد وتركيب ألواح زينجو (سكوريت) بسماكة 0.5 مم (m2) '
    ),
    (
        'mt2',
        'mt2-supply and install eternite sheets fixed to steel pipes or purlings.',
        'توريد وتركيب ألواح   (m2) '
    ),
    (
        'mt3',
        'mt3-dismantle and refix steel pipes and eternite sheets or skourit .',
        'إعادة تركيب وإصلاح ألواح زينجو (سكوريت) (m2) '
    ),
    (
        'mt4',
        'mt4-supply and install 4\'\' mild steel pipes 2.25mm thick.',
        'توريد وتركيب مواسير حديد بسماكة 2.25 مم  وقطر 4 إنش (rm) '
    ),
    (
        'mt5',
        'mt5-supply and install open steel profile 80x40 mm.',
        'توريد وتركيب بروفيل معدني 8*4 سم (rm) '
    ),
    (
        'mt6',
        'mt6-supply and install steel door size [120x220]cm.',
        'توريد وتركيب أبواب حديد (120*220) سم (no.) '
    ),
    (
        'mt7',
        'mt7-maintenance of steel door size [120x220]cm.',
        'صيانة أبواب معدنية (120*220) سم (no.) '
    ),
    (
        'mt8',
        'mt8-supply and install steel door size [220x400]cm.',
        'توريد وتركيب أبواب معدنية (أبواب حواصل) (m2) '
    ),
    (
        'mt9',
        'mt9-maintenance of steel door size [220x400]cm.',
        'صيانة أبواب معدنية (أبواب حواصل) (400*220) سم (no.) '
    ),
    (
        'mt10',
        'mt10-supply and fix steel balustrade for stair case.',
        'توريد وتركيب دربزين معدني للدرج (rm) '
    ),
    (
        'mt11',
        'mt11-supply and fix security protective screen. ',
        'توريد وتركيب حديد حماية للنوافذ (m2) '
    ),
    (
        'mt12',
        'mt12-supply and install steel stand, average size of 100x100x200cm mild steel.  سيبة حمام شمسي',
        'توريد وتركيب سيبة حمام شمسي 200 * 100* 100 سم (no.) '
    ),
    (
        'mt13',
        'mt13-new mul-t-lock door with frame',
        'باب ملتيلوك جديد مع حلق للباب (no.) '
    ),
    (
        'mt14',
        'mt14-new mul-t-lock door without frame',
        'باب ملتيلوك جديد بدون حلق للباب (no.) '
    ),
    (
        'mt15',
        'mt15-pvc coating',
        'جلد باب ملتي لوك (no.) '
    ),
    (
        'mt16',
        'mt16-new door lock',
        'قفل باب جديد (no.) '
    ),
    (
        'mt17',
        'mt17-complete maintenace',
        'صيانة كاملة لباب ملتيلوك (no.) '
    ),
    (
        'mt19',
        'mt19-supply and install steel emergency escape staircase (linear meter)',
        'توريد وتركيب درج هروب حديدي (متر طولي) '
    ),
    ('', '', ' '),
    ('p18', 'combined', ' '),
    (
        'cm1',
        'cm1-replacement of broken glass 4mm thick for any defected parts.',
        'إستبدال زجاج مكسور بسمك 4 مم (m2) '
    ),
    (
        'cm2',
        'cm2-replacement of broken glass 6mm thick for any defected parts.',
        'إستبدال زجاج مكسور بسمك 6 مم (m2) '
    ),
    (
        'cm3',
        'cm3-replacement of broken glass colored 4mm thick (reflector) for any defected parts.',
        'إستبدال زجاج مكسور بسمك 4 مم عاكس (m2) '
    ),
    (
        'cm4',
        'cm4-replacement of broken glass colored 6mm thick (reflector) for any defected parts.',
        'إستبدال زجاج مكسور بسمك 6 مم عاكس (m2) '
    ),
    (
        'cm5',
        'cm5-replacement of broken reinforced glass  6mm thick  for any defected parts.',
        'إستبدال زجاج بشبك معدني (مسلح) بسمك 6 مم (m2) '
    ),
    (
        'cm6',
        'cm6-replacement of broken panels for louver leaves with all needed accessories',
        'إستبدال زجاج لوفر مكسور (m2) '
    ),
    (
        'cm7',
        'cm7-supply and install fly screen for leaves with all needed acessories.',
        'توريد وتركيب شبك للنوافذ (m2) '
    ),
    (
        'cm8',
        'cm8-supply and install securit glass, 10 cm thickness',
        'تركيب و توريد زجاج 10سم سوكوريت '
    ),
    (
        'cm9',
        'cm9-supply and installation of kitchen cabinets (aluminum or wood).',
        'توريد وتركيب خزائن مطبخ (ألمنيوم) (rm) '
    ),
    (
        'cm10',
        'cm10-supply and installation of pvc cladding',
        'توريد وتركيب معرش جلد (m2) (pvc) '
    ),
    (
        'cm11',
        'cm11-elevator (total damage / maintenance / motor / cabin / number of doors)',
        'مصعد (ضرر كلي / صيانة / ماتور / كابينة / عدد الأبواب) (item) '
    ),
    ('', '', ' '),
    ('p19', 'plumping works', ' '),
    (
        'pm1',
        'pm1-complete solar heating system',
        'حمام شمسي كامل (item) '
    ),
    (
        'pm2',
        'pm2-solar heater cylinder 150 liter.',
        'سخان شمسي بسعة 150 لتر (item) '
    ),
    (
        'pm101',
        'mt12-supply and install steel stand, average size of 100x100x200cm mild steel.  سيبة حمام شمسي',
        'توريد وتركيب سيبة حمام شمسي 200 * 100* 100 سم (no.) '
    ),
    (
        'pm18',
        'pm18-complete single mirror size 190x90cm for solar system best quality.',
        'مرآة حمام شمسي  190*90 سم (no.) '
    ),
    (
        'pm19',
        'pm19-2mm clear glass sheet for solar system mirrors best quality, ',
        'زجاج مرآة حمام شمسي (m2) '
    ),
    (
        'pm3',
        'pm3-ditto but 0.5m3 capacity.',
        'خزان مياه بسعة 500 لتر (item) '
    ),
    (
        'pm4',
        'pm4-1.0m3 capacity plastic water tank',
        'خزان مياه بسعة 1000 لتر (item) '
    ),
    (
        'pm5',
        'pm5-ditto but 1.5m3 capacity.',
        'خزان مياه بسعة 1500 لتر (item) '
    ),
    (
        'pm6',
        'pm6-ditto but 2.m3 capacity.',
        'خزان مياه بسعة 2000 لتر (item) '
    ),
    (
        'pm7',
        'pm7-maintenance of water tank ',
        'صيانة خزان مياه (no.) '
    ),
    (
        'pm8',
        'pm8-maintenance of water network',
        'صيانة شبكة مياه  (no.) '
    ),
    (
        'pm9',
        'pm9-dismantle and refix solar heating system.',
        'فك وإعادة تركيب سخان شمسي كاملاً (item) '
    ),
    (
        'pm10',
        'pm10-upvc drainage pipes 4" internal diameter',
        'أنابيب (مواسير) upvc قطر 4 إنش (mr) '
    ),
    (
        'pm11',
        'pm11-upvc drainage pipes 6" internal diameter.',
        'أنابيب (مواسير) upvc قطر 6 إنش (mr) '
    ),
    (
        'pm12',
        'pm12-upvc drainage pipes 2" or 3" .',
        'أنابيب (مواسير) upvc قطر 2 أو 3 إنش (mr) '
    ),
    (
        'pm13',
        'pm13-galavanized steel pipes [3/4"]',
        'أنابيب (مواسير) معدنية مجلفنة قطر 3/4 انش (mr) '
    ),
    (
        'pm14',
        'pm14-20 mm pressure pvc water pipes [gulanee] with sleeves 25 mm',
        'أنابيب (مواسير) جولاني شاملاً أنابيب (مواسير) شنشوري قطر 25 مم (mr) '
    ),
    (
        'pm15',
        'pm15-1/2" galavanized steel pipes with all fittings including priming & painting.',
        'أنابيب (مواسير) حديد مجلفن شاملاً الوصلات والتأسيس والدهان (mr) '
    ),
    (
        'pm16',
        'pm16-trapped floor gully 4" diameter ',
        'مصفاة أرضية 4 إنش (no.) '
    ),
    (
        'pm20',
        'pm20-flushing cistern box ',
        'صندوق طرد/تصريف مياه (نيجارة) (no.) '
    ),
    (
        'pm21',
        'pm21-0.5" tibisa type chromium water mixer ',
        'خلاط مياه 0.5 إنش (no.) '
    ),
    (
        'pm22',
        'pm22-0.5" tibisa type chromium water tap ',
        'صنبور مياه 0.5 إنش (no.) '
    ),
    (
        'pm23',
        'pm23-white glazed hand wash basin size 52x40cm',
        'مغلسة حمام (no.) '
    ),
    (
        'pm24',
        'pm24-shower tray 700 x700mm (ariston type or equivelent).',
        'حوض إستحمام (أريستون) (no.) '
    ),
    (
        'pm25',
        'pm25-white glazed fireclay sink size 610x405mm',
        'حوض ستانليس للمجلى (no.) '
    ),
    (
        'pm26',
        'pm26-white glazed fireclay squatting w.c [european/arabic] ',
        'كرسي حمام (افرنجي أو عربي) (no.) '
    ),
    (
        'pm27',
        'pm27-one layer bituminous membrane with chippings 4mm thick of high ductility (polybeed)',
        'عزل بطبقة بولوبيد بسمك 4 مم (m2) '
    ),
    (
        'pm28',
        'pm28-manhole 40cm internal diameter ',
        'منهل قطر 40 سم (no.) '
    ),
    (
        'pm29',
        'pm29-manhole 50cm internal diameter ',
        'منهل قطر 50 سم (no.) '
    ),
    (
        'pm30',
        'pm29-water pump 0.5 hp including all acessories',
        'مضخة مياه نص حصان مع كافة لوازمها (item) '
    ),
    (
        'pm31',
        'pm31-water pump 0.5 hp including all acessories',
        'مضخة مياه 3/4 حصان مع كافة لوازمها (item) '
    ),
    (
        'pm32',
        'pm32-ditto but 1.0 hp',
        'مضخة مياه  حصان مع كافة لوازمها (item) '
    ),
    (
        'pm33',
        'pm33-septic tank size 165x90x140cm ',
        'بئر مياه صرف صحي  (item) '
    ),
    (
        'pm34',
        'pm34-percolation pit size 240cm internal dimeter and 400cm high',
        'حفرة ترشيح إمتصاصية (item) '
    ),
    (
        'pm35',
        'pm35-submersible water well',
        'بئر مياه غاطس (item) '
    ),
    (
        'pm36',
        'pm36-underground water tank (capacity 5 m3)',
        'خزان مياه أرضي سعة 5 م3 (item) '
    ),
    (
        'pm37',
        'pm37-water lifting pump (capacity in hp)',
        'ماتور رفع مياه (القدرة بالحصان 3 حصان فأكثر) (item) '
    ),
    (
        'pm38',
        'pm38-manhole 60cm internal diameter',
        'منهل صرف صحي قطر 60 سم (no.) '
    ),
    (
        'pm39',
        'pm39-manhole 80cm internal diameter',
        'منهل صرف صحي قطر 80 سم (no.) '
    ),
    ('', '', ' '),
    ('p20', 'electrical works', ' '),
    (
        'el1',
        'el1-three pins 16a socket outlet',
        'إبريز 16 أمبير (no.) '
    ),
    (
        'el2',
        'el2-three pins 16a socket outlet(waterproove)',
        'إبريز 16 أمبير ضد المياه (no.) '
    ),
    (
        'el3',
        'el3-three pins 16a socket outlet  with 3x2.5mm2 wire and conduits from sbd to the points.',
        'إبريز 16 أمبير شاملاً سلك 1.5 مم2 وتوصيله إلى علبة التجميع (no.) '
    ),
    (
        'el4',
        'el4-one gang one way switch (waterproove)',
        'مفتاح مفرد ضد المياه (no.) '
    ),
    (
        'el5',
        'el5-one gang one way switch',
        'مفتاح مفرد  (no.) '
    ),
    (
        'el6',
        'el6-one gang one way switch  with 1.5mm2 wire and conduits from sdb to the point.',
        'مفتاح مفرد شاملاً سلك التوصيل 1.5مم2 إلى علبة التجميع (no.) '
    ),
    (
        'el7',
        'el7-two gang one way switch  inculding all connections.',
        'مفتاح مزوج مع كل التوصيلات (no.) '
    ),
    (
        'el8',
        'el8-tow gang one way switch with 1.5mm2 wire and conduits from sdb to the point.',
        'مفتاح مزوج مع سلك 1.5 مم2 والتوصيل إلى علبة التجميع (no.) '
    ),
    (
        'el9',
        'el9-flourescent lighting fixture 1x40w with lamp and 3x1.5mm2  wire and conduit',
        'نيون مفرد كامل شاملا توصيلاته واللمبة  (no.) '
    ),
    (
        'el10',
        'el10-circular glob 60w with 3x1.5mm2 wire and conduits',
        'كلوب 60 ولت مع السلك وما يلزم (no.) '
    ),
    (
        'el11',
        'el11-ceiling lighting point with 60watt tungesten lamp and wires, conduits',
        'دواية 60 وات مع لمبة وتوصيلاتها (no.) '
    ),
    (
        'el12',
        'el12-supply and install 2x10 mm2xlpe cable with stay wire from mdb toi the main electrical network including isolator porcelain cup',
        'توريد وتركيب كابل 2*10 مم2 من عمود الكهرباء إلى علبة تجميع الكهرباء المنزلية (rm) '
    ),
    (
        'el13',
        'el13-supply and install 2" galvanized steel console 3 meter long',
        'كنزولة قطر 2" وطول 3متر من الحديد المجلفن (item) '
    ),
    (
        'el14',
        'el14-supply and install 1-phase kwhm , abb type with 1x40 a circuit breaker  inside 30x40cm ci box.',
        'عداد كهرباء (no.) '
    ),
    (
        'el15',
        'el15-pvc sdb, 24 way type is siemens or equal approved comprising:- one 1x40a c.b - one 2x40a/0.03 elcb - four 1x10a c.b - six 1x16 a c.b - earth & nutral bus bars.',
        'علبة كهرباء كاملة شاملة أمانات (1*40 أمبير،4*10 أمبير، 6*16 أمبير،وتوصيلات الإيرث وتوصيلات العلبة) (item) '
    ),
    (
        'el16',
        'el16-10-20 rearrange the existing wires using pvc pipe and repair all defects or fault in the internal network',
        'إعادة ترتيب وصيانة شبكة الكهرباء الداخلية (item) '
    ),
    (
        'el17',
        'el17-10-21 supply and install pvc cover for electrical point (circular or rectangular junction boxes)',
        'غطاء علبة تجميع الكهرباء (طبلون) (no.) '
    ),
    (
        'el18',
        'el18-10-22 relocate kwhm to the new location with all necessary needed, cable, conduit and connection.',
        'تغيير مكان علبة تجميع الكهرباء (item) '
    ),
    (
        'el19',
        'el19-10-23 earthing system consists of 150cm cu electrode with 6mm2 stranded wire from sdb to the electrode with concrete manhole 40cm internal diameter and cover r≤5 ohms',
        'نظام الإيرث شاملاً إليكترود 150 سم مع توصيلاته إلى علبة تجميع الكهرباء (item) '
    ),
    (
        'el20',
        'el20-10-24 circuit breaker 1x10a',
        'قاطع كهربائي  1*10 أمبير (no) '
    ),
    (
        'el21',
        'el21-10-25 circuit breaker 1x16a',
        'قاطع كهربائي  1*16 أمبير (no) '
    ),
    (
        'el22',
        'el22-10-26 circuit breaker 1x20a',
        'قاطع كهربائي  1*20 أمبير (no) '
    ),
    (
        'el23',
        'el23-10-27 2x40a/0.03 elcb',
        'أمان الحياة 2/40 أمبير (no) '
    ),
    (
        'el24',
        'el24-flourescent lighting fixture 2x40w with lamp and 3x1.5mm2  wire and conduit',
        'نيون مزدوج كامل شاملا توصيلاته واللمبة  (no.) '
    ),
    (
        'el25',
        'el25-circular water proof glob 60w with 3x1.5mm2 wire and conduits',
        'دواية 60 وات مع لمبة وتوصيلاتها ضد المياه (no.) '
    ),
    (
        'el26',
        'el26-supply and installation of ceiling fan',
        'توريد وتركيب مروحة سقف (no.) '
    ),
    (
        'el27',
        'el27-supply and installation of lighting spotlights',
        'توريد وتركيب سبوتات إنارة (no.) '
    ),
    (
        'el28',
        'el28-main distribution board (mdb) - main electrical panel (amperage connection)',
        'طبلون كهرباء رئيسي (لوحة توزيع رئيسية) (الاشتراك بالأمبير) (item) '
    ),
    (
        'el29',
        'el29-cctv system (surveillance cameras)',
        'نظام مراقبة (كاميرات) (item) '
    ),
    (
        'el30',
        'el30-generator (capacity in kw) (total damage / maintenance)',
        'مولد (القدرة بالكيلو وات) (ضرر كلي / صيانة) (item) '
    ),
    ('p21', 'pv system works', ' '),
    (
        'pv_note',
        'منظومة الخلايا الشمسية',
        'قدرة المنظومة بالكامل بالوط '
    ),
    (
        'pv1',
        'pv1- total solar system capacity (w)',
        'القدرة الإجمالية للنظام الشمسي (واط) '
    ),
    (
        'pv2',
        'pv2- inverter (1k)',
        'عدد الانفيرتر1 كيلو '
    ),
    (
        'pv3',
        'pv2- inverter (2k)',
        'عدد الانفيرتر2 كيلو '
    ),
    (
        'pv4',
        'pv2- inverter (3k)',
        'عدد الانفيرتر3 كيلو '
    ),
    (
        'pv5',
        'pv3- inverter (5k)',
        'عدد الانفيرتر 5 كيلو '
    ),
    (
        'pv6',
        'pv4- inverter (> 5k)',
        'عدد الانفيرتر أكبر من 7 كيلو '
    ),
    (
        'pv7',
        'batteries 100',
        'عدد البطاريات 100 أميبر '
    ),
    (
        'pv8',
        'batteries 200',
        'عدد البطاريات 200 أميبر '
    ),
    ('pv9', 'batteries 2v', 'عدد البطاريات 2 فولت '),
    (
        'pv10',
        'batteries 3k',
        'عدد البطاريات الليثوم 3 كيلو '
    ),
    (
        'pv11',
        'batteries 5k ',
        'عدد البطاريات الليثوم 5 كيلو '
    ),
    (
        'pv12',
        'pv5- distribution board',
        'طبلون المنطومة '
    ),
    ('p22', 'miscellaneous works', ' '),
    ('item1', 'item 1', 'وصف البند مع إضافة الوحدة '),
    ('quant1', 'quantity', 'الكمية '),
    ('item2', 'item 2', 'وصف البند مع إضافة الوحدة '),
    ('quant2', 'quantity', 'الكمية '),
    ('item3', 'item 3', 'وصف البند مع إضافة الوحدة '),
    ('quant3', 'quantity', 'الكمية '),
    ('item4', 'item 4', 'وصف البند مع إضافة الوحدة '),
    ('quant4', 'quantity', 'الكمية '),
    ('item5', 'item 5', 'وصف البند مع إضافة الوحدة '),
    ('quant5', 'quantity', 'الكمية '),
    (
        'photos_final_comments_unilt',
        'photos & final comments',
        ' '
    ),
    (
        'damge_photo_1',
        'take a photo for the damaged unit',
        'خذ صورة للوحدة المتضررة '
    );