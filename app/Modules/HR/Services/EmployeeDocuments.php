<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeDocument;
use App\Modules\HR\Models\EmployeeDocumentWithdrawal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocuments
{
    public function __construct(private EmployeeOrganizationScope $scope) {}

    public function upload(User $actor, Employee $employee, array $data, UploadedFile $file): EmployeeDocument
    {
        Gate::forUser($actor)->authorize('uploadDocument', $employee);
        $mime = $file->getMimeType();
        if (! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true) || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'المستند يجب أن يكون PDF أو صورة JPEG/PNG، بحجم لا يتجاوز 5 ميغابايت.']);
        }
        $bytes = $file->getContent();
        $path = 'hr/employee-documents/'.$employee->id.'/'.Str::uuid().'.enc';
        if (! Storage::disk('local')->put($path, Crypt::encryptString($bytes))) {
            throw new \RuntimeException('Unable to encrypt and store the private employee document.');
        }
        try {
            return Employee::query()->getConnection()->transaction(function () use ($actor, $employee, $data, $file, $path, $mime, $bytes): EmployeeDocument {
                $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
                $this->scope->forget();
                Gate::forUser($actor)->authorize('uploadDocument', $employee);
                $previous = empty($data['previous_document_id']) ? null : EmployeeDocument::query()->with(['replacement', 'withdrawal'])->findOrFail($data['previous_document_id']);
                if ($previous && ($previous->employee_id !== $employee->id || $previous->replacement || $previous->withdrawal)) {
                    throw ValidationException::withMessages(['previous_document_id' => 'اختر مستنداً حالياً يخص الموظف عند إضافة إصدار جديد.']);
                }
                $name = preg_replace('/[^\p{L}\p{N}. _()-]/u', '_', $file->getClientOriginalName());
                $document = $employee->documents()->create([
                    'category' => $data['category'], 'title' => $data['title'], 'original_name' => $name,
                    'path' => $path, 'mime' => $mime, 'size' => strlen($bytes), 'checksum' => hash('sha256', $bytes),
                    'expires_on' => $data['expires_on'] ?? null, 'previous_document_id' => $previous?->id, 'created_by' => $actor->id,
                ]);
                $this->audit($actor, 'employee.document_uploaded', $document->id);

                return $document;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function download(User $actor, EmployeeDocument $document): StreamedResponse
    {
        $this->scope->forget();
        Gate::forUser($actor)->authorize('viewDocuments', $document->employee);
        $encrypted = Storage::disk('local')->get($document->path);
        abort_if($encrypted === null, 404);
        $bytes = Crypt::decryptString($encrypted);
        abort_unless(hash_equals($document->checksum, hash('sha256', $bytes)), 409, 'المستند تغير؛ يلزم مراجعة HR.');
        $this->audit($actor, 'employee.document_downloaded', $document->id);

        return response()->streamDownload(static function () use ($bytes): void {
            echo $bytes;
        }, $document->original_name, ['Content-Type' => $document->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function assertIntegrity(EmployeeDocument $document): void
    {
        $encrypted = Storage::disk('local')->get($document->path);
        if ($encrypted === null) {
            throw ValidationException::withMessages(['document_id' => 'المستند المرفق غير موجود في التخزين الخاص.']);
        }
        try {
            $bytes = Crypt::decryptString($encrypted);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            throw ValidationException::withMessages(['document_id' => 'تعذر التحقق من تشفير المستند المرفق.']);
        }
        if (! hash_equals($document->checksum, hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['document_id' => 'تغير محتوى المستند المرفق؛ يلزم رفع نسخة سليمة.']);
        }
    }

    public function withdraw(User $actor, EmployeeDocument $document, string $reason): EmployeeDocumentWithdrawal
    {
        Gate::forUser($actor)->authorize('hr.documents.withdraw');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $document, $reason): EmployeeDocumentWithdrawal {
            $employee = Employee::query()->lockForUpdate()->findOrFail($document->employee_id);
            $this->scope->forget();
            Gate::forUser($actor)->authorize('viewDocuments', $employee);
            $prior = $document->withdrawal()->first();
            if ($prior) {
                return $prior;
            }
            $withdrawal = $document->withdrawal()->create(['created_by' => $actor->id, 'reason' => $reason]);
            $this->audit($actor, 'employee.document_withdrawn', $document->id);

            return $withdrawal;
        });
    }

    private function audit(User $actor, string $action, int $id): void
    {
        AuditLog::query()->create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => 'hr_employee_documents', 'subject_id' => $id, 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
    }
}
