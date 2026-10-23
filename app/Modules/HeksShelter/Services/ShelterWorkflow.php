<?php

namespace App\Modules\HeksShelter\Services;

use App\Models\User;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\HeksShelter\Models\ShelterCase;
use App\Modules\HeksShelter\Models\ShelterPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShelterWorkflow
{
    public function __construct(private ShelterSurvey $survey) {}

    public function legacyBenefit(ShelterCase $case): bool
    {
        return HeksBeneficiary::query()->where('identity_number', $case->identity_number)->where(function (Builder $query): void {
            $query->where('is_selected', true)->orWhere('payment_1', '>', 0)->orWhere('payment_2', '>', 0)->orWhere('payment_3', '>', 0)
                ->orWhereHas('payments', fn (Builder $query): Builder => $query->where('payment_1_amount', '>', 0)->orWhere('payment_2_amount', '>', 0)->orWhere('payment_3_amount', '>', 0));
        })->exists();
    }

    public function blockers(ShelterCase $case): array
    {
        $reasons = $this->survey->blockers($case->answers);
        if ($this->legacyBenefit($case)) {
            $reasons[] = 'سبق اعتماد المستفيد أو صرف مساعدة له في HEKS القديم؛ راجع ملفه قبل أي إجراء.';
        }
        if ($case->engineering_status !== 'approved' || $case->social_status !== 'approved') {
            $reasons[] = 'يلزم اعتماد التدقيق الهندسي والاجتماعي.';
        }
        if ((float) $case->grant_amount <= 0) {
            $reasons[] = 'يلزم إعداد كشف الكميات والتكلفة.';
        }

        return $reasons;
    }

    public function saveSurvey(User $user, ?ShelterCase $case, array $answers, ?array $location): ShelterCase
    {
        abort_unless($user->can('heks3.submit'), 403);
        if ($case) {
            abort_unless(in_array($case->status, ['received', 'review', 'returned', 'blocked'], true), 422, 'الملف معتمد أو مغلق؛ لا يمكن استبدال استبيانه.');
            abort_unless($case->identity_number === ShelterSurvey::normalizeIdentity($answers['identity_number']), 422, 'لا يمكن تغيير هوية الملف بعد إنشائه.');
        }
        $answers['identity_number'] = ShelterSurvey::normalizeIdentity($answers['identity_number']);
        if (ShelterCase::query()->where('identity_number', $answers['identity_number'])->when($case, fn (Builder $query): Builder => $query->whereKeyNot($case->id))->exists()) {
            throw ValidationException::withMessages(['answers.identity_number' => 'يوجد ملف بهذه الهوية. افتح الملف الموجود لاستكماله؛ لا تُنشأ استفادة ثانية.']);
        }
        $before = $case?->answers;
        $case ??= new ShelterCase(['engineer_id' => $user->id, 'phase' => 3]);
        $answers['code'] = $case->code ?? (filled($answers['code'] ?? null) ? trim($answers['code']) : 'HEKS-3-'.Str::upper((string) Str::ulid()));
        if (ShelterCase::query()->where('code', $answers['code'])->when($case->exists, fn (Builder $query): Builder => $query->whereKeyNot($case->id))->exists()) {
            throw ValidationException::withMessages(['answers.code' => 'رقم الطلب مستخدم لملف آخر.']);
        }
        $case->fill([
            'code' => $answers['code'], 'name' => $answers['name'], 'identity_number' => $answers['identity_number'],
            'phone' => $answers['phone'], 'governorate' => $answers['governorate'], 'area' => $answers['area'] ?? null,
            'visit_date' => $answers['visit_date'], 'answers' => $answers, 'survey_revision_id' => app(SurveyDesigner::class)->published()->id,
            'status' => $answers['eligibility'] === 'blocked' ? 'blocked' : 'received',
            'engineering_status' => 'pending', 'social_status' => 'pending', 'version' => ($case->version ?? 0) + 1,
        ]);
        if ($location !== null) {
            $case->latitude = $location['latitude'];
            $case->longitude = $location['longitude'];
        }
        try {
            $case->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['answers.identity_number' => 'تم تسجيل الهوية بالفعل. افتح الملف الموجود.']);
        }
        $event = $case->events()->create(['user_id' => $user->id, 'action' => 'survey', 'note' => $before === null ? 'استلام استبيان الموبايل' : 'تحديث الاستبيان وإعادة التدقيق', 'details' => ['survey_revision_id' => $case->survey_revision_id]]);
        app(SurveyAnswerStorage::class)->recordChanges($case->id, $event->id, $before ?? [], $answers);

        return $case;
    }

    public function act(User $user, ShelterCase $case, array $input): void
    {
        $permission = match ($input['action']) {
            'engineering', 'social', 'return' => 'review',
            'boq' => 'pricing', 'payment' => 'finance', 'visit' => 'followup',
            default => 'approve',
        };
        abort_unless($user->can('heks3.'.$permission), 403);
        $case->getConnection()->transaction(function () use ($case, $user, $input): void {
            $case = ShelterCase::query()->visibleTo($user)->lockForUpdate()->findOrFail($case->id);
            abort_unless($case->version === (int) $input['version'], 409, 'تغير الملف؛ حدّث الصفحة قبل حفظ الإجراء.');
            $action = $input['action'];
            $editable = in_array($case->status, ['received', 'review', 'returned', 'blocked'], true);
            $details = ['status_before' => $case->status];
            if (in_array($action, ['engineering', 'social', 'return', 'exclude', 'boq', 'approve'], true)) {
                $this->ensure($editable, 'الملف معتمد أو مغلق. أعد فتحه قبل تغيير القرار أو التكلفة.');
            }
            if (in_array($action, ['engineering', 'social'], true)) {
                $this->ensure($case->answers['eligibility'] === 'safe', 'استكمل معالجة موانع الأهلية أولاً.');
                $case->{$action === 'engineering' ? 'engineering_status' : 'social_status'} = 'approved';
                $case->status = 'review';
            } elseif ($action === 'return' || $action === 'exclude') {
                $case->status = $action === 'return' ? 'returned' : 'excluded';
                $case->engineering_status = $case->social_status = 'pending';
            } elseif ($action === 'boq') {
                $details['previous_lines'] = $case->boqLines()->get()->toArray();
                $total = array_sum(array_map(fn (array $line): float => round(round((float) $line['quantity'], 3) * round((float) $line['unit_price'], 2), 2), $input['lines']));
                $this->ensure($total > 0 && $total <= 99999999, 'إجمالي كشف الكميات خارج النطاق المسموح.');
                $case->boqLines()->delete();
                $case->boqLines()->createMany($input['lines']);
                $case->grant_amount = $total;
                $details['lines'] = $input['lines'];
            } elseif ($action === 'approve') {
                $this->ensure($this->blockers($case) === [], implode(' ', $this->blockers($case)));
                $case->status = 'approved';
                $case->approved_at = now();
            } elseif ($action === 'reopen') {
                $this->ensure(in_array($case->status, ['approved', 'excluded'], true), 'إعادة الفتح متاحة للملفات المعتمدة أو المستبعدة قبل الصرف.');
                $this->ensure($case->benefited_at === null && ! $case->payments()->exists(), 'تم صرف مساعدة؛ لا يمكن إلغاء الاستفادة أو إعادة فتح الاعتماد.');
                $case->status = 'review';
                $case->engineering_status = $case->social_status = 'pending';
                $case->approved_at = null;
            } elseif ($action === 'payment') {
                $this->ensure(in_array($case->status, ['approved', 'in_progress'], true), 'يجب اعتماد الملف قبل تسجيل الصرف.');
                $this->ensure(! $this->legacyBenefit($case), 'سبق الاستفادة أو الاعتماد في HEKS القديم.');
                $this->ensure(! ShelterPayment::query()->where('reference', trim($input['reference']))->exists(), 'مرجع الصرف مستخدم بالفعل.');
                $paidCents = (int) round((float) $case->payments()->sum('amount') * 100);
                $amountCents = (int) round((float) $input['amount'] * 100);
                $this->ensure($amountCents + $paidCents <= (int) round((float) $case->grant_amount * 100), 'الدفعة تتجاوز الرصيد المتبقي من المنحة.');
                $this->ensure($input['paid_on'] >= $case->approved_at->toDateString(), 'تاريخ الصرف لا يسبق تاريخ اعتماد الملف.');
                try {
                    $case->payments()->create(['user_id' => $user->id, 'amount' => $amountCents / 100, 'paid_on' => $input['paid_on'], 'reference' => trim($input['reference']), 'note' => $input['note']]);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['reference' => 'مرجع الصرف مستخدم بالفعل.']);
                }
                $case->benefited_at ??= now();
                $case->status = 'in_progress';
                $details['amount'] = $amountCents / 100;
            } elseif ($action === 'visit') {
                $this->ensure(in_array($case->status, ['approved', 'in_progress'], true), 'زيارة التنفيذ تحتاج ملفاً معتمداً ومفتوحاً.');
                $this->ensure($input['visited_on'] >= $case->visit_date->toDateString(), 'المتابعة لا تسبق زيارة التقييم.');
                $case->visits()->create(['user_id' => $user->id, 'visited_on' => $input['visited_on'], 'completion' => $input['completion'], 'note' => $input['note']]);
                $details['completion'] = $input['completion'];
            } elseif ($action === 'complete') {
                $this->ensure($case->status === 'in_progress' && $case->benefited_at !== null, 'يلزم بدء الصرف قبل إغلاق الملف.');
                $this->ensure((float) $case->visits()->orderByDesc('visited_on')->orderByDesc('id')->value('completion') === 100.0, 'آخر متابعة يجب أن تؤكد إنجاز 100%.');
                $this->ensure((int) round((float) $case->payments()->sum('amount') * 100) === (int) round((float) $case->grant_amount * 100), 'أكمل تسوية الدفعات قبل إغلاق الملف.');
                $case->status = 'completed';
            }
            $case->version++;
            $case->save();
            $case->events()->create(['user_id' => $user->id, 'action' => $action, 'note' => $input['note'], 'details' => [...$details, 'status_after' => $case->status]]);
        }, 3);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['action' => $message]);
        }
    }
}
