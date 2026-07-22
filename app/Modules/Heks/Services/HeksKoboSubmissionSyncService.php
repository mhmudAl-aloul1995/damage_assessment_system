<?php

namespace App\Modules\Heks\Services;

use App\Models\KoboRestSubmission;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqCatalogItem;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksKoboChoice;
use App\Modules\Heks\Models\HeksKoboFieldMapping;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksScore;
use App\Modules\Heks\Models\HeksScoringWeight;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HeksKoboSubmissionSyncService
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $displayLabelCache = [];

    public function __construct(
        private HeksEngineerUserResolver $engineerUserResolver,
        private HeksKoboServiceRegistry $serviceRegistry,
        private HeksValueNormalizer $normalizer,
    ) {}

    /**
     * @return array{status: string, error: ?string, beneficiary: ?HeksBeneficiary, follow_up: ?HeksFollowUp, boq_items: int}|null
     */
    public function sync(KoboRestSubmission $submission): ?array
    {
        if (! $this->isHeksService($submission->service_name)) {
            return null;
        }

        $payload = $submission->payload ?? [];
        $service = $submission->service_name;
        $flatPayload = $this->withMappedDisplayLabels($this->flatten($payload), $service);
        $beneficiary = $this->beneficiary($flatPayload, $service);

        if (! $beneficiary instanceof HeksBeneficiary) {
            return [
                'status' => 'skipped',
                'error' => 'HEKS Kobo submission does not include a beneficiary code or a matching beneficiary name.',
                'beneficiary' => null,
                'follow_up' => null,
                'boq_items' => 0,
            ];
        }

        $this->syncBeneficiary($beneficiary, $flatPayload, $service);
        $this->syncScores($beneficiary, $flatPayload, $service);
        $this->syncLabels($beneficiary, $flatPayload, $service);
        $this->syncAllSurveyAnswers($beneficiary, $flatPayload, $service);
        $this->syncAttachments($beneficiary, $payload, $flatPayload, $service);

        $followUp = null;
        $boqItems = 0;

        if (in_array($this->handler($service), ['followup', 'followup_boq'], true)) {
            $followUp = $this->syncFollowUp($beneficiary, $flatPayload, $service);
        }

        if (in_array($this->handler($service), ['main', 'boq', 'followup_boq'], true)) {
            $boqItems = $this->syncBoqItems($beneficiary, $payload, $flatPayload, $service, $followUp);
        }

        $this->syncKoboRecordFields($submission, $beneficiary, $followUp, $flatPayload);

        return [
            'status' => 'synced',
            'error' => null,
            'beneficiary' => $beneficiary,
            'follow_up' => $followUp,
            'boq_items' => $boqItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function beneficiary(array $payload, string $service): ?HeksBeneficiary
    {
        $code = $this->first($payload, [
            'code',
            'Code',
            'application_code',
            'beneficiary_code',
            'case_code',
            'request_code',
            "\u{0631}\u{0642}\u{0645} \u{0627}\u{0644}\u{0637}\u{0644}\u{0628}/\u{0627}\u{0644}\u{0643}\u{0648}\u{062F}",
            "\u{0631}\u{0642}\u{0645} \u{0627}\u{0644}\u{0637}\u{0644}\u{0628}",
            "\u{0627}\u{0644}\u{0643}\u{0648}\u{062F}",
            "\u{0643}\u{0648}\u{062F}",
            'رقم الطلب/الكود',
            'رقم الطلب',
            'الكود',
            'كود',
        ]);

        if ($code === '') {
            $code = $this->findByKeyPart($payload, ['application_code', 'beneficiary_code', 'case_code', 'request_code', 'code', 'الكود', 'كود']);
        }

        if ($code !== '') {
            return HeksBeneficiary::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $this->beneficiaryName($payload) ?: null,
                    'raw_data' => [$service => $payload],
                ]
            );
        }

        $name = $this->beneficiaryName($payload);

        if ($name === '') {
            return null;
        }

        return HeksBeneficiary::query()
            ->where('name', $name)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncBeneficiary(HeksBeneficiary $beneficiary, array $payload, string $service): void
    {
        $beneficiaryName = $this->beneficiaryName($payload);
        $fieldEngineer = $this->beneficiaryFieldEngineer($payload, $service);

        $data = array_filter([
            'name' => $beneficiaryName,
            'identity_number' => $this->first($payload, ['identity_number', 'id_number', 'beneficiary_id_number', '_003', "\u{0631}\u{0642}\u{0645} \u{0647}\u{0648}\u{064A}\u{0629} \u{0631}\u{0628} \u{0627}\u{0644}\u{0623}\u{0633}\u{0631}\u{0629}", "\u{0631}\u{0642}\u{0645} \u{0627}\u{0644}\u{0647}\u{0648}\u{064A}\u{0629}", "\u{0627}\u{0644}\u{0647}\u{0648}\u{064A}\u{0629}", 'رقم هوية رب الأسرة', 'رقم الهوية', 'الهوية']),
            'phone' => $this->first($payload, ['phone', 'phone_number', 'mobile', 'q_103', 'q_095', 'رقم التواصل', 'رقم الجوال']),
            'alternate_phone' => $this->first($payload, ['alternate_phone', 'رقم تواصل بديل', 'رقم التواصل2']),
            'field_engineer' => $fieldEngineer,
            'field_engineer_user_id' => $this->engineerUserResolver->resolve($fieldEngineer),
            'visit_date' => $this->date($this->first($payload, ['visit_date', 'Visit Date', 'تاريخ الزيارة', '_submission_time'])),
            'governorate' => $this->firstMapped($payload, $service, ['governorate', 'المحافظة']),
            'area' => $this->firstMapped($payload, $service, ['area_001', 'area', 'community', 'المنطقة', 'التجمع/ المنطقة', 'التجمع']),
            'address' => $this->firstMapped($payload, $service, ['address_001', 'address', 'العنوان', 'العنوان بالتفصيل']),
            'household_head_gender' => $this->first($payload, ['head_gender', 'household_head_gender', 'gender', 'جنس رب الأسرة']),
            'marital_status' => $this->first($payload, ['marital_status', 'الحالة الاجتماعية']),
            'displacement_status' => $this->firstMapped($payload, $service, ['displacement_status', 'حالة النزوح حاليا للأسرة', 'حالة النزوح']),
            'occupancy_status' => $this->firstMapped($payload, $service, ['occupancy_type', 'occupancy_status', 'حالة الإشغال الحالي للوحدة السكنية', 'نوع الإشغال الحالي:', 'حالة الإشغال']),
            'damage_status' => $this->firstMapped($payload, $service, ['damage_status', 'Damage assessment', 'تقييم حالة ضرر المأوى']),
            'grant_amount' => $this->decimal($this->firstMapped($payload, $service, ['grant_amount', 'grant', 'GRANT', 'Intervention (ILS)', "Intervention \n(ILS)", 'المنحة', 'قيمة العقد ILS'])),
            'payment_1' => $this->decimal($this->firstMapped($payload, $service, ['payment_1', 'Payment_1', '30%', 'دفعة 30%'])),
            'payment_2' => $this->decimal($this->firstMapped($payload, $service, ['payment_2', 'Payment_2', '50%', 'دفعة 50%'])),
            'payment_3' => $this->decimal($this->firstMapped($payload, $service, ['payment_3', 'Payment_3', '20%', 'دفعة 20%'])),
            'recommendations' => $this->firstMapped($payload, $service, ['recommendations', 'final_recommendation', 'توصيات نهائية', 'توصيات']),
            'social_notes' => $this->firstMapped($payload, $service, ['social_notes', "\u{0645}\u{0644}\u{0627}\u{062D}\u{0638}\u{0627}\u{062A} \u{0625}\u{062C}\u{062A}\u{0645}\u{0627}\u{0639}\u{064A}\u{0629}", 'ملاحظات إجتماعية']),
            'engineer_notes' => $this->firstMapped($payload, $service, ['engineer_notes', "\u{0645}\u{0644}\u{0627}\u{062D}\u{0638}\u{0627}\u{062A} \u{0627}\u{0644}\u{0645}\u{0647}\u{0646}\u{062F}\u{0633}\u{064A}\u{0646}", 'ملاحظات المهندسين']),
            'raw_data' => array_merge($beneficiary->raw_data ?? [], [$service => $payload]),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if (! isset($data['name']) && $this->isInvalidBeneficiaryName((string) $beneficiary->name)) {
            $data['name'] = null;
        }

        if (! isset($data['field_engineer']) && $this->isInvalidEngineerName((string) $beneficiary->field_engineer)) {
            $data['field_engineer'] = null;
        }

        $beneficiary->fill($data)->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function beneficiaryFieldEngineer(array $payload, string $service): string
    {
        $selectedEngineer = $this->firstChoiceLabel($payload, $service, [
            'identification/q_087',
            'q_087',
            '__087',
            "\u{0627}\u{0633}\u{0645} \u{0627}\u{0644}\u{0645}\u{0647}\u{0646}\u{062F}\u{0633} \u{0627}\u{0644}\u{0645}\u{064A}\u{062F}\u{0627}\u{0646}\u{064A}",
        ]);

        if ($selectedEngineer !== '' && ! $this->isOtherChoiceLabel($selectedEngineer)) {
            return $this->cleanEngineerName($selectedEngineer);
        }

        return $this->cleanEngineerName($this->first($payload, [
            'engineer_name',
            'field_engineer',
            'identification/q_093',
            'q_093',
            '__093',
            'Engineer Name',
            "\u{062D}\u{062F}\u{062F} \u{0627}\u{0633}\u{0645}",
            "\u{0627}\u{0633}\u{0645} \u{0627}\u{0644}\u{0645}\u{0647}\u{0646}\u{062F}\u{0633}",
            "\u{0627}\u{0644}\u{0645}\u{0647}\u{0646}\u{062F}\u{0633} \u{0627}\u{0644}\u{0645}\u{062A}\u{0627}\u{0628}\u{0639}",
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncFollowUp(HeksBeneficiary $beneficiary, array $payload, string $service): HeksFollowUp
    {
        $visitNumber = HeksFollowUp::normalizeVisitNumber($this->first($payload, [
            'visit_number',
            'visit #',
            'visit_no',
            "\u{0631}\u{0642}\u{0645} \u{0627}\u{0644}\u{0632}\u{064A}\u{0627}\u{0631}\u{0629}",
        ]));
        $boqFilename = $this->first($payload, ['boq_filename', 'Insert BOQ', 'BOQ']);
        $boqUrl = $this->first($payload, ['boq_url', 'Insert BOQ_URL', 'BOQ_URL']);

        if ($boqUrl === '' && $boqFilename !== '') {
            $boqUrl = $this->followUpBoqAttachmentUrl($beneficiary, $boqFilename);
        }

        $followUp = HeksFollowUp::query()->firstOrNew([
            'heks_beneficiary_id' => $beneficiary->id,
            'code' => $beneficiary->code,
            'visit_number' => $visitNumber,
        ]);

        $engineerName = $this->cleanEngineerName($this->firstChoiceLabel($payload, $service, [
            'group_bv71d05/Engineer_Name',
            'Engineer_Name',
            'engineer_name',
            'Engineer Name',
            "\u{0627}\u{0633}\u{0645} \u{0627}\u{0644}\u{0645}\u{0647}\u{0646}\u{062F}\u{0633}",
            "\u{0627}\u{0644}\u{0645}\u{0647}\u{0646}\u{062F}\u{0633} \u{0627}\u{0644}\u{0645}\u{062A}\u{0627}\u{0628}\u{0639}",
        ]));

        $completedAmount = $this->decimal($this->first($payload, [
            'completed_amount_ils',
            'completed_amount',
            "\u{0625}\u{062C}\u{0645}\u{0627}\u{0644}\u{064A} \u{0645}\u{0627} \u{062A}\u{0645} \u{0627}\u{0646}\u{062C}\u{0627}\u{0632}\u{0629} \u{062D}\u{062A}\u{0649} \u{0627}\u{0644}\u{0622}\u{0646} ILS",
        ]));
        $completionPercentage = $this->percentage($this->first($payload, [
            'completion_percentage',
            'completion_percent',
            'group_ab98d17/integer_hv9hz51',
            'integer_hv9hz51',
            'group_ab98d17/integer_lp9qe22',
            'integer_lp9qe22',
            "\u{0646}\u{0633}\u{0628}\u{0629} \u{0627}\u{0644}\u{0625}\u{0646}\u{062C}\u{0627}\u{0632} \u{0628}\u{0627}\u{0644}\u{0623}\u{0639}\u{0645}\u{0627}\u{0644} %",
        ]));

        $followUp->fill(array_filter([
            'visit_date' => $this->date($this->first($payload, ['visit_date', 'Visit Date', "\u{062A}\u{0627}\u{0631}\u{064A}\u{062E} \u{0627}\u{0644}\u{0632}\u{064A}\u{0627}\u{0631}\u{0629}", '_submission_time'])),
            'engineer_name' => $engineerName,
            'engineer_user_id' => $this->engineerUserResolver->resolve($engineerName),
            'working_condition' => $this->first($payload, ['working_condition', 'Working condition', "\u{062D}\u{0627}\u{0644}\u{0629} \u{0627}\u{0644}\u{0639}\u{0645}\u{0644}"]),
            'other_condition' => $this->first($payload, ['other_condition', 'Other condition:', "\u{062D}\u{0627}\u{0644}\u{0629} \u{0623}\u{062E}\u{0631}\u{0649}"]),
            'engineer_recommendations' => $this->first($payload, ['engineer_recommendations', 'recommendations', "\u{062A}\u{0648}\u{0635}\u{064A}\u{0627}\u{062A} \u{0627}\u{0644}\u{0645}\u{0647}\u{0646}\u{062F}\u{0633} \u{0644}\u{0644}\u{0632}\u{064A}\u{0627}\u{0631}\u{0629}"]),
            'boq_filename' => $boqFilename,
            'boq_url' => $boqUrl,
            'raw_data' => $payload,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
        $followUp->completed_amount_ils = $completedAmount;
        $followUp->completion_percentage = $completionPercentage;
        $followUp->save();

        if ($engineerName !== '' && HeksBeneficiary::isRawEngineerCode((string) $beneficiary->field_engineer)) {
            $beneficiary->forceFill([
                'field_engineer' => $engineerName,
                'field_engineer_user_id' => $this->engineerUserResolver->resolve($engineerName),
            ])->save();
        }

        return $followUp;
    }

    private function followUpBoqAttachmentUrl(HeksBeneficiary $beneficiary, string $filename): string
    {
        $normalizedFilename = $this->normalizedAttachmentFilename($filename);

        if ($normalizedFilename === '') {
            return '';
        }

        $attachment = HeksAttachment::query()
            ->where('heks_beneficiary_id', $beneficiary->id)
            ->where('attachment_type', 'follow_up_boq')
            ->whereNotNull('url')
            ->get()
            ->first(function (HeksAttachment $attachment) use ($normalizedFilename): bool {
                return $this->normalizedAttachmentFilename((string) $attachment->filename) === $normalizedFilename;
            });

        return (string) ($attachment?->url ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncScores(HeksBeneficiary $beneficiary, array $payload, string $service): void
    {
        $social = $this->decimal($this->first($payload, ['social_score', 'Social Score', "\u{062A}\u{0642}\u{064A}\u{064A}\u{0645} \u{0627}\u{0644}\u{062D}\u{0627}\u{0644}\u{0629} \u{0627}\u{0644}\u{0627}\u{062C}\u{062A}\u{0645}\u{0627}\u{0639}\u{064A}\u{0629}  (30)", "\u{062A}\u{0642}\u{064A}\u{064A}\u{0645} \u{0627}\u{0644}\u{062D}\u{0627}\u{0644}\u{0629}\n\u{0627}\u{0644}\u{0627}\u{062C}\u{062A}\u{0645}\u{0627}\u{0639}\u{064A}\u{0629}  (30)", 'تقييم الحالة الاجتماعية  (30)', "تقييم الحالة \nالاجتماعية  (30)", 'تقييم الحالة الاجتماعية من 35', 'التقييم الاجتماعي']));
        $technical = $this->decimal($this->first($payload, ['technical_score', 'Technical Score', "\u{062A}\u{0642}\u{064A}\u{064A}\u{0645} \u{0627}\u{0644}\u{062D}\u{0627}\u{0644}\u{0629} \u{0627}\u{0644}\u{0641}\u{0646}\u{064A}\u{0629} (70)", "\u{062A}\u{0642}\u{064A}\u{064A}\u{0645} \u{0627}\u{0644}\u{062D}\u{0627}\u{0644}\u{0629}\n\u{0627}\u{0644}\u{0641}\u{0646}\u{064A}\u{0629} (70)", 'تقييم الحالة الفنية (70)', "تقييم الحالة \nالفنية (70)", 'التقييم الفني']));
        $total = $this->decimal($this->first($payload, ['total_score', 'final_score', 'Total Score', "\u{0627}\u{0644}\u{062A}\u{0642}\u{064A}\u{064A}\u{0645} \u{0627}\u{0644}\u{0643}\u{0644}\u{064A}", 'التقييم الكلي']));

        $calculated = $this->calculatedScores($payload, $service);
        $social ??= $calculated['social_score'];
        $technical ??= $calculated['technical_score'];
        $total ??= $social !== null && $technical !== null
            ? round((float) $social + (float) $technical, 2)
            : null;

        if ($social === null && $technical === null && $total === null) {
            return;
        }

        $classification = $this->first($payload, ['classification', 'Classification', 'priority', "\u{0627}\u{0644}\u{062A}\u{0635}\u{0646}\u{064A}\u{0641}", "\u{0627}\u{0644}\u{0623}\u{0648}\u{0644}\u{0648}\u{064A}\u{0629}"]);
        $classification = $classification !== '' ? $classification : $this->classificationForScore($total);

        HeksScore::query()->updateOrCreate(
            ['heks_beneficiary_id' => $beneficiary->id, 'source' => $service],
            [
                'grant_amount' => $this->decimal($this->first($payload, ['grant_amount', 'grant', 'GRANT', 'Intervention (ILS)', "Intervention \n(ILS)"])),
                'payment_1' => $this->decimal($this->first($payload, ['payment_1', 'Payment_1', '30%'])),
                'payment_2' => $this->decimal($this->first($payload, ['payment_2', 'Payment_2', '50%'])),
                'payment_3' => $this->decimal($this->first($payload, ['payment_3', 'Payment_3', '20%'])),
                'social_score' => $social,
                'technical_score' => $technical,
                'total_score' => $total,
                'classification' => $classification,
                'raw_data' => array_merge($payload, [
                    '_heks_score_calculation' => $calculated['details'],
                ]),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{social_score: ?float, technical_score: ?float, details: array<string, mixed>}
     */
    private function calculatedScores(array $payload, string $service): array
    {
        $socialFromWeights = $this->scoreFromOptionWeights($payload, $service, ['S-V'], 30);
        $technicalFromWeights = $this->scoreFromOptionWeights($payload, $service, ['T-V', 'Shelter Technical Weights'], 70);
        $socialFromMatrix = $socialFromWeights['score'] === null
            ? $this->socialMatrixScore($payload)
            : ['score' => null, 'matches' => []];

        return [
            'social_score' => $socialFromWeights['score'] ?? $socialFromMatrix['score'],
            'technical_score' => $technicalFromWeights['score'],
            'details' => [
                'social_weights' => $socialFromWeights['matches'],
                'social_matrix' => $socialFromMatrix['matches'],
                'technical_weights' => $technicalFromWeights['matches'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $sources
     * @return array{score: ?float, matches: array<int, array<string, mixed>>}
     */
    private function scoreFromOptionWeights(array $payload, string $service, array $sources, float $maxScore): array
    {
        $score = 0.0;
        $matches = [];

        $weights = HeksScoringWeight::query()
            ->whereIn('source', $sources)
            ->whereNotNull('question_key')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (HeksScoringWeight $weight): string => trim((string) $weight->question_key));

        foreach ($weights as $questionKey => $questionWeights) {
            if ($questionKey === '') {
                continue;
            }

            $answer = $this->scoringAnswer($payload, $service, $questionKey);

            if ($answer === null) {
                continue;
            }

            $questionScore = 0.0;
            $questionMatches = [];

            foreach ($questionWeights as $weight) {
                $optionValue = trim((string) $weight->option_value);

                if ($optionValue !== '' && ! $this->answerMatchesScoringOption((string) $answer['value'], $service, $questionKey, $optionValue)) {
                    continue;
                }

                $points = $this->pointsForScoringWeight($weight);

                if ($points === null) {
                    continue;
                }

                $questionScore += $points;
                $questionMatches[] = [
                    'source' => $weight->source,
                    'question_key' => $questionKey,
                    'answer_key' => $answer['key'],
                    'answer' => $answer['value'],
                    'option_value' => $optionValue !== '' ? $optionValue : null,
                    'points' => $points,
                ];
            }

            if ($questionMatches === []) {
                continue;
            }

            $score += $questionScore;
            array_push($matches, ...$questionMatches);
        }

        if ($matches === []) {
            return ['score' => null, 'matches' => []];
        }

        return [
            'score' => round(min($score, $maxScore), 2),
            'matches' => $matches,
        ];
    }

    private function pointsForScoringWeight(HeksScoringWeight $weight): ?float
    {
        $optionScore = $weight->option_score !== null ? (float) $weight->option_score : null;
        $weightScore = $weight->weight !== null ? (float) $weight->weight : null;

        return $optionScore ?? $weightScore;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{key: string, value: string}|null
     */
    private function scoringAnswer(array $payload, string $service, string $questionKey): ?array
    {
        foreach ($this->mappedCandidates($service, [$questionKey]) as $candidate) {
            foreach ($this->fieldLookupKeys($candidate) as $lookupKey) {
                if (! array_key_exists($lookupKey, $payload) || ! filled($payload[$lookupKey]) || is_array($payload[$lookupKey])) {
                    continue;
                }

                $value = trim((string) $payload[$lookupKey]);

                if (! $this->isInvalidValue($value)) {
                    return ['key' => $lookupKey, 'value' => $value];
                }
            }
        }

        $value = $this->first($payload, $this->mappedCandidates($service, [$questionKey]));

        return $value !== '' ? ['key' => $questionKey, 'value' => $value] : null;
    }

    private function answerMatchesScoringOption(string $answer, string $service, string $questionKey, string $optionValue): bool
    {
        $optionLabel = $this->choiceLabel($service, $questionKey, $optionValue);
        $optionTokens = array_filter([$optionValue, $optionLabel]);

        foreach ($this->scoringAnswerParts($answer) as $answerPart) {
            $answerLabel = $this->choiceLabel($service, $questionKey, $answerPart);
            $answerTokens = array_filter([$answerPart, $answerLabel]);

            foreach ($answerTokens as $answerToken) {
                foreach ($optionTokens as $optionToken) {
                    if ($this->normalizeScoreToken($answerToken) === $this->normalizeScoreToken($optionToken)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function scoringAnswerParts(string $answer): array
    {
        $parts = preg_split('/[\s,;|]+/u', $answer, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(array_merge([$answer], $parts))));
    }

    private function normalizeScoreToken(string $value): string
    {
        return Str::lower((string) preg_replace('/[^\pL\pN]+/u', '', $value));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{score: ?float, matches: array<int, array<string, mixed>>}
     */
    private function socialMatrixScore(array $payload): array
    {
        $score = 0.0;
        $matches = [];

        foreach ($this->socialScoringCriteria() as $criterion) {
            $answer = $this->first($payload, $criterion['keys']);

            if ($answer === '' || ! $this->isPositiveScoreValue($answer, $criterion['positive_terms'])) {
                continue;
            }

            $score += 5;
            $matches[] = [
                'criterion' => $criterion['label'],
                'answer' => $answer,
                'points' => 5,
            ];
        }

        if ($matches === []) {
            return ['score' => null, 'matches' => []];
        }

        return ['score' => round(min($score, 30), 2), 'matches' => $matches];
    }

    /**
     * @return array<int, array{label: string, keys: array<int, string>, positive_terms: array<int, string>}>
     */
    private function socialScoringCriteria(): array
    {
        return [
            ['label' => 'Female-headed household', 'keys' => ['head_gender', 'household_head_gender', 'gender'], 'positive_terms' => ['female', 'woman', 'انثى', 'أنثى']],
            ['label' => 'Children under 18 present', 'keys' => ['children_under_18', 'children', 'under_18'], 'positive_terms' => ['yes', 'يوجد', 'نعم']],
            ['label' => 'Disability present', 'keys' => ['disability', 'pwd', 'people_with_disability'], 'positive_terms' => ['yes', 'يوجد', 'نعم']],
            ['label' => 'Chronic disease present', 'keys' => ['chronic_disease', 'chronic', 'illness'], 'positive_terms' => ['yes', 'يوجد', 'نعم']],
            ['label' => 'Pregnant or lactating women', 'keys' => ['pregnant', 'lactating', 'pregnant_lactating'], 'positive_terms' => ['yes', 'يوجد', 'نعم']],
            ['label' => 'No stable income', 'keys' => ['stable_income', 'fixed_income', 'regular_income'], 'positive_terms' => ['no', 'لا']],
        ];
    }

    /**
     * @param  array<int, string>  $positiveTerms
     */
    private function isPositiveScoreValue(string $value, array $positiveTerms): bool
    {
        $normalized = $this->normalizeScoreToken($value);

        $normalizedNumber = str_replace([',', ' '], '', $value);

        if (is_numeric($normalizedNumber)) {
            $number = (float) $normalizedNumber;

            if (in_array('>60', $positiveTerms, true) || in_array('<18', $positiveTerms, true)) {
                return $number > 60 || $number < 18;
            }

            return in_array($normalizedNumber, ['1', '5'], true);
        }

        foreach ($positiveTerms as $term) {
            if (str_contains($normalized, $this->normalizeScoreToken($term))) {
                return true;
            }
        }

        return in_array($normalized, ['1', 'true', 'yes', 'نعم'], true);
    }

    private function classificationForScore(?float $total): string
    {
        if ($total === null) {
            return '';
        }

        return match (true) {
            $total >= 80 => 'Extreme',
            $total >= 65 => 'Very High',
            $total >= 50 => 'High',
            $total >= 35 => 'Moderate',
            default => 'Low',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncLabels(HeksBeneficiary $beneficiary, array $payload, string $service): void
    {
        $labels = [
            'screening' => ['screening', 'Screening'],
            'damage_status' => ['damage_status', 'Damage assessment', 'تقييم حالة ضرر المأوى'],
            'roof_status' => ['roof_status', 'Roof condition', 'حالة السقف'],
            'kitchen_status' => ['kitchen_status', 'General kitchen condition', 'حالة المطبخ'],
            'occupancy_status' => ['occupancy_status', 'حالة الإشغال الحالي للوحدة السكنية', 'نوع الإشغال الحالي:', 'حالة الإشغال'],
            'final_recommendation' => ['final_recommendation', 'recommendations', 'توصيات نهائية'],
        ];

        foreach ($labels as $key => $candidates) {
            $value = $this->first($payload, $candidates);

            if ($value === '') {
                continue;
            }

            HeksLabel::query()->updateOrCreate(
                ['heks_beneficiary_id' => $beneficiary->id, 'source' => $service, 'label_key' => $key],
                [
                    'label_value' => $value,
                    'version' => $this->first($payload, ['__version__', '_submission___version__']),
                    'raw_data' => $payload,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncAllSurveyAnswers(HeksBeneficiary $beneficiary, array $payload, string $service): void
    {
        foreach ($payload as $key => $value) {
            if ($this->shouldSkipSurveyAnswer($key, $value)) {
                continue;
            }

            $displayValue = $this->displayValue($value);

            if ($displayValue === '') {
                continue;
            }

            HeksLabel::query()->updateOrCreate(
                [
                    'heks_beneficiary_id' => $beneficiary->id,
                    'source' => $service,
                    'label_key' => $this->surveyAnswerLabelKey($key, $service),
                ],
                [
                    'label_value' => $displayValue,
                    'version' => $this->first($payload, ['__version__', '_submission___version__']),
                    'raw_data' => [
                        'field_key' => $key,
                        'field_label' => $this->cleanFieldLabel($key, $service),
                        'value' => $value,
                        'source' => $service,
                    ],
                ]
            );
        }
    }

    private function shouldSkipSurveyAnswer(string $key, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $normalized = $this->normalizeKey($key);

        return $normalized === ''
            || str_contains($normalized, 'uuid')
            || str_contains($normalized, 'parenttablename')
            || str_contains($normalized, 'parentindex')
            || str_contains($normalized, 'validationstatus')
            || str_contains($normalized, 'submittedby')
            || str_contains($normalized, 'tags')
            || str_contains($normalized, 'notes')
            || str_contains($normalized, 'index');
    }

    private function surveyAnswerLabelKey(string $key, string $service): string
    {
        $cleanLabel = Str::of($this->cleanFieldLabel($key, $service))
            ->limit(70, '')
            ->toString();

        return 'survey:'.substr(sha1($key), 0, 12).':'.$cleanLabel;
    }

    private function cleanFieldLabel(string $key, string $service): string
    {
        $mappedLabel = $this->displayLabelForField($service, $key);

        if ($mappedLabel !== null) {
            return $mappedLabel;
        }

        return Str::of($key)
            ->replace(["\r", "\n", "\t"], ' ')
            ->replace(['/', '_'], ' ')
            ->squish()
            ->toString();
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $flatPayload
     */
    private function syncAttachments(HeksBeneficiary $beneficiary, array $payload, array $flatPayload, string $service): void
    {
        $attachments = Arr::get($payload, '_attachments', []);

        if (is_array($attachments)) {
            foreach (array_values($attachments) as $index => $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                $url = (string) ($attachment['download_url'] ?? $attachment['download_large_url'] ?? $attachment['url'] ?? '');
                $filename = (string) ($attachment['filename'] ?? basename(parse_url($url, PHP_URL_PATH) ?: ''));

                if ($url === '' && $filename === '') {
                    continue;
                }

                HeksAttachment::query()->updateOrCreate(
                    [
                        'heks_beneficiary_id' => $beneficiary->id,
                        'source' => $service,
                        'filename' => $filename,
                    ],
                    [
                        'url' => $url,
                        'source_index' => $index,
                        'attachment_type' => $this->attachmentType($filename),
                        'raw_data' => $attachment,
                    ]
                );
            }
        }

        foreach ($flatPayload as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            if (! $this->looksLikeAttachment($key, $value)) {
                continue;
            }

            HeksAttachment::query()->updateOrCreate(
                [
                    'heks_beneficiary_id' => $beneficiary->id,
                    'source' => $service,
                    'filename' => basename(parse_url($value, PHP_URL_PATH) ?: $value),
                ],
                [
                    'url' => Str::startsWith($value, ['http://', 'https://']) ? $value : null,
                    'parent_table' => $key,
                    'attachment_type' => $this->attachmentType($value),
                    'raw_data' => ['field' => $key, 'value' => $value],
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $flatPayload
     */
    private function syncBoqItems(HeksBeneficiary $beneficiary, array $payload, array $flatPayload, string $service, ?HeksFollowUp $followUp): int
    {
        $rows = $this->boqRows($payload, $flatPayload, $service);
        $syncedKeys = [];
        $saved = 0;

        foreach ($rows as $index => $row) {
            $flatRow = $this->flatten($row);
            $description = $this->first($flatRow, ['description', 'item_description', 'وصف البند', 'البند']);

            if ($description === '') {
                continue;
            }

            $quantity = $this->decimal($this->first($flatRow, ['quantity', 'qty', 'الكمية'])) ?? 0;
            $unitPrice = $this->decimal($this->first($flatRow, ['unit_price_ils', 'unit_price', 'سعر الوحدة', 'تكلفة الوحدة ILS'])) ?? 0;
            $totalPrice = $this->decimal($this->first($flatRow, ['total_price_ils', 'total_price', 'الإجمالي'])) ?? ($quantity * $unitPrice);
            $itemCode = $this->first($flatRow, ['item_code', 'item_no', 'رقم البند']);

            HeksBoqItem::query()->updateOrCreate(
                [
                    'heks_beneficiary_id' => $beneficiary->id,
                    'heks_follow_up_id' => $followUp?->id,
                    'source' => $service,
                    'item_code' => $itemCode !== '' ? $itemCode : null,
                    'description' => $description,
                ],
                [
                    'section' => $this->first($flatRow, ['section', 'القسم']),
                    'unit' => $this->first($flatRow, ['unit', 'الوحدة']),
                    'quantity' => $quantity,
                    'unit_price_ils' => $unitPrice,
                    'total_price_ils' => $totalPrice,
                    'notes' => $this->first($flatRow, ['notes', 'ملاحظات']),
                    'raw_data' => array_merge($flatRow, ['_kobo_row_index' => $index]),
                ]
            );

            $syncedKeys[$this->boqItemSyncKey($itemCode !== '' ? $itemCode : null, $description)] = true;
            $saved++;
        }

        $this->deleteStaleSyncedBoqItems($beneficiary, $followUp, $service, array_keys($syncedKeys));

        return $saved;
    }

    /**
     * @param  array<int, string>  $syncedKeys
     */
    private function deleteStaleSyncedBoqItems(HeksBeneficiary $beneficiary, ?HeksFollowUp $followUp, string $service, array $syncedKeys): void
    {
        HeksBoqItem::query()
            ->where('heks_beneficiary_id', $beneficiary->id)
            ->where('heks_follow_up_id', $followUp?->id)
            ->where('source', $service)
            ->get()
            ->each(function (HeksBoqItem $item) use ($syncedKeys): void {
                $itemKey = $this->boqItemSyncKey($item->item_code, $item->description);

                if (! in_array($itemKey, $syncedKeys, true)) {
                    $item->delete();
                }
            });
    }

    private function boqItemSyncKey(?string $itemCode, string $description): string
    {
        return sha1(trim((string) $itemCode).'|'.trim($description));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $flatPayload
     * @return array<int, array<string, mixed>>
     */
    private function boqRows(array $payload, array $flatPayload, string $service): array
    {
        foreach (['boq_items', 'items', 'BOQ', 'جدول_الكميات'] as $key) {
            $rows = Arr::get($payload, $key);

            if (is_array($rows) && array_is_list($rows)) {
                return array_values(array_filter($rows, 'is_array'));
            }
        }

        if ($this->hasFilledScalarField($flatPayload, ['description', 'item_description'])
            && $this->hasFilledScalarField($flatPayload, ['quantity', 'qty'])) {
            return [$flatPayload];
        }

        return $this->quantityRows($flatPayload, $service);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $fields
     */
    private function hasFilledScalarField(array $payload, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $payload) || ! filled($payload[$field]) || is_array($payload[$field])) {
                continue;
            }

            if (! $this->isInvalidValue(trim((string) $payload[$field]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $flatPayload
     * @return array<int, array<string, mixed>>
     */
    private function quantityRows(array $flatPayload, string $service): array
    {
        $catalogItems = HeksBoqCatalogItem::query()
            ->where('is_active', true)
            ->whereNotNull('item_code')
            ->orderBy('sort_order')
            ->get()
            ->keyBy(fn (HeksBoqCatalogItem $item): string => $this->normalizeKey((string) $item->item_code));
        $rows = [];
        $seen = [];

        foreach ($flatPayload as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $quantity = $this->decimal((string) $value);

            if ($quantity === null || $quantity <= 0) {
                continue;
            }

            $itemCode = $this->boqItemCodeForQuantityField($service, (string) $key);

            if ($itemCode === null && ! $this->looksLikeQuantityKey((string) $key)) {
                continue;
            }

            $signature = $itemCode !== null ? 'code:'.$itemCode : 'key:'.$key;

            $catalogItem = $itemCode !== null ? $catalogItems->get($this->normalizeKey($itemCode)) : null;

            if (! $this->shouldImportQuantityField($service, (string) $key, $itemCode, $catalogItem instanceof HeksBoqCatalogItem)) {
                continue;
            }

            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $unitPrice = $catalogItem instanceof HeksBoqCatalogItem ? (float) $catalogItem->unit_price_ils : 0.0;

            $rows[] = [
                'section' => $catalogItem instanceof HeksBoqCatalogItem ? $catalogItem->section : $this->boqSectionFromQuantityKey((string) $key),
                'item_code' => $catalogItem instanceof HeksBoqCatalogItem ? $catalogItem->item_code : $itemCode,
                'description' => $catalogItem instanceof HeksBoqCatalogItem ? $catalogItem->description : $this->boqQuantityDescription($service, (string) $key),
                'unit' => $catalogItem instanceof HeksBoqCatalogItem ? $catalogItem->unit : '',
                'quantity' => $quantity,
                'unit_price_ils' => $unitPrice,
                'total_price_ils' => $quantity * $unitPrice,
                'notes' => $catalogItem instanceof HeksBoqCatalogItem
                    ? 'Imported from KoBo quantity field'
                    : 'Imported from unmapped KoBo BOQ quantity field',
            ];
        }

        return $rows;
    }

    private function boqItemCodeForQuantityField(string $service, string $key): ?string
    {
        foreach (array_filter([$this->displayLabelForField($service, $key), $key]) as $value) {
            $code = $this->boqItemCodeFromText((string) $value);

            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    private function shouldImportQuantityField(string $service, string $key, ?string $itemCode, bool $hasCatalogItem): bool
    {
        $displayLabel = $this->displayLabelForField($service, $key);

        if ($this->looksLikeQuantityKey($key) || ($displayLabel !== null && $this->looksLikeQuantityKey($displayLabel))) {
            return true;
        }

        if ($itemCode === null || ! $hasCatalogItem) {
            return false;
        }

        return $this->handler($service) === 'main'
            && $this->looksLikeTechnicalItemCodeKey($key, $itemCode);
    }

    private function boqItemCodeFromText(string $value): ?string
    {
        if (preg_match('/(?:البند|item)\s*([0-9]+(?:[\.,][0-9]+)?)/iu', $value, $matches) === 1) {
            return str_replace(',', '.', $matches[1]);
        }

        $lastSegment = Str::afterLast($value, '/');

        if (preg_match('/(?:^|_)([0-9]+)_([0-9]+)(?:_|$)/', $lastSegment, $matches) === 1) {
            return $matches[1].'.'.$matches[2];
        }

        return null;
    }

    private function boqSectionFromQuantityKey(string $key): string
    {
        $segment = Str::before($key, '/');

        if (preg_match('/sec_([0-9]+)/', $segment, $matches) === 1) {
            return 'Section '.$matches[1];
        }

        return '';
    }

    private function boqQuantityDescription(string $service, string $key): string
    {
        return $this->displayLabelForField($service, $key)
            ?? Str::of($key)->replace(['/', '_'], ' ')->squish()->toString();
    }

    /**
     * @param  array<string, mixed>  $flatPayload
     */
    private function quantityForCatalogItem(array $flatPayload, string $itemCode): ?float
    {
        $normalizedCode = $this->normalizeKey($itemCode);

        foreach ($flatPayload as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $quantity = $this->decimal((string) $value);

            if ($quantity === null || $quantity <= 0) {
                continue;
            }

            $normalizedKey = $this->normalizeKey($key);

            if (! str_contains($normalizedKey, $normalizedCode)) {
                continue;
            }

            if (! $this->looksLikeQuantityKey($key) && ! str_contains($normalizedKey, 'boq') && ! $this->looksLikeTechnicalItemCodeKey($key, $itemCode)) {
                continue;
            }

            return $quantity;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flatPayload
     * @return array<int, array<string, mixed>>
     */
    private function questionQuantityRows(array $flatPayload): array
    {
        $rows = [];

        foreach ($flatPayload as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $quantity = $this->decimal((string) $value);

            if ($quantity === null || $quantity <= 0 || ! $this->looksLikeQuantityKey($key)) {
                continue;
            }

            $rows[] = [
                'description' => Str::of($key)->replace(['/', '_'], ' ')->squish()->toString(),
                'quantity' => $quantity,
                'unit_price_ils' => 0,
                'total_price_ils' => 0,
                'notes' => 'Imported from unmapped KoBo BOQ quantity field',
            ];
        }

        return $rows;
    }

    private function looksLikeQuantityKey(string $key): bool
    {
        $normalized = Str::lower($key);

        return str_contains($normalized, 'quantity')
            || str_contains($normalized, 'qty')
            || str_contains($normalized, 'boq')
            || str_contains($normalized, 'item')
            || str_contains($normalized, 'كمية')
            || str_contains($normalized, 'البند');
    }

    private function looksLikeTechnicalItemCodeKey(string $key, string $itemCode): bool
    {
        if (! str_contains($itemCode, '.')) {
            return false;
        }

        $normalizedCode = $this->normalizeKey($itemCode);

        if ($normalizedCode === '') {
            return false;
        }

        return collect(explode('/', $key))
            ->contains(fn (string $segment): bool => $this->normalizeKey($segment) === $normalizedCode);
    }

    /**
     * @param  array<string, mixed>  $flatPayload
     */
    private function syncKoboRecordFields(KoboRestSubmission $submission, HeksBeneficiary $beneficiary, ?HeksFollowUp $followUp, array $flatPayload): void
    {
        $tableName = $this->koboRecordTable($submission->service_name);

        if ($tableName === null || ! Schema::hasTable($tableName)) {
            return;
        }

        $fieldValues = [];

        foreach ($flatPayload as $field => $value) {
            if (! is_string($field) || $field === '') {
                continue;
            }

            $column = $this->ensureKoboRecordColumn($submission->service_name, $tableName, $field);

            if ($column === null) {
                continue;
            }

            $fieldValues[$column] = $this->koboRecordValue($value);
        }

        $now = now();
        $existingId = DB::table($tableName)
            ->where('submission_uuid', $submission->submission_uuid)
            ->value('id');

        DB::table($tableName)->updateOrInsert(
            ['submission_uuid' => $submission->submission_uuid],
            array_merge([
                'heks_beneficiary_id' => $beneficiary->id,
                'heks_follow_up_id' => $followUp?->id,
                'kobo_rest_submission_id' => $submission->id,
                'service_name' => $submission->service_name,
                'received_at' => $submission->received_at,
                'synced_at' => $now,
                'source_record_key' => $this->sourceRecordKey($submission),
                'raw_data' => json_encode($submission->payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $existingId === null ? $now : DB::raw('created_at'),
                'updated_at' => $now,
            ], $fieldValues)
        );
    }

    private function sourceRecordKey(KoboRestSubmission $submission): string
    {
        return $submission->service_name.':'.($submission->submission_uuid ?: $submission->id);
    }

    private function koboRecordTable(string $service): ?string
    {
        $configuredTable = $this->serviceRegistry->wideTable($service);

        if ($configuredTable !== null) {
            return $configuredTable;
        }

        return match ($service) {
            'heks-main' => 'heks_main_kobo_records',
            'heks-followups' => 'heks_followups_kobo_records',
            'heks-boq' => 'heks_boq_kobo_records',
            'heks-followup-boq' => 'heks_followup_boq_kobo_records',
            default => null,
        };
    }

    private function ensureKoboRecordColumn(string $service, string $tableName, string $field): ?string
    {
        $existingMapping = HeksKoboFieldMapping::query()
            ->where('service_name', $service)
            ->where('kobo_field', $field)
            ->first();

        if ($existingMapping instanceof HeksKoboFieldMapping) {
            if (! Schema::hasColumn($tableName, $existingMapping->column_name)) {
                Schema::table($tableName, function ($table) use ($existingMapping): void {
                    $table->text($existingMapping->column_name)->nullable();
                });
            }

            return $existingMapping->column_name;
        }

        $column = $this->uniqueKoboColumnName($service, $tableName, $field);

        if ($column === '') {
            return null;
        }

        HeksKoboFieldMapping::query()->create(
            [
                'service_name' => $service,
                'table_name' => $tableName,
                'kobo_field' => $field,
                'column_name' => $column,
                'data_type' => $this->detectedDataType($field),
                'mapping_status' => 'wide_only',
                'confidence' => 'low',
            ]
        );

        if (! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, function ($table) use ($column): void {
                $table->text($column)->nullable();
            });
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withMappedDisplayLabels(array $payload, string $service): array
    {
        foreach ($this->displayLabels($service) as $field => $label) {
            foreach ($this->fieldLookupKeys($field) as $lookupKey) {
                if (! array_key_exists($lookupKey, $payload) || array_key_exists($label, $payload)) {
                    continue;
                }

                $payload[$label] = $payload[$lookupKey];
            }
        }

        return $payload;
    }

    private function displayLabelForField(string $service, string $field): ?string
    {
        $labels = $this->displayLabels($service);

        foreach ($this->fieldLookupKeys($field) as $lookupKey) {
            if (isset($labels[$lookupKey])) {
                return $labels[$lookupKey];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function displayLabels(string $service): array
    {
        if (array_key_exists($service, $this->displayLabelCache)) {
            return $this->displayLabelCache[$service];
        }

        return $this->displayLabelCache[$service] = HeksKoboFieldMapping::query()
            ->whereIn('service_name', $this->serviceLookupKeys($service))
            ->whereNotNull('display_label')
            ->get(['kobo_field', 'display_label'])
            ->flatMap(function (HeksKoboFieldMapping $mapping): array {
                $label = trim((string) $mapping->display_label);

                if ($label === '') {
                    return [];
                }

                return collect($this->fieldLookupKeys((string) $mapping->kobo_field))
                    ->mapWithKeys(fn (string $field): array => [$field => $label])
                    ->all();
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $candidates
     */
    private function firstMapped(array $payload, string $service, array $candidates): string
    {
        return $this->first($payload, $this->mappedCandidates($service, $candidates));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $fields
     */
    private function firstChoiceLabel(array $payload, string $service, array $fields): string
    {
        foreach ($fields as $field) {
            foreach ($this->fieldLookupKeys($field) as $lookupKey) {
                if (! array_key_exists($lookupKey, $payload) || ! filled($payload[$lookupKey]) || is_array($payload[$lookupKey])) {
                    continue;
                }

                $value = trim((string) $payload[$lookupKey]);
                $label = $this->choiceLabel($service, $field, $value);

                return $label !== '' ? $label : $value;
            }
        }

        return '';
    }

    private function choiceLabel(string $service, string $field, string $value): string
    {
        if ($value === '') {
            return '';
        }

        foreach ($this->serviceLookupKeys($service) as $serviceName) {
            foreach ($this->fieldLookupKeys($field) as $questionKey) {
                $label = HeksKoboChoice::query()
                    ->where('service_name', $serviceName)
                    ->where('question_key', $questionKey)
                    ->where('choice_name', $value)
                    ->where('is_active', true)
                    ->value('choice_label');

                if (is_string($label) && trim($label) !== '') {
                    return trim($label);
                }

                $label = HeksKoboFieldMapping::query()
                    ->where('service_name', $serviceName)
                    ->whereIn('kobo_field', [
                        "{$questionKey}/{$value}",
                        Str::afterLast($questionKey, '/')."/{$value}",
                    ])
                    ->value('display_label');

                if (is_string($label) && trim($label) !== '') {
                    return trim(Str::afterLast($label, '/'));
                }
            }
        }

        return '';
    }

    private function isOtherChoiceLabel(string $value): bool
    {
        $normalized = Str::of($value)
            ->lower()
            ->replace(['أ', 'إ', 'آ'], 'ا')
            ->toString();

        return str_contains($normalized, 'other')
            || str_contains($normalized, 'اخر');
    }

    /**
     * @param  array<int, string>  $candidates
     * @return array<int, string>
     */
    private function mappedCandidates(string $service, array $candidates): array
    {
        $mappedCandidates = $candidates;
        $normalizedCandidates = collect($candidates)
            ->map(fn (string $candidate): string => $this->normalizeKey($candidate))
            ->filter()
            ->values()
            ->all();

        foreach ($this->displayLabels($service) as $field => $label) {
            $normalizedLabel = $this->normalizeKey($label);

            if ($normalizedLabel === '') {
                continue;
            }

            foreach ($normalizedCandidates as $candidate) {
                if (! str_contains($normalizedLabel, $candidate) && ! str_contains($candidate, $normalizedLabel)) {
                    continue;
                }

                $mappedCandidates[] = $field;
                $mappedCandidates[] = $label;
            }
        }

        return array_values(array_unique(array_filter($mappedCandidates)));
    }

    /**
     * @return array<int, string>
     */
    private function serviceLookupKeys(string $service): array
    {
        return array_values(array_unique(array_filter([
            $service,
            str_replace('_', '-', $service),
            str_replace('-', '_', $service),
            match ($service) {
                'heks_main' => 'heks-main',
                'heks-main' => 'heks_main',
                'heks_followup' => 'heks-followups',
                'heks-followups', 'heks-followup' => 'heks_followup',
                'heks_boq' => 'heks-boq',
                'heks-boq' => 'heks_boq',
                'heks_followup_boq' => 'heks-followup-boq',
                'heks-followup-boq' => 'heks_followup_boq',
                default => null,
            },
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function fieldLookupKeys(string $field): array
    {
        $field = trim($field);

        if ($field === '') {
            return [];
        }

        $keys = [$field];

        if (str_contains($field, '/')) {
            $keys[] = Str::afterLast($field, '/');
        }

        return array_values(array_unique($keys));
    }

    private function uniqueKoboColumnName(string $service, string $tableName, string $field): string
    {
        $base = $this->koboColumnName($field);
        $column = $base;
        $attempt = 0;

        while (
            Schema::hasColumn($tableName, $column)
            || HeksKoboFieldMapping::query()->where('service_name', $service)->where('column_name', $column)->exists()
        ) {
            $attempt++;
            $suffix = substr(sha1($field.$attempt), 0, 8);
            $column = substr($base, 0, 55).'_'.$suffix;
        }

        return $column;
    }

    private function koboColumnName(string $field): string
    {
        $column = Str::of($field)
            ->replace(['/', '-', '.', ' ', ':'], '_')
            ->replaceMatches('/[^A-Za-z0-9_]+/', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->lower()
            ->toString();

        if ($column === '') {
            $column = 'field_'.substr(sha1($field), 0, 12);
        }

        if (is_numeric($column[0])) {
            $column = 'field_'.$column;
        }

        if (strlen($column) > 58) {
            $column = substr($column, 0, 45).'_'.substr(sha1($field), 0, 12);
        }

        return $column;
    }

    private function koboRecordValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function normalizeKey(string $value): string
    {
        return Str::lower((string) preg_replace('/[^\pL\pN]+/u', '', $value));
    }

    private function isHeksService(string $service): bool
    {
        return Str::startsWith($service, 'heks-') || $this->serviceRegistry->accepts($service);
    }

    private function handler(string $service): string
    {
        $configured = $this->serviceRegistry->service($service);

        if (is_string($configured['normalized_handler'] ?? null)) {
            return $configured['normalized_handler'];
        }

        return match ($service) {
            'heks-followups' => 'followup',
            'heks-boq' => 'boq',
            'heks-followup-boq' => 'followup_boq',
            default => 'main',
        };
    }

    private function detectedDataType(string $field): string
    {
        $normalized = Str::lower($field);

        if (str_contains($normalized, 'date')) {
            return 'date';
        }

        if (str_contains($normalized, 'amount') || str_contains($normalized, 'payment') || str_contains($normalized, 'grant')) {
            return 'number';
        }

        if (str_contains($normalized, 'photo') || str_contains($normalized, 'attachment') || str_contains($normalized, '_url')) {
            return 'attachment';
        }

        return 'text';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function beneficiaryName(array $payload): string
    {
        $candidates = [
            'name',
            'Name',
            'head_name',
            'respondent_name',
            'beneficiary_name',
            'beneficiary_full_name',
            "\u{0627}\u{0633}\u{0645} \u{0627}\u{0644}\u{0645}\u{0633}\u{062A}\u{0641}\u{064A}\u{062F}",
            "\u{0627}\u{0644}\u{0645}\u{0633}\u{062A}\u{0641}\u{064A}\u{062F}",
            "\u{0627}\u{0633}\u{0645} \u{0631}\u{0628} \u{0627}\u{0644}\u{0623}\u{0633}\u{0631}\u{0629}",
            "\u{0627}\u{0633}\u{0645} \u{0627}\u{0644}\u{0634}\u{062E}\u{0635} \u{0627}\u{0644}\u{0645}\u{0642}\u{0627}\u{0628}\u{0644}",
        ];

        foreach ($candidates as $candidate) {
            $name = $this->first($payload, [$candidate]);

            if ($name !== '' && ! $this->isInvalidBeneficiaryName($name)) {
                return $name;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $candidates
     */
    private function first(array $payload, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $payload) && filled($payload[$candidate])) {
                if (is_array($payload[$candidate])) {
                    continue;
                }

                $value = trim((string) $payload[$candidate]);

                if (! $this->isInvalidValue($value)) {
                    return $value;
                }
            }

            if ($this->isGenericCandidate($candidate)) {
                $pathSegmentMatch = $this->findByPathSegment($payload, $candidate);

                if ($pathSegmentMatch !== '') {
                    return $pathSegmentMatch;
                }

                continue;
            }

            $match = $this->findByKeyPart($payload, [$candidate]);

            if ($match !== '') {
                return $match;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $needles
     */
    private function findByKeyPart(array $payload, array $needles): string
    {
        foreach ($payload as $key => $value) {
            if (! filled($value) || is_array($value) || $this->isComputedFieldKey($key)) {
                continue;
            }

            $value = trim((string) $value);

            if ($this->isInvalidValue($value)) {
                continue;
            }

            $normalizedKey = $this->normalizeKey($key);
            $normalizedLastSegment = $this->normalizeKey(Str::afterLast($key, '/'));

            foreach ($needles as $needle) {
                $normalizedNeedle = $this->normalizeKey($needle);

                if ($normalizedNeedle === '') {
                    continue;
                }

                if (str_contains($key, '/')) {
                    if ($normalizedLastSegment === $normalizedNeedle) {
                        return $value;
                    }

                    continue;
                }

                if (str_contains($normalizedKey, $normalizedNeedle)) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function isGenericCandidate(string $candidate): bool
    {
        return in_array($this->normalizeKey($candidate), ['name', 'code', 'id', 'phone', 'mobile', 'area'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findByPathSegment(array $payload, string $candidate): string
    {
        $normalizedCandidate = $this->normalizeKey($candidate);

        if ($normalizedCandidate === '') {
            return '';
        }

        foreach ($payload as $key => $value) {
            if (! str_contains($key, '/') || ! filled($value) || is_array($value) || $this->isComputedFieldKey($key)) {
                continue;
            }

            $value = trim((string) $value);

            if ($this->isInvalidValue($value)) {
                continue;
            }

            if ($this->normalizeKey(Str::afterLast($key, '/')) === $normalizedCandidate) {
                return $value;
            }
        }

        return '';
    }

    private function isComputedFieldKey(string $key): bool
    {
        return str_contains($key, '${')
            || str_contains($key, '}')
            || str_contains($key, ':${');
    }

    private function isInvalidValue(string $value): bool
    {
        $normalized = $this->normalizeKey($value);

        return in_array($normalized, ['na', 'n/a', 'n/a#', 'na#', '#na', '#n/a', 'null', 'undefined'], true)
            || str_starts_with($normalized, 'na')
            || str_starts_with($normalized, '#na')
            || preg_match('/^[\s_\-]+$/u', $value) === 1;
    }

    private function isInvalidBeneficiaryName(string $value): bool
    {
        $normalized = $this->normalizeKey($value);

        return $this->isInvalidValue($value)
            || $this->isLikelyKoboUsername($value)
            || in_array($normalized, [
                "\u{0646}\u{0641}\u{0633}\u{0647}",
                "\u{0646}\u{0641}\u{0633}\u{0647}\u{0627}",
                "\u{0646}\u{0641}\u{0633}\u{0627}\u{0644}\u{0634}\u{062E}\u{0635}",
                "\u{0630}\u{0627}\u{062A}\u{0647}",
            ], true);
    }

    private function cleanEngineerName(string $value): string
    {
        return $this->isInvalidEngineerName($value) ? '' : $value;
    }

    private function isInvalidEngineerName(string $value): bool
    {
        return $this->isInvalidValue($value)
            || HeksBeneficiary::isRawEngineerCode($value);
    }

    private function isLikelyKoboUsername(string $value): bool
    {
        $trimmed = trim($value);

        return preg_match('/^[A-Za-z][A-Za-z0-9_.-]{2,30}$/', $trimmed) === 1
            && ! str_contains($trimmed, ' ');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        $flat = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}/{$key}";

            if (is_array($value) && ! array_is_list($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = $value;
                $flat[(string) $key] = $value;
            }
        }

        return $flat;
    }

    private function decimal(string $value): ?float
    {
        return $this->normalizer->money($value);
    }

    private function percentage(string $value): ?float
    {
        $percentage = $this->decimal($value);

        return $percentage !== null && $percentage >= 0 && $percentage <= 100
            ? $percentage
            : null;
    }

    private function date(string $value): ?string
    {
        return $this->normalizer->date($value);
    }

    private function looksLikeAttachment(string $key, string $value): bool
    {
        $lowerKey = Str::lower($key);
        $lowerValue = Str::lower($value);

        return str_contains($lowerKey, 'photo')
            || str_contains($lowerKey, 'image')
            || str_contains($lowerKey, 'attachment')
            || str_contains($lowerKey, 'boq')
            || str_contains($lowerKey, 'صورة')
            || str_contains($lowerKey, 'مرفق')
            || preg_match('/\.(jpg|jpeg|png|webp|pdf|xlsx|xls)(\?.*)?$/', $lowerValue) === 1;
    }

    private function normalizedAttachmentFilename(string $filename): string
    {
        $baseName = basename(str_replace('\\', '/', $filename));
        $baseName = urldecode($baseName);
        $baseName = preg_replace('/[\s_()\[\]{}-]+/u', '', $baseName) ?? $baseName;

        return Str::lower($baseName);
    }

    private function attachmentType(string $value): string
    {
        $lower = Str::lower($value);

        if (str_contains($lower, 'boq') || str_contains($lower, '.xls')) {
            return 'follow_up_boq';
        }

        if (preg_match('/\.(jpg|jpeg|png|webp)(\?.*)?$/', $lower) === 1) {
            return 'shelter_photo';
        }

        return 'kobo_attachment';
    }
}
