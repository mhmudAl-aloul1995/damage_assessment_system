DROP TABLE IF EXISTS `filters`;

CREATE TABLE
    `filters` (
        id INT PRIMARY KEY AUTO_INCREMENT,
        `list_name` VARCHAR(512),
        `list_name_arabic` VARCHAR(512),
        `name` VARCHAR(512),
        `label` VARCHAR(512),
        UNIQUE KEY `unique_filter` (`list_name`, `name`)
    );

INSERT INTO
    `filters` (`list_name`, `name`, `label`)
VALUES
    ('yes_no', 'yes', 'Yes'),
    ('field_status', 'COMPLETED', 'COMPLETED'),
    ('field_status', 'Not_Completed', 'Not Completed'),
    ('yes_no', 'no', 'No'),
    ('weather', 'fine', 'Fine'),
    ('weather', 'windy', 'Windy'),
    ('weather', 'rainy', 'Rainy'),
    ('security', 'Safe', 'لا يوجد عائق'),
    ('security', 'Unsafe', 'يوجد عائق'),
    ('security_situation', 'Safe', 'لا يوجد عائق'),
    ('security_situation', 'Unsafe', 'يوجد عائق'),
    ('building_debris_exist', 'yes', 'نعم'),
    ('building_debris_exist', 'no', 'لا'),
    ('is_damaged_before', 'yes', 'نعم'),
    ('is_damaged_before', 'no', 'لا'),
    ('current_address', 'yes', 'نعم'),
    ('current_address', 'no', 'لا'),
    ('occupied', 'yes', 'نعم'),
    ('is_refugee', 'no', 'لا'),
    ('is_refugee', 'yes', 'نعم'),
    ('has_sewage', 'no', 'لا'),
    ('has_sewage', 'yes', 'نعم'),
    ('has_well', 'no', 'لا'),
    ('has_well', 'yes', 'نعم'),
    ('has_solar', 'no', 'لا'),
    ('has_solar', 'yes', 'نعم'),
    ('has_elevator', 'no', 'لا'),
    ('has_elevator', 'yes', 'نعم'),
    ('is_finished', 'no', 'لا'),
    ('has_electric_room', 'no', 'لا'),
    ('has_electric_room', 'yes', 'نعم'),
    ('is_finished', 'yes', 'نعم'),
    ('sex', 'male', 'ذكر'),
    ('sex', 'female', 'أنثى'),
    (
        'is_the_housing_unit_or_living_habitable',
        'no',
        'لا'
    ),
    (
        'is_the_housing_unit_or_living_habitable',
        'yes',
        'نعم'
    ),
    ('community_participation', 'no', 'لا'),
    ('community_participation', 'yes', 'نعم'),
    ('prefab_moving', 'no', 'لا'),
    ('prefab_moving', 'yes', 'نعم'),
    ('reh_kitchen', 'no3', 'لا'),
    ('reh_kitchen', 'yes3', 'نعم'),
    ('reh_bathroom', 'no3', 'لا'),
    ('reh_bathroom', 'yes3', 'نعم'),
    ('bodies_present', 'no3', 'لا'),
    ('bodies_present', 'yes3', 'نعم'),
    ('bodies_present', 'notsure3', 'لست متأكد'),
    ('building_authorization', 'no', 'لا'),
    ('building_authorization', 'yes', 'نعم'),
    ('land_fully_owned', 'no', 'لا'),
    ('land_fully_owned', 'yes', 'نعم'),
    ('has_documents', 'no', 'لا'),
    ('has_documents', 'yes', 'نعم'),
    ('has_fence', 'no', 'لا'),
    ('has_fence', 'yes', 'نعم'),
    ('has_dispute', 'no', 'لا'),
    ('has_dispute', 'yes', 'نعم'),
    ('has_parking', 'no', 'لا'),
    ('has_parking', 'yes', 'نعم'),
    ('has_canopy', 'no', 'لا'),
    ('has_canopy', 'yes', 'نعم'),
    ('has_basement', 'no', 'لا'),
    ('has_basement', 'yes', 'نعم'),
    ('has_mezzanine', 'no', 'لا'),
    ('has_mezzanine', 'yes', 'نعم'),
    ('has_other_service', 'no', 'لا'),
    ('has_other_service', 'yes', 'نعم'),
    ('fence_damage_status', 'damaged', 'يوجد ضرر'),
    ('fence_damage_status', 'no_damage', 'لا يوجد ضرر'),
    ('occupied', 'no', 'لا'),
    ('uxo_present', 'yes3', 'نعم'),
    ('uxo_present', 'no3', 'لا'),
    ('unit_committee_status', 'Yes', 'نعم'),
    ('unit_committee_status', 'No', 'لا'),
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
        'unit_damage_status',
        'fully_damaged2',
        'Totally Damaged'
    ),
    (
        'unit_damage_status',
        'partially_damaged2',
        'Partially Damaged'
    ),
    (
        'unit_damage_status',
        'committee_review2',
        'Committee Review'
    ),
    ('building_type', 'house1', 'House/منزل'),
    ('building_type', 'villa', 'Villa/فيلا'),
    ('building_type', 'building', 'Building/مبنى'),
    ('building_type', 'canopy', 'Canopy/مظلة'),
    ('building_type', 'tower', 'Tower/برج'),
    ('building_type', 'building_other', 'Other/أخرى'),
    ('building_material', 'concrete', 'Concrete'),
    ('building_material', 'metal', 'Metal'),
    ('building_material', 'asbestos', 'Asbestos'),
    ('building_material', 'wood', 'Wood'),
    ('building_material', 'other_material', 'Other'),
    ('building_age', 'years0_5', '0-5 years'),
    ('building_age', 'years6_10', '6-10 years'),
    ('building_age', 'years11_20', '11-20 years'),
    ('building_age', 'years21_50', '21-50 years'),
    ('building_age', 'years51_100', '51-100 years'),
    ('building_age', 'years101more', '101+ years'),
    ('building_age', 'not_sure', 'Not sure'),
    ('building_debris_qty', 'Small', 'Small (≤10 m³)'),
    (
        'building_debris_qty',
        'Medium',
        'Medium (10–30 m³)'
    ),
    ('building_debris_qty', 'Large', 'Large (>30 m³)'),
    ('yes_no_notsure', 'yes3', 'Yes'),
    ('yes_no_notsure', 'no3', 'No'),
    ('yes_no_notsure', 'notsure3', 'Not Sure'),
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
    (
        'building_roof_type',
        'clay_tile',
        'Clay Tile / قرميد'
    ),
    (
        'building_roof_type',
        'concrete2',
        'Concrete / باطون'
    ),
    (
        'building_roof_type',
        'asbestos2',
        'Asbestos / اسبست'
    ),
    (
        'building_roof_type',
        'secorite',
        'Iron Sheets (Secorite) / صاج'
    ),
    (
        'building_roof_type',
        'other_roof',
        'Other / أخرى'
    ),
    (
        'building_use',
        'residential',
        'Residential / للسكن'
    ),
    ('building_use', 'work', 'Work / للعمل'),
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
    ('building_responsible', 'owner', 'Owner المالك'),
    (
        'building_responsible',
        'board',
        'Board of management مجلس الإدرا ة'
    ),
    (
        'building_responsible',
        'heirs',
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
    ('doc_type', 'other_doc', 'Other / أخرى'),
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
    ('building_documents', 'id2', 'Id  / صورة الهوية'),
    (
        'building_documents',
        'ownership',
        'Ownership_Document / إثبات  ملكية الأرض/الشقة'
    ),
    (
        'building_documents',
        'permit',
        'Permit / رخصة البلدية'
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
    ('housing_unit_type', 'roof', 'روف / roof'),
    (
        'housing_unit_type',
        'warehouse',
        'حاصل /  warehouse'
    ),
    ('housing_unit_type', 'canopy2', 'مظلة / canopy'),
    (
        'housing_unit_type',
        'mezzanine',
        'سدة /  mezzanine'
    ),
    ('agreement_type', 'verbal', 'Verbal  شفهي'),
    ('agreement_type', 'written', 'Written  كتابي'),
    ('agreement_type', 'unknown', 'Unknown  غير معروف'),
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
    ('damage_status', 'damaged', 'Damaged يوجد ضرر'),
    ('infra_type2', 'Housing', 'Housing سكني'),
    ('infra_type2', 'Economic', 'Economic اقتصادي'),
    ('infra_type2', 'Social', 'Social اجتماعي'),
    ('identity_type1', 'idd', 'ID هوية'),
    ('identity_type1', 'passport', 'Passport جواز سفر'),
    ('identity_type1', 'other_id', 'Other أخرى'),
    ('gender', 'male', 'ذكر / Male'),
    ('gender', 'female', 'أنثى / Female'),
    ('job', 'employed', 'موظف / Employed'),
    ('job', 'freelancer', 'عمل حر / Freelancer'),
    ('job', 'unemployed2', 'غير موظف / Unemployed'),
    ('job', 'other_job', 'أخرى / Other'),
    ('marital_status', 'Single2', 'Single / أعزب'),
    (
        'marital_status',
        'Divorced',
        'Divorced  / مطلق/ة'
    ),
    ('marital_status', 'Widow', 'Widow / أرمل/ة'),
    ('marital_status', 'Married', 'Married  / متزوج/ة'),
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
    ('current_residence', 'tent2', 'Tent  خيمة'),
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
    (
        'handicapped_type',
        'Wheelchair',
        'Wheelchair / كرسي متحرك'
    ),
    ('handicapped_type', 'Blind', 'Blind / كفيف'),
    (
        'handicapped_type',
        'Physically',
        'Physically disabled / معاق حركيا'
    ),
    ('handicapped_type', 'Elderly', 'Elderly / مسن'),
    (
        'handicapped_type',
        'Mental',
        'Mental / معاق عقليا'
    ),
    (
        'handicapped_type',
        'other_handicapped_type',
        'Other / أخرى'
    ),
    ('shelter_type', 'school', 'School  مدرسة'),
    (
        'shelter_type',
        'public_building',
        'Public building  مبنى عام'
    ),
    ('shelter_type', 'hospital', 'Hospital  مستشفى'),
    (
        'shelter_type',
        'public_service_facility',
        'Public service facility  مرافق عامة'
    ),
    ('shelter_type', 'park', 'Park  حديقة'),
    (
        'shelter_type',
        'Private_Land',
        'أرض خاصة / Private Land'
    ),
    ('shelter_type', 'playground', 'Playground  ملعب'),
    ('shelter_type', 'camp', 'مخيم / Camp'),
    ('shelter_type', 'other_shelter', 'Other  أخرى'),
    ('governorate', 'North', 'North / شمال غزة'),
    ('governorate', 'Gaza', 'Gaza  / غزة'),
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
    ('governorate', 'Rafah', 'Rafah  / رفح'),
    ('locality', 'Um_AlNasser', 'Um_AlNasser أم النصر'),
    (
        'locality',
        'Bait_Hanoun',
        'Bait_Hanoun بيت حانون'
    ),
    ('locality', 'Jabalia', 'Jabalia جباليا'),
    ('locality', 'Bait_Lahia', 'Bait_Lahia بيت لاهيا'),
    ('locality', 'Gaza_loc', 'Gaza غزة'),
    (
        'locality',
        'Juhr_Aldeek',
        'Juhr_Aldeek جحر الديك'
    ),
    ('locality', 'Almughraqa', 'Almughraqa المغراقة'),
    ('locality', 'Alzahra', 'Alzahra الزهرة'),
    ('locality', 'AlNusairat', 'AlNusairat النصيرات'),
    ('locality', 'Alburaje', 'Alburaje البريج'),
    ('locality', 'AlMaghazi', 'AlMaghazi المغازي'),
    ('locality', 'AlMusadar', 'AlMusadar المصدر'),
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
    ('locality', 'Alzawayda', 'Alzawayda الزوايدة'),
    ('locality', 'Khanyounis', 'Khanyounis خانيونس'),
    ('locality', 'Alqarara', 'Alqarara القرارة'),
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
    ('locality', 'Khuzaa', 'Khuzaa خزاعة'),
    ('locality', 'AlFukhari', 'AlFukhari الفخاري'),
    ('locality', 'Rafah_loc', 'Rafah رفح'),
    ('locality', 'AlNasr', 'AlNasser النصر'),
    ('locality', 'AlShuka', 'AlShuka الشوكة'),
    ('neighborhood', 'Alsyfa', 'Alsyfa - السيفا'),
    (
        'neighborhood',
        'Alshykh_Zayd_Aqlybw',
        'Alshykh_Zayd_Aqlybw - الشيخ زايد - اقليبو'
    ),
    ('neighborhood', 'Almnshyh', 'Almnshyh - المنشية'),
    ('neighborhood', 'Alshyma', 'Alshyma - الشيماء'),
    ('neighborhood', 'Alqwasmh', 'Alqwasmh - القواسمة'),
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
    ('neighborhood', 'AlإSra', 'AlإSra - الإسراء'),
    ('neighborhood', 'Alatatrh', 'Alatatrh - العطاطرة'),
    ('neighborhood', 'Alslatyn', 'Alslatyn - السلاطين'),
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
    ('neighborhood', 'Alskh', 'Alskh - السكة'),
    ('neighborhood', 'Dmrh', 'Dmrh - دمرة'),
    ('neighborhood', 'Alzytwn.', 'Alzytwn - الزيتون'),
    (
        'neighborhood',
        'Almntqh_Alzraayh_Alshmalyh',
        'Almntqh_Alzraayh_Alshmalyh - المنطقة الزراعية الشمالية'
    ),
    ('neighborhood', 'Alsnaayh', 'Alsnaayh - الصناعية'),
    (
        'neighborhood',
        'Basl_Naym',
        'Basl_Naym - باسل نعيم'
    ),
    ('neighborhood', 'Alqrman', 'Alqrman - القرمان'),
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
    ('neighborhood', 'Alkramh.', 'Alkramh - الكرامة'),
    ('neighborhood', 'Alslam3', 'Alslam - السلام'),
    (
        'neighborhood',
        'Jbalya_Alshrqyh',
        'Jbalya_Alshrqyh - جباليا الشرقية'
    ),
    ('neighborhood', 'Alnwr', 'Alnwr - النور'),
    (
        'neighborhood',
        'Tl_Alzatr',
        'Tl_Alzatr - تل الزعتر'
    ),
    ('neighborhood', 'Alrwdh', 'Alrwdh - الروضة'),
    (
        'neighborhood',
        'Albldh_Alqdymh.',
        'Albldh_Alqdymh - البلدة القديمة'
    ),
    ('neighborhood', 'Alnzhh.', 'Alnzhh - النزهة'),
    ('neighborhood', 'Alzhwr.', 'Alzhwr - الزهور'),
    ('neighborhood', 'Alnhdh', 'Alnhdh - النهضة'),
    (
        'neighborhood',
        'Abad_Alrhmn',
        'Abad_Alrhmn - عباد الرحمن'
    ),
    ('neighborhood', 'Alabraj', 'Alabraj - الأبراج'),
    ('neighborhood', 'Albnat', 'Albnat - البنات'),
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
    ('neighborhood', 'Alnzaz', 'Alnzaz - النزاز'),
    ('neighborhood', 'Almsryyn', 'Almsryyn - المصريين'),
    ('neighborhood', 'Alfrth', 'Alfrth - الفرطة'),
    (
        'neighborhood',
        'Alqtbanyh',
        'Alqtbanyh - القطبانية'
    ),
    ('neighborhood', 'Alaml1', 'Alaml - الأمل'),
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
    ('neighborhood', 'Qdyh', 'Qdyh - قديح'),
    ('neighborhood', 'Alznh', 'Alznh - الزنة'),
    ('neighborhood', 'Almnarh', 'Almnarh - المنارة'),
    ('neighborhood', 'Alshabh2', 'Alshabh - الصحابة'),
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
    ('neighborhood', 'Alnsr2', 'Alnsr - النصر'),
    ('neighborhood', 'Alstr', 'Alstr - السطر'),
    ('neighborhood', 'Aljla', 'Aljla - الجلاء'),
    ('neighborhood', 'Alktybh', 'Alktybh - الكتيبة'),
    ('neighborhood', 'Althryr', 'Althryr - التحرير'),
    ('neighborhood', 'Almhth', 'Almhth - المحطة'),
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
    ('neighborhood', 'Alslam1.', 'Alslam - السلام'),
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
    ('neighborhood', 'Man', 'Man - معن'),
    ('neighborhood', 'Alshhda.', 'Alshhda - الشهداء'),
    ('neighborhood', 'Alansar2', 'Alansar - الأنصار'),
    ('neighborhood', 'Alslam2', 'Alslam - السلام'),
    (
        'neighborhood',
        'Slah_Aldyn3',
        'Slah_Aldyn - صلاح الدين'
    ),
    ('neighborhood', 'Mkh', 'Mkh - مكة'),
    ('neighborhood', 'Alzlal', 'Alzlal - الظلال'),
    ('neighborhood', 'Almrwj', 'Almrwj - المروج'),
    ('neighborhood', 'Armydh', 'Armydh - ارميضه'),
    ('neighborhood', 'Alrdwan', 'Alrdwan - الرضوان'),
    ('neighborhood', 'Alanwar', 'Alanwar - الأنوار'),
    ('neighborhood', 'Alsnaty', 'Alsnaty - السناطي'),
    ('neighborhood', 'Alawdh1', 'Alawdh - العودة'),
    (
        'neighborhood',
        'Alshhadyh',
        'Alshhadyh - الشحادية'
    ),
    ('neighborhood', 'Alfrahyn', 'Alfrahyn - الفراحين'),
    ('neighborhood', 'Qdyh1', 'Qdyh - قديح'),
    ('neighborhood', 'Alaskry', 'Alaskry - العسكري'),
    ('neighborhood', 'Almwl', 'Almwl - المول'),
    ('neighborhood', 'Abw_Ywsf', 'Abw_Ywsf - أبو يوسف'),
    ('neighborhood', 'Alshwaf', 'Alshwaf - الشواف'),
    ('neighborhood', 'Abw_Rydh', 'Abw_Rydh - أبو ريدة'),
    ('neighborhood', 'Alnjar', 'Alnjar - النجار'),
    ('neighborhood', 'Altqwa1', 'Altqwa - التقوى'),
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
    ('neighborhood', 'Alshrwq', 'Alshrwq - الشروق'),
    ('neighborhood', 'Alnaym', 'Alnaym - النعيم'),
    ('neighborhood', 'Alrbya', 'Alrbya - الربيع'),
    ('neighborhood', 'Alwsty', 'Alwsty - الوسطي'),
    ('neighborhood', 'Alslam1', 'Alslam - السلام'),
    ('neighborhood', 'Alwrwd', 'Alwrwd - الورود'),
    ('neighborhood', 'Am_Alwad', 'Am_Alwad - أم الواد'),
    ('neighborhood', 'Alshrqy', 'Alshrqy - الشرقي'),
    (
        'neighborhood',
        'Mntqh_Rqm6..',
        'Area No.6 - منطقة رقم6'
    ),
    ('neighborhood', 'Albldyh', 'Albldyh - البلدية'),
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
    ('neighborhood', 'Fyad', 'Fyad - فياض'),
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
    ('neighborhood', 'Alrbat.', 'Alrbat - الرباط'),
    ('neighborhood', 'Alaml.', 'Alaml - الأمل'),
    ('neighborhood', 'Alawdh..', 'Alawdh - العودة'),
    (
        'neighborhood',
        'Abw_Alajyn',
        'Abw_Alajyn - أبو العجين'
    ),
    ('neighborhood', 'Alfyrwz', 'Alfyrwz - الفيروز'),
    ('neighborhood', 'Albrkh', 'Albrkh - البركة'),
    (
        'neighborhood',
        'Mntqh_Rqm4.',
        'Area No.4 - منطقة رقم4'
    ),
    ('neighborhood', 'Alsdrh', 'Alsdrh - السدرة'),
    ('neighborhood', 'Alsfa..', 'Alsfa - الصفا'),
    ('neighborhood', 'Aldmytha', 'Aldmytha - الدميثاء'),
    ('neighborhood', 'Alansar1', 'Alansar - الأنصار'),
    (
        'neighborhood',
        'Slah_Aldyn2',
        'Slah_Aldyn - صلاح الدين'
    ),
    ('neighborhood', 'Almtayn', 'Almtayn - المطاين'),
    (
        'neighborhood',
        'Alharh_Alshrqyh',
        'Alharh_Alshrqyh - الحارة الشرقية'
    ),
    ('neighborhood', 'Albld', 'Albld - البلد'),
    ('neighborhood', 'Albsh', 'Albsh - البصة'),
    ('neighborhood', 'Alqraan', 'Alqraan - القرعان'),
    ('neighborhood', 'Bsharh', 'Bsharh - بشارة'),
    ('neighborhood', 'Abw_Aryf', 'Abw_Aryf - أبو عريف'),
    ('neighborhood', 'Alhdbh', 'Alhdbh - الحدبة'),
    ('neighborhood', 'Alhkr', 'Alhkr - الحكر'),
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
    ('neighborhood', 'Am_Zhyr', 'Am_Zhyr - أم ظهير'),
    (
        'neighborhood',
        'Almshaalh_Abw_Fyad',
        'Almshaalh_Abw_Fyad - المشاعلة أبو فياض'
    ),
    ('neighborhood', 'Bwba', 'Bwba - بوبع'),
    (
        'neighborhood',
        'Abw_Khyshh',
        'Abw_Khyshh - أبو خيشة'
    ),
    ('neighborhood', 'Aldawh', 'Aldawh - الدعوة'),
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
    ('neighborhood', 'Abw_Mzyd', 'Abw_Mzyd - أبو مزيد'),
    ('neighborhood', 'Alsbkhh', 'Alsbkhh - السبخة'),
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
    ('neighborhood', 'Alaarwqy', 'Alaarwqy - العاروقي'),
    ('neighborhood', 'Alzafran', 'Alzafran - الزعفران'),
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
    ('neighborhood', 'Alkramh', 'Alkramh - الكرامة'),
    ('neighborhood', 'Alnzhh', 'Alnzhh - النزهة'),
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
    ('neighborhood', 'Alansar.', 'Alansar - الأنصار'),
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
    ('neighborhood', 'Alslam.', 'Alslam - السلام'),
    ('neighborhood', 'Alfarwq', 'Alfarwq - الفاروق'),
    ('neighborhood', 'Alaml', 'Alaml - الأمل'),
    ('neighborhood', 'Alawdh.', 'Alawdh - العودة'),
    ('neighborhood', 'Alsdyq', 'Alsdyq - الصديق'),
    ('neighborhood', 'Alshabh.', 'Alshabh - الصحابة'),
    ('neighborhood', 'Alwahh', 'Alwahh - الواحة'),
    (
        'neighborhood',
        'Tl_Alzhwr',
        'Tl_Alzhwr - تل الزهور'
    ),
    ('neighborhood', 'Alrhmh.', 'Alrhmh - الرحمة'),
    (
        'neighborhood',
        'Slah_Aldyn',
        'Slah_Aldyn - صلاح الدين'
    ),
    ('neighborhood', 'Alansar', 'Alansar - الأنصار'),
    (
        'neighborhood',
        'Mkhym_Dyr_Alblh',
        'Mkhym_Dyr_Alblh - مخيم دير البلح'
    ),
    ('neighborhood', 'Alshrth', 'Alshrth - الشرطة'),
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
    ('neighborhood', 'AlآThar', 'AlآThar - الآثار'),
    (
        'neighborhood',
        'Abwslym_Alghrby',
        'Abwslym_Alghrby - أبوسليم الغربي'
    ),
    ('neighborhood', 'F_Blwk', 'F_Blwk - F بلوك'),
    (
        'neighborhood',
        'Abw_Slym_Alshrqy',
        'Abw_Slym_Alshrqy - أبو سليم الشرقي'
    ),
    ('neighborhood', 'Srswr', 'Srswr - صرصور'),
    ('neighborhood', 'Alshhda', 'Alshhda - الشهداء'),
    ('neighborhood', 'C_Blwk', 'C_Blwk - C بلوك'),
    ('neighborhood', 'Almfty', 'Almfty - المفتي'),
    ('neighborhood', 'Mtr', 'Mtr - مطر'),
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
    ('neighborhood', 'Alslam', 'Alslam - السلام'),
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
    ('neighborhood', 'Alrbat', 'Alrbat - الرباط'),
    ('neighborhood', 'Alshaar', 'Alshaar - الشاعر'),
    ('neighborhood', 'Alshabh', 'Alshabh - الصحابة'),
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
    ('neighborhood', 'Alsfa', 'Alsfa - الصفا'),
    ('neighborhood', 'Almwasy', 'Almwasy - المواصي'),
    ('neighborhood', 'Almhrrat', 'Almhrrat - المحررات'),
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
    ('neighborhood', 'Alhshash', 'Alhshash - الحشاش'),
    (
        'neighborhood',
        'Alshabwrh',
        'Alshabwrh - الشابورة'
    ),
    ('neighborhood', 'Msbh', 'Msbh - مصبح'),
    ('neighborhood', 'Alzhwr', 'Alzhwr - الزهور'),
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
    ('neighborhood', 'Aladary', 'Aladary - الاداري'),
    ('neighborhood', 'Tbh_Zara', 'Tbh_Zara - تبة زارع'),
    ('neighborhood', 'Aljnynh', 'Aljnynh - الجنينة'),
    ('neighborhood', 'Albywk', 'Albywk - البيوك'),
    (
        'neighborhood',
        'Mdrsh_Albnat',
        'Mdrsh_Albnat - مدرسة البنات'
    ),
    ('neighborhood', 'Alhsy', 'Alhsy - الهسي'),
    (
        'neighborhood',
        'Alshlalfh_Walqra',
        'Alshlalfh_Walqra - الشلالفة والقرا'
    ),
    ('neighborhood', 'Abw_Lwly', 'Abw_Lwly - أبو لولي'),
    (
        'neighborhood',
        'Mntqh_Rqm5...',
        'Area No.5 - منطقة رقم5'
    ),
    ('neighborhood', 'Alwady', 'Alwady - الوادي'),
    (
        'neighborhood',
        'Blal_Bn_Rbah',
        'Blal_Bn_Rbah - بلال بن رباح'
    ),
    ('neighborhood', 'Alzytwn', 'Alzytwn - الزيتون'),
    ('neighborhood', 'Alawdh', 'Alawdh - العودة'),
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
    ('neighborhood', 'Alnsr', 'Alnsr - النصر'),
    ('debris_volume', 'small', 'small (≤10 m³)'),
    ('debris_volume', 'medium', 'medium (10–30 m³)'),
    ('debris_volume', 'large', 'large (>30 m³)'),
    ('roof_type', 'clay_tile', 'clay tile / قرميد'),
    ('roof_type', 'concrete2', 'concrete / باطون'),
    ('roof_type', 'asbestos2', 'asbestos / اسبست'),
    (
        'roof_type',
        'secorite',
        'iron sheets (secorite) / صاج'
    ),
    ('roof_type', 'other_roof', 'other / أخرى'),
    (
        'ownership',
        'single',
        'single owner / ملكية فردية'
    ),
    (
        'ownership',
        'multiple',
        'multiple  owner / ملكية مشتركة'
    ),
    ('responsible', 'owner', 'owner المالك'),
    (
        'responsible',
        'board',
        'board of management مجلس الإدرا ة'
    ),
    (
        'responsible',
        'heirs',
        'heirs (owner is deceased) ورثة (المالك متوفي)'
    ),
    (
        'dispute_type',
        'with_owner',
        'خلاف مع مالك آخر / dispute with another owner'
    ),
    (
        'dispute_type',
        'with_state',
        'خلاف مع الدولة/جهة حكومية / dispute with the state/government'
    ),
    (
        'dispute_type',
        'boundary',
        'خلاف على حدود / boundary dispute'
    ),
    (
        'dispute_type',
        'other_dispute_type',
        'أخرى / other'
    ),
    (
        'housing_unit_type',
        'services',
        'وحدة خدمات المبنى'
    ),
    ('infra_type', 'housing', 'housing سكني'),
    ('infra_type', 'economic', 'economic اقتصادي'),
    ('infra_type', 'social', 'social اجتماعي'),
    ('identity_type', 'idd', 'id هوية'),
    ('identity_type', 'passport', 'passport جواز سفر'),
    ('identity_type', 'other_id', 'other أخرى'),
    ('job', 'retierd', 'متقاعد /  retierd'),
    (
        'current_residence',
        'out_of_country',
        'out of country / خارج البلاد'
    ),
    (
        'handicapped',
        'wheelchair',
        'wheelchair / كرسي متحرك'
    ),
    ('handicapped', 'blind', 'blind / كفيف'),
    (
        'handicapped',
        'physically',
        'physically disabled / معاق حركيا'
    ),
    ('handicapped', 'elderly', 'elderly / مسن'),
    ('handicapped', 'mental', 'mental / معاق عقليا'),
    (
        'handicapped',
        'other_handicapped',
        'other / أخرى'
    ),
    ('neighborhood', 'aldrj', 'aldrj - الدرج'),
    (
        'neighborhood',
        'alshykh_rdwan',
        'alshykh_rdwan - الشيخ رضوان'
    ),
    ('neighborhood', 'altfah', 'altfah - التفاح'),
    (
        'neighborhood',
        'alshjaayh_ajdydh',
        'alshjaayh_ajdydh - الشجاعية - اجديدة'
    ),
    (
        'neighborhood',
        'albldh_alqdymh',
        'albldh_alqdymh - البلدة القديمة'
    ),
    ('neighborhood', 'alsbrh', 'alsbrh - الصبرة'),
    ('neighborhood', 'tl_alhwa', 'tl_alhwa - تل الهوا'),
    (
        'neighborhood',
        'alrmal_aljnwby',
        'alrmal_aljnwby - الرمال الجنوبي'
    ),
    (
        'neighborhood',
        'alshykh_ajlyn',
        'alshykh_ajlyn - الشيخ عجلين'
    ),
    (
        'neighborhood',
        'alshjaayh_altrkman',
        'alshjaayh_altrkman - الشجاعية - التركمان'
    ),
    (
        'neighborhood',
        'ajdydh_alshrqyh',
        'ajdydh_alshrqyh - اجديدة الشرقية'
    ),
    (
        'neighborhood',
        'altrkman_alshrqy',
        'altrkman_alshrqy - التركمان الشرقي'
    ),
    (
        'neighborhood',
        'mntqh_rqm2',
        'area no.2 - منطقة رقم2'
    ),
    (
        'neighborhood',
        'mdynh_alzhra',
        'mdynh_alzhra - مدينة الزهراء'
    ),
    (
        'neighborhood',
        'jhr_aldyk',
        'jhr_aldyk - جحر الديك'
    ),
    ('neighborhood', 'alhda.', 'alhda - الهدى'),
    ('neighborhood', 'alqadsyh', 'alqadsyh - القادسية'),
    ('neighborhood', 'alrhmh', 'alrhmh - الرحمة'),
    (
        'neighborhood',
        'abw_hryrh',
        'abw_hryrh - أبو هريرة'
    ),
    ('neighborhood', 'bdr', 'bdr - بدر'),
    ('neighborhood', 'altqwa', 'altqwa - التقوى'),
    ('neighborhood', 'alإyman', 'alإyman - الإيمان'),
    ('neighborhood', 'alhda', 'alhda - الهدى'),
    (
        'neighborhood',
        'mntqh_rqm3',
        'area no.3 - منطقة رقم3'
    ),
    (
        'neighborhood',
        'mntqh_rqm1',
        'area no.1 - منطقة رقم1'
    ),
    (
        'neighborhood',
        'mntqh_rqm4',
        'area no.4 - منطقة رقم4'
    ),
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
    ('fire_severity', 'moderate', 'متوسط /moderate'),
    ('fire_severity', 'light', 'خفيف / light'),
    (
        'resident_in_building',
        'owner2',
        'owner / المالك'
    ),
    (
        'resident_in_building',
        'rentee',
        'rentee / مستأجر'
    ),
    (
        'resident_in_building',
        'hosted_resident',
        'hosted resident / مقيم بدون مقابل'
    ),
    (
        'resident_in_building',
        'unoccupied',
        'unoccupied / خالية'
    ),
    (
        'external_finishing',
        'west_bank_stone',
        'west bank stone / حجر قدسي'
    ),
    (
        'external_finishing',
        'free_lime_plastering',
        'free lime plastering / قصارة باطون'
    ),
    (
        'external_finishing',
        'plastering',
        'plastering / قصارة ناعمة'
    ),
    (
        'external_finishing',
        'tyroleen',
        'tyroleen / قصارة مع رشقة'
    ),
    (
        'external_finishing',
        'painted_tyrloeen',
        'painted tyroleen / قصارة رشقة مع دهان'
    ),
    (
        'external_finishing',
        'painted_plastering',
        'painted plastering / قصارة ناعمة مع دهان'
    ),
    (
        'external_finishing',
        'unfinished2',
        'unfinished / بدون تشطيب'
    ),
    (
        'external_finishing',
        'italian_plastering',
        'شلخته ايطالية / italian plastering'
    ),
    (
        'external_finishing',
        'other_finishing',
        'other / أخرى'
    ),
    (
        'internal_finishing',
        'super_lux',
        'super lux / فاخر'
    ),
    ('internal_finishing', 'normal', 'normal / متوسط'),
    (
        'internal_finishing',
        'sub_normal',
        'sub_normal / تحت المتوسط'
    ),
    ('furniture_ownership', 'owner', 'owner / المالك'),
    (
        'furniture_ownership',
        'tenant',
        'tenant / المستأجر'
    ),
    (
        'finishing_status_prewar',
        'finished',
        'finished  مشطب'
    ),
    (
        'finishing_status_prewar',
        'unfinished',
        'unfinished  غير مشطب'
    ),
    (
        'finishing_partial_types',
        'concrete_section',
        'concrete section / مقطع باطون'
    ),
    (
        'finishing_partial_types',
        'tiles',
        'tiles / بلاط'
    ),
    (
        'finishing_partial_types',
        'plaster',
        'plaster / قصارة'
    ),
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
    (
        'mhpss_experinced_list',
        'emotional_challenges',
        'التحديات العاطفية'
    ),
    (
        'mhpss_experinced_list',
        'mental_issues',
        'المشاكل العقلية'
    ),
    (
        'mhpss_experinced_list',
        'psychological_issues',
        'المشاكل النفسية'
    ),
    ('mhpss_experinced_list', 'nothing', 'لا يوجد'),
    (
        'mhpss_experinced_list',
        'mhpss_exp_others',
        'أخرى'
    ),
    (
        'mhpss_support_list',
        'individual_counseling',
        'الإستشارة الفردية'
    ),
    (
        'mhpss_support_list',
        'group_therapy',
        'العلاج الجماعي'
    ),
    (
        'mhpss_support_list',
        'family_support',
        'الدعم الأسري'
    ),
    (
        'mhpss_support_list',
        'community_activities',
        'الأنشطة المجتمعية'
    ),
    (
        'mhpss_support_list',
        'mhpss_support_others',
        'أخرى'
    ),
    ('currently_living', 'cl1', 'سكن مؤقت'),
    ('currently_living', 'cl2', 'مع أقارب أو أصدقاء'),
    (
        'currently_living',
        'cl3',
        'في منزل مدمر أو غير آمن'
    ),
    ('currently_living', 'cl4', 'أخرى (يرجى التحديد)'),
    ('agreement_duration', 'd1', 'سنوي'),
    ('agreement_duration', 'd2', 'شهري'),
    ('agreement_duration', 'd3', 'نصف سنوي'),
    (
        'garage_type',
        'g1',
        'داخلي ملحق بالمبنى / internal attached'
    ),
    (
        'garage_type',
        'g2',
        'خارجي منفصل / external separate'
    ),
    ('garage_type', 'g3', 'تحت الأرض / underground'),
    ('garage_type', 'g4', 'مظلة / carport'),
    ('garage_type', 'g5', 'أخرى / other'),
    (
        'service_ownership',
        'One_Owner',
        'أحد الملاك / one_owner'
    ),
    (
        'service_ownership',
        'All_Owners',
        'جميع الملاك / all_owners'
    ),
    ('survey_status', 'completed', 'completed'),
    ('survey_status', 'pending', 'pending'),
    ('survey_status', 'not_started', 'not_started'),
    ('unit_ownership', 'owned', 'owned ملك'),
    ('unit_ownership', 'rented_unit', 'rented مستأجر'),
    (
        'unit_ownership',
        'squaterd',
        'squaterd سكن غير قانوني / وضع يد'
    ),
    ('unit_ownership', 'shared', 'shared سكن مشترك'),
    ('unit_ownership', 'other_ownership', 'other أخرى'),
    (
        'external_finishing_of_the_unit',
        'tyroleen',
        'Tyroleen / قصارة مع رشقة'
    ),
    (
        'external_finishing_of_the_unit',
        'free_lime_plastering',
        'Free Lime Plastering / قصارة باطون'
    ),
    (
        'external_finishing_of_the_unit',
        'painted_tyrloeen',
        'Painted Tyroleen / قصارة رشقة مع دهان'
    ),
    (
        'external_finishing_of_the_unit',
        'other_finishing',
        'Other / أخرى'
    ),
    (
        'external_finishing_of_the_unit',
        'unfinished2',
        'Unfinished / بدون تشطيب'
    ),
    (
        'external_finishing_of_the_unit',
        'painted_plastering',
        'Painted Plastering / قصارة ناعمة مع دهان'
    ),
    (
        'external_finishing_of_the_unit',
        'west_bank_stone',
        'West Bank Stone / حجر قدسي'
    ),
    (
        'external_finishing_of_the_unit',
        'plastering',
        'Plastering / قصارة ناعمة'
    ),
    (
        'external_finishing_of_the_unit',
        'Italian_Plastering',
        'Italian Plastering / شلختة إيطالية'
    );

UPDATE filters
SET
    list_name_arabic = CASE list_name
        -- ========= CORE =========
        WHEN 'yes_no' THEN 'نعم / لا'
        WHEN 'yes_no_notsure' THEN 'نعم / لا / غير متأكد'
        WHEN 'yes_no_maybe' THEN 'نعم / لا / ربما'
        -- ========= STATUS =========
        WHEN 'field_status' THEN 'حالة العمل الميداني'
        WHEN 'visit_status' THEN 'حالة الزيارة'
        WHEN 'survey_status' THEN 'حالة المسح'
        WHEN 'unit_committee_status' THEN 'حالة لجنة الوحدة'
        -- ========= AGREEMENT =========
        WHEN 'agreement_type' THEN 'نوع الاتفاق'
        WHEN 'agreement_duration' THEN 'مدة الاتفاق'
        -- ========= ENV =========
        WHEN 'weather' THEN 'الطقس'
        WHEN 'security' THEN 'هل يوجد عائق'
        WHEN 'security_situation' THEN 'هل يوجد عائق'
        -- ========= BUILDING =========
        WHEN 'building_type' THEN 'نوع المبنى'
        WHEN 'building_material' THEN 'مادة البناء'
        WHEN 'building_age' THEN 'عمر المبنى'
        WHEN 'building_use' THEN 'استخدام المبنى'
        WHEN 'building_ownership' THEN 'ملكية المبنى'
        WHEN 'building_responsible' THEN 'الجهة المسؤولة عن المبنى'
        WHEN 'building_status_visit' THEN 'حالة المبنى عند الزيارة'
        WHEN 'building_damage_status' THEN 'حالة ضرر المبنى'
        WHEN 'building_debris_exist' THEN 'وجود ركام بالمبنى'
        WHEN 'building_debris_qty' THEN 'كمية ركام المبنى'
        WHEN 'building_roof_type' THEN 'نوع سقف المبنى'
        WHEN 'roof_type' THEN 'نوع السقف'
        -- ========= UNIT =========
        WHEN 'housing_unit_type' THEN 'نوع الوحدة السكنية'
        WHEN 'unit_damage_status' THEN 'حالة ضرر الوحدة'
        WHEN 'unit_stripping' THEN 'تجريد الوحدة'
        WHEN 'unit_stripping_details' THEN 'تفاصيل تجريد الوحدة'
        WHEN 'unit_support_needed' THEN 'الدعم المطلوب للوحدة'
        WHEN 'the_unit_resident' THEN 'المقيم في الوحدة'
        -- ========= FINISHING =========
        WHEN 'external_finishing' THEN 'التشطيب الخارجي'
        WHEN 'external_finishing_of_the_unit' THEN 'التشطيب الخارجي للوحدة'
        WHEN 'internal_finishing' THEN 'التشطيب الداخلي'
        WHEN 'internal_finishing_of_the_unit' THEN 'التشطيب الداخلي للوحدة'
        WHEN 'finishing_extent' THEN 'مدى التشطيب'
        WHEN 'finishing_partial_types' THEN 'أنواع التشطيب الجزئي'
        WHEN 'finishing_status_prewar' THEN 'حالة التشطيب قبل الحرب'
        -- ========= DAMAGE =========
        WHEN 'damage_status' THEN 'حالة الضرر'
        WHEN 'service_status' THEN 'حالة الخدمة'
        WHEN 'solar_damage_status' THEN 'حالة ضرر الطاقة الشمسية'
        WHEN 'sewage_damage_status' THEN 'حالة ضرر الصرف الصحي'
        WHEN 'well_damage_status' THEN 'حالة ضرر البئر'
        WHEN 'electric_room_damage_status' THEN 'حالة ضرر غرفة الكهرباء'
        WHEN 'fence_damage_status' THEN 'حالة ضرر السور'
        WHEN 'mezzanine_status' THEN 'حالة السدة'
        WHEN 'basement_status' THEN 'حالة البدروم'
        WHEN 'canopy_status' THEN 'حالة المظلة'
        WHEN 'elevator_motor' THEN 'حالة محرك المصعد'
        WHEN 'elevator_box' THEN 'حالة صندوق المصعد'
        WHEN 'elevator_status' THEN 'حالة المصعد'
        WHEN 'staircase_status' THEN 'حالة الدرج'
        -- ========= FIRE =========
        WHEN 'has_fire' THEN 'هل يوجد حريق'
        WHEN 'fire_extent' THEN 'مدى الحريق'
        WHEN 'fire_locations' THEN 'مواقع الحريق'
        WHEN 'fire_severity' THEN 'شدة الحريق'
        -- ========= SERVICES =========
        WHEN 'has_sewage' THEN 'وجود صرف صحي'
        WHEN 'has_well' THEN 'وجود بئر'
        WHEN 'has_solar' THEN 'وجود طاقة شمسية'
        WHEN 'has_elevator' THEN 'وجود مصعد'
        WHEN 'has_electric_room' THEN 'وجود غرفة كهرباء'
        WHEN 'has_fence' THEN 'وجود سور'
        WHEN 'has_parking' THEN 'وجود موقف سيارات'
        WHEN 'has_canopy' THEN 'وجود مظلة'
        WHEN 'has_basement' THEN 'وجود بدروم'
        WHEN 'has_mezzanine' THEN 'وجود سدة'
        WHEN 'has_other_service' THEN 'وجود خدمة أخرى'
        -- ========= LEGAL =========
        WHEN 'doc_type' THEN 'نوع الوثيقة'
        WHEN 'doc_types_available' THEN 'أنواع الوثائق المتوفرة'
        WHEN 'doc_challenges' THEN 'تحديات الوثائق'
        WHEN 'building_documents' THEN 'وثائق المبنى'
        WHEN 'select_document' THEN 'اختيار الوثيقة'
        WHEN 'dispute_type' THEN 'نوع النزاع'
        WHEN 'dispute_types' THEN 'أنواع النزاع'
        WHEN 'classification' THEN 'التصنيف'
        WHEN 'has_documents' THEN 'وجود مستندات'
        WHEN 'has_dispute' THEN 'وجود نزاع'
        WHEN 'building_authorization' THEN 'تفويض المبنى'
        WHEN 'land_fully_owned' THEN 'ملكية الأرض'
        -- ========= PEOPLE =========
        WHEN 'gender' THEN 'الجنس'
        WHEN 'job' THEN 'الوظيفة'
        WHEN 'owner_job' THEN 'وظيفة المالك'
        WHEN 'rentee_job' THEN 'عمل المستأجر'
        WHEN 'marital_status' THEN 'الحالة الاجتماعية'
        WHEN 'owner_status' THEN 'حالة المالك'
        WHEN 'identity_type' THEN 'نوع الهوية'
        WHEN 'identity_type1' THEN 'نوع الهوية'
        WHEN 'handicapped' THEN 'إعاقة'
        WHEN 'handicapped_type' THEN 'نوع الإعاقة'
        WHEN 'are_there_people_with_disability' THEN 'هل يوجد أشخاص من ذوي الإعاقة'
        -- ========= SOCIAL =========
        WHEN 'current_residence' THEN 'مكان الإقامة الحالي'
        WHEN 'currently_living' THEN 'يقيم حاليًا'
        WHEN 'resident_in_building' THEN 'مقيم في المبنى'
        WHEN 'shelter_type' THEN 'نوع مركز الإيواء'
        WHEN 'mhpss_experinced' THEN 'هل تعرض لدعم نفسي'
        WHEN 'mhpss_experinced_list' THEN 'تفاصيل الدعم النفسي'
        WHEN 'mhpss_support' THEN 'الدعم النفسي والاجتماعي'
        WHEN 'mhpss_support_list' THEN 'أنواع الدعم النفسي'
        -- ========= OWNERSHIP =========
        WHEN 'ownership' THEN 'الملكية'
        WHEN 'house_unit_ownership' THEN 'ملكية الوحدة السكنية'
        WHEN 'service_ownership' THEN 'ملكية الخدمة'
        WHEN 'furniture_ownership' THEN 'ملكية الأثاث'
        -- ========= OTHER =========
        WHEN 'is_finished' THEN 'التشطيب'
        WHEN 'community_participation' THEN 'مشاركة مجتمعية'
        WHEN 'prefab_types' THEN 'أنواع الوحدات الجاهزة'
        WHEN 'prefab_moving' THEN 'إمكانية النقل'
        WHEN 'garage_type' THEN 'نوع الكراج'
        WHEN 'debris_volume' THEN 'حجم الركام'
        WHEN 'rubble_removal_is_needed' THEN 'هل إزالة الركام مطلوبة'
        WHEN 'stripping_locations' THEN 'مواقع التجريد'
        WHEN 'bodies_present' THEN 'وجود جثامين'
        WHEN 'uxo_present' THEN 'وجود مخلفات حربية'
        WHEN 'activation_of_uxo_ha_d_material_clearance' THEN 'إزالة مخلفات الذخائر غير المنفجرة'
        WHEN 'infra_type' THEN 'نوع البنية التحتية'
        WHEN 'infra_type2' THEN 'نوع البنية التحتية'
        WHEN 'responsible' THEN 'المسؤول'
        -- ========= LOCATION =========
        WHEN 'governorate' THEN 'المحافظة'
        WHEN 'locality' THEN 'المنطقة'
        WHEN 'neighborhood' THEN 'الحي'
        -- ========= FORCE FALLBACK =========
        ELSE CONCAT ('حقل ', REPLACE (list_name, '_', ' '))
    END;

-- 1) حذف القديم
DELETE FROM filters
WHERE
    list_name = 'neighborhood';

-- 2) إدخال بدون تكرار
DELETE FROM filters
WHERE
    list_name = 'neighborhood';

INSERT INTO
    filters (`list_name`, `name`, `label`)
SELECT
    'neighborhood' AS list_name,
    TRIM(
        CASE
            WHEN neighborhood LIKE '% - %' THEN SUBSTRING_INDEX (neighborhood, ' - ', 1)
            ELSE neighborhood
        END
    ) AS name,
    TRIM(
        CASE
            WHEN neighborhood LIKE '% - %' THEN SUBSTRING_INDEX (neighborhood, ' - ', -1)
            ELSE neighborhood
        END
    ) AS label
FROM
    buildings
WHERE
    neighborhood IS NOT NULL
    AND TRIM(neighborhood) <> ''
GROUP BY
    TRIM(
        CASE
            WHEN neighborhood LIKE '% - %' THEN SUBSTRING_INDEX (neighborhood, ' - ', 1)
            ELSE neighborhood
        END
    );

UPDATE filters
SET
    list_name_arabic = 'الحي',
    label = CASE name
        WHEN 'Al-Daraj' THEN 'الدرج'
        WHEN 'Al-Amal' THEN 'الأمل'
        WHEN 'Sarsour' THEN 'صرصور'
        WHEN 'New Ref Camp' THEN 'مخيم العودة الجديد'
        WHEN 'City Center' THEN 'مركز المدينة'
        ELSE label
    END
WHERE
    list_name = 'neighborhood';