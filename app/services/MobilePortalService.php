<?php

namespace App\services;

use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Support\Navigation\Sidebar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MobilePortalService
{
    public const MODULES = ['damage_assessment', 'damage_assessment_borrowers', 'heks', 'hr', 'administration'];

    public function authorize(User $user, string $module): void
    {
        abort_unless(in_array($module, self::MODULES, true), 404);
        abort_unless(collect(Sidebar::forUser($user))->contains('key', $module), 403);
        if ($module === 'heks' || $module === 'administration') {
            abort_unless($user->hasRole('Database Officer'), 403);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function catalog(User $user): array
    {
        return collect(Sidebar::forUser($user))->filter(fn (array $module): bool => in_array($module['key'], self::MODULES, true))
            ->map(fn (array $module): array => [
                'key' => $module['key'], 'title' => __($module['title']),
                'description' => __($module['description'] ?? ''),
                'sections' => collect($module['sections'])->map(fn (array $section): string => __($section['title']))->values()->all(),
                'record_search' => in_array($module['key'], ['damage_assessment_borrowers', 'heks', 'administration'], true),
            ])->values()->all();
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function records(User $user, string $module, array $filters): array
    {
        $this->authorize($user, $module);
        $query = $this->query($module);
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $columns = match ($module) {
                'damage_assessment_borrowers' => ['borrower_name', 'borrower_id_number', 'phone_primary', 'loan_number'],
                'heks' => ['name', 'code', 'identity_number', 'phone'],
                default => ['name', 'email'],
            };
            $query->where(function (Builder $query) use ($columns, $pattern): void {
                foreach ($columns as $column) {
                    $query->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }
        $result = $query->orderByDesc('id')->paginate(20, ['*'], 'page', (int) ($filters['page'] ?? 1));

        return ['data' => $result->getCollection()->map(fn (Model $row): array => $this->summary($module, $row))->all(),
            'total' => $result->total(), 'current_page' => $result->currentPage(), 'last_page' => $result->lastPage()];
    }

    public function record(User $user, string $module, int $id, bool $lock = false): Model
    {
        $this->authorize($user, $module);

        return $this->query($module)->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->findOrFail($id);
    }

    /** @return array<string, mixed> */
    public function detail(User $user, string $module, int $id): array
    {
        $row = $this->record($user, $module, $id);
        $labels = match ($module) {
            'damage_assessment_borrowers' => ['borrower_name' => 'اسم المقترض', 'borrower_id_number' => 'الهوية', 'phone_primary' => 'الهاتف', 'current_residence_address' => 'السكن الحالي', 'loan_unit_address' => 'عنوان الوحدة', 'loan_unit_area' => 'المساحة م²', 'loan_unit_damage_status' => 'حالة الضرر', 'risk_level' => 'مستوى المخاطر', 'risk_score' => 'مؤشر المخاطر', 'notes' => 'ملاحظات'],
            'heks' => ['code' => 'الكود', 'name' => 'الاسم', 'identity_number' => 'الهوية', 'phone' => 'الهاتف', 'governorate' => 'المحافظة', 'area' => 'المنطقة', 'address' => 'العنوان', 'damage_status' => 'حالة الضرر', 'selection_status' => 'حالة الاختيار', 'payment_status' => 'حالة الدفعات', 'grant_amount' => 'المنحة', 'engineer_notes' => 'ملاحظات هندسية', 'recommendations' => 'التوصيات'],
            default => ['name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'الهاتف', 'is_active' => 'حالة الحساب'],
        };

        return [...$this->summary($module, $row), 'version' => $this->version($row),
            'details' => collect($labels)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label, 'value' => $this->display($key, $row->$key)])->values()->all()];
    }

    public function version(Model $row): string
    {
        return hash('sha256', json_encode($row->getAttributes(), JSON_THROW_ON_ERROR));
    }

    private function query(string $module): Builder
    {
        return match ($module) {
            'damage_assessment_borrowers' => DamageAssessmentBorrower::query(),
            'heks' => HeksBeneficiary::query(),
            'administration' => User::query()->select(['id', 'name', 'email', 'phone', 'is_active', 'updated_at']),
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    private function summary(string $module, Model $row): array
    {
        return ['id' => (int) $row->getKey(), 'module' => $module,
            'title' => $module === 'damage_assessment_borrowers' ? $row->borrower_name : $row->name,
            'subtitle' => match ($module) {
                'damage_assessment_borrowers' => $row->loan_unit_address,
                'heks' => trim(($row->code ?? '').' · '.($row->area ?? '')),
                default => $row->email,
            },
            'status' => match ($module) {
                'damage_assessment_borrowers' => $this->display('risk_level', $row->risk_level),
                'heks' => $row->selection_status,
                default => $row->is_active ? 'نشط' : 'غير نشط',
            }];
    }

    private function display(string $key, mixed $value): ?string
    {
        if ($key === 'is_active') {
            return $value ? 'نشط' : 'غير نشط';
        }
        $choices = match ($key) {
            'risk_level' => ['low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'مرتفع', 'critical' => 'حرج'],
            'loan_unit_damage_status' => ['destroyed' => 'هدم كلي', 'severe_uninhabitable' => 'بليغ غير صالح للسكن', 'severe_habitable' => 'بليغ صالح للسكن', 'minor' => 'أضرار طفيفة'],
            default => [],
        };

        return is_scalar($value) ? ($choices[(string) $value] ?? (string) $value) : null;
    }
}
