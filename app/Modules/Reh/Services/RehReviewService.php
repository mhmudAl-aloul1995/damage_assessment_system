<?php

namespace App\Modules\Reh\Services;

use App\Models\User;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Validation\ValidationException;

class RehReviewService
{
    public function __construct(private RehSurvey $survey) {}

    public function apply(RehUnit $record, User $user, array $data): RehUnit
    {
        return $record->getConnection()->transaction(function () use ($record, $user, $data): RehUnit {
            $unit = RehUnit::query()->with('building')->lockForUpdate()->findOrFail($record->id);
            abort_if($unit->version !== (int) $data['version'], 409, 'تغيّر الملف منذ فتحه. أعد تحميله قبل الحفظ.');
            $action = $data['action'];
            if ($unit->decision !== 'pending' && $action !== 'reopen') {
                throw ValidationException::withMessages(['action' => 'أعد فتح التدقيق قبل تعديل ملف صدر بشأنه قرار.']);
            }
            $before = $unit->only(['workflow_status', 'engineering_status', 'social_status', 'decision', 'reviewer_id', 'quantity_overrides']);
            if ($action === 'claim') {
                if ($unit->reviewer_id && $unit->reviewer_id !== $user->id) {
                    throw ValidationException::withMessages(['action' => 'الملف مسند بالفعل إلى مراجع آخر.']);
                }
                $unit->reviewer_id = $user->id;
                if ($unit->workflow_status === 'new') {
                    $unit->workflow_status = 'in_review';
                }
            } elseif ($action === 'quantities') {
                $overrides = $unit->quantity_overrides ?? [];
                $fields = $this->survey->dictionary()['fields'];
                foreach ($data['quantities'] as $field => $quantity) {
                    if (! str_starts_with($field, 'boq_') || ! isset($fields[$field]) || ($quantity !== null && $fields[$field]['type'] === 'integer' && (float) $quantity !== floor((float) $quantity))) {
                        throw ValidationException::withMessages(['quantities' => 'تحقق من البند ووحدة القياس؛ البنود العددية تقبل أعدادًا صحيحة فقط.']);
                    }
                    if ($quantity === null) {
                        unset($overrides[$field]);
                    } else {
                        $overrides[$field] = (float) $quantity;
                    }
                }
                $unit->quantity_overrides = $overrides;
                $unit->engineering_status = 'pending';
                $unit->workflow_status = 'in_review';
            } elseif (in_array($action, ['engineering_approve', 'social_approve'], true)) {
                if ($unit->source_missing || $unit->building->source_missing) {
                    throw ValidationException::withMessages(['action' => 'تحقق أولًا من السجل المفقود في المصدر.']);
                }
                $unit->{$action === 'engineering_approve' ? 'engineering_status' : 'social_status'} = 'approved';
                $unit->workflow_status = $unit->engineering_status === 'approved' && $unit->social_status === 'approved' ? 'ready' : 'in_review';
            } elseif ($action === 'select') {
                if ($unit->engineering_status !== 'approved' || $unit->social_status !== 'approved' || $this->survey->issues($unit) !== []) {
                    throw ValidationException::withMessages(['action' => 'أكمل التدقيقين وعالج ملاحظات السلامة وجودة البيانات قبل القبول المبدئي.']);
                }
                $unit->decision = 'selected';
                $unit->workflow_status = 'decided';
            } elseif ($action === 'exclude') {
                $unit->decision = 'excluded';
                $unit->workflow_status = 'decided';
            } else {
                $unit->decision = 'pending';
                $unit->engineering_status = 'pending';
                $unit->social_status = 'pending';
                $unit->workflow_status = match ($action) {
                    'return' => 'returned', 'second_assessment' => 'second_assessment', default => 'in_review',
                };
            }
            $unit->version++;
            $unit->save();
            $unit->reviews()->create(['user_id' => $user->id, 'action' => $action, 'note' => $data['note'], 'changes' => ['before' => $before, 'after' => $unit->only(array_keys($before))]]);

            return $unit;
        });
    }
}
