<?php

namespace App\Services\BuildingDeletion;

use App\Enums\BuildingDeletionSignatureAction;
use App\Models\BuildingDeletionRequest;
use App\Models\BuildingDeletionSignature;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BuildingDeletionSignatureService
{
    public function store(
        BuildingDeletionRequest $request,
        User $user,
        BuildingDeletionSignatureAction $action,
        string $signatureData,
        ?string $notes = null,
    ): BuildingDeletionSignature {
        if ($request->signatures()->where('user_id', $user->id)->where('action', $action->value)->exists()) {
            throw ValidationException::withMessages([
                'signature' => 'This signature is immutable and has already been recorded.',
            ]);
        }

        $binary = $this->decodePng($signatureData);
        $path = 'damage-assessment/building-deletions/'.$request->id.'/signatures/'.$action->value.'-'.$user->id.'-'.now()->format('YmdHis').'.png';

        Storage::disk('local')->put($path, $binary);

        return BuildingDeletionSignature::query()->create([
            'request_id' => $request->id,
            'user_id' => $user->id,
            'role' => $user->roles()->pluck('name')->implode(', ') ?: 'user',
            'action' => $action,
            'signature_path' => $path,
            'notes' => $notes,
            'signed_at' => now(),
        ]);
    }

    private function decodePng(string $signatureData): string
    {
        $data = preg_replace('#^data:image/png;base64,#', '', $signatureData) ?? $signatureData;
        $decoded = base64_decode($data, true);

        if ($decoded === false || $decoded === '') {
            throw ValidationException::withMessages([
                'signature' => 'A valid PNG electronic signature is required.',
            ]);
        }

        return $decoded;
    }
}
