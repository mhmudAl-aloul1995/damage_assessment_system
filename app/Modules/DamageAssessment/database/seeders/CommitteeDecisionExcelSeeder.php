<?php

namespace Database\Seeders;

use App\services\CommitteeDecisionExcelImportService;
use Illuminate\Database\Seeder;

class CommitteeDecisionExcelSeeder extends Seeder
{
    /**
     * @var list<array{objectid: int, globalid: string|null, decision: string, action: string|null, members: string|null, hint: string|null}>
     */
    private const RECORDS = [
        ['objectid' => 3293, 'globalid' => '{0FEB5DF2-7062-4F23-B61B-BB5C046A62E1}', 'decision' => 'هدم كلي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5137, 'globalid' => '{435CF742-07BD-4D4F-A917-CDC87840038A}', 'decision' => 'تحول لجنة فنية أخرى', 'action' => null, 'members' => null, 'hint' => null],
        ['objectid' => 5221, 'globalid' => '{C9C60F15-F260-44D1-BB44-092EBA71998B}', 'decision' => 'تحول لجنة فنية أخرى', 'action' => null, 'members' => null, 'hint' => null],
        ['objectid' => 5647, 'globalid' => '{61FC4279-ED87-4A11-8826-648668F6A940}', 'decision' => 'جزئي اعادة حصر', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5908, 'globalid' => '{2DDFD8D3-7808-4EEC-81B0-ABCB912CDE04}', 'decision' => 'جزئي اعادة حصر', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5975, 'globalid' => '{1F1E1E66-2401-452E-BDBF-7464AFA85E76}', 'decision' => 'جزئي اعادة حصر (ارضي واول جزئي-ثاني وثالث كلي)', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5044, 'globalid' => null, 'decision' => 'ضرر جزئي ', 'action' => 'الرجاء اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5167, 'globalid' => null, 'decision' => 'ضرر جزئي طوابق ارضي وسدة واول وهدم كلي 4 طوابق', 'action' => 'الرجاء اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5232, 'globalid' => null, 'decision' => 'هدم كلي', 'action' => 'الرجاء اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5706, 'globalid' => null, 'decision' => 'يتم تحويلها لمساح لفحصها', 'action' => null, 'members' => null, 'hint' => null],
        ['objectid' => 5825, 'globalid' => null, 'decision' => 'ضرر جزئي', 'action' => 'الرجاء اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5896, 'globalid' => null, 'decision' => 'هدم كلي', 'action' => 'الرجاء اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6179, 'globalid' => null, 'decision' => 'هدم كلي', 'action' => 'الرجاء اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6472, 'globalid' => null, 'decision' => 'ضرر جزئي', 'action' => 'الرجاء اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3409, 'globalid' => null, 'decision' => 'قص الجزء الغربي من العمارة من الدرج حتى المخازن  من منسوب الحزام الأرضي للدور الأرضي والسدة والأول', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3443, 'globalid' => null, 'decision' => 'حصر المبنى جزئي مع التركيز في احتساب مناطق القص للاسقف', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3983, 'globalid' => null, 'decision' => 'اعادة حصر -ضرر جزئي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 4097, 'globalid' => null, 'decision' => 'المبنى قديم متهالك عدم الحصر لان الاضرار ليست بسبب الحرب', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 4416, 'globalid' => null, 'decision' => 'المبنى قديم متهالك عدم الحصر لان الاضرار ليست بسبب الحرب', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 4548, 'globalid' => null, 'decision' => 'احتساب كامل المبنى هدم كلي مع التوصية بالاخلاء الفوري المبنى تعرض لميول ويشكل خطر كبير على سلامة السكان والتاكيد على ان الدعم غير كافي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5235, 'globalid' => null, 'decision' => 'إعادة حصر المبنى اضرار جزئية الناتجة عن الحرب (طفيفة)', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5577, 'globalid' => null, 'decision' => 'هدم كلي للمبنى ', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5792, 'globalid' => null, 'decision' => 'ضرر جزئي  -اعادة حصر', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6056, 'globalid' => null, 'decision' => 'ضرر جزئي  -اعادة حصر', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6057, 'globalid' => null, 'decision' => 'ضرر جزئي  -اعادة حصر', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6841, 'globalid' => null, 'decision' => 'حصر المبنى هدم كلي مع التأكد من المساحات الباطون + الملحق السكوريت وعدد الأسر النووية(ملحق السكوريت في الأرضي حوالي 60 م2 والأول سكوريت وحدة غير سكنية مصنع حلويات', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3254, 'globalid' => null, 'decision' => 'احتساب الوحدة هدم كلي كاملة وحدة واحدة باسم رياض الدريملي
عدد 2 اسرة نووية وليست وحدتين', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3792, 'globalid' => null, 'decision' => 'قص الجزء الشرقي للسقف المتضرر لشقة الدالي حتى الروف
مع التوصية بالتدعيم العاجل في الملاحظات', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3341, 'globalid' => null, 'decision' => '(كل المبنى هدم كلي) تكملة حصر الدور الأرضي هدم كلي كباقي الوحدات', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6002, 'globalid' => null, 'decision' => 'إعادة حصر جميع وحدات المبنى هدم كلي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'وحدات', 'hint' => null],
        ['objectid' => 3928, 'globalid' => null, 'decision' => 'الروف + 3 وحدات تحته كلي اما الوحدة الخامسة شقة لبد ضرر جزئي اعادة حصر مع المشرف', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5656, 'globalid' => null, 'decision' => 'إعادة حصر جميع الوحدات جزئي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5637, 'globalid' => null, 'decision' => 'مبنى قديم متهالك حصر الاضرار بسبب الحرب فقط', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 4031, 'globalid' => null, 'decision' => 'إعادة حصر الوحدة في الدور الثاني جزئي مع التركيز على الاعمدة جهة عمارة أبو عيدة كونها مغطاة بشوادر', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3588, 'globalid' => null, 'decision' => 'تم الزيارة عدة مرات ولا توجد استجابة تعذر الوصول للسكان', 'action' => null, 'members' => null, 'hint' => null],
        ['objectid' => 6253, 'globalid' => null, 'decision' => 'إعادة حصر كامل المبنى جزئي وكتابة في الملاحظات الردم من المبنى المجاور', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5240, 'globalid' => null, 'decision' => 'حصر الوحدة جزئي مع عمل طبقة عزل على مساحة كامل السقف', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3422, 'globalid' => null, 'decision' => 'إعادة حصر المبنى هدم كلي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3434, 'globalid' => null, 'decision' => 'حصر الوحدة في الطابق الأخير هدم كلي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 3721, 'globalid' => null, 'decision' => 'إعادة حصر المبنى جزئي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6270, 'globalid' => null, 'decision' => 'إعادة حصر الوحدات جزئي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 7380, 'globalid' => null, 'decision' => 'ضرر جزئي للطابق الأرضي والأول مع ضرورة التوصية بالتدعيم 
والتركيز على حساب كميات خرسانة القواعد والرقاب والأعمدة والأحزمة الأرضية للقواعد الطرفية كونها غير ظاهرة', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 5272, 'globalid' => 'Mojahed.Faraht', 'decision' => 'هدم كلي كل المبنى', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6277, 'globalid' => null, 'decision' => 'ضرر جزئي اعادة حصر', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6858, 'globalid' => 'Amal.Busafia', 'decision' => 'حصر الدور الأرضي والأول جزئي والدور الثاني هدم كلي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 6943, 'globalid' => null, 'decision' => 'حصر الطابق الثاني والثالث والرابع الجهة الغربية يسار الدرج هدم كلي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => null, 'hint' => null],
        ['objectid' => 11721, 'globalid' => null, 'decision' => 'ازالة 80 متر مربع من السقف المتضرر وحصر الباقي جزئي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/م.ايهاب ابو عودة -وزارة الاشغال', 'hint' => null],
        ['objectid' => 11982, 'globalid' => 'Eyad.Elwan', 'decision' => 'قص الجزء الغربي من المبنى المتضرر واعادة حصر باقي المبنى', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. اسامة مرزوق-مجلس الاسكان/عبد الرحمن شملخ -UNDP/م.ايهاب ابو عودة -وزارة الاشغال', 'hint' => null],
        ['objectid' => 12074, 'globalid' => null, 'decision' => 'اعادة حصر المبنى بالكامل ضرر جزئي مع توصية عمل فحوصات مخبرية للاعمدة والسقف للتأكد من درجة الحريق ونسبة تضرر للأعمدة والاسقف في ملاحظات المهندس', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 14358, 'globalid' => null, 'decision' => 'تحويل المبنى الى لجنة حصر المباني الاثرية', 'action' => null, 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/ايهاب ابو عودة -وزارة الاشغال', 'hint' => null],
        ['objectid' => 15709, 'globalid' => null, 'decision' => 'الطابق الاول بعد الحواصل الشقة الجنوبية الغربية الشمالية قص جزء من السقف المتضرر وحصر باقي الشقة-الطابق الثاني قصارة +دهان-الطابق الثالث كلي وما يليه من طوابق مع توصية اخلاء الوحدات من الطابق الثالث للخطورة الشديدة الموجودة جراء انهيار الاسقف وتضرر الاعمدة', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 11752, 'globalid' => null, 'decision' => 'الجزء الجنوبي حصر جزئي 4 وحدات-الجزء الشمالي قص كلي 2 وحدة', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. اسامة مرزوق-مجلس الاسكان/عبد الرحمن شملخ -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 13526, 'globalid' => null, 'decision' => 'اعادة الحصر جزئي وقص جزء من السقف المتضرر', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 13525, 'globalid' => null, 'decision' => 'اعادة الحصر -قص 80 متر مربع من السقف من مساحة 400 متر مربع في البرندة -القص يكون في الباكية 3و 4 والمطبخ والجزء الخلفي المغلق-بالاضافة الى حصر الجزء العلوي من المبنى المسقوف زينجو جزئي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 6745, 'globalid' => null, 'decision' => 'جزئي اعادة حصر المبنى', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 4732, 'globalid' => null, 'decision' => 'الطابق الارضي جزئي قص السقف حسب الاضرار والتأكد من عدد الطوابق', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 4905, 'globalid' => null, 'decision' => 'الأرضي جزئي -والتحري عن باقي الطوابق(التأكد من الاشغال)', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 4790, 'globalid' => null, 'decision' => 'الطابق الارضي قص السقف في 3 غرف والباقي يتم حصره -مساحته 170 متر مربع', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. اسامة مرزوق-مجلس الاسكان/عبد الرحمن شملخ -UNDP/م.ايهاب ابو عودة -وزارة الاشغال', 'hint' => null],
        ['objectid' => 5106, 'globalid' => null, 'decision' => 'المبنى هدم كلي -في الارضي الاعمدة تعرضت لحريق شديد كذلك السقف مما ادى الى تاكل الحديد والخرسانة في كثير من الاماكن', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. اسامة مرزوق-مجلس الاسكان/عبد الرحمن شملخ -UNDP/م.ايهاب ابو عودة -وزارة الاشغال', 'hint' => null],
        ['objectid' => 6799, 'globalid' => null, 'decision' => 'اعادة حصر المبنى بالكامل -جزئي', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => null],
        ['objectid' => 4949, 'globalid' => null, 'decision' => 'المبنى هدم كلي -الاعمدة في الارضي جميعها يوجد بها كسر -فقط 60 متر مربع لا يوجد بها ضرر من مساحة 165 متر مربع', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. اسامة مرزوق-مجلس الاسكان/عبد الرحمن شملخ -UNDP/م.ايهاب ابو عودة -وزارة الاشغال', 'hint' => null],
        ['objectid' => 12565, 'globalid' => null, 'decision' => 'اعادة حصر جزئي -عبارة عن حريق وتفريغ للشقة مع قص الاجزاء المتضررة من السقف في اكثر من غرفة', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م. محسن حرزالله-مجلس الاسكان/م . اسامة مرزوق-مجلس الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => 'وحدات'],
        ['objectid' => 12303, 'globalid' => null, 'decision' => 'اعادة حصر الوحدة جزئي ةقص الجزء المتضرر من السقف', 'action' => 'اعادة المبنى للمهندس لحصره', 'members' => 'م . اسامة مرزوق-مجلس الاسكان/عدنان العكلوك -UNDP/م.جمال ابو الكاس -وزارة الاشغال', 'hint' => 'وحدات'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(CommitteeDecisionExcelImportService $importer): void
    {
        $summary = $importer->importRecords(self::RECORDS);

        $this->command?->info(sprintf(
            'Committee review seed completed: %d rows, %d created, %d updated, %d skipped.',
            $summary['rows'],
            $summary['decisions_created'],
            $summary['decisions_updated'],
            $summary['skipped_rows'],
        ));

        if ($summary['missing_users'] !== []) {
            $this->command?->warn(sprintf(
                'Committee review seed has %d committee member names that were not matched to users.',
                count($summary['missing_users']),
            ));
        }
    }
}
