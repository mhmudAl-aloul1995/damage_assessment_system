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
    INSERT INTO `filters` (`list_name`, `name`, `label`) VALUES
    
    ('yes_no', 'yes', 'yes'),
    ('yes_no', 'no', 'no'),
    
    ('weather', 'fine', 'fine'),
    ('weather', 'windy', 'windy'),
    ('weather', 'rainy', 'rainy'),
    
    
    ('security', 'safe', 'لا يوجد عائق'),
    ('security', 'unsafe', 'يوجد عائق'),
    
    
    ('visit_status', 'not_visited', 'not visited  لم يتم زيارة المبنى'),
    ('visit_status', 'partial_done', 'partial assessment completed  تم عمل حصر جزئي'),
    ('visit_status', 'full_done', 'full assessment completed  تم عمل الحصر بالكامل'),
    
    
    ('building_damage_status', 'fully_damaged', 'totally damaged'),
    ('building_damage_status', 'partially_damaged', 'partially damaged'),
    ('building_damage_status', 'committee_review', 'committee review'),
    
    
    ('unit_damage_status', 'fully_damaged2', 'totally damaged'),
    ('unit_damage_status', 'partially_damaged2', 'partially damaged'),
    ('unit_damage_status', 'committee_review2', 'committee review'),
    
    
    ('building_type', 'house1', 'house/منزل'),
    ('building_type', 'villa', 'villa/فيلا'),
    ('building_type', 'building', 'building/مبنى'),
    ('building_type', 'canopy', 'canopy/مظلة'),
    ('building_type', 'tower', 'tower/برج'),
    ('building_type', 'building_other', 'other/أخرى'),
    
    
    ('building_material', 'concrete', 'concrete'),
    ('building_material', 'metal', 'metal'),
    ('building_material', 'asbestos', 'asbestos'),
    ('building_material', 'wood', 'wood'),
    ('building_material', 'other_material', 'other'),
    
    
    ('building_age', 'years0_5', '0-5 years'),
    ('building_age', 'years6_10', '6-10 years'),
    ('building_age', 'years11_20', '11-20 years'),
    ('building_age', 'years21_50', '21-50 years'),
    ('building_age', 'years51_100', '51-100 years'),
    ('building_age', 'years101more', '101+ years'),
    ('building_age', 'not_sure', 'not sure'),
    
    
    ('debris_volume', 'small', 'small (≤10 m³)'),
    ('debris_volume', 'medium', 'medium (10–30 m³)'),
    ('debris_volume', 'large', 'large (>30 m³)'),
    
    
    ('yes_no_notsure', 'yes3', 'yes'),
    ('yes_no_notsure', 'no3', 'no'),
    ('yes_no_notsure', 'notsure3', 'not sure'),
    
    
    
    ('building_status_visit', 'standing_partial_remove', 'standing but needs to be removed/  قائم لكنه بحاجة للإزالة'),
    ('building_status_visit', 'removed', 'removed / تمت الإزالة'),
    ('building_status_visit', 'rubble', 'rubble / أنقاض'),
    ('building_status_visit', 'dangerous', 'dangerous / خطير'),
    
    
    ('roof_type', 'clay_tile', 'clay tile / قرميد'),
    ('roof_type', 'concrete2', 'concrete / باطون'),
    ('roof_type', 'asbestos2', 'asbestos / اسبست'),
    ('roof_type', 'secorite', 'iron sheets (secorite) / صاج'),
    ('roof_type', 'other_roof', 'other / أخرى'),
    
    
    ('building_use', 'residential', ' residential / للسكن'),
    ('building_use', 'work', '   work / للعمل'),
    ('building_use', 'combined', 'combined / للعمل والسكن'),
    
    
    ('ownership', 'single', 'single owner / ملكية فردية'),
    ('ownership', 'multiple', 'multiple  owner / ملكية مشتركة'),
    
    
    ('responsible   ', 'owner', 'owner المالك     '),
    ('responsible   ', 'board', 'board of management مجلس الإدرا ة'),
    ('responsible   ', 'heirs', 'heirs (owner is deceased) ورثة (المالك متوفي)'),
    
    
    ('owner_status', 'owner_present', 'owner present / المالك موجود'),
    ('owner_status', 'owner_captive', 'owner detained / captive المالك أسير'),
    ('owner_status', 'owner_deceased', 'owner deceased المالك متوفى'),
    ('owner_status', 'owner_missing', 'owner missing المالك مفقود'),
    
    
    
    ('doc_type', 'title_deed', 'title deed / land registry / سند/طابو'),
    ('doc_type', 'tax_record', 'tax record / مستند/سجل ضريبي'),
    ('doc_type', 'purchase_contract', 'purchase contract / عقد بيع/شراء'),
    ('doc_type', 'rental_contract', 'rental contract / عقد إيجار'),
    ('doc_type', 'utility_bill', 'utility bill / فاتورة خدمات'),
    ('doc_type', 'other_doc', 'other / أخرى'),
    
    
    
    ('doc_challenges', 'lost', 'documents lost / فقدان الوثائق'),
    ('doc_challenges', 'access', 'difficulty accessing authorities/records /  صعوبة الوصول للجهات/السجلات'),
    ('doc_challenges', 'legal', 'legal issues / مشاكل قانونية'),
    ('doc_challenges', 'cost', 'financial cost / تكلفة مالية'),
    ('doc_challenges', 'other_challenge', 'other /  أخرى'),
    
    
    ('dispute_type', 'with_owner', 'خلاف مع مالك آخر / dispute with another owner'),
    ('dispute_type', 'with_state', 'خلاف مع الدولة/جهة حكومية / dispute with the state/government'),
    ('dispute_type', 'boundary', 'خلاف على حدود / boundary dispute'),
    ('dispute_type', 'other_dispute_type', 'أخرى / other'),
    
    
    ('classification', 'excellent', 'الوضع ممتاز: أوراق كاملة، لا نزاع، ملكية واضحة'),
    ('classification', 'disputed', 'متنازع عليها: خلاف مع ملاك آخر أو على حدود أو حقوق استخدام'),
    ('classification', 'missing_docs', 'نقص مستندات: بعض الأوراق مفقودة أو غير مكتملة'),
    
    
    ('building_documents', 'id2', ' id  / صورة الهوية'),
    ('building_documents', 'ownership', ' ownership_document / إثبات  ملكية الأرض/الشقة'),
    ('building_documents', 'permit', ' permit / رخصة البلدية'),
    ('building_documents', 'other_b_doc', 'other / أخرى'),
    
    
    
    ('housing_unit_type', 'basement', 'بدروم / basement'),
    ('housing_unit_type', 'apartment', 'شقة / apartment'),
    ('housing_unit_type', 'roof', 'روف / roof'),
    ('housing_unit_type', 'warehouse', 'حاصل /  warehouse'),
    ('housing_unit_type', 'canopy2', 'مظلة / canopy'),
    ('housing_unit_type', 'mezzanine', 'سدة /  mezzanine'),
    ('housing_unit_type', 'services', 'وحدة خدمات المبنى'),
    
    ('agreement_type', 'verbal', 'verbal  شفهي'),
    ('agreement_type', 'written', 'written  كتابي'),
    ('agreement_type', 'unknown', 'unknown  غير معروف'),
    
    
    ('service_status', 'functional', 'functional  سليم'),
    ('service_status', 'partially_damaged3', 'partially damaged  متضرر جزئياً'),
    ('service_status', 'fully_damaged3', 'fully damaged  متضرر كلياً'),
    ('service_status', 'not_usable', 'not usable  غير صالح للاستخدام'),
    
    
    ('damage_status', 'no_damage', 'no damage لا يوجد ضرر'),
    ('damage_status', 'damaged', 'damaged يوجد ضرر'),
    
    
    ('infra_type', 'housing', 'housing سكني'),
    ('infra_type', 'economic', 'economic اقتصادي'),
    ('infra_type', 'social', 'social اجتماعي'),
    
    ('identity_type', 'idd', 'id هوية'),
    ('identity_type', 'passport', 'passport جواز سفر'),
    ('identity_type', 'other_id', 'other أخرى'),
    
    ('gender', 'male', 'ذكر / male'),
    ('gender', 'female', 'أنثى / female'),
    
    ('job', 'employed', 'موظف / employed'),
    ('job', 'freelancer', 'عمل حر / freelancer'),
    ('job', 'unemployed2', 'لا يعمل / unemployed'),
    ('job', 'retierd', 'متقاعد /  retierd'),
    ('job', 'other_job', 'أخرى / other'),
    
    
    ('marital_status', 'single2', 'single / أعزب'),
    ('marital_status', 'divorced', 'divorced  / مطلق/ة'),
    ('marital_status', 'widow', 'widow / أرمل/ة'),
    ('marital_status', 'married', 'married  / متزوج/ة'),
    
    
    
    ('current_residence', 'rented2', 'rented accommodation  سكن مستأجر'),
    ('current_residence', 'hosted2', 'with relatives / hosted  عند أقارب / مستضاف'),
    ('current_residence', 'tent2', 'tent  خيمة'),
    ('current_residence', 'collective_shelter2', 'collective shelter  مركز إيواء جماعي'),
    ('current_residence', 'public_facility2', 'public facility  مرفق عام'),
    ('current_residence', 'informal2', 'informal shelter  سكن غير رسمي'),
    ('current_residence', 'out_of_country', 'out of country / خارج البلاد'),
    ('current_residence', 'other_current2', 'other  أخرى'),
    
    ('handicapped', 'wheelchair', 'wheelchair / كرسي متحرك'),
    ('handicapped', 'blind', 'blind / كفيف'),
    ('handicapped', 'physically', 'physically disabled / معاق حركيا'),
    ('handicapped', 'elderly', 'elderly / مسن'),
    ('handicapped', 'mental', 'mental / معاق عقليا'),
    ('handicapped', 'other_handicapped', 'other / أخرى'),
    
    
    ('shelter_type', 'school', 'school  مدرسة'),
    ('shelter_type', 'public_building', 'public building  مبنى عام'),
    ('shelter_type', 'hospital', 'hospital  مستشفى'),
    ('shelter_type', 'public_service_facility', 'public service facility  مرافق عامة'),
    ('shelter_type', 'park', 'park  حديقة'),
    ('shelter_type', 'private_land', 'أرض خاصة / private land'),
    ('shelter_type', 'playground', 'playground  ملعب'),
    ('shelter_type', 'camp', 'مخيم / camp'),
    ('shelter_type', 'other_shelter', 'other  أخرى'),
    
    
    ('governorate', 'north', 'north / شمال غزة'),
    ('governorate', 'gaza', 'gaza  / غزة'),
    ('governorate', 'middle_area', 'middle area  / الوسطى'),
    ('governorate', 'khan_younis', 'khan younis  / خانيونس'),
    ('governorate', 'rafah', 'rafah  / رفح'),
    
    ('locality', 'um_alnasser', 'um_alnasser أم النصر'),
    ('locality', 'bait_hanoun', 'bait_hanoun بيت حانون'),
    ('locality', 'jabalia', 'jabalia جباليا'),
    ('locality', 'bait_lahia', 'bait_lahia بيت لاهيا'),
    ('locality', 'gaza_loc', 'gaza غزة'),
    ('locality', 'juhr_aldeek', 'juhr_aldeek جحر الديك'),
    ('locality', 'almughraqa', 'almughraqa المغراقة'),
    ('locality', 'alzahra', 'alzahra الزهرة'),
    ('locality', 'alnusairat', 'alnusairat النصيرات'),
    ('locality', 'alburaje', 'alburaje البريج'),
    ('locality', 'almaghazi', 'almaghazi المغازي'),
    ('locality', 'almusadar', 'almusadar المصدر'),
    ('locality', 'wadi_alsalqa', 'wadi_alsalqa وادي السلقا'),
    ('locality', 'dair_albalah', 'dair_albalah دير البلح'),
    ('locality', 'alzawayda', 'alzawayda الزوايدة'),
    ('locality', 'khanyounis', 'khanyounis خانيونس'),
    ('locality', 'alqarara', 'alqarara القرارة'),
    ('locality', 'bani_sohaila', 'bani_sohaila بني سهيلا'),
    ('locality', 'abasan_aljadida', 'abasan_aljadida عبسان الجديدة'),
    ('locality', 'abasan_alkabeira', 'abasan_alkabeira عبسان الكبيرة'),
    ('locality', 'khuzaa', 'khuzaa خزاعة'),
    ('locality', 'alfukhari', 'alfukhari الفخاري'),
    ('locality', 'rafah_loc', 'rafah رفح '),
    ('locality', 'alnasr', 'alnasser النصر'),
    ('locality', 'alshuka', 'alshuka الشوكة'),
    ('neighborhood', 'alsyfa', 'alsyfa - السيفا'),
    ('neighborhood', 'alshykh_zayd_aqlybw', 'alshykh_zayd_aqlybw - الشيخ زايد - اقليبو'),
    ('neighborhood', 'almnshyh', 'almnshyh - المنشية'),
    ('neighborhood', 'alshyma', 'alshyma - الشيماء'),
    ('neighborhood', 'alqwasmh', 'alqwasmh - القواسمة'),
    ('neighborhood', 'alaml_albrkh', 'alaml_albrkh - الأمل - البركة'),
    ('neighborhood', 'ber_alnajh', 'ber_alnajh - بئر النعجة'),
    ('neighborhood', 'mrkz_albld', 'mrkz_albld - مركز البلد'),
    ('neighborhood', 'alإsra', 'alإsra - الإسراء'),
    ('neighborhood', 'alatatrh', 'alatatrh - العطاطرة'),
    ('neighborhood', 'alslatyn', 'alslatyn - السلاطين'),
    ('neighborhood', 'almntqh_alzraayh_alshrqyh', 'almntqh_alzraayh_alshrqyh - المنطقة الزراعية الشرقية'),
    ('neighborhood', 'azbh_byt_hanwn', 'azbh_byt_hanwn - عزبة بيت حانون'),
    ('neighborhood', 'alskh', 'alskh - السكة'),
    ('neighborhood', 'dmrh', 'dmrh - دمرة'),
    ('neighborhood', 'alzytwn.', 'alzytwn - الزيتون'),
    ('neighborhood', 'almntqh_alzraayh_alshmalyh', 'almntqh_alzraayh_alshmalyh - المنطقة الزراعية الشمالية'),
    ('neighborhood', 'alsnaayh', 'alsnaayh - الصناعية'),
    ('neighborhood', 'basl_naym', 'basl_naym - باسل نعيم'),
    ('neighborhood', 'alqrman', 'alqrman - القرمان'),
    ('neighborhood', 'alqryh_alawla', 'alqryh_alawla - القرية الأولى'),
    ('neighborhood', 'alqryh_althanyh', 'alqryh_althanyh - القرية الثانية'),
    ('neighborhood', 'alkramh.', 'alkramh - الكرامة'),
    ('neighborhood', 'alslam3', 'alslam - السلام'),
    ('neighborhood', 'jbalya_alshrqyh', 'jbalya_alshrqyh - جباليا الشرقية'),
    ('neighborhood', 'alnwr', 'alnwr - النور'),
    ('neighborhood', 'tl_alzatr', 'tl_alzatr - تل الزعتر'),
    ('neighborhood', 'alrwdh', 'alrwdh - الروضة'),
    ('neighborhood', 'albldh_alqdymh.', 'albldh_alqdymh - البلدة القديمة'),
    ('neighborhood', 'alnzhh.', 'alnzhh - النزهة'),
    ('neighborhood', 'alzhwr.', 'alzhwr - الزهور'),
    ('neighborhood', 'alnhdh', 'alnhdh - النهضة'),
    ('neighborhood', 'abad_alrhmn', 'abad_alrhmn - عباد الرحمن'),
    ('neighborhood', 'alabraj', 'alabraj - الأبراج'),
    ('neighborhood', 'albnat', 'albnat - البنات'),
    ('neighborhood', 'abd_aldaym', 'abd_aldaym - عبد الدايم'),
    ('neighborhood', 'albld_alqdym', 'albld_alqdym - البلد القديم'),
    ('neighborhood', 'alnzaz', 'alnzaz - النزاز'),
    ('neighborhood', 'almsryyn', 'almsryyn - المصريين'),
    ('neighborhood', 'alfrth', 'alfrth - الفرطة'),
    ('neighborhood', 'alqtbanyh', 'alqtbanyh - القطبانية'),
    ('neighborhood', 'alaml1', 'alaml - الأمل'),
    ('neighborhood', 'mkhym_jbalya', 'mkhym_jbalya - مخيم جباليا'),
    ('neighborhood', 'mshrwa_byt_lahya', 'mshrwa_byt_lahya - مشروع بيت لاهيا'),
    ('neighborhood', 'mntqh_rqm2.', 'area no.2 - منطقة رقم2'),
    ('neighborhood', 'abw_taymh', 'abw_taymh - أبو طعيمة'),
    ('neighborhood', 'qdyh', 'qdyh - قديح'),
    ('neighborhood', 'alznh', 'alznh - الزنة'),
    ('neighborhood', 'almnarh', 'almnarh - المنارة'),
    ('neighborhood', 'alshabh2', 'alshabh - الصحابة'),
    ('neighborhood', 'almwasy_alshmaly', 'almwasy_alshmaly - المواصي الشمالي'),
    ('neighborhood', 'almwasy_aljnwby', 'almwasy_aljnwby - المواصي الجنوبي'),
    ('neighborhood', 'alnsr2', 'alnsr - النصر'),
    ('neighborhood', 'alstr', 'alstr - السطر'),
    ('neighborhood', 'aljla', 'aljla - الجلاء'),
    ('neighborhood', 'alktybh', 'alktybh - الكتيبة'),
    ('neighborhood', 'althryr', 'althryr - التحرير'),
    ('neighborhood', 'almhth', 'almhth - المحطة'),
    ('neighborhood', 'albtn_alsmyn', 'albtn_alsmyn - البطن السمين'),
    ('neighborhood', 'qyzan_abw_rshwan', 'qyzan_abw_rshwan - قيزان أبو رشوان'),
    ('neighborhood', 'mrkz_almdynh', 'mrkz_almdynh - مركز المدينة'),
    ('neighborhood', 'alshykh_nasr', 'alshykh_nasr - الشيخ ناصر'),
    ('neighborhood', 'alslam1.', 'alslam - السلام'),
    ('neighborhood', 'qyzan_alnjar', 'qyzan_alnjar - قيزان النجار'),
    ('neighborhood', 'qaa_alqryn', 'qaa_alqryn - قاع القرين'),
    ('neighborhood', 'jwrh_allwt', 'jwrh_allwt - جورة اللوت'),
    ('neighborhood', 'man', 'man - معن'),
    ('neighborhood', 'alshhda.', 'alshhda - الشهداء'),
    ('neighborhood', 'alansar2', 'alansar - الأنصار'),
    ('neighborhood', 'alslam2', 'alslam - السلام'),
    ('neighborhood', 'slah_aldyn3', 'slah_aldyn - صلاح الدين'),
    ('neighborhood', 'mkh', 'mkh - مكة'),
    ('neighborhood', 'alzlal', 'alzlal - الظلال'),
    ('neighborhood', 'almrwj', 'almrwj - المروج'),
    ('neighborhood', 'armydh', 'armydh - ارميضه'),
    ('neighborhood', 'alrdwan', 'alrdwan - الرضوان'),
    ('neighborhood', 'alanwar', 'alanwar - الأنوار'),
    ('neighborhood', 'alsnaty', 'alsnaty - السناطي'),
    ('neighborhood', 'alawdh1', 'alawdh - العودة'),
    ('neighborhood', 'alshhadyh', 'alshhadyh - الشحادية'),
    ('neighborhood', 'alfrahyn', 'alfrahyn - الفراحين'),
    ('neighborhood', 'qdyh1', 'qdyh - قديح'),
    ('neighborhood', 'alaskry', 'alaskry - العسكري'),
    ('neighborhood', 'almwl', 'almwl - المول'),
    ('neighborhood', 'abw_ywsf', 'abw_ywsf - أبو يوسف'),
    ('neighborhood', 'alshwaf', 'alshwaf - الشواف'),
    ('neighborhood', 'abw_rydh', 'abw_rydh - أبو ريدة'),
    ('neighborhood', 'alnjar', 'alnjar - النجار'),
    ('neighborhood', 'altqwa1', 'altqwa - التقوى'),
    ('neighborhood', 'mntqh_rqm7..', 'area no.7 - منطقة رقم7'),
    ('neighborhood', 'mntqh_rqm5..', 'area no.5 - منطقة رقم5'),
    ('neighborhood', 'mntqh_rqm4..', 'area no.4- منطقة رقم4'),
    ('neighborhood', 'alshrwq', 'alshrwq - الشروق'),
    ('neighborhood', 'alnaym', 'alnaym - النعيم'),
    ('neighborhood', 'alrbya', 'alrbya - الربيع'),
    ('neighborhood', 'alwsty', 'alwsty - الوسطي'),
    ('neighborhood', 'alslam1', 'alslam - السلام'),
    ('neighborhood', 'alwrwd', 'alwrwd - الورود'),
    ('neighborhood', 'am_alwad', 'am_alwad - أم الواد'),
    ('neighborhood', 'alshrqy', 'alshrqy - الشرقي'),
    ('neighborhood', 'mntqh_rqm6..', 'area no.6 - منطقة رقم6'),
    ('neighborhood', 'albldyh', 'albldyh - البلدية'),
    ('neighborhood', 'askan_alawrwby', 'askan_alawrwby - اسكان الأوروبي'),
    ('neighborhood', 'albhr_walmwasy', 'albhr_walmwasy - البحر والمواصي'),
    ('neighborhood', 'almhrrat.', 'almhrrat - المحررات'),
    ('neighborhood', 'mntqh_al_86', 'area no.86 - منطقة الـ 86'),
    ('neighborhood', 'almntqh_alghrbyh', 'almntqh_alghrbyh - المنطقة الغربية'),
    ('neighborhood', 'fyad', 'fyad - فياض'),
    ('neighborhood', 'alshykh_hmwdh', 'alshykh_hmwdh - الشيخ حمودة'),
    ('neighborhood', 'alabadlh_walastl', 'alabadlh_walastl - العبادلة والأسطل'),
    ('neighborhood', 'mkhym_khan_ywns', 'mkhym_khan_ywns - مخيم خان يونس'),
    ('neighborhood', 'alrbat.', 'alrbat - الرباط'),
    ('neighborhood', 'alaml.', 'alaml - الأمل'),
    ('neighborhood', 'alawdh..', 'alawdh - العودة'),
    ('neighborhood', 'abw_alajyn', 'abw_alajyn - أبو العجين'),
    ('neighborhood', 'alfyrwz', 'alfyrwz - الفيروز'),
    ('neighborhood', 'albrkh', 'albrkh - البركة'),
    ('neighborhood', 'mntqh_rqm4.', 'area no.4 - منطقة رقم4'),
    ('neighborhood', 'alsdrh', 'alsdrh - السدرة'),
    ('neighborhood', 'alsfa..', 'alsfa - الصفا'),
    ('neighborhood', 'aldmytha', 'aldmytha - الدميثاء'),
    ('neighborhood', 'alansar1', 'alansar - الأنصار'),
    ('neighborhood', 'slah_aldyn2', 'slah_aldyn - صلاح الدين'),
    ('neighborhood', 'almtayn', 'almtayn - المطاين'),
    ('neighborhood', 'alharh_alshrqyh', 'alharh_alshrqyh - الحارة الشرقية'),
    ('neighborhood', 'albld', 'albld - البلد'),
    ('neighborhood', 'albsh', 'albsh - البصة'),
    ('neighborhood', 'alqraan', 'alqraan - القرعان'),
    ('neighborhood', 'bsharh', 'bsharh - بشارة'),
    ('neighborhood', 'abw_aryf', 'abw_aryf - أبو عريف'),
    ('neighborhood', 'alhdbh', 'alhdbh - الحدبة'),
    ('neighborhood', 'alhkr', 'alhkr - الحكر'),
    ('neighborhood', 'am_alazban', 'am_alazban - ام العزبان'),
    ('neighborhood', 'aljafrawy', 'aljafrawy - الجعفراوي'),
    ('neighborhood', 'am_zhyr', 'am_zhyr - أم ظهير'),
    ('neighborhood', 'almshaalh_abw_fyad', 'almshaalh_abw_fyad - المشاعلة أبو فياض'),
    ('neighborhood', 'bwba', 'bwba - بوبع'),
    ('neighborhood', 'abw_khyshh', 'abw_khyshh - أبو خيشة'),
    ('neighborhood', 'aldawh', 'aldawh - الدعوة'),
    ('neighborhood', 'alastqamh', 'alastqamh - الاستقامة'),
    ('neighborhood', 'brkh_alwz', 'brkh_alwz - بركة الوز'),
    ('neighborhood', 'abw_mzyd', 'abw_mzyd - أبو مزيد'),
    ('neighborhood', 'alsbkhh', 'alsbkhh - السبخة'),
    ('neighborhood', 'mntqh_rqm13', 'area no.13 - منطقة رقم13'),
    ('neighborhood', 'mntqh_rqm8', 'area no.8 - منطقة رقم8'),
    ('neighborhood', 'alaarwqy', 'alaarwqy - العاروقي'),
    ('neighborhood', 'alzafran', 'alzafran - الزعفران'),
    ('neighborhood', 'almntqh_alzraayh', 'almntqh_alzraayh - المنطقة الزراعية'),
    ('neighborhood', 'almntqh_alsnaayh', 'almntqh_alsnaayh - المنطقة الصناعية'),
    ('neighborhood', 'abw_jlalh', 'abw_jlalh - أبو جلالة'),
    ('neighborhood', 'alkramh', 'alkramh - الكرامة'),
    ('neighborhood', 'alnzhh', 'alnzhh - النزهة'),
    ('neighborhood', 'mntqh_rqm7.', 'area no.7 - منطقة رقم7'),
    ('neighborhood', 'mntqh_rqm6.', 'area no.6 - منطقة رقم6'),
    ('neighborhood', 'alansar.', 'alansar - الأنصار'),
    ('neighborhood', 'mntqh_rqm5.', 'area no.5 - منطقة رقم5'),
    ('neighborhood', 'almntqh_alshrqyh', 'almntqh_alshrqyh - المنطقة الشرقية'),
    ('neighborhood', 'alslam.', 'alslam - السلام'),
    ('neighborhood', 'alfarwq', 'alfarwq - الفاروق'),
    ('neighborhood', 'alaml', 'alaml - الأمل'),
    ('neighborhood', 'alawdh.', 'alawdh - العودة'),
    ('neighborhood', 'alsdyq', 'alsdyq - الصديق'),
    ('neighborhood', 'alshabh.', 'alshabh - الصحابة'),
    ('neighborhood', 'alwahh', 'alwahh - الواحة'),
    ('neighborhood', 'tl_alzhwr', 'tl_alzhwr - تل الزهور'),
    ('neighborhood', 'alrhmh.', 'alrhmh - الرحمة'),
    ('neighborhood', 'slah_aldyn', 'slah_aldyn - صلاح الدين'),
    ('neighborhood', 'alansar', 'alansar - الأنصار'),
    ('neighborhood', 'mkhym_dyr_alblh', 'mkhym_dyr_alblh - مخيم دير البلح'),
    ('neighborhood', 'alshrth', 'alshrth - الشرطة'),
    ('neighborhood', 'abw_mghsyb', 'abw_mghsyb - أبو مغصيب'),
    ('neighborhood', 'almkhym_aljdyd', 'almkhym_aljdyd - المخيم الجديد'),
    ('neighborhood', 'abw_mhady', 'abw_mhady - أبو مهادي'),
    ('neighborhood', 'alhsaynh_alghrby', 'alhsaynh_alghrby - الحساينة الغربي'),
    ('neighborhood', 'alآthar', 'alآthar - الآثار'),
    ('neighborhood', 'abwslym_alghrby', 'abwslym_alghrby - أبوسليم الغربي'),
    ('neighborhood', 'f_blwk', 'f_blwk - f بلوك'),
    ('neighborhood', 'abw_slym_alshrqy', 'abw_slym_alshrqy - أبو سليم الشرقي'),
    ('neighborhood', 'srswr', 'srswr - صرصور'),
    ('neighborhood', 'alshhda', 'alshhda - الشهداء'),
    ('neighborhood', 'c_blwk', 'c_blwk - c بلوك'),
    ('neighborhood', 'almfty', 'almfty - المفتي'),
    ('neighborhood', 'mtr', 'mtr - مطر'),
    ('neighborhood', 'mntqh_rqm6', 'area no.6 - منطقة رقم6'),
    ('neighborhood', 'abw_amyrh', 'abw_amyrh - أبو عميرة'),
    ('neighborhood', 'alslam', 'alslam - السلام'),
    ('neighborhood', 'mntqh_rqm1.', 'area no.1 - منطقة رقم1'),
    ('neighborhood', 'mntqh_rqm2..', 'area no.2 - منطقة رقم2'),
    ('neighborhood', 'mntqh_rqm3..', 'area no.3 - منطقة رقم3'),
    ('neighborhood', 'mntqh_rqm4...', 'area no.4 - منطقة رقم4'),
    ('neighborhood', 'mntqh_rqm5', 'area no.5 - منطقة رقم5'),
    ('neighborhood', 'mntqh_rqm7', 'area no.7 - منطقة رقم7'),
    ('neighborhood', 'alrbat', 'alrbat - الرباط'),
    ('neighborhood', 'alshaar', 'alshaar - الشاعر'),
    ('neighborhood', 'alshabh', 'alshabh - الصحابة'),
    ('neighborhood', 'abw_snymh_waldbary', 'abw_snymh_waldbary - أبو سنيمة والدباري'),
    ('neighborhood', 'qryh_alshhda', 'qryh_alshhda - قرية الشهداء'),
    ('neighborhood', 'msab_bn_amyr', 'msab_bn_amyr - مصعب بن عمير'),
    ('neighborhood', 'alsfa', 'alsfa - الصفا'),
    ('neighborhood', 'almwasy', 'almwasy - المواصي'),
    ('neighborhood', 'almhrrat', 'almhrrat - المحررات'),
    ('neighborhood', 'tl_alsltan', 'tl_alsltan - تل السلطان'),
    ('neighborhood', 'rfh_alghrbyh', 'rfh_alghrbyh - رفح الغربية'),
    ('neighborhood', 'alhshash', 'alhshash - الحشاش'),
    ('neighborhood', 'alshabwrh', 'alshabwrh - الشابورة'),
    ('neighborhood', 'msbh', 'msbh - مصبح'),
    ('neighborhood', 'alzhwr', 'alzhwr - الزهور'),
    ('neighborhood', 'mkhym_rfh', 'mkhym_rfh - مخيم رفح'),
    ('neighborhood', 'khrbh_alads', 'khrbh_alads - خربة العدس'),
    ('neighborhood', 'aladary', 'aladary - الاداري'),
    ('neighborhood', 'tbh_zara', 'tbh_zara - تبة زارع'),
    ('neighborhood', 'aljnynh', 'aljnynh - الجنينة'),
    ('neighborhood', 'albywk', 'albywk - البيوك'),
    ('neighborhood', 'mdrsh_albnat', 'mdrsh_albnat - مدرسة البنات'),
    ('neighborhood', 'alhsy', 'alhsy - الهسي'),
    ('neighborhood', 'alshlalfh_walqra', 'alshlalfh_walqra - الشلالفة والقرا'),
    ('neighborhood', 'abw_lwly', 'abw_lwly - أبو لولي'),
    ('neighborhood', 'mntqh_rqm5...', 'area no.5 - منطقة رقم5'),
    ('neighborhood', 'alwady', 'alwady - الوادي'),
    ('neighborhood', 'blal_bn_rbah', 'blal_bn_rbah - بلال بن رباح'),
    ('neighborhood', 'alzytwn', 'alzytwn - الزيتون'),
    ('neighborhood', 'alawdh', 'alawdh - العودة'),
    ('neighborhood', 'mkhym_alshata', 'mkhym_alshata - مخيم الشاطىء'),
    ('neighborhood', 'alrmal_alshmaly', 'alrmal_alshmaly - الرمال الشمالي'),
    ('neighborhood', 'alnsr', 'alnsr - النصر'),
    ('neighborhood', 'aldrj', 'aldrj - الدرج'),
    ('neighborhood', 'alshykh_rdwan', 'alshykh_rdwan - الشيخ رضوان'),
    ('neighborhood', 'altfah', 'altfah - التفاح'),
    ('neighborhood', 'alshjaayh_ajdydh', 'alshjaayh_ajdydh - الشجاعية - اجديدة'),
    ('neighborhood', 'albldh_alqdymh', 'albldh_alqdymh - البلدة القديمة'),
    ('neighborhood', 'alsbrh', 'alsbrh - الصبرة'),
    ('neighborhood', 'tl_alhwa', 'tl_alhwa - تل الهوا'),
    ('neighborhood', 'alrmal_aljnwby', 'alrmal_aljnwby - الرمال الجنوبي'),
    ('neighborhood', 'alshykh_ajlyn', 'alshykh_ajlyn - الشيخ عجلين'),
    ('neighborhood', 'alshjaayh_altrkman', 'alshjaayh_altrkman - الشجاعية - التركمان'),
    ('neighborhood', 'ajdydh_alshrqyh', 'ajdydh_alshrqyh - اجديدة الشرقية'),
    ('neighborhood', 'altrkman_alshrqy', 'altrkman_alshrqy - التركمان الشرقي'),
    ('neighborhood', 'mntqh_rqm2', 'area no.2 - منطقة رقم2'),
    ('neighborhood', 'mdynh_alzhra', 'mdynh_alzhra - مدينة الزهراء'),
    ('neighborhood', 'jhr_aldyk', 'jhr_aldyk - جحر الديك'),
    ('neighborhood', 'alhda.', 'alhda - الهدى'),
    ('neighborhood', 'alqadsyh', 'alqadsyh - القادسية'),
    ('neighborhood', 'alrhmh', 'alrhmh - الرحمة'),
    ('neighborhood', 'abw_hryrh', 'abw_hryrh - أبو هريرة'),
    ('neighborhood', 'bdr', 'bdr - بدر'),
    ('neighborhood', 'altqwa', 'altqwa - التقوى'),
    ('neighborhood', 'alإyman', 'alإyman - الإيمان'),
    ('neighborhood', 'alhda', 'alhda - الهدى'),
    ('neighborhood', 'mntqh_rqm3', 'area no.3 - منطقة رقم3'),
    ('neighborhood', 'mntqh_rqm1', 'area no.1 - منطقة رقم1'),
    ('neighborhood', 'mntqh_rqm4', 'area no.4 - منطقة رقم4'),
    
    
    ('rentee_job', 'employee', 'employee / موظف'),
    ('rentee_job', 'businessman', 'businessman / تاجر'),
    ('rentee_job', 'technical', 'technical / مهني'),
    ('rentee_job', 'unemployed', 'unemployed / لايعمل'),
    ('rentee_job', 'other_rentee', 'other / أخرى'),
    
    
    ('fire_locations', 'kitchen', 'مطبخ / kitchen'),
    ('fire_locations', 'bathroom', 'حمام / bathroom'),
    ('fire_locations', 'room', 'غرفة / room'),
    ('fire_locations', 'other_fire', 'أخرى / other'),
    
    
    
    ('fire_extent', 'total', 'كلي / total'),
    ('fire_extent', 'partial', 'جزئي / partial'),
    
    
    ('fire_severity', 'severe', 'شديد / severe'),
    ('fire_severity', 'moderate', 'متوسط /moderate '),
    ('fire_severity', 'light', 'خفيف / light'),
    
    
    
    ('resident_in_building', 'owner2', 'owner / المالك'),
    ('resident_in_building', 'rentee', 'rentee / مستأجر'),
    ('resident_in_building', 'hosted_resident', 'hosted resident / مقيم بدون مقابل'),
    ('resident_in_building', 'unoccupied', 'unoccupied / خالية'),
    
    
    
    ('external_finishing', 'west_bank_stone', 'west bank stone / حجر قدسي'),
    ('external_finishing', 'free_lime_plastering', 'free lime plastering / قصارة باطون'),
    ('external_finishing', 'plastering', 'plastering / قصارة ناعمة'),
    ('external_finishing', 'tyroleen', 'tyroleen / قصارة مع رشقة'),
    ('external_finishing', 'painted_tyrloeen', 'painted tyroleen / قصارة رشقة مع دهان'),
    ('external_finishing', 'painted_plastering', 'painted plastering / قصارة ناعمة مع دهان'),
    ('external_finishing', 'unfinished2', 'unfinished / بدون تشطيب'),
    ('external_finishing', 'italian_plastering', 'شلخته ايطالية / italian plastering'),
    ('external_finishing', 'other_finishing', 'other / أخرى'),
    
    
    ('internal_finishing', 'super_lux', 'super lux / فاخر'),
    ('internal_finishing', 'normal', 'normal / متوسط'),
    ('internal_finishing', 'sub_normal', 'sub_normal / تحت المتوسط'),
    
    
    ('furniture_ownership', 'owner', 'owner / المالك'),
    ('furniture_ownership', 'tenant', 'tenant / المستأجر'),
    
    ('finishing_status_prewar', 'finished', 'finished  مشطب'),
    ('finishing_status_prewar', 'unfinished', 'unfinished  غير مشطب'),
    
    
    ('finishing_partial_types', 'concrete_section', 'concrete section / مقطع باطون'),
    ('finishing_partial_types', 'tiles', 'tiles / بلاط'),
    ('finishing_partial_types', 'plaster', 'plaster / قصارة'),
    
    
    ('prefab_type', 'pt1', 'الحجم والمساحة'),
    ('prefab_type', 'pt2', 'المتانة والأمان'),
    ('prefab_type', 'pt3', 'التكلفة'),
    ('prefab_type', 'pt4', 'القرب من خدمات المجتمع'),
    ('prefab_type', 'pt5', 'الخصوصية'),
    ('prefab_type', 'pt6', 'أخرى'),
    
    
    ('yes_no_maybe', 'yes2', 'نعم'),
    ('yes_no_maybe', 'no2', 'لا'),
    ('yes_no_maybe', 'maybe2', 'ربما'),
    
    
    
    ('mhpss_experinced_list', 'stress', 'التوتر'),
    ('mhpss_experinced_list', 'anxiety', 'القلق'),
    ('mhpss_experinced_list', 'emotional_challenges', 'التحديات العاطفية'),
    ('mhpss_experinced_list', 'mental_issues', 'المشاكل العقلية'),
    ('mhpss_experinced_list', 'psychological_issues', ' المشاكل النفسية'),
    ('mhpss_experinced_list', 'nothing', 'لا يوجد'),
    ('mhpss_experinced_list', 'mhpss_exp_others', 'أخرى'),
    
    ('mhpss_support_list', 'individual_counseling', 'الإستشارة الفردية'),
    ('mhpss_support_list', 'group_therapy', 'العلاج الجماعي'),
    ('mhpss_support_list', 'family_support', 'الدعم الأسري'),
    ('mhpss_support_list', 'community_activities', ' الأنشطة المجتمعية'),
    ('mhpss_support_list', 'mhpss_support_others', 'أخرى'),
    
    
    ('currently_living', 'cl1', 'سكن مؤقت'),
    ('currently_living', 'cl2', 'مع أقارب أو أصدقاء'),
    ('currently_living', 'cl3', 'في منزل مدمر أو غير آمن'),
    ('currently_living', 'cl4', 'أخرى (يرجى التحديد)'),
    
    
    
    ('agreement_duration', 'd1', 'سنوي'),
    ('agreement_duration', 'd2', 'شهري '),
    ('agreement_duration', 'd3', 'نصف سنوي '),
    
    
    ('garage_type', 'g1', 'داخلي ملحق بالمبنى / internal attached'),
    ('garage_type', 'g2', 'خارجي منفصل / external separate'),
    ('garage_type', 'g3', 'تحت الأرض / underground'),
    ('garage_type', 'g4', 'مظلة / carport'),
    ('garage_type', 'g5', 'أخرى / other'),
    
    
    
    ('service_ownership', 'one_owner', 'أحد الملاك / one_owner'),
    ('service_ownership', 'all_owners', 'جميع الملاك / all_owners'),
    
    
    ('survey_status', 'completed', 'completed'),
    ('survey_status', 'pending', 'pending'),
    ('survey_status', 'not_started', 'not_started'),
    
    ('unit_ownership', 'owned', 'owned ملك'),
    ('unit_ownership', 'rented_unit', 'rented مستأجر'),
    ('unit_ownership', 'squaterd', 'squaterd سكن غير قانوني / وضع يد'),
    ('unit_ownership', 'shared', 'shared سكن مشترك'),
    ('unit_ownership', 'other_ownership', 'other أخرى'),
    
    ('neighborhood', 'alshlalfh_walqra', 'alshlalfh_walqra - الشلالفة والقرا'),
    ('neighborhood', 'abw_lwly', 'abw_lwly - أبو لولي'),
    ('neighborhood', 'mntqh_rqm5...', 'area no.5 - منطقة رقم5'),
    ('neighborhood', 'alwady', 'alwady - الوادي'),
    ('neighborhood', 'blal_bn_rbah', 'blal_bn_rbah - بلال بن رباح'),
    ('neighborhood', 'alzytwn', 'alzytwn - الزيتون');
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    