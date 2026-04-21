DROP TABLE IF EXISTS `areas`;

CREATE TABLE
    `areas` (
        id INT PRIMARY KEY AUTO_INCREMENT,
        `type` VARCHAR(512),
        `field_val_en` VARCHAR(512),
        `field_val_ar` VARCHAR(512)
    );

INSERT INTO
    `areas` (`type`, `field_val_en`, `field_val_ar`)
VALUES
    ('governorate', 'North', 'North / شمال غزة '),
    ('governorate', 'Gaza', 'Gaza  / غزة '),
    (
        'governorate',
        'Middle_Area',
        'Middle Area  / الوسطى '
    ),
    (
        'governorate',
        'Khan_Younis',
        'Khan Younis  / خانيونس '
    ),
    ('governorate', 'Rafah', 'Rafah  / رفح '),
    (
        'locality',
        'Um_AlNasser',
        'Um_AlNasser أم النصر '
    ),
    (
        'locality',
        'Bait_Hanoun',
        'Bait_Hanoun بيت حانون '
    ),
    ('locality', 'Jabalia', 'Jabalia جباليا '),
    ('locality', 'Bait_Lahia', 'Bait_Lahia بيت لاهيا '),
    ('locality', 'Gaza_loc', 'Gaza غزة '),
    (
        'locality',
        'Juhr_Aldeek',
        'Juhr_Aldeek جحر الديك '
    ),
    ('locality', 'Almughraqa', 'Almughraqa المغراقة '),
    ('locality', 'Alzahra', 'Alzahra الزهرة '),
    ('locality', 'AlNusairat', 'AlNusairat النصيرات '),
    ('locality', 'Alburaje', 'Alburaje البريج '),
    ('locality', 'AlMaghazi', 'AlMaghazi المغازي '),
    ('locality', 'AlMusadar', 'AlMusadar المصدر '),
    (
        'locality',
        'Wadi_Alsalqa',
        'Wadi_Alsalqa وادي السلقا '
    ),
    (
        'locality',
        'Dair_Albalah',
        'Dair_Albalah دير البلح '
    ),
    ('locality', 'Alzawayda', 'Alzawayda الزوايدة '),
    ('locality', 'Khanyounis', 'Khanyounis خانيونس '),
    ('locality', 'Alqarara', 'Alqarara القرارة '),
    (
        'locality',
        'Bani_Sohaila',
        'Bani_Sohaila بني سهيلا '
    ),
    (
        'locality',
        'Abasan_AlJadida',
        'Abasan_AlJadida عبسان الجديدة '
    ),
    (
        'locality',
        'Abasan_alkabeira',
        'Abasan_alkabeira عبسان الكبيرة '
    ),
    ('locality', 'Khuzaa', 'Khuzaa خزاعة '),
    ('locality', 'AlFukhari', 'AlFukhari الفخاري '),
    ('locality', 'Rafah_loc', 'Rafah رفح  '),
    ('locality', 'AlNasr', 'AlNasser النصر '),
    ('locality', 'AlShuka', 'AlShuka الشوكة '),
    ('neighborhood', 'Alsyfa', 'Alsyfa - السيفا '),
    (
        'neighborhood',
        'Alshykh_Zayd_Aqlybw',
        'Alshykh_Zayd_Aqlybw - الشيخ زايد - اقليبو '
    ),
    ('neighborhood', 'Almnshyh', 'Almnshyh - المنشية '),
    ('neighborhood', 'Alshyma', 'Alshyma - الشيماء '),
    (
        'neighborhood',
        'Alqwasmh',
        'Alqwasmh - القواسمة '
    ),
    (
        'neighborhood',
        'Alaml_Albrkh',
        'Alaml_Albrkh - الأمل - البركة '
    ),
    (
        'neighborhood',
        'Ber_Alnajh',
        'Ber_Alnajh - بئر النعجة '
    ),
    (
        'neighborhood',
        'Mrkz_Albld',
        'Mrkz_Albld - مركز البلد '
    ),
    ('neighborhood', 'AlإSra', 'AlإSra - الإسراء '),
    (
        'neighborhood',
        'Alatatrh',
        'Alatatrh - العطاطرة '
    ),
    (
        'neighborhood',
        'Alslatyn',
        'Alslatyn - السلاطين '
    ),
    (
        'neighborhood',
        'Almntqh_Alzraayh_Alshrqyh',
        'Almntqh_Alzraayh_Alshrqyh - المنطقة الزراعية الشرقية '
    ),
    (
        'neighborhood',
        'Azbh_Byt_Hanwn',
        'Azbh_Byt_Hanwn - عزبة بيت حانون '
    ),
    ('neighborhood', 'Alskh', 'Alskh - السكة '),
    ('neighborhood', 'Dmrh', 'Dmrh - دمرة '),
    ('neighborhood', 'Alzytwn.', 'Alzytwn - الزيتون '),
    (
        'neighborhood',
        'Almntqh_Alzraayh_Alshmalyh',
        'Almntqh_Alzraayh_Alshmalyh - المنطقة الزراعية الشمالية '
    ),
    (
        'neighborhood',
        'Alsnaayh',
        'Alsnaayh - الصناعية '
    ),
    (
        'neighborhood',
        'Basl_Naym',
        'Basl_Naym - باسل نعيم '
    ),
    ('neighborhood', 'Alqrman', 'Alqrman - القرمان '),
    (
        'neighborhood',
        'Alqryh_Alawla',
        'Alqryh_Alawla - القرية الأولى '
    ),
    (
        'neighborhood',
        'Alqryh_Althanyh',
        'Alqryh_Althanyh - القرية الثانية '
    ),
    ('neighborhood', 'Alkramh.', 'Alkramh - الكرامة '),
    ('neighborhood', 'Alslam3', 'Alslam - السلام '),
    (
        'neighborhood',
        'Jbalya_Alshrqyh',
        'Jbalya_Alshrqyh - جباليا الشرقية '
    ),
    ('neighborhood', 'Alnwr', 'Alnwr - النور '),
    (
        'neighborhood',
        'Tl_Alzatr',
        'Tl_Alzatr - تل الزعتر '
    ),
    ('neighborhood', 'Alrwdh', 'Alrwdh - الروضة '),
    (
        'neighborhood',
        'Albldh_Alqdymh.',
        'Albldh_Alqdymh - البلدة القديمة '
    ),
    ('neighborhood', 'Alnzhh.', 'Alnzhh - النزهة '),
    ('neighborhood', 'Alzhwr.', 'Alzhwr - الزهور '),
    ('neighborhood', 'Alnhdh', 'Alnhdh - النهضة '),
    (
        'neighborhood',
        'Abad_Alrhmn',
        'Abad_Alrhmn - عباد الرحمن '
    ),
    ('neighborhood', 'Alabraj', 'Alabraj - الأبراج '),
    ('neighborhood', 'Albnat', 'Albnat - البنات '),
    (
        'neighborhood',
        'Abd_Aldaym',
        'Abd_Aldaym - عبد الدايم '
    ),
    (
        'neighborhood',
        'Albld_Alqdym',
        'Albld_Alqdym - البلد القديم '
    ),
    ('neighborhood', 'Alnzaz', 'Alnzaz - النزاز '),
    (
        'neighborhood',
        'Almsryyn',
        'Almsryyn - المصريين '
    ),
    ('neighborhood', 'Alfrth', 'Alfrth - الفرطة '),
    (
        'neighborhood',
        'Alqtbanyh',
        'Alqtbanyh - القطبانية '
    ),
    ('neighborhood', 'Alaml1', 'Alaml - الأمل '),
    (
        'neighborhood',
        'Mkhym_Jbalya',
        'Mkhym_Jbalya - مخيم جباليا '
    ),
    (
        'neighborhood',
        'Mshrwa_Byt_Lahya',
        'Mshrwa_Byt_Lahya - مشروع بيت لاهيا '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm2.',
        'Area No.2 - منطقة رقم2 '
    ),
    (
        'neighborhood',
        'Abw_Taymh',
        'Abw_Taymh - أبو طعيمة '
    ),
    ('neighborhood', 'Qdyh', 'Qdyh - قديح '),
    ('neighborhood', 'Alznh', 'Alznh - الزنة '),
    ('neighborhood', 'Almnarh', 'Almnarh - المنارة '),
    ('neighborhood', 'Alshabh2', 'Alshabh - الصحابة '),
    (
        'neighborhood',
        'Almwasy_Alshmaly',
        'Almwasy_Alshmaly - المواصي الشمالي '
    ),
    (
        'neighborhood',
        'Almwasy_Aljnwby',
        'Almwasy_Aljnwby - المواصي الجنوبي '
    ),
    ('neighborhood', 'Alnsr2', 'Alnsr - النصر '),
    ('neighborhood', 'Alstr', 'Alstr - السطر '),
    ('neighborhood', 'Aljla', 'Aljla - الجلاء '),
    ('neighborhood', 'Alktybh', 'Alktybh - الكتيبة '),
    ('neighborhood', 'Althryr', 'Althryr - التحرير '),
    ('neighborhood', 'Almhth', 'Almhth - المحطة '),
    (
        'neighborhood',
        'Albtn_Alsmyn',
        'Albtn_Alsmyn - البطن السمين '
    ),
    (
        'neighborhood',
        'Qyzan_Abw_Rshwan',
        'Qyzan_Abw_Rshwan - قيزان أبو رشوان '
    ),
    (
        'neighborhood',
        'Mrkz_Almdynh',
        'Mrkz_Almdynh - مركز المدينة '
    ),
    (
        'neighborhood',
        'Alshykh_Nasr',
        'Alshykh_Nasr - الشيخ ناصر '
    ),
    ('neighborhood', 'Alslam1.', 'Alslam - السلام '),
    (
        'neighborhood',
        'Qyzan_Alnjar',
        'Qyzan_Alnjar - قيزان النجار '
    ),
    (
        'neighborhood',
        'Qaa_Alqryn',
        'Qaa_Alqryn - قاع القرين '
    ),
    (
        'neighborhood',
        'Jwrh_Allwt',
        'Jwrh_Allwt - جورة اللوت '
    ),
    ('neighborhood', 'Man', 'Man - معن '),
    ('neighborhood', 'Alshhda.', 'Alshhda - الشهداء '),
    ('neighborhood', 'Alansar2', 'Alansar - الأنصار '),
    ('neighborhood', 'Alslam2', 'Alslam - السلام '),
    (
        'neighborhood',
        'Slah_Aldyn3',
        'Slah_Aldyn - صلاح الدين '
    ),
    ('neighborhood', 'Mkh', 'Mkh - مكة '),
    ('neighborhood', 'Alzlal', 'Alzlal - الظلال '),
    ('neighborhood', 'Almrwj', 'Almrwj - المروج '),
    ('neighborhood', 'Armydh', 'Armydh - ارميضه '),
    ('neighborhood', 'Alrdwan', 'Alrdwan - الرضوان '),
    ('neighborhood', 'Alanwar', 'Alanwar - الأنوار '),
    ('neighborhood', 'Alsnaty', 'Alsnaty - السناطي '),
    ('neighborhood', 'Alawdh1', 'Alawdh - العودة '),
    (
        'neighborhood',
        'Alshhadyh',
        'Alshhadyh - الشحادية '
    ),
    (
        'neighborhood',
        'Alfrahyn',
        'Alfrahyn - الفراحين '
    ),
    ('neighborhood', 'Qdyh1', 'Qdyh - قديح '),
    ('neighborhood', 'Alaskry', 'Alaskry - العسكري '),
    ('neighborhood', 'Almwl', 'Almwl - المول '),
    (
        'neighborhood',
        'Abw_Ywsf',
        'Abw_Ywsf - أبو يوسف '
    ),
    ('neighborhood', 'Alshwaf', 'Alshwaf - الشواف '),
    (
        'neighborhood',
        'Abw_Rydh',
        'Abw_Rydh - أبو ريدة '
    ),
    ('neighborhood', 'Alnjar', 'Alnjar - النجار '),
    ('neighborhood', 'Altqwa1', 'Altqwa - التقوى '),
    (
        'neighborhood',
        'Mntqh_Rqm7..',
        'Area No.7 - منطقة رقم7 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm5..',
        'Area No.5 - منطقة رقم5 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm4..',
        'Area No.4- منطقة رقم4 '
    ),
    ('neighborhood', 'Alshrwq', 'Alshrwq - الشروق '),
    ('neighborhood', 'Alnaym', 'Alnaym - النعيم '),
    ('neighborhood', 'Alrbya', 'Alrbya - الربيع '),
    ('neighborhood', 'Alwsty', 'Alwsty - الوسطي '),
    ('neighborhood', 'Alslam1', 'Alslam - السلام '),
    ('neighborhood', 'Alwrwd', 'Alwrwd - الورود '),
    (
        'neighborhood',
        'Am_Alwad',
        'Am_Alwad - أم الواد '
    ),
    ('neighborhood', 'Alshrqy', 'Alshrqy - الشرقي '),
    (
        'neighborhood',
        'Mntqh_Rqm6..',
        'Area No.6 - منطقة رقم6 '
    ),
    ('neighborhood', 'Albldyh', 'Albldyh - البلدية '),
    (
        'neighborhood',
        'Askan_Alawrwby',
        'Askan_Alawrwby - اسكان الأوروبي '
    ),
    (
        'neighborhood',
        'Albhr_Walmwasy',
        'Albhr_Walmwasy - البحر والمواصي '
    ),
    (
        'neighborhood',
        'Almhrrat.',
        'Almhrrat - المحررات '
    ),
    (
        'neighborhood',
        'Mntqh_Al_86',
        'Area No.86 - منطقة الـ 86 '
    ),
    (
        'neighborhood',
        'Almntqh_Alghrbyh',
        'Almntqh_Alghrbyh - المنطقة الغربية '
    ),
    ('neighborhood', 'Fyad', 'Fyad - فياض '),
    (
        'neighborhood',
        'Alshykh_Hmwdh',
        'Alshykh_Hmwdh - الشيخ حمودة '
    ),
    (
        'neighborhood',
        'Alabadlh_Walastl',
        'Alabadlh_Walastl - العبادلة والأسطل '
    ),
    (
        'neighborhood',
        'Mkhym_Khan_Ywns',
        'Mkhym_Khan_Ywns - مخيم خان يونس '
    ),
    ('neighborhood', 'Alrbat.', 'Alrbat - الرباط '),
    ('neighborhood', 'Alaml.', 'Alaml - الأمل '),
    ('neighborhood', 'Alawdh..', 'Alawdh - العودة '),
    (
        'neighborhood',
        'Abw_Alajyn',
        'Abw_Alajyn - أبو العجين '
    ),
    ('neighborhood', 'Alfyrwz', 'Alfyrwz - الفيروز '),
    ('neighborhood', 'Albrkh', 'Albrkh - البركة '),
    (
        'neighborhood',
        'Mntqh_Rqm4.',
        'Area No.4 - منطقة رقم4 '
    ),
    ('neighborhood', 'Alsdrh', 'Alsdrh - السدرة '),
    ('neighborhood', 'Alsfa..', 'Alsfa - الصفا '),
    (
        'neighborhood',
        'Aldmytha',
        'Aldmytha - الدميثاء '
    ),
    ('neighborhood', 'Alansar1', 'Alansar - الأنصار '),
    (
        'neighborhood',
        'Slah_Aldyn2',
        'Slah_Aldyn - صلاح الدين '
    ),
    ('neighborhood', 'Almtayn', 'Almtayn - المطاين '),
    (
        'neighborhood',
        'Alharh_Alshrqyh',
        'Alharh_Alshrqyh - الحارة الشرقية '
    ),
    ('neighborhood', 'Albld', 'Albld - البلد '),
    ('neighborhood', 'Albsh', 'Albsh - البصة '),
    ('neighborhood', 'Alqraan', 'Alqraan - القرعان '),
    ('neighborhood', 'Bsharh', 'Bsharh - بشارة '),
    (
        'neighborhood',
        'Abw_Aryf',
        'Abw_Aryf - أبو عريف '
    ),
    ('neighborhood', 'Alhdbh', 'Alhdbh - الحدبة '),
    ('neighborhood', 'Alhkr', 'Alhkr - الحكر '),
    (
        'neighborhood',
        'Am_Alazban',
        'Am_Alazban - ام العزبان '
    ),
    (
        'neighborhood',
        'Aljafrawy',
        'Aljafrawy - الجعفراوي '
    ),
    ('neighborhood', 'Am_Zhyr', 'Am_Zhyr - أم ظهير '),
    (
        'neighborhood',
        'Almshaalh_Abw_Fyad',
        'Almshaalh_Abw_Fyad - المشاعلة أبو فياض '
    ),
    ('neighborhood', 'Bwba', 'Bwba - بوبع '),
    (
        'neighborhood',
        'Abw_Khyshh',
        'Abw_Khyshh - أبو خيشة '
    ),
    ('neighborhood', 'Aldawh', 'Aldawh - الدعوة '),
    (
        'neighborhood',
        'Alastqamh',
        'Alastqamh - الاستقامة '
    ),
    (
        'neighborhood',
        'Brkh_Alwz',
        'Brkh_Alwz - بركة الوز '
    ),
    (
        'neighborhood',
        'Abw_Mzyd',
        'Abw_Mzyd - أبو مزيد '
    ),
    ('neighborhood', 'Alsbkhh', 'Alsbkhh - السبخة '),
    (
        'neighborhood',
        'Mntqh_Rqm13',
        'Area No.13 - منطقة رقم13 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm8',
        'Area No.8 - منطقة رقم8 '
    ),
    (
        'neighborhood',
        'Alaarwqy',
        'Alaarwqy - العاروقي '
    ),
    (
        'neighborhood',
        'Alzafran',
        'Alzafran - الزعفران '
    ),
    (
        'neighborhood',
        'Almntqh_Alzraayh',
        'Almntqh_Alzraayh - المنطقة الزراعية '
    ),
    (
        'neighborhood',
        'Almntqh_Alsnaayh',
        'Almntqh_Alsnaayh - المنطقة الصناعية '
    ),
    (
        'neighborhood',
        'Abw_Jlalh',
        'Abw_Jlalh - أبو جلالة '
    ),
    ('neighborhood', 'Alkramh', 'Alkramh - الكرامة '),
    ('neighborhood', 'Alnzhh', 'Alnzhh - النزهة '),
    (
        'neighborhood',
        'Mntqh_Rqm7.',
        'Area No.7 - منطقة رقم7 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm6.',
        'Area No.6 - منطقة رقم6 '
    ),
    ('neighborhood', 'Alansar.', 'Alansar - الأنصار '),
    (
        'neighborhood',
        'Mntqh_Rqm5.',
        'Area No.5 - منطقة رقم5 '
    ),
    (
        'neighborhood',
        'Almntqh_Alshrqyh',
        'Almntqh_Alshrqyh - المنطقة الشرقية '
    ),
    ('neighborhood', 'Alslam.', 'Alslam - السلام '),
    ('neighborhood', 'Alfarwq', 'Alfarwq - الفاروق '),
    ('neighborhood', 'Alaml', 'Alaml - الأمل '),
    ('neighborhood', 'Alawdh.', 'Alawdh - العودة '),
    ('neighborhood', 'Alsdyq', 'Alsdyq - الصديق '),
    ('neighborhood', 'Alshabh.', 'Alshabh - الصحابة '),
    ('neighborhood', 'Alwahh', 'Alwahh - الواحة '),
    (
        'neighborhood',
        'Tl_Alzhwr',
        'Tl_Alzhwr - تل الزهور '
    ),
    ('neighborhood', 'Alrhmh.', 'Alrhmh - الرحمة '),
    (
        'neighborhood',
        'Slah_Aldyn',
        'Slah_Aldyn - صلاح الدين '
    ),
    ('neighborhood', 'Alansar', 'Alansar - الأنصار '),
    (
        'neighborhood',
        'Mkhym_Dyr_Alblh',
        'Mkhym_Dyr_Alblh - مخيم دير البلح '
    ),
    ('neighborhood', 'Alshrth', 'Alshrth - الشرطة '),
    (
        'neighborhood',
        'Abw_Mghsyb',
        'Abw_Mghsyb - أبو مغصيب '
    ),
    (
        'neighborhood',
        'Almkhym_Aljdyd',
        'Almkhym_Aljdyd - المخيم الجديد '
    ),
    (
        'neighborhood',
        'Abw_Mhady',
        'Abw_Mhady - أبو مهادي '
    ),
    (
        'neighborhood',
        'Alhsaynh_Alghrby',
        'Alhsaynh_Alghrby - الحساينة الغربي '
    ),
    ('neighborhood', 'AlآThar', 'AlآThar - الآثار '),
    (
        'neighborhood',
        'Abwslym_Alghrby',
        'Abwslym_Alghrby - أبوسليم الغربي '
    ),
    ('neighborhood', 'F_Blwk', 'F_Blwk - F بلوك '),
    (
        'neighborhood',
        'Abw_Slym_Alshrqy',
        'Abw_Slym_Alshrqy - أبو سليم الشرقي '
    ),
    ('neighborhood', 'Srswr', 'Srswr - صرصور '),
    ('neighborhood', 'Alshhda', 'Alshhda - الشهداء '),
    ('neighborhood', 'C_Blwk', 'C_Blwk - C بلوك '),
    ('neighborhood', 'Almfty', 'Almfty - المفتي '),
    ('neighborhood', 'Mtr', 'Mtr - مطر '),
    (
        'neighborhood',
        'Mntqh_Rqm6',
        'Area No.6 - منطقة رقم6 '
    ),
    (
        'neighborhood',
        'Abw_Amyrh',
        'Abw_Amyrh - أبو عميرة '
    ),
    ('neighborhood', 'Alslam', 'Alslam - السلام '),
    (
        'neighborhood',
        'Mntqh_Rqm1.',
        'Area No.1 - منطقة رقم1 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm2..',
        'Area No.2 - منطقة رقم2 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm3..',
        'Area No.3 - منطقة رقم3 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm4...',
        'Area No.4 - منطقة رقم4 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm5',
        'Area No.5 - منطقة رقم5 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm7',
        'Area No.7 - منطقة رقم7 '
    ),
    ('neighborhood', 'Alrbat', 'Alrbat - الرباط '),
    ('neighborhood', 'Alshaar', 'Alshaar - الشاعر '),
    ('neighborhood', 'Alshabh', 'Alshabh - الصحابة '),
    (
        'neighborhood',
        'Abw_Snymh_Waldbary',
        'Abw_Snymh_Waldbary - أبو سنيمة والدباري '
    ),
    (
        'neighborhood',
        'Qryh_Alshhda',
        'Qryh_Alshhda - قرية الشهداء '
    ),
    (
        'neighborhood',
        'Msab_Bn_Amyr',
        'Msab_Bn_Amyr - مصعب بن عمير '
    ),
    ('neighborhood', 'Alsfa', 'Alsfa - الصفا '),
    ('neighborhood', 'Almwasy', 'Almwasy - المواصي '),
    (
        'neighborhood',
        'Almhrrat',
        'Almhrrat - المحررات '
    ),
    (
        'neighborhood',
        'Tl_Alsltan',
        'Tl_Alsltan - تل السلطان '
    ),
    (
        'neighborhood',
        'Rfh_Alghrbyh',
        'Rfh_Alghrbyh - رفح الغربية '
    ),
    ('neighborhood', 'Alhshash', 'Alhshash - الحشاش '),
    (
        'neighborhood',
        'Alshabwrh',
        'Alshabwrh - الشابورة '
    ),
    ('neighborhood', 'Msbh', 'Msbh - مصبح '),
    ('neighborhood', 'Alzhwr', 'Alzhwr - الزهور '),
    (
        'neighborhood',
        'Mkhym_Rfh',
        'Mkhym_Rfh - مخيم رفح '
    ),
    (
        'neighborhood',
        'Khrbh_Alads',
        'Khrbh_Alads - خربة العدس '
    ),
    ('neighborhood', 'Aladary', 'Aladary - الاداري '),
    (
        'neighborhood',
        'Tbh_Zara',
        'Tbh_Zara - تبة زارع '
    ),
    ('neighborhood', 'Aljnynh', 'Aljnynh - الجنينة '),
    ('neighborhood', 'Albywk', 'Albywk - البيوك '),
    (
        'neighborhood',
        'Mdrsh_Albnat',
        'Mdrsh_Albnat - مدرسة البنات '
    ),
    ('neighborhood', 'Alhsy', 'Alhsy - الهسي '),
    (
        'neighborhood',
        'Alshlalfh_Walqra',
        'Alshlalfh_Walqra - الشلالفة والقرا '
    ),
    (
        'neighborhood',
        'Abw_Lwly',
        'Abw_Lwly - أبو لولي '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm5...',
        'Area No.5 - منطقة رقم5 '
    ),
    ('neighborhood', 'Alwady', 'Alwady - الوادي '),
    (
        'neighborhood',
        'Blal_Bn_Rbah',
        'Blal_Bn_Rbah - بلال بن رباح '
    ),
    ('neighborhood', 'Alzytwn', 'Alzytwn - الزيتون '),
    ('neighborhood', 'Alawdh', 'Alawdh - العودة '),
    (
        'neighborhood',
        'Mkhym_Alshata',
        'Mkhym_Alshata - مخيم الشاطىء '
    ),
    (
        'neighborhood',
        'Alrmal_Alshmaly',
        'Alrmal_Alshmaly - الرمال الشمالي '
    ),
    ('neighborhood', 'Alnsr', 'Alnsr - النصر '),
    ('neighborhood', 'Aldrj', 'Aldrj - الدرج '),
    (
        'neighborhood',
        'Alshykh_Rdwan',
        'Alshykh_Rdwan - الشيخ رضوان '
    ),
    ('neighborhood', 'Altfah', 'Altfah - التفاح '),
    (
        'neighborhood',
        'Alshjaayh_Ajdydh',
        'Alshjaayh_Ajdydh - الشجاعية - اجديدة '
    ),
    (
        'neighborhood',
        'Albldh_Alqdymh',
        'Albldh_Alqdymh - البلدة القديمة '
    ),
    ('neighborhood', 'Alsbrh', 'Alsbrh - الصبرة '),
    (
        'neighborhood',
        'Tl_Alhwa',
        'Tl_Alhwa - تل الهوا '
    ),
    (
        'neighborhood',
        'Alrmal_Aljnwby',
        'Alrmal_Aljnwby - الرمال الجنوبي '
    ),
    (
        'neighborhood',
        'Alshykh_Ajlyn',
        'Alshykh_Ajlyn - الشيخ عجلين '
    ),
    (
        'neighborhood',
        'Alshjaayh_Altrkman',
        'Alshjaayh_Altrkman - الشجاعية - التركمان '
    ),
    (
        'neighborhood',
        'Ajdydh_Alshrqyh',
        'Ajdydh_Alshrqyh - اجديدة الشرقية '
    ),
    (
        'neighborhood',
        'Altrkman_Alshrqy',
        'Altrkman_Alshrqy - التركمان الشرقي '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm2',
        'Area No.2 - منطقة رقم2 '
    ),
    (
        'neighborhood',
        'Mdynh_Alzhra',
        'Mdynh_Alzhra - مدينة الزهراء '
    ),
    (
        'neighborhood',
        'Jhr_Aldyk',
        'Jhr_Aldyk - جحر الديك '
    ),
    ('neighborhood', 'Alhda.', 'Alhda - الهدى '),
    (
        'neighborhood',
        'Alqadsyh',
        'Alqadsyh - القادسية '
    ),
    ('neighborhood', 'Alrhmh', 'Alrhmh - الرحمة '),
    (
        'neighborhood',
        'Abw_Hryrh',
        'Abw_Hryrh - أبو هريرة '
    ),
    ('neighborhood', 'Bdr', 'Bdr - بدر '),
    ('neighborhood', 'Altqwa', 'Altqwa - التقوى '),
    ('neighborhood', 'AlإYman', 'AlإYman - الإيمان '),
    ('neighborhood', 'Alhda', 'Alhda - الهدى '),
    (
        'neighborhood',
        'Mntqh_Rqm3',
        'Area No.3 - منطقة رقم3 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm1',
        'Area No.1 - منطقة رقم1 '
    ),
    (
        'neighborhood',
        'Mntqh_Rqm4',
        'Area No.4 - منطقة رقم4 '
    );