DROP TABLE IF EXISTS `filters`;

CREATE TABLE
    `filters` (
        id INT PRIMARY KEY AUTO_INCREMENT,
        `list_name` VARCHAR(512),
        `name` VARCHAR(512),
        `label` VARCHAR(512)
    );

INSERT INTO
    `filters` (`list_name`, `name`, `label`)
VALUES

    ('yes_no','yes','Yes'),
    ('field_status','COMPLETED','COMPLETED'),
    ('field_status','Not_Completed','Not Completed'),
    ('yes_no','no','No'),
    ('weather','fine','Fine'),
    ('weather','windy','Windy'),
    ('weather','rainy','Rainy'),
    ('security','Safe','لا يوجد عائق'),
    ('security','Unsafe','يوجد عائق'),
     ('security_situation','Safe','لا يوجد عائق'),
    ('security_situation','Unsafe','يوجد عائق'),
    ('building_debris_exist','yes','نعم'),
    ('building_debris_exist','no','لا'),
    ('is_damaged_before','yes','نعم'),
    ('is_damaged_before','no','لا'),
        ('current_address','yes','نعم'),
    ('current_address','no','لا'),
            ('occupied','yes','نعم'),
              ('is_refugee','no','لا'),
            ('is_refugee','yes','نعم'),
               ('has_sewage','no','لا'),
            ('has_sewage','yes','نعم'),
                  ('has_well','no','لا'),
            ('has_well','yes','نعم'),
                ('has_solar','no','لا'),
            ('has_solar','yes','نعم'),
                ('has_elevator','no','لا'),
            ('has_elevator','yes','نعم'),
                  ('is_finished','no','لا'),
                   ('has_electric_room','no','لا'),
            ('has_electric_room','yes','نعم'),
            ('is_finished','yes','نعم'),
                              ('is_the_housing_unit_or_living_habitable','no','لا'),
            ('is_the_housing_unit_or_living_habitable','yes','نعم'),
                                ('community_participation','no','لا'),
            ('community_participation','yes','نعم'),
                           ('prefab_moving','no','لا'),
            ('prefab_moving','yes','نعم'),
                         ('reh_kitchen','no','لا'),
            ('reh_kitchen','yes','نعم'),
                         ('reh_bathroom','no','لا'),
            ('reh_bathroom','yes','نعم'),
                ('bodies_present','no3','لا'),
            ('bodies_present','yes3','نعم'),
            ('bodies_present','notsure3','لست متأكد'),
                       ('building_authorization','no','لا'),
            ('building_authorization','yes','نعم'),
              ('land_fully_owned','no','لا'),
            ('land_fully_owned','yes','نعم'),
                  ('has_documents','no','لا'),
            ('has_documents','yes','نعم'),
              ('has_fence','no','لا'),
            ('has_fence','yes','نعم'),
               ('has_dispute','no','لا'),
            ('has_dispute','yes','نعم'),
              ('has_parking','no','لا'),
            ('has_parking','yes','نعم'),
               ('has_canopy','no','لا'),
            ('has_canopy','yes','نعم'),
                  ('has_basement','no','لا'),
            ('has_basement','yes','نعم'),
                ('has_mezzanine','no','لا'),
            ('has_mezzanine','yes','نعم'),
            ('fence_damage_status','damaged','يوجد ضرر'),
            ('fence_damage_status','no_damage','لا يوجد ضرر'),
               ('has_well','no','لا'),
            ('has_well','yes','نعم'),
    ('occupied','no','لا'),
    ('uxo_present','yes3','نعم'),
    ('uxo_present','no3','لا'),
    ('unit_committee_status','Yes','نعم'),
    ('unit_committee_status','No','لا'),
    (
       'visit_status',
       'not_visited',
       'Not visited  لم يتم زيارة المبنى'
    ),
    (
       'visit_status',
       'partial_done',
       'Partial assessment completed  تم عمل حصر جزئي'
    ),
    (
       'visit_status',
       'full_done',
       'Full assessment completed  تم عمل الحصر بالكامل'
    ),
    (
       'building_damage_status',
       'fully_damaged',
       'Totally Damaged'
    ),
    (
       'building_damage_status',
       'partially_damaged',
       'Partially Damaged'
    ),
    (
       'building_damage_status',
       'committee_review',
       'Committee Review'
    ),
    (
       'unit_damage_Status',
       'fully_damaged2',
       'Totally Damaged'
    ),
    (
       'unit_damage_Status',
       'partially_damaged2',
       'Partially Damaged'
    ),
    (
       'unit_damage_Status',
       'committee_review2',
       'Committee Review'
    ),
    ('building_type','house1','House/منزل'),
    ('building_type','villa','Villa/فيلا'),
    ('building_type','building','Building/مبنى'),
    ('building_type','canopy','Canopy/مظلة'),
    ('building_type','tower','Tower/برج'),
    (
       'building_type',
       'building_other',
       'Other/أخرى'
    ),
    ('building_material','concrete','Concrete'),
    ('building_material','metal','Metal'),
    ('building_material','asbestos','Asbestos'),
    ('building_material','wood','Wood'),
    ('building_material','other_material','Other'),
    ('building_age','years0_5','0-5 years'),
    ('building_age','years6_10','6-10 years'),
    ('building_age','years11_20','11-20 years'),
    ('building_age','years21_50','21-50 years'),
    ('building_age','years51_100','51-100 years'),
    ('building_age','years101more','101+ years'),
    ('building_age','not_sure','Not sure'),
    ('building_debris_qty','Small','Small (≤10 m³)'),
    ('building_debris_qty','Medium','Medium (10–30 m³)'),
    ('building_debris_qty','Large','Large (>30 m³)'),
    ('yes_no_notsure','yes3','Yes'),
    ('yes_no_notsure','no3','No'),
    ('yes_no_notsure','notsure3','Not Sure'),
    (
       'building_status_visit',
       'standing_partial_remove',
       'Standing but needs to be removed/  قائم لكنه بحاجة للإزالة'
    ),
    (
       'building_status_visit',
       'removed',
       'Removed / تمت الإزالة'
    ),
    (
       'building_status_visit',
       'rubble',
       'Rubble / أنقاض'
    ),
    (
       'building_status_visit',
       'dangerous',
       'Dangerous / خطير'
    ),
    ('building_roof_type','clay_tile','Clay Tile / قرميد'),
    ('building_roof_type','concrete2','Concrete / باطون'),
    ('building_roof_type','asbestos2','Asbestos / اسبست'),
    (
       'building_roof_type',
       'secorite',
       'Iron Sheets (Secorite) / صاج'
    ),
    ('building_roof_type','other_roof','Other / أخرى'),
    (
       'building_use',
       'residential',
       ' Residential / للسكن'
    ),
    ('building_use','work','   Work / للعمل'),
    (
       'building_use',
       'combined',
       'Combined / للعمل والسكن'
    ),
    (
       'building_ownership',
       'single',
       'Single owner / ملكية فردية'
    ),
    (
       'building_ownership',
       'multiple',
       'multiple  owner / ملكية مشتركة'
    ),
    (
       'building_responsible  ',
       'owner      ',
       'Owner المالك     '
    ),
    (
       'building_responsible  ',
       'board',
       'Board of management مجلس الإدرا ة'
    ),
    (
       'building_responsible  ',
       ' heirs',
       'Heirs (owner is deceased) ورثة (المالك متوفي)'
    ),
    (
       'owner_status',
       'Owner_present',
       'Owner present / المالك موجود'
    ),
    (
       'owner_status',
       'Owner_captive',
       'Owner detained / captive المالك أسير'
    ),
    (
       'owner_status',
       'Owner_deceased',
       'Owner deceased المالك متوفى'
    ),
    (
       'owner_status',
       'Owner_missing',
       'Owner missing المالك مفقود'
    ),
    (
       'doc_type',
       'title_deed',
       'Title deed / Land registry / سند/طابو'
    ),
    (
       'doc_type',
       'tax_record',
       'Tax record / مستند/سجل ضريبي'
    ),
    (
       'doc_type',
       'purchase_contract',
       'Purchase contract / عقد بيع/شراء'
    ),
    (
       'doc_type',
       'rental_contract',
       'Rental contract / عقد إيجار'
    ),
    (
       'doc_type',
       'utility_bill',
       'Utility bill / فاتورة خدمات'
    ),
    ('doc_type','other_doc','Other / أخرى'),
    (
       'doc_challenges',
       'lost',
       'Documents lost / فقدان الوثائق'
    ),
    (
       'doc_challenges',
       'access',
       'Difficulty accessing authorities/records /  صعوبة الوصول للجهات/السجلات'
    ),
    (
       'doc_challenges',
       'legal',
       'Legal issues / مشاكل قانونية'
    ),
    (
       'doc_challenges',
       'cost',
       'Financial cost / تكلفة مالية'
    ),
    (
       'doc_challenges',
       'other_challenge',
       'Other /  أخرى'
    ),
    (
       'dispute_types',
       'with_owner',
       'خلاف مع مالك آخر / Dispute with another owner'
    ),
    (
       'dispute_types',
       'with_state',
       'خلاف مع الدولة/جهة حكومية / Dispute with the state/government'
    ),
    (
       'dispute_types',
       'boundary',
       'خلاف على حدود / Boundary dispute'
    ),
    (
       'dispute_types',
       'other_dispute_type',
       'أخرى / Other'
    ),
    (
       'classification',
       'excellent',
       'الوضع ممتاز: أوراق كاملة، لا نزاع، ملكية واضحة'
    ),
    (
       'classification',
       'disputed',
       'متنازع عليها: خلاف مع ملاك آخر أو على حدود أو حقوق استخدام'
    ),
    (
       'classification',
       'missing_docs',
       'نقص مستندات: بعض الأوراق مفقودة أو غير مكتملة'
    ),
    (
       'building_documents',
       'id2',
       ' Id  / صورة الهوية'
    ),
    (
       'building_documents',
       'ownership',
       ' Ownership_Document / إثبات  ملكية الأرض/الشقة'
    ),
    (
       'building_documents',
       'permit',
       ' Permit / رخصة البلدية'
    ),
    (
       'building_documents',
       'other_b_doc',
       'Other / أخرى'
    ),
    (
       'housing_unit_type',
       'basement',
       'بدروم / basement'
    ),
    (
       'housing_unit_type',
       'apartment',
       'شقة / apartment'
    ),
    ('housing_unit_type','roof','روف / roof'),
    (
       'housing_unit_type',
       'warehouse',
       'حاصل /  warehouse'
    ),
    (
       'housing_unit_type',
       'canopy2',
       'مظلة / canopy'
    ),
    (
       'housing_unit_type',
       'mezzanine',
       'سدة /  mezzanine'
    ),
    ('agreement_type','verbal','Verbal  شفهي'),
    ('agreement_type','written','Written  كتابي'),
    (
       'agreement_type',
       'unknown',
       'Unknown  غير معروف'
    ),
    (
       'service_status',
       'functional',
       'Functional  سليم'
    ),
    (
       'service_status',
       'partially_damaged3',
       'Partially damaged  متضرر جزئياً'
    ),
    (
       'service_status',
       'fully_damaged3',
       'Fully damaged  متضرر كلياً'
    ),
    (
       'service_status',
       'not_usable',
       'Not usable  غير صالح للاستخدام'
    ),
    (
       'damage_status',
       'no_damage',
       'No damage لا يوجد ضرر'
    ),
    ('damage_status','damaged','Damaged يوجد ضرر'),
    ('infra_type2','Housing','Housing سكني'),
    ('infra_type2','Economic','Economic اقتصادي'),
    ('infra_type2','Social','Social اجتماعي'),
    ('identity_type1','idd','ID هوية'),
    (
       'identity_type1',
       'passport',
       'Passport جواز سفر'
    ),
    ('identity_type1','other_id','Other أخرى'),
    ('gender','male','ذكر / Male'),
    ('gender','female','أنثى / Female'),
    ('job','employed','موظف / Employed'),
    ('job','freelancer','عمل حر / Freelancer'),
    ('job','unemployed2','غير موظف / Unemployed'),
    ('job','other_job','أخرى / Other'),
    ('marital_status','Single2','Single / أعزب'),
    (
       'marital_status',
       'Divorced',
       'Divorced  / مطلق/ة'
    ),
    ('marital_status','Widow','Widow / أرمل/ة'),
    (
       'marital_status',
       'Married',
       'Married  / متزوج/ة'
    ),
    (
       'current_residence',
       'rented2',
       'Rented accommodation  سكن مستأجر'
    ),
    (
       'current_residence',
       'hosted2',
       'With relatives / hosted  عند أقارب / مستضاف'
    ),
    ('current_residence','tent2','Tent  خيمة'),
    (
       'current_residence',
       'rented2',
       'Rented accommodation  سكن مستأجر'
    ),
    (
       'current_residence',
       'hosted2',
       'With relatives / hosted  عند أقارب / مستضاف'
    ),
    ('current_residence','tent2','Tent  خيمة'),
    (
       'current_residence',
       'collective_shelter2',
       'Collective shelter  مركز إيواء جماعي'
    ),
    (
       'current_residence',
       'public_facility2',
       'Public facility  مرفق عام'
    ),
    (
       'current_residence',
       'informal2',
       'Informal shelter  سكن غير رسمي'
    ),
    (
       'current_residence',
       'other_current2',
       'Other  أخرى'
    ),
    ('','',''),
    ('','',''),
    (
       'handicapped_type',
       'Wheelchair',
       'Wheelchair / كرسي متحرك'
    ),
    ('handicapped_type','Blind','Blind / كفيف'),
    (
       'handicapped_type',
       'Physically',
       'Physically disabled / معاق حركيا'
    ),
    ('handicapped_type','Elderly','Elderly / مسن'),
    ('handicapped_type','Mental','Mental / معاق عقليا'),
    (
       'handicapped_type',
       'other_handicapped_type',
       'Other / أخرى'
    ),
    ('','',''),
    ('','',''),
    ('shelter_type','school','School  مدرسة'),
    (
       'shelter_type',
       'public_building',
       'Public building  مبنى عام'
    ),
    ('shelter_type','hospital','Hospital  مستشفى'),
    (
       'shelter_type',
       'public_service_facility',
       'Public service facility  مرافق عامة'
    ),
    ('shelter_type','park','Park  حديقة'),
    (
       'shelter_type',
       'Private_Land',
       'أرض خاصة / Private Land'
    ),
    ('shelter_type','playground','Playground  ملعب'),
    ('shelter_type','camp','مخيم / Camp'),
    ('shelter_type','other_shelter','Other  أخرى'),
    ('','',''),
    ('','',''),
    ('governorate','North','North / شمال غزة'),
    ('governorate','Gaza','Gaza  / غزة'),
    (
       'governorate',
       'Middle_Area',
       'Middle Area  / الوسطى'
    ),
    (
       'governorate',
       'Khan_Younis',
       'Khan Younis  / خانيونس'
    ),
    ('governorate','Rafah','Rafah  / رفح'),
    ('','',''),
    (
       'locality',
       'Um_AlNasser',
       'Um_AlNasser أم النصر'
    ),
    (
       'locality',
       'Bait_Hanoun',
       'Bait_Hanoun بيت حانون'
    ),
    ('locality','Jabalia','Jabalia جباليا'),
    ('locality','Bait_Lahia','Bait_Lahia بيت لاهيا'),
    ('locality','Gaza_loc','Gaza غزة'),
    (
       'locality',
       'Juhr_Aldeek',
       'Juhr_Aldeek جحر الديك'
    ),
    ('locality','Almughraqa','Almughraqa المغراقة'),
    ('locality','Alzahra','Alzahra الزهرة'),
    ('locality','AlNusairat','AlNusairat النصيرات'),
    ('locality','Alburaje','Alburaje البريج'),
    ('locality','AlMaghazi','AlMaghazi المغازي'),
    ('locality','AlMusadar','AlMusadar المصدر'),
    (
       'locality',
       'Wadi_Alsalqa',
       'Wadi_Alsalqa وادي السلقا'
    ),
    (
       'locality',
       'Dair_Albalah',
       'Dair_Albalah دير البلح'
    ),
    ('locality','Alzawayda','Alzawayda الزوايدة'),
    ('locality','Khanyounis','Khanyounis خانيونس'),
    ('locality','Alqarara','Alqarara القرارة'),
    (
       'locality',
       'Bani_Sohaila',
       'Bani_Sohaila بني سهيلا'
    ),
    (
       'locality',
       'Abasan_AlJadida',
       'Abasan_AlJadida عبسان الجديدة'
    ),
    (
       'locality',
       'Abasan_alkabeira',
       'Abasan_alkabeira عبسان الكبيرة'
    ),
    ('locality','Khuzaa','Khuzaa خزاعة'),
    ('locality','AlFukhari','AlFukhari الفخاري'),
    ('locality','Rafah_loc','Rafah رفح '),
    ('locality','AlNasr','AlNasser النصر'),
    ('locality','AlShuka','AlShuka الشوكة'),
    ('neighborhood','Alsyfa','Alsyfa - السيفا'),
    (
       'neighborhood',
       'Alshykh_Zayd_Aqlybw',
       'Alshykh_Zayd_Aqlybw - الشيخ زايد - اقليبو'
    ),
    ('neighborhood','Almnshyh','Almnshyh - المنشية'),
    ('neighborhood','Alshyma','Alshyma - الشيماء'),
    (
       'neighborhood',
       'Alqwasmh',
       'Alqwasmh - القواسمة'
    ),
    (
       'neighborhood',
       'Alaml_Albrkh',
       'Alaml_Albrkh - الأمل - البركة'
    ),
    (
       'neighborhood',
       'Ber_Alnajh',
       'Ber_Alnajh - بئر النعجة'
    ),
    (
       'neighborhood',
       'Mrkz_Albld',
       'Mrkz_Albld - مركز البلد'
    ),
    ('neighborhood','AlإSra','AlإSra - الإسراء'),
    (
       'neighborhood',
       'Alatatrh',
       'Alatatrh - العطاطرة'
    ),
    (
       'neighborhood',
       'Alslatyn',
       'Alslatyn - السلاطين'
    ),
    (
       'neighborhood',
       'Almntqh_Alzraayh_Alshrqyh',
       'Almntqh_Alzraayh_Alshrqyh - المنطقة الزراعية الشرقية'
    ),
    (
       'neighborhood',
       'Azbh_Byt_Hanwn',
       'Azbh_Byt_Hanwn - عزبة بيت حانون'
    ),
    ('neighborhood','Alskh','Alskh - السكة'),
    ('neighborhood','Dmrh','Dmrh - دمرة'),
    ('neighborhood','Alzytwn.','Alzytwn - الزيتون'),
    (
       'neighborhood',
       'Almntqh_Alzraayh_Alshmalyh',
       'Almntqh_Alzraayh_Alshmalyh - المنطقة الزراعية الشمالية'
    ),
    (
       'neighborhood',
       'Alsnaayh',
       'Alsnaayh - الصناعية'
    ),
    (
       'neighborhood',
       'Basl_Naym',
       'Basl_Naym - باسل نعيم'
    ),
    ('neighborhood','Alqrman','Alqrman - القرمان'),
    (
       'neighborhood',
       'Alqryh_Alawla',
       'Alqryh_Alawla - القرية الأولى'
    ),
    (
       'neighborhood',
       'Alqryh_Althanyh',
       'Alqryh_Althanyh - القرية الثانية'
    ),
    ('neighborhood','Alkramh.','Alkramh - الكرامة'),
    ('neighborhood','Alslam3','Alslam - السلام'),
    (
       'neighborhood',
       'Jbalya_Alshrqyh',
       'Jbalya_Alshrqyh - جباليا الشرقية'
    ),
    ('neighborhood','Alnwr','Alnwr - النور'),
    (
       'neighborhood',
       'Tl_Alzatr',
       'Tl_Alzatr - تل الزعتر'
    ),
    ('neighborhood','Alrwdh','Alrwdh - الروضة'),
    (
       'neighborhood',
       'Albldh_Alqdymh.',
       'Albldh_Alqdymh - البلدة القديمة'
    ),
    ('neighborhood','Alnzhh.','Alnzhh - النزهة'),
    ('neighborhood','Alzhwr.','Alzhwr - الزهور'),
    ('neighborhood','Alnhdh','Alnhdh - النهضة'),
    (
       'neighborhood',
       'Abad_Alrhmn',
       'Abad_Alrhmn - عباد الرحمن'
    ),
    ('neighborhood','Alabraj','Alabraj - الأبراج'),
    ('neighborhood','Albnat','Albnat - البنات'),
    (
       'neighborhood',
       'Abd_Aldaym',
       'Abd_Aldaym - عبد الدايم'
    ),
    (
       'neighborhood',
       'Albld_Alqdym',
       'Albld_Alqdym - البلد القديم'
    ),
    ('neighborhood','Alnzaz','Alnzaz - النزاز'),
    (
       'neighborhood',
       'Almsryyn',
       'Almsryyn - المصريين'
    ),
    ('neighborhood','Alfrth','Alfrth - الفرطة'),
    (
       'neighborhood',
       'Alqtbanyh',
       'Alqtbanyh - القطبانية'
    ),
    ('neighborhood','Alaml1','Alaml - الأمل'),
    (
       'neighborhood',
       'Mkhym_Jbalya',
       'Mkhym_Jbalya - مخيم جباليا'
    ),
    (
       'neighborhood',
       'Mshrwa_Byt_Lahya',
       'Mshrwa_Byt_Lahya - مشروع بيت لاهيا'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm2.',
       'Area No.2 - منطقة رقم2'
    ),
    (
       'neighborhood',
       'Abw_Taymh',
       'Abw_Taymh - أبو طعيمة'
    ),
    ('neighborhood','Qdyh','Qdyh - قديح'),
    ('neighborhood','Alznh','Alznh - الزنة'),
    ('neighborhood','Almnarh','Almnarh - المنارة'),
    ('neighborhood','Alshabh2','Alshabh - الصحابة'),
    (
       'neighborhood',
       'Almwasy_Alshmaly',
       'Almwasy_Alshmaly - المواصي الشمالي'
    ),
    (
       'neighborhood',
       'Almwasy_Aljnwby',
       'Almwasy_Aljnwby - المواصي الجنوبي'
    ),
    ('neighborhood','Alnsr2','Alnsr - النصر'),
    ('neighborhood','Alstr','Alstr - السطر'),
    ('neighborhood','Aljla','Aljla - الجلاء'),
    ('neighborhood','Alktybh','Alktybh - الكتيبة'),
    ('neighborhood','Althryr','Althryr - التحرير'),
    ('neighborhood','Almhth','Almhth - المحطة'),
    (
       'neighborhood',
       'Albtn_Alsmyn',
       'Albtn_Alsmyn - البطن السمين'
    ),
    (
       'neighborhood',
       'Qyzan_Abw_Rshwan',
       'Qyzan_Abw_Rshwan - قيزان أبو رشوان'
    ),
    (
       'neighborhood',
       'Mrkz_Almdynh',
       'Mrkz_Almdynh - مركز المدينة'
    ),
    (
       'neighborhood',
       'Alshykh_Nasr',
       'Alshykh_Nasr - الشيخ ناصر'
    ),
    ('neighborhood','Alslam1.','Alslam - السلام'),
    (
       'neighborhood',
       'Qyzan_Alnjar',
       'Qyzan_Alnjar - قيزان النجار'
    ),
    (
       'neighborhood',
       'Qaa_Alqryn',
       'Qaa_Alqryn - قاع القرين'
    ),
    (
       'neighborhood',
       'Jwrh_Allwt',
       'Jwrh_Allwt - جورة اللوت'
    ),
    ('neighborhood','Man','Man - معن'),
    ('neighborhood','Alshhda.','Alshhda - الشهداء'),
    ('neighborhood','Alansar2','Alansar - الأنصار'),
    ('neighborhood','Alslam2','Alslam - السلام'),
    (
       'neighborhood',
       'Slah_Aldyn3',
       'Slah_Aldyn - صلاح الدين'
    ),
    ('neighborhood','Mkh','Mkh - مكة'),
    ('neighborhood','Alzlal','Alzlal - الظلال'),
    ('neighborhood','Almrwj','Almrwj - المروج'),
    ('neighborhood','Armydh','Armydh - ارميضه'),
    ('neighborhood','Alrdwan','Alrdwan - الرضوان'),
    ('neighborhood','Alanwar','Alanwar - الأنوار'),
    ('neighborhood','Alsnaty','Alsnaty - السناطي'),
    ('neighborhood','Alawdh1','Alawdh - العودة'),
    (
       'neighborhood',
       'Alshhadyh',
       'Alshhadyh - الشحادية'
    ),
    (
       'neighborhood',
       'Alfrahyn',
       'Alfrahyn - الفراحين'
    ),
    ('neighborhood','Qdyh1','Qdyh - قديح'),
    ('neighborhood','Alaskry','Alaskry - العسكري'),
    ('neighborhood','Almwl','Almwl - المول'),
    (
       'neighborhood',
       'Abw_Ywsf',
       'Abw_Ywsf - أبو يوسف'
    ),
    ('neighborhood','Alshwaf','Alshwaf - الشواف'),
    (
       'neighborhood',
       'Abw_Rydh',
       'Abw_Rydh - أبو ريدة'
    ),
    ('neighborhood','Alnjar','Alnjar - النجار'),
    ('neighborhood','Altqwa1','Altqwa - التقوى'),
    (
       'neighborhood',
       'Mntqh_Rqm7..',
       'Area No.7 - منطقة رقم7'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm5..',
       'Area No.5 - منطقة رقم5'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm4..',
       'Area No.4- منطقة رقم4'
    ),
    ('neighborhood','Alshrwq','Alshrwq - الشروق'),
    ('neighborhood','Alnaym','Alnaym - النعيم'),
    ('neighborhood','Alrbya','Alrbya - الربيع'),
    ('neighborhood','Alwsty','Alwsty - الوسطي'),
    ('neighborhood','Alslam1','Alslam - السلام'),
    ('neighborhood','Alwrwd','Alwrwd - الورود'),
    (
       'neighborhood',
       'Am_Alwad',
       'Am_Alwad - أم الواد'
    ),
    ('neighborhood','Alshrqy','Alshrqy - الشرقي'),
    (
       'neighborhood',
       'Mntqh_Rqm6..',
       'Area No.6 - منطقة رقم6'
    ),
    ('neighborhood','Albldyh','Albldyh - البلدية'),
    (
       'neighborhood',
       'Askan_Alawrwby',
       'Askan_Alawrwby - اسكان الأوروبي'
    ),
    (
       'neighborhood',
       'Albhr_Walmwasy',
       'Albhr_Walmwasy - البحر والمواصي'
    ),
    (
       'neighborhood',
       'Almhrrat.',
       'Almhrrat - المحررات'
    ),
    (
       'neighborhood',
       'Mntqh_Al_86',
       'Area No.86 - منطقة الـ 86'
    ),
    (
       'neighborhood',
       'Almntqh_Alghrbyh',
       'Almntqh_Alghrbyh - المنطقة الغربية'
    ),
    ('neighborhood','Fyad','Fyad - فياض'),
    (
       'neighborhood',
       'Alshykh_Hmwdh',
       'Alshykh_Hmwdh - الشيخ حمودة'
    ),
    (
       'neighborhood',
       'Alabadlh_Walastl',
       'Alabadlh_Walastl - العبادلة والأسطل'
    ),
    (
       'neighborhood',
       'Mkhym_Khan_Ywns',
       'Mkhym_Khan_Ywns - مخيم خان يونس'
    ),
    ('neighborhood','Alrbat.','Alrbat - الرباط'),
    ('neighborhood','Alaml.','Alaml - الأمل'),
    ('neighborhood','Alawdh..','Alawdh - العودة'),
    (
       'neighborhood',
       'Abw_Alajyn',
       'Abw_Alajyn - أبو العجين'
    ),
    ('neighborhood','Alfyrwz','Alfyrwz - الفيروز'),
    ('neighborhood','Albrkh','Albrkh - البركة'),
    (
       'neighborhood',
       'Mntqh_Rqm4.',
       'Area No.4 - منطقة رقم4'
    ),
    ('neighborhood','Alsdrh','Alsdrh - السدرة'),
    ('neighborhood','Alsfa..','Alsfa - الصفا'),
    (
       'neighborhood',
       'Aldmytha',
       'Aldmytha - الدميثاء'
    ),
    ('neighborhood','Alansar1','Alansar - الأنصار'),
    (
       'neighborhood',
       'Slah_Aldyn2',
       'Slah_Aldyn - صلاح الدين'
    ),
    ('neighborhood','Almtayn','Almtayn - المطاين'),
    (
       'neighborhood',
       'Alharh_Alshrqyh',
       'Alharh_Alshrqyh - الحارة الشرقية'
    ),
    ('neighborhood','Albld','Albld - البلد'),
    ('neighborhood','Albsh','Albsh - البصة'),
    ('neighborhood','Alqraan','Alqraan - القرعان'),
    ('neighborhood','Bsharh','Bsharh - بشارة'),
    (
       'neighborhood',
       'Abw_Aryf',
       'Abw_Aryf - أبو عريف'
    ),
    ('neighborhood','Alhdbh','Alhdbh - الحدبة'),
    ('neighborhood','Alhkr','Alhkr - الحكر'),
    (
       'neighborhood',
       'Am_Alazban',
       'Am_Alazban - ام العزبان'
    ),
    (
       'neighborhood',
       'Aljafrawy',
       'Aljafrawy - الجعفراوي'
    ),
    ('neighborhood','Am_Zhyr','Am_Zhyr - أم ظهير'),
    (
       'neighborhood',
       'Almshaalh_Abw_Fyad',
       'Almshaalh_Abw_Fyad - المشاعلة أبو فياض'
    ),
    ('neighborhood','Bwba','Bwba - بوبع'),
    (
       'neighborhood',
       'Abw_Khyshh',
       'Abw_Khyshh - أبو خيشة'
    ),
    ('neighborhood','Aldawh','Aldawh - الدعوة'),
    (
       'neighborhood',
       'Alastqamh',
       'Alastqamh - الاستقامة'
    ),
    (
       'neighborhood',
       'Brkh_Alwz',
       'Brkh_Alwz - بركة الوز'
    ),
    (
       'neighborhood',
       'Abw_Mzyd',
       'Abw_Mzyd - أبو مزيد'
    ),
    ('neighborhood','Alsbkhh','Alsbkhh - السبخة'),
    (
       'neighborhood',
       'Mntqh_Rqm13',
       'Area No.13 - منطقة رقم13'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm8',
       'Area No.8 - منطقة رقم8'
    ),
    (
       'neighborhood',
       'Alaarwqy',
       'Alaarwqy - العاروقي'
    ),
    (
       'neighborhood',
       'Alzafran',
       'Alzafran - الزعفران'
    ),
    (
       'neighborhood',
       'Almntqh_Alzraayh',
       'Almntqh_Alzraayh - المنطقة الزراعية'
    ),
    (
       'neighborhood',
       'Almntqh_Alsnaayh',
       'Almntqh_Alsnaayh - المنطقة الصناعية'
    ),
    (
       'neighborhood',
       'Abw_Jlalh',
       'Abw_Jlalh - أبو جلالة'
    ),
    ('neighborhood','Alkramh','Alkramh - الكرامة'),
    ('neighborhood','Alnzhh','Alnzhh - النزهة'),
    (
       'neighborhood',
       'Mntqh_Rqm7.',
       'Area No.7 - منطقة رقم7'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm6.',
       'Area No.6 - منطقة رقم6'
    ),
    ('neighborhood','Alansar.','Alansar - الأنصار'),
    (
       'neighborhood',
       'Mntqh_Rqm5.',
       'Area No.5 - منطقة رقم5'
    ),
    (
       'neighborhood',
       'Almntqh_Alshrqyh',
       'Almntqh_Alshrqyh - المنطقة الشرقية'
    ),
    ('neighborhood','Alslam.','Alslam - السلام'),
    ('neighborhood','Alfarwq','Alfarwq - الفاروق'),
    ('neighborhood','Alaml','Alaml - الأمل'),
    ('neighborhood','Alawdh.','Alawdh - العودة'),
    ('neighborhood','Alsdyq','Alsdyq - الصديق'),
    ('neighborhood','Alshabh.','Alshabh - الصحابة'),
    ('neighborhood','Alwahh','Alwahh - الواحة'),
    (
       'neighborhood',
       'Tl_Alzhwr',
       'Tl_Alzhwr - تل الزهور'
    ),
    ('neighborhood','Alrhmh.','Alrhmh - الرحمة'),
    (
       'neighborhood',
       'Slah_Aldyn',
       'Slah_Aldyn - صلاح الدين'
    ),
    ('neighborhood','Alansar','Alansar - الأنصار'),
    (
       'neighborhood',
       'Mkhym_Dyr_Alblh',
       'Mkhym_Dyr_Alblh - مخيم دير البلح'
    ),
    ('neighborhood','Alshrth','Alshrth - الشرطة'),
    (
       'neighborhood',
       'Abw_Mghsyb',
       'Abw_Mghsyb - أبو مغصيب'
    ),
    (
       'neighborhood',
       'Almkhym_Aljdyd',
       'Almkhym_Aljdyd - المخيم الجديد'
    ),
    (
       'neighborhood',
       'Abw_Mhady',
       'Abw_Mhady - أبو مهادي'
    ),
    (
       'neighborhood',
       'Alhsaynh_Alghrby',
       'Alhsaynh_Alghrby - الحساينة الغربي'
    ),
    ('neighborhood','AlآThar','AlآThar - الآثار'),
    (
       'neighborhood',
       'Abwslym_Alghrby',
       'Abwslym_Alghrby - أبوسليم الغربي'
    ),
    ('neighborhood','F_Blwk','F_Blwk - F بلوك'),
    (
       'neighborhood',
       'Abw_Slym_Alshrqy',
       'Abw_Slym_Alshrqy - أبو سليم الشرقي'
    ),
    ('neighborhood','Srswr','Srswr - صرصور'),
    ('neighborhood','Alshhda','Alshhda - الشهداء'),
    ('neighborhood','C_Blwk','C_Blwk - C بلوك'),
    ('neighborhood','Almfty','Almfty - المفتي'),
    ('neighborhood','Mtr','Mtr - مطر'),
    (
       'neighborhood',
       'Mntqh_Rqm6',
       'Area No.6 - منطقة رقم6'
    ),
    (
       'neighborhood',
       'Abw_Amyrh',
       'Abw_Amyrh - أبو عميرة'
    ),
    ('neighborhood','Alslam','Alslam - السلام'),
    (
       'neighborhood',
       'Mntqh_Rqm1.',
       'Area No.1 - منطقة رقم1'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm2..',
       'Area No.2 - منطقة رقم2'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm3..',
       'Area No.3 - منطقة رقم3'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm4...',
       'Area No.4 - منطقة رقم4'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm5',
       'Area No.5 - منطقة رقم5'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm7',
       'Area No.7 - منطقة رقم7'
    ),
    ('neighborhood','Alrbat','Alrbat - الرباط'),
    ('neighborhood','Alshaar','Alshaar - الشاعر'),
    ('neighborhood','Alshabh','Alshabh - الصحابة'),
    (
       'neighborhood',
       'Abw_Snymh_Waldbary',
       'Abw_Snymh_Waldbary - أبو سنيمة والدباري'
    ),
    (
       'neighborhood',
       'Qryh_Alshhda',
       'Qryh_Alshhda - قرية الشهداء'
    ),
    (
       'neighborhood',
       'Msab_Bn_Amyr',
       'Msab_Bn_Amyr - مصعب بن عمير'
    ),
    ('neighborhood','Alsfa','Alsfa - الصفا'),
    ('neighborhood','Almwasy','Almwasy - المواصي'),
    (
       'neighborhood',
       'Almhrrat',
       'Almhrrat - المحررات'
    ),
    (
       'neighborhood',
       'Tl_Alsltan',
       'Tl_Alsltan - تل السلطان'
    ),
    (
       'neighborhood',
       'Rfh_Alghrbyh',
       'Rfh_Alghrbyh - رفح الغربية'
    ),
    ('neighborhood','Alhshash','Alhshash - الحشاش'),
    (
       'neighborhood',
       'Alshabwrh',
       'Alshabwrh - الشابورة'
    ),
    ('neighborhood','Msbh','Msbh - مصبح'),
    ('neighborhood','Alzhwr','Alzhwr - الزهور'),
    (
       'neighborhood',
       'Mkhym_Rfh',
       'Mkhym_Rfh - مخيم رفح'
    ),
    (
       'neighborhood',
       'Khrbh_Alads',
       'Khrbh_Alads - خربة العدس'
    ),
    ('neighborhood','Aladary','Aladary - الاداري'),
    (
       'neighborhood',
       'Tbh_Zara',
       'Tbh_Zara - تبة زارع'
    ),
    ('neighborhood','Aljnynh','Aljnynh - الجنينة'),
    ('neighborhood','Albywk','Albywk - البيوك'),
    (
       'neighborhood',
       'Mdrsh_Albnat',
       'Mdrsh_Albnat - مدرسة البنات'
    ),
    ('neighborhood','Alhsy','Alhsy - الهسي'),
    (
       'neighborhood',
       'Alshlalfh_Walqra',
       'Alshlalfh_Walqra - الشلالفة والقرا'
    ),
    (
       'neighborhood',
       'Abw_Lwly',
       'Abw_Lwly - أبو لولي'
    ),
    (
       'neighborhood',
       'Mntqh_Rqm5...',
       'Area No.5 - منطقة رقم5'
    ),
    ('neighborhood','Alwady','Alwady - الوادي'),
    (
       'neighborhood',
       'Blal_Bn_Rbah',
       'Blal_Bn_Rbah - بلال بن رباح'
    ),
    ('neighborhood','Alzytwn','Alzytwn - الزيتون'),
    ('neighborhood','Alawdh','Alawdh - العودة'),
    (
       'neighborhood',
       'Mkhym_Alshata',
       'Mkhym_Alshata - مخيم الشاطىء'
    ),
    (
       'neighborhood',
       'Alrmal_Alshmaly',
       'Alrmal_Alshmaly - الرمال الشمالي'
    ),
    ('neighborhood','Alnsr','Alnsr - النصر');