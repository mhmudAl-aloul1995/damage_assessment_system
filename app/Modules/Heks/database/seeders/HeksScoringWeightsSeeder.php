<?php

namespace Database\Seeders;

use App\Modules\Heks\Models\HeksScoringWeight;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HeksScoringWeightsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            HeksScoringWeight::query()
                ->whereIn('survey_phase', $this->phases())
                ->whereIn('source', ['Shelter Technical Weights', 'S-V'])
                ->delete();

            foreach ($this->phases() as $phase) {
                foreach ([...$this->technicalWeights(), ...$this->socialWeights()] as $weight) {
                    HeksScoringWeight::query()->create(array_merge($weight, [
                        'survey_phase' => $phase,
                    ]));
                }
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function phases(): array
    {
        return ['phase_1', 'phase_2'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function technicalWeights(): array
    {
        return [
            $this->technical('Sealing & Internal Privacy', 'Damage assessment', 'تقييم حالة ضرر المأوى:', 4, 1, 4, 2, 0),
            $this->technical('Sealing & Internal Privacy', 'Roof condition (حالة السقف)', 'حالة السقف', 4, 2, 4, 2, 0),
            $this->technical('Sealing & Internal Privacy', 'One main room can be sealed properly', 'يوجد على الأقل غرفة واحدة معزولة ومحكمة الإغلاق ومؤمنة ضد الحرارة والبرودة:', 3, 3, 3, 1.5, 0),
            $this->technical('Sealing & Internal Privacy', 'Need sealing / partitions between rooms', 'يوجد مشكلة في الفواصل والقواطع بين الغرف وخدمات الوحدة:', 2, 4, 2, 1, 0),
            $this->technical('External walls', 'External walls', 'حالة الجدران الخارجية', 3, 5, 3, 1.5, 0),
            $this->technical('Internal walls', 'Internal walls', 'حالة الجدران الداخلية', 3, 6, 3, 1.5, 0),
            $this->technical('Sealing & Internal Privacy', 'Proper sealing of bedroom windows', 'إحكام إغلاق نوافذ غرف النوم', 3, 7, 3, 1.5, 0),
            $this->technical('WASH / Bathroom', 'Functional toilet bathroom conditions', 'كحد أدني يوجد مرحاض في الوحدة السكنية بمساحة 3.5 الي 4 م2، مع تهويه للخارج صالح للإستخدام لأفراد الأسرة', 3, 8, 3, 1.5, 0),
            $this->technical('WASH / Bathroom', 'Toilet seat with flush', 'يتواجد في الحمام كرسي حمام مع نيجارا بحالة جيدة', 2, 9, 2, 1, 0),
            $this->technical('WASH / Bathroom', 'Bathroom door sealing', 'إحكام إغلاق باب دورة المياه:', 3, 10, 3, 1.5, 0),
            $this->technical('WASH / Bathroom', 'Sink in bathroom', 'يتواجد في الحمام مغسلة لدورة المياه', 1, 11, 1, 0.5, 0),
            $this->technical('WASH / Bathroom', 'Shower mixer', 'يتواجد في الحمام دش/خلاط', 1, 12, 1, 0.5, 0),
            $this->technical('Doors & Windows', 'Bedroom windows sealing', 'يوجد بالمرحاض نافذه قابله للفتح والإغلاق ومحكمة', 1, 13, 1, 0.5, 0),
            $this->technical('Doors & Windows', 'Bedroom doors sealing', 'إحكام إغلاق أبواب غرف النوم:', 3, 14, 3, 1.5, 0),
            $this->technical('Doors & Windows', 'Main entrance door sealing', 'يوجد باب رئيسي محكم الإغلاق للوحدة السكنية', 3, 15, 3, 1.5, 0),
            $this->technical('Water & Sewer', 'Connection to water network', 'الوحدة متصلة بشبكة امداد المياه في الوحدة السكنية', 4, 16, 4, 2, 0),
            $this->technical('Water & Sewer', 'Connection to sewer network', 'الوحدة متصلة بشبكة الصرف الصحي الرئيسية', 4, 17, 4, 2, 0),
            $this->technical('Water & Sewer', 'Water tank ≥1000L', 'كحد أدني يوجد خزان مياه سعة 1000 لتر بحالة جيدة', 1, 18, 1, 0, 0),
            $this->technical('Water & Sewer', 'Drinking water tank 200L', 'يوجد خزان سعة 200 لتر مع صنبور في مكان امن', 1, 19, 1, 0, 0),
            $this->technical('Kitchen', 'General kitchen condition', 'حالة المطبخ:', 3, 20, 3, 1.5, 0),
            $this->technical('Kitchen', 'Kitchen countertop availability', 'يوجد حوض مجلى وطاولة إعداد طعام داخل المطبخ؟', 4, 21, 4, 2, 0),
            $this->technical('Space & Accessibility', 'Overcrowding (<5.5 m² per person)', 'المساحة تكفي لعدد الأفراد (مساحة الفرد أكثر أو أقل من 5.5 م2 من مساحة الوحدة):', 4, 22, 4, 2, 0),
            $this->technical('Space & Accessibility', 'Total people inside Unit', 'اجمالي عدد الأفراد بالوحدة السكنية', 4, 23, 4, 2, 0),
            $this->technical('Space & Accessibility', 'PWD safe access', 'اذا كان هناك أشخاص ذوي اعاقه فهم لديهم امكانيه الوصول الآمن الي مرافق الوحدة السكنية (الحمام والمطبخ)', 2, 24, 2, 1, 0),
            $this->technical('Space & Accessibility', 'Safe access to the unit - Stair', 'ما هي حالة الدرج المؤدي إلى الوحدة السكنية', 2, 25, 2, 1, 0),
            $this->technical('Lighting', 'Lighting', 'يوجد مصدر انارة ليلى في الوحدة السكنية؟', 2, 26, 2, 1, 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function socialWeights(): array
    {
        return [
            $this->social('Female-headed household', 'جنس رب الأسرة', 'أنثى'),
            $this->social('Household head age >60 or <18', 'العمر', 'أقل من 17'),
            $this->social('Household head age >60 or <18', 'العمر', 'أكبر من 60'),
            $this->social('At least one chronic health condition', 'هل يعاني من معيل الأسرة من مرض مزمن', 'نعم'),
            $this->social('Disability present in household', 'يوجد أشخاص ذوي إعاقة', 'نعم'),
            $this->social('Single-parent household (Divorced, Separated, widowed, abandoned)', 'الحالة الاجتماعية', 'أرمل/ـة'),
            $this->social('Single-parent household (Divorced, Separated, widowed, abandoned)', 'الحالة الاجتماعية', 'مطلق/ـة'),
            $this->social('Single-parent household (Divorced, Separated, widowed, abandoned)', 'الحالة الاجتماعية', 'منفصل/ـة أو مهجورة'),
            $this->social('No adult able to support repairs', 'يوجد شخص بالغ واحد على الأقل في المنزل يمكنه المساعدة في الإصلاح أو الصيانة؟', 'لا'),
            $this->social('Household can organize repairs themselves if given cash', 'تستطيع الأسرة تنظيم أعمال الصيانة بنفسها إذا مُنحت مبلغاً نقدياً؟', 'نعم'),
            $this->social('Availability of valid proof of ownership/lease/hosting agreement', 'يتوفر إثبات ساري المفعول للملكية/الإيجار/اتفاقية الاستضافة', 'نعم'),
            $this->social('Children <18 present', 'أفراد الأسرة 8 سنوات وأقل', 'نعم'),
            $this->social('Presence of pregnant/lactating women in the housing unit', 'يوجد نساء حوامل أو مرضعات', 'نعم'),
            $this->social('Current housing type, shared unit with more than one family', 'نوع المساحة المستخدمة', 'مساحة مشتركة بين أكثر من أسرة'),
            $this->social('No continuous secured income', 'هل تمتلك الأسرة مصدر دخل ثابت أو منتظم؟', 'لا'),
            $this->social('Heavily dependent on food aid', 'هل تعتمد الأسرة في توفير الطعام على التكية و/أو المساعدات الغذائية؟', 'بشكل كامل'),
            $this->social('Heavily dependent on food aid', 'هل تعتمد الأسرة في توفير الطعام على التكية و/أو المساعدات الغذائية؟', 'بشكل جزئي'),
            $this->social('No or insufficient condition of furniture', 'هل يتوفر لدى الأسرة الاحتياجات الأساسية للمعيشة مثل مواد الفراش وأدوات المطبخ', 'نعم، بشكل غير كافي'),
            $this->social('No or insufficient condition of furniture', 'هل يتوفر لدى الأسرة الاحتياجات الأساسية للمعيشة مثل مواد الفراش وأدوات المطبخ', 'لا'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technical(string $category, string $indicator, string $questionKey, float $weight, int $order, float $max, float $avg, float $min): array
    {
        return [
            'source' => 'Shelter Technical Weights',
            'category' => $category,
            'indicator' => $indicator,
            'question_key' => $questionKey,
            'option_value' => null,
            'weight' => $weight,
            'option_score' => $weight,
            'raw_data' => [
                'source_file' => "Heks_Final_V1 Score - All BNF's -Heks 23.07.2026.xlsx",
                'source_sheet' => 'Shelter Technical Weights',
                'order' => $order,
                'max' => $max,
                'avg' => $avg,
                'min' => $min,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function social(string $indicator, string $questionKey, string $optionValue): array
    {
        return [
            'source' => 'S-V',
            'category' => 'Social Vulnerability',
            'indicator' => $indicator,
            'question_key' => $questionKey,
            'option_value' => $optionValue,
            'weight' => null,
            'option_score' => 5,
            'raw_data' => [
                'source_file' => 'Scoring matrix S -2 (3).docx',
                'source_sheet' => 'S-V',
                'no_score' => 0,
                'yes_score' => 5,
            ],
        ];
    }
}
